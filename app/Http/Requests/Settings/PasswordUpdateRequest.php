<?php

namespace App\Http\Requests\Settings;

use App\Concerns\PasswordValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;

class PasswordUpdateRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'current_password' => $this->currentPasswordRules(),
            'password' => [
                ...$this->passwordRules(),
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (Hash::check($value, $this->user()->password)) {
                        $fail('Your new password must be different from your current password.');
                    }
                },
            ],
        ];
    }
}
