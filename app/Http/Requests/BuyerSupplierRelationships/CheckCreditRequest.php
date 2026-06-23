<?php

namespace App\Http\Requests\BuyerSupplierRelationships;

use App\Http\Requests\BaseFormRequest;

class CheckCreditRequest extends BaseFormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0'],
        ];
    }
}
