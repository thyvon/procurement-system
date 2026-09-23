<?php

namespace Modules\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'image' => ['required', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'image' => ['description' => 'The avatar image file (JPG, PNG, or WebP, max 2 MB).', 'example' => 'avatar.jpg'],
        ];
    }
}
