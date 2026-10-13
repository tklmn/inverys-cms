<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Support\Locales;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique(User::class)->ignore($this->user()->id)],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            // New address only with the current password (hijacked session ≠ account takeover)
            'current_password' => [
                Rule::requiredIf(fn () => strtolower((string) $this->input('email')) !== strtolower((string) $this->user()->email)),
                'nullable',
                'current_password',
            ],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'avatar' => ['nullable', 'image', 'max:2048'],
            // Personal settings; empty = site default
            'language' => ['nullable', 'string', Rule::in(app(Locales::class)->codes())],
            'timezone' => ['nullable', 'string', Rule::in(timezone_identifiers_list())],
            'date_format' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * "name" here is the username, not just any name.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => __('translation.name')];
    }
}
