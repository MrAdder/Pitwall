<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListEntraUsersRequest extends FormRequest
{
    /**
     * Authorisation is handled by the `permission:users.read` middleware on
     * the route, which runs before this request is resolved.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'string', 'max:200'],
            'enabled' => ['sometimes', 'boolean'],
            'department' => ['sometimes', 'string', 'max:200'],

            // Allow-listed rather than free text: a sort column goes straight
            // into an ORDER BY clause.
            'sort' => ['sometimes', Rule::in(['display_name', 'user_principal_name', 'department', 'last_sign_in_at', 'created_date_time'])],
            'direction' => ['sometimes', Rule::in(['asc', 'desc'])],

            'per_page' => ['sometimes', 'integer', 'min:1', 'max:200'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('enabled')) {
            $this->merge(['enabled' => filter_var($this->query('enabled'), FILTER_VALIDATE_BOOL)]);
        }
    }
}
