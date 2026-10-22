<?php

namespace App\Http\Requests\Enterprise;

use Illuminate\Foundation\Http\FormRequest;

class GrantEmergencyAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->hasPermissionTo('grant_emergency_access');
    }

    public function rules(): array
    {
        return [
            'target_user_id' => 'required|exists:users,id',
            'permission' => 'required|string|exists:permissions,name',
            'duration_hours' => 'nullable|integer|min:1|max:24',
            'reason' => 'required|string|min:10|max:1000',
        ];
    }
}
