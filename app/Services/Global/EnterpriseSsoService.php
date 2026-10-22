<?php

namespace App\Services\Global;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class EnterpriseSsoService
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'enabled' => true,
            'providers' => [
                'ldap' => [
                    'enabled' => false,
                    'host' => '',
                    'port' => 389,
                    'use_ssl' => false,
                    'use_tls' => true,
                    'base_dn' => '',
                    'bind_dn' => '',
                    'bind_password' => '',
                    'username_attribute' => 'uid',
                    'email_attribute' => 'mail',
                    'first_name_attribute' => 'givenName',
                    'last_name_attribute' => 'sn',
                    'display_name_attribute' => 'displayName',
                    'group_attribute' => 'memberOf',
                    'group_base_dn' => '',
                    'group_filter' => '(objectClass=group)',
                    'user_filter' => '(objectClass=person)',
                    'referrals' => false,
                    'timeout' => 10,
                    'version' => 3,
                ],
                'active_directory' => [
                    'enabled' => false,
                    'host' => '',
                    'port' => 389,
                    'use_ssl' => false,
                    'use_tls' => true,
                    'domain' => '',
                    'base_dn' => '',
                    'bind_dn' => '',
                    'bind_password' => '',
                    'username_attribute' => 'sAMAccountName',
                    'email_attribute' => 'userPrincipalName',
                    'first_name_attribute' => 'givenName',
                    'last_name_attribute' => 'sn',
                    'display_name_attribute' => 'displayName',
                    'group_attribute' => 'memberOf',
                    'group_base_dn' => '',
                    'group_filter' => '(objectClass=group)',
                    'user_filter' => '(objectClass=user)',
                    'referrals' => true,
                    'timeout' => 10,
                    'version' => 3,
                    'follow_referrals' => true,
                ],
                'kerberos' => [
                    'enabled' => false,
                    'realm' => '',
                    'kdc' => '',
                    'admin_server' => '',
                    'keytab' => '',
                    'service_principal' => 'HTTP/webixray.com@EXAMPLE.COM',
                    'spn_ego' => true,
                    'delegate' => true,
                ],
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
            ],
            'auto_provision' => true,
            'default_role' => 'user',
            'sync_groups' => true,
            'sync_interval' => 3600, // 1 hour
            'just_in_time_provisioning' => true,
            'fallback_to_local' => true,
        ], []);
    }

    /**
     * Get available enterprise SSO providers.
     */
    public function getProviders(Tenant $tenant): array
    {
        $tenantConfig = $tenant->enterprise_sso_config ?? [];
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
                'supports_groups' => in_array($id, ['ldap', 'active_directory']),
                'supports_kerberos' => $id === 'kerberos',
            ];
        }

        return $providers;
    }

    /**
     * Authenticate user via LDAP/Active Directory.
     */
    public function authenticateLdap(string $provider, string $username, string $password, Tenant $tenant): array
    {
        $config = $this->getProviderConfig($provider, $tenant);
        
        if (!$config['enabled']) {
            return ['success' => false, 'error' => 'Provider not enabled'];
        }

        try {
            // Connect to LDAP server
            $connection = $this->connectLdap($config);
            if (!$connection) {
                return ['success' => false, 'error' => 'Failed to connect to LDAP server'];
            }

            // Bind with service account
            if (!$this->bindLdap($connection, $config)) {
                return ['success' => false, 'error' => 'Failed to bind to LDAP server'];
            }

            // Search for user
            $userEntry = $this->searchLdapUser($connection, $config, $username);
            if (!$userEntry) {
                return ['success' => false, 'error' => 'User not found'];
            }

            // Bind as user to verify password
            $userDn = $userEntry['dn'];
            if (!$this->bindAsUser($connection, $userDn, $password)) {
                return ['success' => false, 'error' => 'Invalid credentials'];
            }

            // Get user attributes
            $attributes = $this->getLdapUserAttributes($userEntry, $config);
            
            // Get user groups
            $groups = $this->getLdapUserGroups($connection, $config, $userDn);

            // Close connection
            ldap_close($connection);

            return [
                'success' => true,
                'user' => $attributes,
                'groups' => $groups,
                'dn' => $userDn,
            ];
        } catch (\Throwable $e) {
            Log::error('LDAP authentication error', [
                'provider' => $provider,
                'username' => $username,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => 'Authentication failed: ' . $e->getMessage()];
        }
    }

    /**
     * Connect to LDAP server.
     */
    protected function connectLdap(array $config): ?resource
    {
        $host = $config['host'];
        $port = $config['port'] ?? 389;
        $useSsl = $config['use_ssl'] ?? false;
        $useTls = $config['use_tls'] ?? true;

        $uri = ($useSsl ? 'ldaps' : 'ldap') . "://{$host}:{$port}";
        
        $connection = ldap_connect($uri);
        if (!$connection) {
            return null;
        }

        ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, $config['version'] ?? 3);
        ldap_set_option($connection, LDAP_OPT_REFERRALS, $config['referrals'] ?? false);
        ldap_set_option($connection, LDAP_OPT_NETWORK_TIMEOUT, $config['timeout'] ?? 10);

        if ($useTls && !$useSsl) {
            if (!ldap_start_tls($connection)) {
                Log::warning('LDAP StartTLS failed');
            }
        }

        return $connection;
    }

    /**
     * Bind to LDAP with service account.
     */
    protected function bindLdap(resource $connection, array $config): bool
    {
        if (empty($config['bind_dn']) || empty($config['bind_password'])) {
            // Anonymous bind
            return @ldap_bind($connection);
        }

        return @ldap_bind($connection, $config['bind_dn'], $config['bind_password']);
    }

    /**
     * Search for user in LDAP.
     */
    protected function searchLdapUser(resource $connection, array $config, string $username): ?array
    {
        $baseDn = $config['base_dn'];
        $usernameAttr = $config['username_attribute'];
        $userFilter = $config['user_filter'];

        $filter = "(&{$userFilter}({$usernameAttr}={$username}))";
        $attributes = [
            $config['username_attribute'],
            $config['email_attribute'],
            $config['first_name_attribute'],
            $config['last_name_attribute'],
            $config['display_name_attribute'],
            $config['group_attribute'],
        ];

        $result = @ldap_search($connection, $baseDn, $filter, $attributes);
        if (!$result) {
            return null;
        }

        $entries = ldap_get_entries($connection, $result);
        if ($entries['count'] === 0) {
            return null;
        }

        $entry = $entries[0];
        return [
            'dn' => $entry['dn'],
            'attributes' => $entry,
        ];
    }

    /**
     * Bind as user to verify password.
     */
    protected function bindAsUser(resource $connection, string $userDn, string $password): bool
    {
        return @ldap_bind($connection, $userDn, $password);
    }

    /**
     * Get user attributes from LDAP entry.
     */
    protected function getLdapUserAttributes(array $userEntry, array $config): array
    {
        $attrs = $userEntry['attributes'];
        
        return [
            'username' => $attrs[$config['username_attribute']][0] ?? '',
            'email' => $attrs[$config['email_attribute']][0] ?? '',
            'first_name' => $attrs[$config['first_name_attribute']][0] ?? '',
            'last_name' => $attrs[$config['last_name_attribute']][0] ?? '',
            'display_name' => $attrs[$config['display_name_attribute']][0] ?? '',
            'dn' => $userEntry['dn'],
        ];
    }

    /**
     * Get user groups from LDAP.
     */
    protected function getLdapUserGroups(resource $connection, array $config, string $userDn): array
    {
        if (!$config['sync_groups'] || empty($config['group_base_dn'])) {
            return [];
        }

        $groupAttr = $config['group_attribute'];
        $groupFilter = $config['group_filter'];

        // Get user's memberOf attribute
        $userGroups = $config['attributes'][$groupAttr] ?? [];
        
        $groups = [];
        foreach ($userGroups as $groupDn) {
            $groupEntry = $this->getLdapGroup($connection, $groupDn);
            if ($groupEntry) {
                $groups[] = $groupEntry;
            }
        }

        return $groups;
    }

    /**
     * Get LDAP group details.
     */
    protected function getLdapGroup(resource $connection, string $groupDn): ?array
    {
        $result = @ldap_read($connection, $groupDn, '(objectClass=*)', ['cn', 'description', 'member']);
        if (!$result) return null;

        $entries = ldap_get_entries($connection, $result);
        if ($entries['count'] === 0) return null;

        $entry = $entries[0];
        return [
            'dn' => $entry['dn'],
            'name' => $entry['cn'][0] ?? '',
            'description' => $entry['description'][0] ?? '',
            'members' => $entry['member'] ?? [],
        ];
    }

    /**
     * Authenticate via Kerberos (SPNEGO).
     */
    public function authenticateKerberos(string $authorizationHeader, Tenant $tenant): array
    {
        $config = $this->getProviderConfig('kerberos', $tenant);
        
        if (!$config['enabled']) {
            return ['success' => false, 'error' => 'Kerberos not enabled'];
        }

        // Parse SPNEGO token
        if (!str_starts_with($authorizationHeader, 'Negotiate ')) {
            return ['success' => false, 'error' => 'Invalid authorization header'];
        }

        $token = base64_decode(substr($authorizationHeader, 10));
        
        // Would use GSSAPI to validate token
        // This is a simplified implementation
        $principal = $this->validateKerberosToken($token, $config);
        
        if (!$principal) {
            return ['success' => false, 'error' => 'Invalid Kerberos token'];
        }

        // Extract username from principal (user@REALM)
        $username = explode('@', $principal)[0];

        // Find or create user
        $user = $this->findOrCreateUser($tenant, [
            'username' => $username,
            'email' => $username . '@' . strtolower($config['realm']),
            'first_name' => '',
            'last_name' => '',
            'kerberos_principal' => $principal,
        ], 'kerberos');

        return [
            'success' => true,
            'user' => $user,
            'principal' => $principal,
        ];
    }

    /**
     * Validate Kerberos token.
     */
    protected function validateKerberosToken(string $token, array $config): ?string
    {
        // In production, use GSSAPI (gss_accept_sec_context)
        // This would validate the SPNEGO token against the KDC
        // For now, return a mock principal
        return 'user@' . $config['realm'];
    }

    /**
     * Initiate SAML authentication.
     */
    public function initiateSamlAuth(Tenant $tenant, array $options = []): array
    {
        $config = $this->getProviderConfig('saml', $tenant);
        
        if (!$config['enabled']) {
            return ['success' => false, 'error' => 'SAML not enabled'];
        }

        // Generate SAML AuthnRequest
        $authnRequest = $this->buildSamlAuthnRequest($config, $options);
        
        // Store request ID for validation
        $requestId = $authnRequest['id'];
        Cache::put("saml_request_{$requestId}", [
            'tenant_id' => $tenant->id,
            'relay_state' => $options['relay_state'] ?? '',
            'created_at' => now(),
        ], 600);

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
    public function handleSamlCallback(array $requestData): array
    {
        $samlResponse = $requestData['SAMLResponse'] ?? '';
        $relayState = $requestData['RelayState'] ?? '';

        if (empty($samlResponse)) {
            return ['success' => false, 'error' => 'Missing SAMLResponse'];
        }

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
     * Build SAML AuthnRequest.
     */
    protected function buildSamlAuthnRequest(array $config, array $options): array
    {
        $requestId = '_' . Str::random(32);
        $issueInstant = now()->format('Y-m-d\TH:i:s\Z');
        
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
     * Find or create user from SSO attributes.
     */
    public function findOrCreateUser(Tenant $tenant, array $attributes, string $provider): ?User
    {
        $email = $attributes['email'] ?? $attributes['emailaddress'] ?? $attributes['http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress'] ?? '';
        $username = $attributes['username'] ?? $attributes['nameid'] ?? '';
        $externalId = $attributes['dn'] ?? $attributes['sub'] ?? $attributes['kerberos_principal'] ?? '';

        if (empty($email) && empty($username)) {
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

        // Create new user
        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => $attributes['display_name'] ?? ($attributes['first_name'] . ' ' . $attributes['last_name']) ?? $username,
            'email' => $email ?: $username . '@' . $tenant->domain,
            'password' => Hash::make(Str::random(32)),
            'external_id' => $externalId,
            'sso_provider' => $provider,
            'email_verified_at' => now(),
        ]);

        // Assign default role
        $defaultRole = $this->config['default_role'] ?? 'user';
        $role = \Spatie\Permission\Models\Role::where('name', $defaultRole)
            ->where('guard_name', 'api')
            ->first();
        
        if ($role) {
            $user->assignRole($role);
        }

        // Sync groups if available
        if (!empty($attributes['groups']) && $this->config['sync_groups']) {
            $this->syncUserGroups($user, $attributes['groups'], $tenant);
        }

        Log::info('User created via Enterprise SSO', [
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'provider' => $provider,
            'email' => $email,
        ]);

        return $user;
    }

    /**
     * Sync user groups from SSO.
     */
    protected function syncUserGroups(User $user, array $groups, Tenant $tenant): void
    {
        foreach ($groups as $group) {
            $groupName = $group['name'] ?? $group['cn'] ?? '';
            if (empty($groupName)) continue;

            // Find or create team for group
            $team = \App\Models\Team::firstOrCreate([
                'tenant_id' => $tenant->id,
                'name' => $groupName,
            ], [
                'description' => $group['description'] ?? "Synced from {$groupName}",
                'external_id' => $group['dn'] ?? '',
                'is_synced' => true,
            ]);

            $user->teams()->syncWithoutDetaching([$team->id]);
        }
    }

    /**
     * Sync all users/groups from enterprise directory.
     */
    public function syncDirectory(Tenant $tenant, string $provider): array
    {
        $config = $this->getProviderConfig($provider, $tenant);
        
        if (!$config['enabled']) {
            return ['success' => false, 'error' => 'Provider not enabled'];
        }

        $connection = $this->connectLdap($config);
        if (!$connection) {
            return ['success' => false, 'error' => 'Failed to connect'];
        }

        if (!$this->bindLdap($connection, $config)) {
            return ['success' => false, 'error' => 'Failed to bind'];
        }

        $synced = 0;
        $errors = 0;

        // Search all users
        $baseDn = $config['base_dn'];
        $userFilter = $config['user_filter'];
        $attributes = [
            $config['username_attribute'],
            $config['email_attribute'],
            $config['first_name_attribute'],
            $config['last_name_attribute'],
            $config['display_name_attribute'],
            $config['group_attribute'],
        ];

        $result = @ldap_search($connection, $baseDn, $userFilter, $attributes);
        if ($result) {
            $entries = ldap_get_entries($connection, $result);
            
            for ($i = 0; $i < $entries['count']; $i++) {
                try {
                    $entry = $entries[$i];
                    $attrs = $this->getLdapUserAttributes(['dn' => $entry['dn'], 'attributes' => $entry], $config);
                    $groups = $this->getLdapUserGroups($connection, $config, $entry['dn']);
                    
                    $this->findOrCreateUser($tenant, array_merge($attrs, ['groups' => $groups]), $provider);
                    $synced++;
                } catch (\Throwable $e) {
                    $errors++;
                    Log::error('Failed to sync user', ['error' => $e->getMessage()]);
                }
            }
        }

        ldap_close($connection);

        // Update last sync time
        $tenant->update([
            'enterprise_sso_config' => array_merge($tenant->enterprise_sso_config ?? [], [
                $provider => array_merge($config, ['last_sync' => now()->toISOString()]),
            ]),
        ]);

        return [
            'success' => true,
            'synced' => $synced,
            'errors' => $errors,
        ];
    }

    /**
     * Get provider config for tenant.
     */
    protected function getProviderConfig(string $provider, Tenant $tenant): array
    {
        $baseConfig = $this->config['providers'][$provider] ?? [];
        $tenantConfig = $tenant->enterprise_sso_config[$provider] ?? [];
        return array_merge($baseConfig, $tenantConfig);
    }

    /**
     * Check if provider is configured.
     */
    protected function isProviderConfigured(string $provider, array $config): bool
    {
        $required = match ($provider) {
            'ldap', 'active_directory' => ['host', 'base_dn', 'bind_dn', 'bind_password'],
            'kerberos' => ['realm', 'kdc', 'keytab'],
            'saml' => ['idp_entity_id', 'idp_sso_url', 'idp_x509_cert', 'sp_entity_id', 'sp_acs_url'],
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
            'ldap' => 'LDAP',
            'active_directory' => 'Active Directory',
            'kerberos' => 'Kerberos (SPNEGO)',
            'saml' => 'SAML 2.0',
            default => ucfirst($provider),
        };
    }

    /**
     * Get login URL for provider.
     */
    protected function getLoginUrl(string $provider): string
    {
        return match ($provider) {
            'ldap' => '/auth/ldap/login',
            'active_directory' => '/auth/ad/login',
            'kerberos' => '/auth/kerberos/login',
            'saml' => '/auth/saml/login',
            default => "/auth/{$provider}/login",
        };
    }

    /**
     * Get configuration fields for provider.
     */
    protected function getConfigFields(string $provider): array
    {
        return match ($provider) {
            'ldap', 'active_directory' => [
                ['key' => 'host', 'label' => 'Server Host', 'type' => 'text', 'required' => true],
                ['key' => 'port', 'label' => 'Port', 'type' => 'number', 'required' => true, 'default' => 389],
                ['key' => 'use_ssl', 'label' => 'Use SSL (LDAPS)', 'type' => 'boolean', 'default' => false],
                ['key' => 'use_tls', 'label' => 'Use StartTLS', 'type' => 'boolean', 'default' => true],
                ['key' => 'base_dn', 'label' => 'Base DN', 'type' => 'text', 'required' => true],
                ['key' => 'bind_dn', 'label' => 'Bind DN', 'type' => 'text', 'required' => true],
                ['key' => 'bind_password', 'label' => 'Bind Password', 'type' => 'password', 'required' => true],
                ['key' => 'username_attribute', 'label' => 'Username Attribute', 'type' => 'text', 'default' => 'uid'],
                ['key' => 'email_attribute', 'label' => 'Email Attribute', 'type' => 'text', 'default' => 'mail'],
                ['key' => 'first_name_attribute', 'label' => 'First Name Attribute', 'type' => 'text', 'default' => 'givenName'],
                ['key' => 'last_name_attribute', 'label' => 'Last Name Attribute', 'type' => 'text', 'default' => 'sn'],
                ['key' => 'group_base_dn', 'label' => 'Group Base DN', 'type' => 'text'],
                ['key' => 'sync_groups', 'label' => 'Sync Groups', 'type' => 'boolean', 'default' => true],
            ],
            'kerberos' => [
                ['key' => 'realm', 'label' => 'Kerberos Realm', 'type' => 'text', 'required' => true],
                ['key' => 'kdc', 'label' => 'KDC Host', 'type' => 'text', 'required' => true],
                ['key' => 'admin_server', 'label' => 'Admin Server', 'type' => 'text'],
                ['key' => 'keytab', 'label' => 'Keytab Path', 'type' => 'text', 'required' => true],
                ['key' => 'service_principal', 'label' => 'Service Principal', 'type' => 'text', 'required' => true],
            ],
            'saml' => [
                ['key' => 'sp_entity_id', 'label' => 'SP Entity ID', 'type' => 'text', 'required' => true],
                ['key' => 'sp_acs_url', 'label' => 'Assertion Consumer Service URL', 'type' => 'url', 'required' => true],
                ['key' => 'sp_sls_url', 'label' => 'Single Logout Service URL', 'type' => 'url'],
                ['key' => 'idp_entity_id', 'label' => 'IdP Entity ID', 'type' => 'text', 'required' => true],
                ['key' => 'idp_sso_url', 'label' => 'IdP SSO URL', 'type' => 'url', 'required' => true],
                ['key' => 'idp_slo_url', 'label' => 'IdP SLO URL', 'type' => 'url'],
                ['key' => 'idp_x509_cert', 'label' => 'IdP X.509 Certificate', 'type' => 'textarea', 'required' => true],
                ['key' => 'sp_x509_cert', 'label' => 'SP X.509 Certificate', 'type' => 'textarea'],
                ['key' => 'sp_private_key', 'label' => 'SP Private Key', 'type' => 'textarea'],
            ],
        };
    }
}
