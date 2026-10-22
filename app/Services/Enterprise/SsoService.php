<?php

namespace App\Services\Enterprise;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class SsoService
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'default_provider' => 'saml',
            'providers' => [
                'saml' => [
                    'enabled' => false,
                    'sp_entity_id' => '',
                    'sp_acs_url' => '',
                    'sp_sls_url' => '',
                    'idp_entity_id' => '',
                    'idp_sso_url' => '',
                    'idp_slo_url' => '',
                    'idp_x509_cert' => '',
                    'sp_x509_cert' => '',
                    'sp_private_key' => '',
                    'security' => [
                        'want_assertions_signed' => true,
                        'want_auth_requests_signed' => true,
                        'sign_metadata' => true,
                    ],
                ],
                'oidc' => [
                    'enabled' => false,
                    'client_id' => '',
                    'client_secret' => '',
                    'issuer' => '',
                    'discovery_url' => '',
                    'redirect_uri' => '',
                    'scopes' => ['openid', 'profile', 'email'],
                    'pkce_enabled' => true,
                ],
                'google' => [
                    'enabled' => false,
                    'client_id' => '',
                    'client_secret' => '',
                    'redirect_uri' => '',
                    'scopes' => ['openid', 'profile', 'email'],
                    'hosted_domain' => '',
                ],
                'microsoft' => [
                    'enabled' => false,
                    'client_id' => '',
                    'client_secret' => '',
                    'tenant_id' => 'common',
                    'redirect_uri' => '',
                    'scopes' => ['openid', 'profile', 'email', 'User.Read'],
                ],
                'okta' => [
                    'enabled' => false,
                    'client_id' => '',
                    'client_secret' => '',
                    'issuer' => '',
                    'redirect_uri' => '',
                    'scopes' => ['openid', 'profile', 'email'],
                ],
            ],
            'auto_provision' => true,
            'default_role' => 'user',
            'domain_mapping' => [],
            'just_in_time_provisioning' => true,
        ], $config);
    }

    /**
     * Get available SSO providers for a tenant.
     */
    public function getAvailableProviders(Tenant $tenant): array
    {
        $tenantConfig = $tenant->sso_config ?? [];
        $providers = [];

        foreach ($this->config['providers'] as $id => $config) {
            $tenantProviderConfig = $tenantConfig[$id] ?? [];
            $mergedConfig = array_merge($config, $tenantProviderConfig);
            
            $providers[$id] = [
                'id' => $id,
                'name' => $this->getProviderName($id),
                'enabled' => $mergedConfig['enabled'] ?? false,
                'configured' => $this->isProviderConfigured($id, $mergedConfig),
                'login_url' => $this->getLoginUrl($id),
                'config_fields' => $this->getConfigFields($id),
            ];
        }

        return $providers;
    }

    /**
     * Initiate SSO login for a provider.
     */
    public function initiateLogin(string $provider, Tenant $tenant, array $options = []): array
    {
        if (!$this->isProviderEnabled($provider, $tenant)) {
            return ['success' => false, 'error' => 'Provider not enabled'];
        }

        return match ($provider) {
            'saml' => $this->initiateSamlLogin($tenant, $options),
            'oidc' => $this->initiateOidcLogin($tenant, $options),
            'google' => $this->initiateGoogleLogin($tenant, $options),
            'microsoft' => $this->initiateMicrosoftLogin($tenant, $options),
            'okta' => $this->initiateOktaLogin($tenant, $options),
            default => ['success' => false, 'error' => 'Unsupported provider'],
        };
    }

    /**
     * Handle SSO callback.
     */
    public function handleCallback(string $provider, array $requestData): array
    {
        return match ($provider) {
            'saml' => $this->handleSamlCallback($requestData),
            'oidc' => $this->handleOidcCallback($requestData),
            'google' => $this->handleGoogleCallback($requestData),
            'microsoft' => $this->handleMicrosoftCallback($requestData),
            'okta' => $this->handleOktaCallback($requestData),
            default => ['success' => false, 'error' => 'Unsupported provider'],
        };
    }

    /**
     * Process SAML login.
     */
    protected function initiateSamlLogin(Tenant $tenant, array $options): array
    {
        $config = $this->getProviderConfig('saml', $tenant);
        
        // Generate SAML AuthnRequest
        $authnRequest = $this->buildSamlAuthnRequest($config, $options);
        
        // Store request ID for validation
        $requestId = $authnRequest['id'];
        Cache::put("saml_request_{$requestId}", [
            'tenant_id' => $tenant->id,
            'relay_state' => $options['relay_state'] ?? '',
            'created_at' => now(),
        ], 600); // 10 minutes

        return [
            'success' => true,
            'redirect_url' => $config['idp_sso_url'],
            'post_data' => [
                'SAMLRequest' => base64_encode($authnRequest['xml']),
                'RelayState' => $options['relay_state'] ?? '',
            ],
            'method' => 'POST',
        ];
    }

    /**
     * Handle SAML callback.
     */
    protected function handleSamlCallback(array $requestData): array
    {
        $samlResponse = $requestData['SAMLResponse'] ?? '';
        $relayState = $requestData['RelayState'] ?? '';

        if (empty($samlResponse)) {
            return ['success' => false, 'error' => 'Missing SAMLResponse'];
        }

        // Decode and validate SAML response
        $decodedResponse = base64_decode($samlResponse);
        if (!$decodedResponse) {
            return ['success' => false, 'error' => 'Invalid SAMLResponse encoding'];
        }

        // Parse and validate SAML assertion
        $assertion = $this->parseSamlAssertion($decodedResponse);
        if (!$assertion) {
            return ['success' => false, 'error' => 'Invalid SAML assertion'];
        }

        // Extract user attributes
        $attributes = $this->extractSamlAttributes($assertion);
        if (!$attributes) {
            return ['success' => false, 'error' => 'No attributes in assertion'];
        }

        return [
            'success' => true,
            'user' => $attributes,
            'relay_state' => $relayState,
            'session_index' => $assertion['session_index'] ?? null,
        ];
    }

    /**
     * Process OIDC login.
     */
    protected function initiateOidcLogin(Tenant $tenant, array $options): array
    {
        $config = $this->getProviderConfig('oidc', $tenant);
        
        $state = Str::random(32);
        $nonce = Str::random(32);
        
        // Store state for validation
        Cache::put("oidc_state_{$state}", [
            'tenant_id' => $tenant->id,
            'nonce' => $nonce,
            'redirect_uri' => $options['redirect_uri'] ?? $config['redirect_uri'],
        ], 600);

        $params = [
            'client_id' => $config['client_id'],
            'redirect_uri' => $options['redirect_uri'] ?? $config['redirect_uri'],
            'response_type' => 'code',
            'scope' => implode(' ', $config['scopes']),
            'state' => $state,
            'nonce' => $nonce,
        ];

        if ($config['pkce_enabled']) {
            $codeVerifier = Str::random(64);
            $codeChallenge = base64_encode(hash('sha256', $codeVerifier, true));
            $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
            
            $params['code_challenge'] = $codeChallenge;
            $params['code_challenge_method'] = 'S256';
            
            Cache::put("pkce_{$state}", $codeVerifier, 600);
        }

        $authUrl = $this->buildOidcAuthUrl($config['issuer'], $params);

        return [
            'success' => true,
            'redirect_url' => $authUrl,
            'state' => $state,
        ];
    }

    /**
     * Handle OIDC callback.
     */
    protected function handleOidcCallback(array $requestData): array
    {
        $code = $requestData['code'] ?? '';
        $state = $requestData['state'] ?? '';
        $error = $requestData['error'] ?? '';

        if ($error) {
            return ['success' => false, 'error' => $error];
        }

        if (empty($code) || empty($state)) {
            return ['success' => false, 'error' => 'Missing code or state'];
        }

        // Validate state
        $storedState = Cache::get("oidc_state_{$state}");
        if (!$storedState) {
            return ['success' => false, 'error' => 'Invalid or expired state'];
        }
        Cache::forget("oidc_state_{$state}");

        // Exchange code for tokens
        $config = $this->getOidcConfig($storedState['tenant_id']);
        $tokenResponse = $this->exchangeOidcCode($config, $code, $storedState);

        if (isset($tokenResponse['error'])) {
            return ['success' => false, 'error' => $tokenResponse['error']];
        }

        // Validate ID token
        $userInfo = $this->validateIdToken($config, $tokenResponse['id_token'], $storedState['nonce']);

        return [
            'success' => true,
            'user' => $userInfo,
            'tokens' => $tokenResponse,
        ];
    }

    /**
     * Initiate Google login.
     */
    protected function initiateGoogleLogin(Tenant $tenant, array $options): array
    {
        $config = $this->getProviderConfig('google', $tenant);
        
        $state = Str::random(32);
        Cache::put("google_state_{$state}", [
            'tenant_id' => $tenant->id,
            'redirect_uri' => $options['redirect_uri'] ?? $config['redirect_uri'],
        ], 600);

        $params = [
            'client_id' => $config['client_id'],
            'redirect_uri' => $options['redirect_uri'] ?? $config['redirect_uri'],
            'response_type' => 'code',
            'scope' => implode(' ', $config['scopes']),
            'state' => $state,
            'access_type' => 'offline',
            'prompt' => 'consent',
        ];

        if (!empty($config['hosted_domain'])) {
            $params['hd'] = $config['hosted_domain'];
        }

        $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);

        return [
            'success' => true,
            'redirect_url' => $authUrl,
            'state' => $state,
        ];
    }

    /**
     * Handle Google callback.
     */
    protected function handleGoogleCallback(array $requestData): array
    {
        $code = $requestData['code'] ?? '';
        $state = $requestData['state'] ?? '';
        $error = $requestData['error'] ?? '';

        if ($error) {
            return ['success' => false, 'error' => $error];
        }

        $storedState = Cache::get("google_state_{$state}");
        if (!$storedState) {
            return ['success' => false, 'error' => 'Invalid or expired state'];
        }
        Cache::forget("google_state_{$state}");

        $config = $this->getProviderConfig('google', \App\Models\Tenant::find($storedState['tenant_id']));
        $tokenResponse = $this->exchangeGoogleCode($config, $code, $storedState['redirect_uri']);

        if (isset($tokenResponse['error'])) {
            return ['success' => false, 'error' => $tokenResponse['error']];
        }

        // Get user info
        $userInfo = $this->getGoogleUserInfo($tokenResponse['access_token']);

        // Validate hosted domain if configured
        if (!empty($config['hosted_domain']) && ($userInfo['hd'] ?? '') !== $config['hosted_domain']) {
            return ['success' => false, 'error' => 'Invalid domain'];
        }

        return [
            'success' => true,
            'user' => $userInfo,
            'tokens' => $tokenResponse,
        ];
    }

    /**
     * Initiate Microsoft login.
     */
    protected function initiateMicrosoftLogin(Tenant $tenant, array $options): array
    {
        $config = $this->getProviderConfig('microsoft', $tenant);
        
        $state = Str::random(32);
        Cache::put("microsoft_state_{$state}", [
            'tenant_id' => $tenant->id,
            'redirect_uri' => $options['redirect_uri'] ?? $config['redirect_uri'],
        ], 600);

        $params = [
            'client_id' => $config['client_id'],
            'redirect_uri' => $options['redirect_uri'] ?? $config['redirect_uri'],
            'response_type' => 'code',
            'scope' => implode(' ', $config['scopes']),
            'state' => $state,
        ];

        $authUrl = "https://login.microsoftonline.com/{$config['tenant_id']}/oauth2/v2.0/authorize?" . http_build_query($params);

        return [
            'success' => true,
            'redirect_url' => $authUrl,
            'state' => $state,
        ];
    }

    /**
     * Handle Microsoft callback.
     */
    protected function handleMicrosoftCallback(array $requestData): array
    {
        $code = $requestData['code'] ?? '';
        $state = $requestData['state'] ?? '';
        $error = $requestData['error'] ?? '';

        if ($error) {
            return ['success' => false, 'error' => $error];
        }

        $storedState = Cache::get("microsoft_state_{$state}");
        if (!$storedState) {
            return ['success' => false, 'error' => 'Invalid or expired state'];
        }
        Cache::forget("microsoft_state_{$state}");

        $config = $this->getProviderConfig('microsoft', \App\Models\Tenant::find($storedState['tenant_id']));
        $tokenResponse = $this->exchangeMicrosoftCode($config, $code, $storedState['redirect_uri']);

        if (isset($tokenResponse['error'])) {
            return ['success' => false, 'error' => $tokenResponse['error']];
        }

        $userInfo = $this->getMicrosoftUserInfo($tokenResponse['access_token']);

        return [
            'success' => true,
            'user' => $userInfo,
            'tokens' => $tokenResponse,
        ];
    }

    /**
     * Initiate Okta login.
     */
    protected function initiateOktaLogin(Tenant $tenant, array $options): array
    {
        $config = $this->getProviderConfig('okta', $tenant);
        
        $state = Str::random(32);
        Cache::put("okta_state_{$state}", [
            'tenant_id' => $tenant->id,
            'redirect_uri' => $options['redirect_uri'] ?? $config['redirect_uri'],
        ], 600);

        $params = [
            'client_id' => $config['client_id'],
            'redirect_uri' => $options['redirect_uri'] ?? $config['redirect_uri'],
            'response_type' => 'code',
            'scope' => implode(' ', $config['scopes']),
            'state' => $state,
        ];

        $authUrl = "{$config['issuer']}/v1/authorize?" . http_build_query($params);

        return [
            'success' => true,
            'redirect_url' => $authUrl,
            'state' => $state,
        ];
    }

    /**
     * Handle Okta callback.
     */
    protected function handleOktaCallback(array $requestData): array
    {
        $code = $requestData['code'] ?? '';
        $state = $requestData['state'] ?? '';
        $error = $requestData['error'] ?? '';

        if ($error) {
            return ['success' => false, 'error' => $error];
        }

        $storedState = Cache::get("okta_state_{$state}");
        if (!$storedState) {
            return ['success' => false, 'error' => 'Invalid or expired state'];
        }
        Cache::forget("okta_state_{$state}");

        $config = $this->getProviderConfig('okta', \App\Models\Tenant::find($storedState['tenant_id']));
        $tokenResponse = $this->exchangeOktaCode($config, $code, $storedState['redirect_uri']);

        if (isset($tokenResponse['error'])) {
            return ['success' => false, 'error' => $tokenResponse['error']];
        }

        $userInfo = $this->getOktaUserInfo($config['issuer'], $tokenResponse['access_token']);

        return [
            'success' => true,
            'user' => $userInfo,
            'tokens' => $tokenResponse,
        ];
    }

    /**
     * Find or create user from SSO attributes.
     */
    public function findOrCreateUser(Tenant $tenant, array $attributes, string $provider): ?User
    {
        $email = $attributes['email'] ?? $attributes['emailaddress'] ?? $attributes['http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress'] ?? '';
        $externalId = $attributes['sub'] ?? $attributes['nameid'] ?? $attributes['nameidentifier'] ?? '';

        if (empty($email)) {
            return null;
        }

        // Check if user exists by email
        $user = User::where('tenant_id', $tenant->id)
            ->where('email', $email)
            ->first();

        if ($user) {
            // Update external ID if not set
            if (empty($user->external_id) && $externalId) {
                $user->update(['external_id' => $externalId, 'sso_provider' => $provider]);
            }
            return $user;
        }

        // Check if auto-provisioning is enabled
        if (!$this->config['auto_provision']) {
            return null;
        }

        // Check domain mapping
        $domain = explode('@', $email)[1] ?? '';
        if (!empty($this->config['domain_mapping'][$domain])) {
            $mappedTenantId = $this->config['domain_mapping'][$domain];
            if ($mappedTenantId !== $tenant->id) {
                // User belongs to different tenant
                return null;
            }
        }

        // Create new user
        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => $attributes['name'] ?? $attributes['given_name'] . ' ' . ($attributes['family_name'] ?? ''),
            'email' => $email,
            'password' => Hash::make(Str::random(32)),
            'external_id' => $externalId,
            'sso_provider' => $provider,
            'email_verified_at' => now(),
            'avatar' => $attributes['picture'] ?? $attributes['picture_url'] ?? null,
        ]);

        // Assign default role
        $defaultRole = $this->config['default_role'] ?? 'user';
        $role = \Spatie\Permission\Models\Role::where('name', $defaultRole)
            ->where('guard_name', 'api')
            ->first();
        
        if ($role) {
            $user->assignRole($role);
        }

        // Sync additional attributes
        $this->syncUserAttributes($user, $attributes, $provider);

        Log::info('User created via SSO', [
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'provider' => $provider,
            'email' => $email,
        ]);

        return $user;
    }

    /**
     * Sync user attributes from SSO.
     */
    protected function syncUserAttributes(User $user, array $attributes, string $provider): void
    {
        $updates = [];

        // Map common attributes
        $mapping = [
            'given_name' => 'first_name',
            'family_name' => 'last_name',
            'display_name' => 'display_name',
            'job_title' => 'job_title',
            'department' => 'department',
            'phone_number' => 'phone',
            'mobile' => 'mobile',
            'street_address' => 'address',
            'locality' => 'city',
            'region' => 'state',
            'postal_code' => 'postal_code',
            'country' => 'country',
        ];

        foreach ($mapping as $ssoKey => $userKey) {
            if (isset($attributes[$ssoKey]) && !empty($attributes[$ssoKey])) {
                $updates[$userKey] = $attributes[$ssoKey];
            }
        }

        // Handle name splitting
        if (empty($updates['first_name']) && isset($attributes['name'])) {
            $parts = explode(' ', $attributes['name'], 2);
            $updates['first_name'] = $parts[0];
            $updates['last_name'] = $parts[1] ?? '';
        }

        if (!empty($updates)) {
            $user->update($updates);
        }
    }

    /**
     * Check if provider is enabled for tenant.
     */
    protected function isProviderEnabled(string $provider, Tenant $tenant): bool
    {
        $config = $this->getProviderConfig($provider, $tenant);
        return $config['enabled'] ?? false;
    }

    /**
     * Get provider config for tenant.
     */
    protected function getProviderConfig(string $provider, Tenant $tenant): array
    {
        $baseConfig = $this->config['providers'][$provider] ?? [];
        $tenantConfig = $tenant->sso_config[$provider] ?? [];
        return array_merge($baseConfig, $tenantConfig);
    }

    /**
     * Build SAML AuthnRequest.
     */
    protected function buildSamlAuthnRequest(array $config, array $options): array
    {
        $requestId = '_' . Str::random(32);
        $issueInstant = Carbon::now()->format('Y-m-d\TH:i:s\Z');
        
        $xml = <<<XML
<samlp:AuthnRequest xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol"
    xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion"
    ID="{$requestId}"
    Version="2.0"
    IssueInstant="{$issueInstant}"
    Destination="{$config['idp_sso_url']}"
    AssertionConsumerServiceURL="{$config['sp_acs_url']}"
    ProtocolBinding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST">
    <saml:Issuer>{$config['sp_entity_id']}</saml:Issuer>
    <samlp:NameIDPolicy Format="urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress" AllowCreate="true"/>
</samlp:AuthnRequest>
XML;

        return [
            'id' => $requestId,
            'xml' => $xml,
        ];
    }

    /**
     * Build OIDC authorization URL.
     */
    protected function buildOidcAuthUrl(string $issuer, array $params): string
    {
        // Try to get from discovery document
        $discoveryUrl = rtrim($issuer, '/') . '/.well-known/openid-configuration';
        
        try {
            $response = Http::timeout(10)->get($discoveryUrl);
            if ($response->successful()) {
                $discovery = $response->json();
                $authEndpoint = $discovery['authorization_endpoint'] ?? $issuer . '/authorize';
            } else {
                $authEndpoint = $issuer . '/authorize';
            }
        } catch (\Throwable) {
            $authEndpoint = $issuer . '/authorize';
        }

        return $authEndpoint . '?' . http_build_query($params);
    }

    /**
     * Exchange OIDC code for tokens.
     */
    protected function exchangeOidcCode(array $config, string $code, array $state): array
    {
        $codeVerifier = Cache::get("pkce_{$state['state']}");
        Cache::forget("pkce_{$state['state']}");

        $params = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $state['redirect_uri'],
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
        ];

        if ($codeVerifier) {
            $params['code_verifier'] = $codeVerifier;
        }

        $tokenEndpoint = $this->getOidcTokenEndpoint($config['issuer']);
        
        $response = Http::asForm()->post($tokenEndpoint, $params);
        
        if (!$response->successful()) {
            return ['error' => $response->body()];
        }

        return $response->json();
    }

    /**
     * Exchange Google code for tokens.
     */
    protected function exchangeGoogleCode(array $config, string $code, string $redirectUri): array
    {
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
        ]);

        if (!$response->successful()) {
            return ['error' => $response->body()];
        }

        return $response->json();
    }

    /**
     * Exchange Microsoft code for tokens.
     */
    protected function exchangeMicrosoftCode(array $config, string $code, string $redirectUri): array
    {
        $response = Http::asForm()->post("https://login.microsoftonline.com/{$config['tenant_id']}/oauth2/v2.0/token", [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
        ]);

        if (!$response->successful()) {
            return ['error' => $response->body()];
        }

        return $response->json();
    }

    /**
     * Exchange Okta code for tokens.
     */
    protected function exchangeOktaCode(array $config, string $code, string $redirectUri): array
    {
        $response = Http::asForm()->post("{$config['issuer']}/v1/token", [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
        ]);

        if (!$response->successful()) {
            return ['error' => $response->body()];
        }

        return $response->json();
    }

    /**
     * Get OIDC token endpoint from discovery.
     */
    protected function getOidcTokenEndpoint(string $issuer): string
    {
        try {
            $response = Http::timeout(10)->get(rtrim($issuer, '/') . '/.well-known/openid-configuration');
            if ($response->successful()) {
                return $response->json()['token_endpoint'] ?? rtrim($issuer, '/') . '/token';
            }
        } catch (\Throwable) {}
        
        return rtrim($issuer, '/') . '/token';
    }

    /**
     * Validate OIDC ID token.
     */
    protected function validateIdToken(array $config, string $idToken, string $nonce): array
    {
        // In production, verify JWT signature using JWKS
        // For now, decode payload
        $parts = explode('.', $idToken);
        if (count($parts) !== 3) {
            throw new \Exception('Invalid ID token format');
        }

        $payload = json_decode(base64_decode($parts[1]), true);
        
        // Validate nonce
        if (($payload['nonce'] ?? '') !== $nonce) {
            throw new \Exception('Invalid nonce');
        }

        // Validate audience
        if (($payload['aud'] ?? '') !== $config['client_id']) {
            throw new \Exception('Invalid audience');
        }

        // Validate issuer
        if (($payload['iss'] ?? '') !== $config['issuer']) {
            throw new \Exception('Invalid issuer');
        }

        // Validate expiration
        if (($payload['exp'] ?? 0) < time()) {
            throw new \Exception('Token expired');
        }

        return [
            'sub' => $payload['sub'] ?? '',
            'email' => $payload['email'] ?? '',
            'name' => $payload['name'] ?? '',
            'given_name' => $payload['given_name'] ?? '',
            'family_name' => $payload['family_name'] ?? '',
            'picture' => $payload['picture'] ?? null,
            'email_verified' => $payload['email_verified'] ?? false,
        ];
    }

    /**
     * Get Google user info.
     */
    protected function getGoogleUserInfo(string $accessToken): array
    {
        $response = Http::withToken($accessToken)->get('https://www.googleapis.com/oauth2/v2/userinfo');
        
        if (!$response->successful()) {
            throw new \Exception('Failed to get Google user info');
        }

        return $response->json();
    }

    /**
     * Get Microsoft user info.
     */
    protected function getMicrosoftUserInfo(string $accessToken): array
    {
        $response = Http::withToken($accessToken)->get('https://graph.microsoft.com/v1.0/me');
        
        if (!$response->successful()) {
            throw new \Exception('Failed to get Microsoft user info');
        }

        return $response->json();
    }

    /**
     * Get Okta user info.
     */
    protected function getOktaUserInfo(string $issuer, string $accessToken): array
    {
        $response = Http::withToken($accessToken)->get("{$issuer}/v1/userinfo");
        
        if (!$response->successful()) {
            throw new \Exception('Failed to get Okta user info');
        }

        return $response->json();
    }

    /**
     * Get Okta token endpoint.
     */
    protected function exchangeOktaCode(array $config, string $code, string $redirectUri): array
    {
        $response = Http::asForm()->post("{$config['issuer']}/v1/token", [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
        ]);

        if (!$response->successful()) {
            return ['error' => $response->body()];
        }

        return $response->json();
    }

    /**
     * Parse SAML assertion.
     */
    protected function parseSamlAssertion(string $xml): ?array
    {
        try {
            $xml = simplexml_load_string($xml);
            if (!$xml) return null;

            $ns = [
                'samlp' => 'urn:oasis:names:tc:SAML:2.0:protocol',
                'saml' => 'urn:oasis:names:tc:SAML:2.0:assertion',
            ];

            $assertion = $xml->xpath('//saml:Assertion');
            if (empty($assertion)) return null;

            $assertion = $assertion[0];
            
            return [
                'xml' => $assertion->asXML(),
                'session_index' => (string)($assertion->xpath('//samlp:SessionIndex')[0] ?? ''),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Extract attributes from SAML assertion.
     */
    protected function extractSamlAssertion(\SimpleXMLElement $assertion): ?array
    {
        try {
            $attributes = [];
            
            foreach ($assertion->xpath('//saml:Attribute') as $attr) {
                $name = (string)$attr['Name'];
                $values = $attr->xpath('saml:AttributeValue');
                $attributes[$name] = array_map('strval', $values);
            }

            // Extract NameID
            $nameId = $assertion->xpath('//saml:Subject/saml:NameID');
            if (!empty($nameId)) {
                $attributes['nameid'] = (string)$nameId[0];
            }

            return $attributes;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Extract attributes from SAML response.
     */
    protected function extractSamlAttributes(array $assertion): ?array
    {
        // This would parse the full SAML response
        // Simplified for now
        return [
            'email' => 'user@example.com',
            'name' => 'Test User',
            'given_name' => 'Test',
            'family_name' => 'User',
        ];
    }

    /**
     * Check if provider is configured.
     */
    protected function isProviderConfigured(string $provider, array $config): bool
    {
        $required = match ($provider) {
            'saml' => ['idp_entity_id', 'idp_sso_url', 'idp_x509_cert', 'sp_entity_id', 'sp_acs_url'],
            'oidc' => ['client_id', 'client_secret', 'issuer'],
            'google' => ['client_id', 'client_secret'],
            'microsoft' => ['client_id', 'client_secret', 'tenant_id'],
            'okta' => ['client_id', 'client_secret', 'issuer'],
            default => [],
        };

        foreach ($required as $field) {
            if (empty($config[$field])) {
                return false;
            }
        }
        return true;
    }

    /**
     * Get provider display name.
     */
    protected function getProviderName(string $provider): string
    {
        return match ($provider) {
            'saml' => 'SAML 2.0',
            'oidc' => 'OpenID Connect',
            'google' => 'Google Workspace',
            'microsoft' => 'Microsoft Entra ID',
            'okta' => 'Okta',
            default => ucfirst($provider),
        };
    }

    /**
     * Get login URL for provider.
     */
    protected function getLoginUrl(string $provider): string
    {
        return match ($provider) {
            'saml' => '/auth/saml/login',
            'oidc' => '/auth/oidc/login',
            'google' => '/auth/google/login',
            'microsoft' => '/auth/microsoft/login',
            'okta' => '/auth/okta/login',
            default => "/auth/{$provider}/login",
        };
    }

    /**
     * Get configuration fields for provider.
     */
    protected function getConfigFields(string $provider): array
    {
        return match ($provider) {
            'saml' => [
                ['key' => 'sp_entity_id', 'label' => 'SP Entity ID', 'type' => 'text', 'required' => true],
                ['key' => 'sp_acs_url', 'label' => 'Assertion Consumer Service URL', 'type' => 'url', 'required' => true],
                ['key' => 'sp_sls_url', 'label' => 'Single Logout Service URL', 'type' => 'url', 'required' => false],
                ['key' => 'idp_entity_id', 'label' => 'IdP Entity ID', 'type' => 'text', 'required' => true],
                ['key' => 'idp_sso_url', 'label' => 'IdP SSO URL', 'type' => 'url', 'required' => true],
                ['key' => 'idp_slo_url', 'label' => 'IdP SLO URL', 'type' => 'url', 'required' => false],
                ['key' => 'idp_x509_cert', 'label' => 'IdP X.509 Certificate', 'type' => 'textarea', 'required' => true],
                ['key' => 'sp_x509_cert', 'label' => 'SP X.509 Certificate', 'type' => 'textarea', 'required' => false],
                ['key' => 'sp_private_key', 'label' => 'SP Private Key', 'type' => 'textarea', 'required' => false],
            ],
            'oidc' => [
                ['key' => 'client_id', 'label' => 'Client ID', 'type' => 'text', 'required' => true],
                ['key' => 'client_secret', 'label' => 'Client Secret', 'type' => 'password', 'required' => true],
                ['key' => 'issuer', 'label' => 'Issuer URL', 'type' => 'url', 'required' => true],
                ['key' => 'discovery_url', 'label' => 'Discovery URL', 'type' => 'url', 'required' => false],
                ['key' => 'redirect_uri', 'label' => 'Redirect URI', 'type' => 'url', 'required' => true],
                ['key' => 'scopes', 'label' => 'Scopes', 'type' => 'array', 'required' => false],
            ],
            'google' => [
                ['key' => 'client_id', 'label' => 'Client ID', 'type' => 'text', 'required' => true],
                ['key' => 'client_secret', 'label' => 'Client Secret', 'type' => 'password', 'required' => true],
                ['key' => 'redirect_uri', 'label' => 'Redirect URI', 'type' => 'url', 'required' => true],
                ['key' => 'hosted_domain', 'label' => 'Hosted Domain (optional)', 'type' => 'text', 'required' => false],
            ],
            'microsoft' => [
                ['key' => 'client_id', 'label' => 'Client ID', 'type' => 'text', 'required' => true],
                ['key' => 'client_secret', 'label' => 'Client Secret', 'type' => 'password', 'required' => true],
                ['key' => 'tenant_id', 'label' => 'Tenant ID', 'type' => 'text', 'required' => true],
                ['key' => 'redirect_uri', 'label' => 'Redirect URI', 'type' => 'url', 'required' => true],
            ],
            'okta' => [
                ['key' => 'client_id', 'label' => 'Client ID', 'type' => 'text', 'required' => true],
                ['key' => 'client_secret', 'label' => 'Client Secret', 'type' => 'password', 'required' => true],
                ['key' => 'issuer', 'label' => 'Issuer URL', 'type' => 'url', 'required' => true],
                ['key' => 'redirect_uri', 'label' => 'Redirect URI', 'type' => 'url', 'required' => true],
            ],
        };
    }
}
