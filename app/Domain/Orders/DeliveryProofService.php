<?php

namespace App\Domain\Orders;

use App\Constants\MediaConstraints;
use App\Domain\Media\ImageUploadService;
use App\Exceptions\DomainException;
use App\Models\DeliveryProof;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class DeliveryProofService
{
    public function __construct(
        private ImageUploadService $imageUploadService
    ) {}

    /**
     * Attach a delivery proof (photo of the handover or a signature) to an order.
     * The image is stored on the configured media disk; only its metadata is persisted.
     *
     * @throws DomainException When the upload cannot be stored
     */
    public function attach(Order $order, User $user, string $type, UploadedFile $file, ?string $notes = null): DeliveryProof
    {
        return DB::transaction(function () use ($order, $user, $type, $file, $notes): DeliveryProof {
            $stored = $this->imageUploadService->store($file, 'delivery-proofs');

            /** @var DeliveryProof */
            return DeliveryProof::create([
                'order_id' => $order->id,
                'user_id' => $user->id,
                'type' => $type,
                'disk' => $stored->disk,
                'path' => $stored->path,
                'url' => $stored->url,
                'notes' => $notes,
            ]);
        });
    }

    /**
     * All proofs attached to an order (photos first, then signatures).
     *
     * @return \Illuminate\Support\Collection<int, DeliveryProof>
     */
    public function forOrder(Order $order): \Illuminate\Support\Collection
    {
        return $order->deliveryProofs()->orderBy('type')->orderBy('id')->get();
    }

    /**
     * @return list<string>
     */
    public static function allowedTypes(): array
    {
        return ['photo', 'signature'];
    }

    /**
     * Max upload size in kilobytes, shared with product media rules.
     */
    public static function maxImageSizeKb(): int
    {
        return MediaConstraints::MAX_IMAGE_SIZE_KB;
    }
}
