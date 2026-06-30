<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaCheck extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'media:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify the configured media disk (MEDIA_DISK) is reachable and writable by uploading and deleting a tiny test image.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $disk = (string) config('media.disk');
        $this->info("Media disk: {$disk}");

        if ($disk === 'cloudinary' && blank(config('filesystems.disks.cloudinary.url'))) {
            $this->error('CLOUDINARY_URL is not set. Set it in .env, or switch MEDIA_DISK=public for local files.');

            return self::FAILURE;
        }

        // 1x1 transparent PNG — the same image resource type the app uploads.
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M8AAAMBAQDJ/pLvAAAAAElFTkSuQmCC',
            true
        );

        if ($png === false) {
            $this->error('Could not build the test image.');

            return self::FAILURE;
        }

        $tmp = sys_get_temp_dir().DIRECTORY_SEPARATOR.'media-check-'.Str::uuid()->toString().'.png';
        file_put_contents($tmp, $png);

        $storage = Storage::disk($disk);
        $path = false;

        try {
            $this->line('Uploading a test image…');
            // Mirrors ImageUploadService::store() — streams a real file, the path
            // Cloudinary's SDK accepts (raw bytes via put() would not work).
            $path = $storage->putFile('health-checks', new File($tmp));

            if ($path === false) {
                $this->error('Upload failed (storage returned no path).');

                return self::FAILURE;
            }

            $exists = $storage->exists($path);
            $url = $storage->url($path);

            $this->line('Cleaning up…');
            $storage->delete($path);

            if (! $exists) {
                $this->error('Upload succeeded but the file could not be read back.');

                return self::FAILURE;
            }

            $this->info('✔ Media storage is working.');
            $this->line("  Resolved URL: {$url}");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            if (is_string($path)) {
                try {
                    $storage->delete($path);
                } catch (\Throwable) {
                    // best-effort cleanup; ignore
                }
            }

            $this->error('✘ Media storage check failed: '.$e->getMessage());

            if ($disk === 'cloudinary') {
                $this->line('  Check that CLOUDINARY_URL (key:secret@cloud-name) is correct.');
            }

            return self::FAILURE;
        } finally {
            @unlink($tmp);
        }
    }
}
