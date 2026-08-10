<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Devices\ComplianceState;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListManagedDevicesRequest extends FormRequest
{
    /**
     * Authorisation is handled by `permission:devices.read` on the route.
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
            'compliance_state' => ['sometimes', Rule::enum(ComplianceState::class)],
            'operating_system' => ['sometimes', 'string', 'max:64'],
            'stale_days' => ['sometimes', 'integer', 'min:1', 'max:365'],

            // Allow-listed: this value reaches an ORDER BY clause.
            'sort' => ['sometimes', Rule::in(['device_name', 'compliance_state', 'operating_system', 'last_sync_date_time', 'enrolled_date_time'])],
            'direction' => ['sometimes', Rule::in(['asc', 'desc'])],

            'per_page' => ['sometimes', 'integer', 'min:1', 'max:200'],
        ];
    }
}
