<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTodoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes','string', 'max:255'],
            'description' => ['sometimes','nullable', 'string'],
            'completed' => ['sometimes','boolean'],
            'image' => ['sometimes','nullable', 'image', 'max:2048'], // Optional image upload, max size 2MB
        ];
    }
}
