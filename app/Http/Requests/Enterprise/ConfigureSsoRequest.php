<?php

namespace App\Http\Requests\Enterprise;

use Illuminate\Foundation\Http\FormRequest;

class ConfigureSsoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->hasPermissionTo('configure_sso');
    }

    public function rules(): array
    {
        return [
            'saml.enabled' => 'boolean',
            'saml.sp_entity_id' => 'nullable|string|max:255',
            'saml.sp_acs_url' => 'nullable|url',
            'saml.sp_sls_url' => 'nullable|url',
            'saml.idp_entity_id' => 'nullable|string|max:255',
            'saml.idp_sso_url' => 'nullable|url',
            'saml.idp_slo_url' => 'nullable|url',
            'saml.idp_x509_cert' => 'nullable|string',
            'saml.sp_x509_cert' => 'nullable|string',
            'saml.sp_private_key' => 'nullable|string',
            
            'oidc.enabled' => 'boolean',
            'oidc.client_id' => 'nullable|string|max:255',
            'oidc.client_secret' => 'nullable|string',
            'oidc.issuer' => 'nullable|url',
            'oidc.discovery_url' => 'nullable|url',
            'oidc.redirect_uri' => 'nullable|url',
            'oidc.scopes' => 'nullable|array',
            'oidc.scopes.*' => 'string',
            'oidc.pkce_enabled' => 'boolean',
            
            'google.enabled' => 'boolean',
            'google.client_id' => 'nullable|string|max:255',
            'google.client_secret' => 'nullable|string',
            'google.redirect_uri' => 'nullable|url',
            'google.scopes' => 'nullable|array',
            'google.scopes.*' => 'string',
            'google.hosted_domain' => 'nullable|string|max:255',
            
            'microsoft.enabled' => 'boolean',
            'microsoft.client_id' => 'nullable|string|max:255',
            'microsoft.client_secret' => 'nullable|string',
            'microsoft.tenant_id' => 'nullable|string|max:255',
            'microsoft.redirect_uri' => 'nullable|url',
            'microsoft.scopes' => 'nullable|array',
            'microsoft.scopes.*' => 'string',
            
            'okta.enabled' => 'boolean',
            'okta.client_id' => 'nullable|string|max:255',
            'okta.client_secret' => 'nullable|string',
            'okta.issuer' => 'nullable|url',
            'okta.redirect_uri' => 'nullable|url',
            'okta.scopes' => 'nullable|array',
            'okta.scopes.*' => 'string',
            
            'auto_provision' => 'boolean',
            'default_role' => 'nullable|string|max:100',
            'just_in_time_provisioning' => 'boolean',
            'domain_mapping' => 'nullable|array',
            'domain_mapping.*' => 'string',
        ];
    }
}
