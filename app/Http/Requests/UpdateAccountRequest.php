<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:254'],
            'current_password' => ['required', 'string', 'max:128'],
            'new_password' => [
                $this->user()->must_change_password ? 'required' : 'nullable',
                'string', 'max:128', 'confirmed', Password::defaults(),
            ],
        ];
    }
}
