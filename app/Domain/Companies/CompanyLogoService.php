<?php

namespace App\Domain\Companies;

use App\Domain\Media\ImageUploadService;
use App\Models\Company;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class CompanyLogoService
{
    public function __construct(private ImageUploadService $uploader) {}

    /**
     * Set (or replace) a company's logo. The new file is uploaded first; once the
     * record points at it, any previous logo file is removed (best effort).
     */
    public function setLogo(Company $company, UploadedFile $file): Company
    {
        $previousDisk = $company->logo_disk;
        $previousPath = $company->logo_path;

        $stored = $this->uploader->store($file, "companies/{$company->id}");

        try {
            DB::transaction(function () use ($company, $stored): void {
                $company->update([
                    'logo_disk' => $stored->disk,
                    'logo_path' => $stored->path,
                    'logo_url' => $stored->url,
                ]);
            });
        } catch (\Throwable $e) {
            $this->safeDelete($stored->disk, $stored->path);

            throw $e;
        }

        if ($previousPath !== null) {
            $this->safeDelete((string) $previousDisk, $previousPath);
        }

        return $company->refresh();
    }

    /**
     * Remove a company's logo and its stored file.
     */
    public function removeLogo(Company $company): Company
    {
        $disk = $company->logo_disk;
        $path = $company->logo_path;

        DB::transaction(function () use ($company): void {
            $company->update([
                'logo_disk' => null,
                'logo_path' => null,
                'logo_url' => null,
            ]);
        });

        if ($path !== null) {
            $this->safeDelete((string) $disk, $path);
        }

        return $company->refresh();
    }

    private function safeDelete(string $disk, string $path): void
    {
        try {
            $this->uploader->delete($disk, $path);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
