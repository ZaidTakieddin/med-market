<?php

namespace App\Http\Requests\Companies;

use App\Constants\MediaConstraints;
use App\Http\Requests\BaseFormRequest;

class UploadCompanyLogoRequest extends BaseFormRequest
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
        return [
            'logo' => [
                'required',
                'file',
                'image',
                'mimes:'.implode(',', MediaConstraints::allowedExtensions()),
                'max:'.MediaConstraints::MAX_IMAGE_SIZE_KB,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'logo.required' => 'A logo image is required.',
            'logo.image' => 'The logo must be a valid image.',
            'logo.mimes' => 'The logo must be a JPEG, PNG, or WebP image.',
            'logo.max' => 'The logo may not be larger than '.(MediaConstraints::MAX_IMAGE_SIZE_KB / 1024).' MB.',
        ];
    }
}
