<?php

namespace App\Http\Requests\Products;

use App\Http\Requests\BaseFormRequest;
use App\Models\Product;
use Illuminate\Validation\Rule;

class ReorderProductImagesRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in controller
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $product = $this->route('product');
        $productId = $product instanceof Product ? $product->id : null;

        return [
            'ordered_ids' => ['required', 'array', 'min:1'],
            'ordered_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('product_images', 'id')->where('product_id', $productId),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ordered_ids.required' => 'A list of image IDs is required.',
            'ordered_ids.*.exists' => 'Each image ID must belong to this product.',
            'ordered_ids.*.distinct' => 'Image IDs must not be repeated.',
        ];
    }
}
