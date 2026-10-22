<?php

namespace App\Http\Requests\Enterprise;

use Illuminate\Foundation\Http\FormRequest;

class DelegatePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->hasPermissionTo('delegate_permissions');
    }

    public function rules(): array
    {
        return [
            'delegatee_id' => 'required|exists:users,id',
            'permission' => 'required|string|exists:permissions,name',
            'conditions' => 'nullable|array',
            'conditions.expires_at' => 'nullable|date|after:now',
            'conditions.permissions' => 'nullable|array',
            'conditions.resources' => 'nullable|array',
            'conditions.time_restrictions' => 'nullable|array',
            'conditions.ip_restrictions' => 'nullable|array',
            'expires_at' => 'nullable|date|after:now',
            'revocable' => 'boolean',
        ];
    }
}
