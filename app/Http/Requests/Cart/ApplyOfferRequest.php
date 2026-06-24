<?php

namespace App\Http\Requests\Cart;

use App\Http\Requests\BaseFormRequest;

class ApplyOfferRequest extends BaseFormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'offer_id' => ['required', 'integer', 'exists:offers,id'],
        ];
    }
}
