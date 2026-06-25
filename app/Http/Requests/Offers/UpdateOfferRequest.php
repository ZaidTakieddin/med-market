<?php

namespace App\Http\Requests\Offers;

use App\Constants\OfferType;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class UpdateOfferRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in controller
    }

    protected function prepareForValidation(): void
    {
        $map = [
            'offerType' => 'offer_type',
            'discountValue' => 'discount_value',
            'quantityRequired' => 'quantity_required',
            'quantityFree' => 'quantity_free',
            'startDate' => 'start_date',
            'endDate' => 'end_date',
            'isActive' => 'is_active',
            'productIds' => 'product_ids',
        ];
        $merge = [];
        foreach ($map as $camel => $snake) {
            if ($this->has($camel) && ! $this->has($snake)) {
                $merge[$snake] = $this->input($camel);
            }
        }
        if ($this->input('end_date') === '' || $this->input('endDate') === '') {
            $merge['end_date'] = null;
        }
        if (! empty($merge)) {
            $this->merge($merge);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $offer = $this->route('offer');
        $startDate = $this->input('start_date') ?? ($offer?->start_date?->format('Y-m-d'));

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'offer_type' => ['sometimes', 'string', Rule::in(OfferType::all())],
            'discount_value' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'quantity_required' => ['sometimes', 'required', 'integer', 'min:1'],
            'quantity_free' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => array_filter([
                'sometimes',
                'nullable',
                'date',
                $startDate ? 'after:'.$startDate : null,
            ]),
            'is_active' => ['sometimes', 'boolean'],
            'product_ids' => ['sometimes', 'nullable', 'array', 'min:1'],
            'product_ids.*' => ['integer', 'exists:products,id'],
            'products' => ['sometimes', 'nullable', 'array', 'min:1'],
            'products.*.product_id' => ['required_with:products', 'integer', 'exists:products,id'],
            'products.*.quantity' => ['required_with:products', 'integer', 'min:1'],
        ];
    }
}
