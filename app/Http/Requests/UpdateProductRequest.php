<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'name'        => ['sometimes', 'string', 'max:191'],
            'description' => ['sometimes', 'nullable', 'string'],
            'price'       => ['sometimes', 'numeric', 'min:0.01'],

            'increment'   => ['sometimes', 'integer'],
            'version'     => ['required_with:increment', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'version.required_with' => 'Version is required when updating stock (Optimistic Locking).',
            'price.min'             => 'Price must be greater than zero.',
        ];
    }
}
