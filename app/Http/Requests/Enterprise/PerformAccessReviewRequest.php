<?php

namespace App\Http\Requests\Enterprise;

use Illuminate\Foundation\Http\FormRequest;

class PerformAccessReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->hasPermissionTo('perform_access_review');
    }

    public function rules(): array
    {
        return [
            'include_inactive_users' => 'boolean',
            'include_expired_roles' => 'boolean',
            'include_delegations' => 'boolean',
            'include_emergency_access' => 'boolean',
            'auto_revoke_findings' => 'boolean',
            'notify_users' => 'boolean',
        ];
    }
}
