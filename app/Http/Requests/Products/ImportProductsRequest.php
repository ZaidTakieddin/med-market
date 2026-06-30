<?php

namespace App\Http\Requests\Products;

use App\Constants\CompanyType;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class ImportProductsRequest extends BaseFormRequest
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
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240'],
            'supplier_company_id' => $supplierCompanyIdRule,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'An import file is required.',
            'file.file' => 'The upload must be a valid file.',
            'file.mimes' => 'The file must be an Excel (.xlsx, .xls) or CSV file.',
            'file.max' => 'The file may not be larger than 10 MB.',
            'supplier_company_id.required' => 'Supplier company is required when importing as admin.',
            'supplier_company_id.exists' => 'The selected supplier company does not exist or is not a supplier.',
        ];
    }
}
