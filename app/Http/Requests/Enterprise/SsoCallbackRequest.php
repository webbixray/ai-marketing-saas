<?php

namespace App\Http\Requests\Enterprise;

use Illuminate\Foundation\Http\FormRequest;

class SsoCallbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Public endpoint for SSO callbacks
    }

    public function rules(): array
    {
        return [
            // SAML
            'SAMLResponse' => 'nullable|string',
            'RelayState' => 'nullable|string',
            
            // OIDC/OAuth
            'code' => 'nullable|string',
            'state' => 'nullable|string',
            'error' => 'nullable|string',
            'error_description' => 'nullable|string',
        ];
    }
}
