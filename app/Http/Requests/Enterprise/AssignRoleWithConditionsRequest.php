<?php

namespace App\Http\Requests\Enterprise;

use Illuminate\Foundation\Http\FormRequest;

class AssignRoleWithConditionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->hasPermissionTo('assign_roles');
    }

    public function rules(): array
    {
        return [
            'user_id' => 'required|exists:users,id',
            'role' => 'required|string|exists:roles,name',
            'conditions' => 'nullable|array',
            'conditions.expires_at' => 'nullable|date|after:now',
            'conditions.permissions' => 'nullable|array',
            'conditions.permissions.*' => 'string|exists:permissions,name',
            'conditions.resources' => 'nullable|array',
            'conditions.resources.*' => 'string',
            'conditions.time_restrictions' => 'nullable|array',
            'conditions.time_restrictions.start_time' => 'nullable|date_format:H:i',
            'conditions.time_restrictions.end_time' => 'nullable|date_format:H:i',
            'conditions.time_restrictions.days' => 'nullable|array',
            'conditions.time_restrictions.days.*' => 'integer|between:0,6',
            'conditions.ip_restrictions' => 'nullable|array',
            'conditions.ip_restrictions.allowed_ips' => 'nullable|array',
            'conditions.ip_restrictions.allowed_ips.*' => 'ip',
            'conditions.ip_restrictions.denied_ips' => 'nullable|array',
            'conditions.ip_restrictions.denied_ips.*' => 'ip',
            'conditions.resource_owner' => 'boolean',
            'conditions.team_member' => 'nullable|string',
        ];
    }
}
