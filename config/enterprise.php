<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Enterprise Features Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration file contains all settings for enterprise features
    | including SSO, SCIM, White-label, Audit Compliance, Advanced RBAC,
    | and Marketplace.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | SSO (Single Sign-On) Configuration
    |--------------------------------------------------------------------------
    |
    | Configure SSO providers for enterprise authentication.
    |
    */
    'sso' => [
        'default_provider' => env('SSO_DEFAULT_PROVIDER', 'saml'),
        'providers' => [
            'saml' => [
                'enabled' => env('SSO_SAML_ENABLED', false),
                'sp_entity_id' => env('SSO_SAML_SP_ENTITY_ID', ''),
                'sp_acs_url' => env('SSO_SAML_SP_ACS_URL', ''),
                'sp_sls_url' => env('SSO_SAML_SP_SLS_URL', ''),
                'idp_entity_id' => env('SSO_SAML_IDP_ENTITY_ID', ''),
                'idp_sso_url' => env('SSO_SAML_IDP_SSO_URL', ''),
                'idp_slo_url' => env('SSO_SAML_IDP_SLO_URL', ''),
                'idp_x509_cert' => env('SSO_SAML_IDP_X509_CERT', ''),
                'sp_x509_cert' => env('SSO_SAML_SP_X509_CERT', ''),
                'sp_private_key' => env('SSO_SAML_SP_PRIVATE_KEY', ''),
                'security' => [
                    'want_assertions_signed' => true,
                    'want_auth_requests_signed' => true,
                    'sign_metadata' => true,
                ],
            ],
            'oidc' => [
                'enabled' => env('SSO_OIDC_ENABLED', false),
                'client_id' => env('SSO_OIDC_CLIENT_ID', ''),
                'client_secret' => env('SSO_OIDC_CLIENT_SECRET', ''),
                'issuer' => env('SSO_OIDC_ISSUER', ''),
                'discovery_url' => env('SSO_OIDC_DISCOVERY_URL', ''),
                'redirect_uri' => env('SSO_OIDC_REDIRECT_URI', ''),
                'scopes' => ['openid', 'profile', 'email'],
                'pkce_enabled' => true,
            ],
            'google' => [
                'enabled' => env('SSO_GOOGLE_ENABLED', false),
                'client_id' => env('SSO_GOOGLE_CLIENT_ID', ''),
                'client_secret' => env('SSO_GOOGLE_CLIENT_SECRET', ''),
                'redirect_uri' => env('SSO_GOOGLE_REDIRECT_URI', ''),
                'scopes' => ['openid', 'profile', 'email'],
                'hosted_domain' => env('SSO_GOOGLE_HOSTED_DOMAIN', ''),
            ],
            'microsoft' => [
                'enabled' => env('SSO_MICROSOFT_ENABLED', false),
                'client_id' => env('SSO_MICROSOFT_CLIENT_ID', ''),
                'client_secret' => env('SSO_MICROSOFT_CLIENT_SECRET', ''),
                'tenant_id' => env('SSO_MICROSOFT_TENANT_ID', 'common'),
                'redirect_uri' => env('SSO_MICROSOFT_REDIRECT_URI', ''),
                'scopes' => ['openid', 'profile', 'email', 'User.Read'],
            ],
            'okta' => [
                'enabled' => env('SSO_OKTA_ENABLED', false),
                'client_id' => env('SSO_OKTA_CLIENT_ID', ''),
                'client_secret' => env('SSO_OKTA_CLIENT_SECRET', ''),
                'issuer' => env('SSO_OKTA_ISSUER', ''),
                'redirect_uri' => env('SSO_OKTA_REDIRECT_URI', ''),
                'scopes' => ['openid', 'profile', 'email'],
            ],
        ],
        'auto_provision' => env('SSO_AUTO_PROVISION', true),
        'default_role' => env('SSO_DEFAULT_ROLE', 'user'),
        'domain_mapping' => [],
        'just_in_time_provisioning' => env('SSO_JIT_PROVISIONING', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | SCIM Provisioning Configuration
    |--------------------------------------------------------------------------
    |
    | Configure SCIM 2.0 provisioning for automated user management.
    |
    */
    'scim' => [
        'enabled' => env('SCIM_ENABLED', false),
        'base_url' => env('SCIM_BASE_URL', '/scim/v2'),
        'bearer_token' => env('SCIM_BEARER_TOKEN', ''),
        'schemas' => [
            'user' => 'urn:ietf:params:scim:schemas:core:2.0:User',
            'group' => 'urn:ietf:params:scim:schemas:core:2.0:Group',
        ],
        'attributes' => [
            'user' => [
                'id',
                'externalId',
                'userName',
                'name' => ['givenName', 'familyName', 'formatted'],
                'displayName',
                'emails' => ['value', 'type', 'primary'],
                'phoneNumbers' => ['value', 'type', 'primary'],
                'addresses' => ['formatted', 'streetAddress', 'locality', 'region', 'postalCode', 'country', 'type', 'primary'],
                'active',
                'groups' => ['value', 'display', 'type'],
                'roles' => ['value', 'display', 'type'],
                'entitlements' => ['value', 'type', 'primary'],
                'locale',
                'preferredLanguage',
                'timezone',
                'urn:ietf:params:scim:schemas:extension:enterprise:2.0:User' => [
                    'employeeNumber',
                    'costCenter',
                    'organization',
                    'division',
                    'department',
                    'manager' => ['value', 'displayName'],
                ],
            ],
        ],
        'filter_attributes' => ['userName', 'externalId', 'id', 'emails.value', 'groups.value'],
        'page_size' => env('SCIM_PAGE_SIZE', 100),
        'sort_order' => env('SCIM_SORT_ORDER', 'asc'),
    ],

    /*
    |--------------------------------------------------------------------------
    | White-Label Configuration
    |--------------------------------------------------------------------------
    |
    | Configure white-label branding for enterprise tenants.
    |
    */
    'white_label' => [
        'enabled' => env('WHITE_LABEL_ENABLED', false),
        'custom_domain_support' => env('WHITE_LABEL_CUSTOM_DOMAIN', true),
        'ssl_auto_provision' => env('WHITE_LABEL_SSL_AUTO', true),
        'ssl_provider' => env('WHITE_LABEL_SSL_PROVIDER', 'letsencrypt'), // letsencrypt, cloudflare, custom
        'cloudflare_api_token' => env('WHITE_LABEL_CLOUDFLARE_API_TOKEN', ''),
        'cloudflare_zone_id' => env('WHITE_LABEL_CLOUDFLARE_ZONE_ID', ''),
        'custom_css_path' => 'white-label/css',
        'custom_js_path' => 'white-label/js',
        'assets_path' => 'white-label/assets',
        'default_theme' => 'light',
        'allowed_origins' => [],
        'cname_verification_timeout' => 300,
        'ssl_renewal_days_before' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit & Compliance Configuration
    |--------------------------------------------------------------------------
    |
    | Configure audit logging and compliance reporting.
    |
    */
    'audit_compliance' => [
        'enabled' => env('AUDIT_ENABLED', true),
        'retention_days' => env('AUDIT_RETENTION_DAYS', 2555), // 7 years
        'archive_after_days' => env('AUDIT_ARCHIVE_DAYS', 365),
        'encryption_enabled' => env('AUDIT_ENCRYPTION', true),
        'encryption_key' => env('AUDIT_ENCRYPTION_KEY', ''),
        'immutable_logs' => env('AUDIT_IMMUTABLE', true),
        'real_time_alerts' => env('AUDIT_REALTIME_ALERTS', true),
        'alert_channels' => explode(',', env('AUDIT_ALERT_CHANNELS', 'email,slack,webhook')),
        'compliance_frameworks' => ['SOC2', 'GDPR', 'HIPAA', 'PCI-DSS'],
        'data_classification' => ['public', 'internal', 'confidential', 'restricted'],
        'access_review_frequency_days' => env('AUDIT_ACCESS_REVIEW_DAYS', 90),
        'failed_login_threshold' => env('AUDIT_FAILED_LOGIN_THRESHOLD', 5),
        'failed_login_window_minutes' => env('AUDIT_FAILED_LOGIN_WINDOW', 15),
        'privileged_action_logging' => env('AUDIT_PRIVILEGED_LOGGING', true),
        'data_export_logging' => env('AUDIT_DATA_EXPORT_LOGGING', true),
        'admin_action_logging' => env('AUDIT_ADMIN_LOGGING', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Advanced RBAC Configuration
    |--------------------------------------------------------------------------
    |
    | Configure advanced role-based access control features.
    |
    */
    'advanced_rbac' => [
        'enable_resource_level_permissions' => env('RBAC_RESOURCE_PERMISSIONS', true),
        'enable_attribute_based_access' => env('RBAC_ABAC', true),
        'enable_time_based_access' => env('RBAC_TIME_BASED', true),
        'enable_delegation' => env('RBAC_DELEGATION', true),
        'enable_emergency_access' => env('RBAC_EMERGENCY_ACCESS', true),
        'max_delegation_depth' => env('RBAC_MAX_DELEGATION_DEPTH', 3),
        'emergency_access_duration_hours' => env('RBAC_EMERGENCY_DURATION', 4),
        'access_review_frequency_days' => env('RBAC_ACCESS_REVIEW_DAYS', 90),
        'auto_revoke_expired' => env('RBAC_AUTO_REVOKE', true),
        'conflict_detection' => env('RBAC_CONFLICT_DETECTION', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Marketplace Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the marketplace for extensions and integrations.
    |
    */
    'marketplace' => [
        'enabled' => env('MARKETPLACE_ENABLED', true),
        'marketplace_url' => env('MARKETPLACE_URL', 'https://marketplace.webixray.com'),
        'api_key' => env('MARKETPLACE_API_KEY', ''),
        'commission_rate' => env('MARKETPLACE_COMMISSION', 0.15),
        'featured_fee' => env('MARKETPLACE_FEATURED_FEE', 50.00),
        'categories' => [
            'agents' => 'AI Agents',
            'templates' => 'Campaign Templates',
            'integrations' => 'Platform Integrations',
            'workflows' => 'Automation Workflows',
            'reports' => 'Report Templates',
            'creative' => 'Creative Assets',
            'scripts' => 'Custom Scripts',
            'connectors' => 'Data Connectors',
        ],
        'review_required' => env('MARKETPLACE_REVIEW_REQUIRED', true),
        'auto_approve_verified' => env('MARKETPLACE_AUTO_APPROVE', true),
        'revenue_sharing' => [
            'developer' => 0.70,
            'platform' => 0.15,
            'affiliate' => 0.10,
            'support' => 0.05,
        ],
        'payout_schedule' => env('MARKETPLACE_PAYOUT_SCHEDULE', 'monthly'),
        'minimum_payout' => env('MARKETPLACE_MINIMUM_PAYOUT', 50.00),
        'refund_policy_days' => env('MARKETPLACE_REFUND_DAYS', 14),
        'auto_update_notifications' => env('MARKETPLACE_AUTO_UPDATE_NOTIFICATIONS', true),
    ],
];
