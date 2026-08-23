<?php

namespace App\Http\Requests\Products;

use App\Constants\CompanyType;
use App\Constants\DiscountType;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class CreateProductRequest extends BaseFormRequest
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
        $user = auth()->user();
        $supplierCompanyIdRule = $user && $user->isAdmin()
            ? ['required', 'integer', Rule::exists('companies', 'id')->where('type', CompanyType::SUPPLIER)]
            : ['nullable', 'integer', 'exists:companies,id'];

        return [
            'supplier_company_id' => $supplierCompanyIdRule,
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'manufacturer_id' => ['nullable', 'integer', 'exists:manufacturers,id'],
            'sku' => ['required', 'string', 'max:100', 'unique:products,sku'],
            'barcode' => ['nullable', 'string', 'max:100'],
            'unit' => ['nullable', 'string', 'max:50', Rule::in(['piece', 'box', 'bottle', 'pack', 'carton'])],
            'wholesale_price' => ['nullable', 'numeric', 'min:0'],
            'wholesale_min_qty' => ['nullable', 'integer', 'min:1', 'required_with:wholesale_price'],
            'pharmacist_price' => ['required', 'numeric', 'min:0'],
            'customer_price' => ['nullable', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'discount_type' => ['nullable', 'string', Rule::in(DiscountType::all())],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'min_stock_level' => ['nullable', 'integer', 'min:0'],
            'batch_number' => ['nullable', 'string', 'max:100'],
            'expiry_date' => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Product name is required.',
            'sku.required' => 'SKU is required.',
            'sku.unique' => 'This SKU already exists.',
            'supplier_company_id.required' => 'Supplier company is required when creating products as admin.',
            'supplier_company_id.exists' => 'The selected supplier company does not exist or is not a supplier.',
            'pharmacist_price.required' => 'Pharmacist price is required.',
            'pharmacist_price.min' => 'Pharmacist price cannot be negative.',
            'customer_price.min' => 'Customer price cannot be negative.',
            'wholesale_price.min' => 'Wholesale price cannot be negative.',
            'wholesale_min_qty.min' => 'Wholesale minimum quantity must be at least 1.',
            'wholesale_min_qty.required_with' => 'Wholesale minimum quantity is required when wholesale price is set.',
            'discount.min' => 'Discount cannot be negative.',
            'discount_type.in' => 'Discount type must be either percentage or fixed.',
        ];
    }
}
