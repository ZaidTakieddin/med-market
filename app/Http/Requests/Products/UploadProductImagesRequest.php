<?php

namespace App\Http\Requests\Products;

use App\Constants\MediaConstraints;
use App\Http\Requests\BaseFormRequest;

class UploadProductImagesRequest extends BaseFormRequest
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
            'images' => ['required', 'array', 'min:1', 'max:'.MediaConstraints::MAX_PRODUCT_IMAGES],
            'images.*' => [
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
            'images.required' => 'At least one image is required.',
            'images.array' => 'Images must be sent as a list (images[]).',
            'images.max' => 'You can upload at most '.MediaConstraints::MAX_PRODUCT_IMAGES.' images at once.',
            'images.*.image' => 'Each upload must be a valid image.',
            'images.*.mimes' => 'Images must be JPEG, PNG, or WebP.',
            'images.*.max' => 'Each image may not be larger than '.(MediaConstraints::MAX_IMAGE_SIZE_KB / 1024).' MB.',
        ];
    }
}
