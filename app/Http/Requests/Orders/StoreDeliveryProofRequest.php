<?php

namespace App\Http\Requests\Orders;

use App\Domain\Orders\DeliveryProofService;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class StoreDeliveryProofRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled against the order in the controller
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxKb = DeliveryProofService::maxImageSizeKb();

        return [
            'image' => ['required', 'image', 'max:'.$maxKb],
            'type' => ['nullable', 'string', Rule::in(DeliveryProofService::allowedTypes())],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
