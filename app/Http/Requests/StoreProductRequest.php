<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'name'           => ['required', 'string', 'max:191', 'unique:products,name'],
            'description'    => ['nullable', 'string'],
            'price'          => ['required', 'numeric', 'min:0.01'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique'           => 'A product with this name already exists.',
            'price.min'             => 'Price must be greater than zero.',
            'stock_quantity.min'    => 'Stock quantity cannot be negative.',
        ];
    }
}
