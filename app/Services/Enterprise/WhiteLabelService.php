<?php

namespace App\Services\Enterprise;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

class WhiteLabelService
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'enabled' => false,
            'custom_domain_support' => true,
            'ssl_auto_provision' => true,
            'ssl_provider' => 'letsencrypt', // letsencrypt, cloudflare, custom
            'cloudflare_api_token' => '',
            'cloudflare_zone_id' => '',
            'custom_css_path' => 'white-label/css',
            'custom_js_path' => 'white-label/js',
            'assets_path' => 'white-label/assets',
            'default_theme' => 'light',
            'allowed_origins' => [],
            'cname_verification_timeout' => 300, // 5 minutes
            'ssl_renewal_days_before' => 30,
        ], []);
    }

    /**
     * Configure white-label for a tenant.
     */
    public function configureTenant(Tenant $tenant, array $config): array
    {
        $validated = $this->validateConfig($config);
        
        $tenant->update([
            'white_label_config' => array_merge($tenant->white_label_config ?? [], $validated),
        ]);

        // If custom domain provided, initiate setup
        if (!empty($validated['custom_domain'])) {
            $this->setupCustomDomain($tenant, $validated['custom_domain']);
        }

        // Apply theme changes
        if (!empty($validated['theme'])) {
            $this->applyTheme($tenant, $validated['theme']);
        }

        // Handle custom CSS/JS
        if (!empty($validated['custom_css'])) {
            $this->saveCustomCss($tenant, $validated['custom_css']);
        }

        if (!empty($validated['custom_js'])) {
            $this->saveCustomJs($tenant, $validated['custom_js']);
        }

        // Handle assets
        if (!empty($validated['assets'])) {
            $this->processAssets($tenant, $validated['assets']);
        }

        // Handle email templates
        if (!empty($validated['email_templates'])) {
            $this->saveEmailTemplates($tenant, $validated['email_templates']);
        }

        return $this->getTenantConfig($tenant);
    }

    /**
     * Get tenant white-label configuration.
     */
    public function getTenantConfig(Tenant $tenant): array
    {
        $config = $tenant->white_label_config ?? [];
        
        return array_merge([
            'enabled' => false,
            'custom_domain' => null,
            'domain_verified' => false,
            'ssl_status' => 'none',
            'theme' => 'light',
            'brand_name' => $tenant->name,
            'logo_url' => null,
            'favicon_url' => null,
            'custom_css' => '',
            'custom_js' => '',
            'assets' => [],
            'email_templates' => [],
            'login_page' => [],
            'colors' => [
                'primary' => '#3B82F6',
                'secondary' => '#64748B',
                'success' => '#10B981',
                'warning' => '#F59E0B',
                'danger' => '#EF4444',
            ],
            'fonts' => [
                'heading' => 'Inter',
                'body' => 'Inter',
            ],
            'custom_domain_status' => 'none',
            'ssl_status' => 'none',
            'ssl_expires_at' => null,
        ], $config);
    }

    /**
     * Validate white-label configuration.
     */
    protected function validateConfig(array $config): array
    {
        $validated = [];

        // Custom domain
        if (!empty($config['custom_domain'])) {
            $domain = strtolower(trim($config['custom_domain']));
            if (!$this->isValidDomain($domain)) {
                throw new \InvalidArgumentException('Invalid domain format');
            }
            $validated['custom_domain'] = $domain;
        }

        // Theme
        if (!empty($config['theme'])) {
            if (!in_array($config['theme'], ['light', 'dark', 'auto'])) {
                throw new \InvalidArgumentException('Invalid theme');
            }
            $validated['theme'] = $config['theme'];
        }

        // Brand name
        if (!empty($config['brand_name'])) {
            $validated['brand_name'] = Str::limit($config['brand_name'], 100);
        }

        // Colors
        if (!empty($config['colors']) && is_array($config['colors'])) {
            $validated['colors'] = array_merge([
                'primary' => '#3B82F6',
                'secondary' => '#64748B',
                'success' => '#10B981',
                'warning' => '#F59E0B',
                'danger' => '#EF4444',
            ], array_intersect_key($config['colors'], array_flip([
                'primary', 'secondary', 'success', 'warning', 'danger',
                'info', 'light', 'dark', 'background', 'surface',
            ]));
        }

        // Fonts
        if (!empty($config['fonts']) && is_array($config['fonts'])) {
            $validated['fonts'] = array_merge([
                'heading' => 'Inter',
                'body' => 'Inter',
            ], array_intersect_key($config['fonts'], array_flip([
                'heading', 'body', 'monospace',
            ])));
        }

        // Custom CSS
        if (!empty($config['custom_css'])) {
            $validated['custom_css'] = Str::limit($config['custom_css'], 50000);
        }

        // Custom JS
        if (!empty($config['custom_js'])) {
            $validated['custom_js'] = Str::limit($config['custom_js'], 50000);
        }

        // Assets
        if (!empty($config['assets']) && is_array($config['assets'])) {
            $validated['assets'] = $this->validateAssets($config['assets']);
        }

        // Email templates
        if (!empty($config['email_templates']) && is_array($config['email_templates'])) {
            $validated['email_templates'] = $this->validateEmailTemplates($config['email_templates']);
        }

        // Login page config
        if (!empty($config['login_page']) && is_array($config['login_page'])) {
            $validated['login_page'] = array_merge([
                'show_powered_by' => true,
                'background_image' => '',
                'custom_message' => '',
                'show_remember_me' => true,
                'show_forgot_password' => true,
                'show_sso_buttons' => true,
            ], $config['login_page']);
        }

        // Favicon
        if (!empty($config['favicon_url'])) {
            $validated['favicon_url'] = $config['favicon_url'];
        }

        // Logo
        if (!empty($config['logo_url'])) {
            $validated['logo_url'] = $config['logo_url'];
        }

        return $validated;
    }

    /**
     * Validate domain format.
     */
    protected function isValidDomain(string $domain): bool
    {
        // Remove protocol if present
        $domain = preg_replace('/^https?:\/\//', '', $domain);
        $domain = rtrim($domain, '/');

        // Basic domain validation
        if (!preg_match('/^([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i', $domain)) {
            return false;
        }

        // Check for reserved domains
        $reserved = ['localhost', 'example.com', 'test.com', 'local'];
        if (in_array($domain, $reserved)) {
            return false;
        }

        return true;
    }

    /**
     * Validate assets.
     */
    protected function validateAssets(array $assets): array
    {
        $validated = [];
        $allowedTypes = ['logo', 'favicon', 'background', 'illustration', 'icon'];
        $allowedMimes = ['image/png', 'image/jpeg', 'image/svg+xml', 'image/webp'];
        $maxSize = 5 * 1024 * 1024; // 5MB

        foreach ($assets as $asset) {
            if (!isset($asset['type']) || !in_array($asset['type'], $allowedTypes)) {
                continue;
            }

            if (!isset($asset['url']) && !isset($asset['content'])) {
                continue;
            }

            $validated[] = [
                'type' => $asset['type'],
                'url' => $asset['url'] ?? null,
                'name' => $asset['name'] ?? '',
                'width' => $asset['width'] ?? null,
                'height' => $asset['height'] ?? null,
            ];
        }

        return $validated;
    }

    /**
     * Validate email templates.
     */
    protected function validateEmailTemplates(array $templates): array
    {
        $validated = [];
        $allowedTypes = ['welcome', 'verification', 'password_reset', 'invitation', 'notification', 'report', 'invoice', 'alert'];

        foreach ($templates as $key => $template) {
            if (!in_array($key, $allowedTypes)) {
                continue;
            }

            $validated[$key] = [
                'subject' => $template['subject'] ?? '',
                'html' => $template['html'] ?? '',
                'text' => $template['text'] ?? '',
                'variables' => $template['variables'] ?? [],
            ];
        }

        return $validated;
    }

    /**
     * Setup custom domain for tenant.
     */
    public function setupCustomDomain(Tenant $tenant, string $domain): array
    {
        // Verify domain ownership
        $verification = $this->verifyDomainOwnership($domain);
        
        $result = [
            'domain' => $domain,
            'verified' => $verification['verified'],
            'verification_method' => $verification['method'] ?? 'dns',
            'dns_records' => $verification['records'] ?? [],
            'ssl_status' => 'pending',
        ];

        $tenant->update([
            'white_label_config' => array_merge($tenant->white_label_config ?? [], [
                'custom_domain' => $domain,
                'domain_verified' => $verification['verified'],
                'custom_domain_status' => $verification['verified'] ? 'verified' : 'pending_verification',
            ]),
        ]);

        // If verified, provision SSL
        if ($verification['verified'] && $this->config['ssl_auto_provision']) {
            $this->provisionSsl($tenant, $domain);
        }

        return $result;
    }

    /**
     * Verify domain ownership via DNS.
     */
    protected function verifyDomainOwnership(string $domain): array
    {
        $verificationToken = 'webixray-verify-' . Str::random(16);
        $txtRecord = "webixray-verification={$verificationToken}";
        
        // Check TXT record
        $txtRecords = dns_get_record($domain, DNS_TXT);
        $verified = false;
        
        foreach ($txtRecords as $record) {
            if (strpos($record['txt'], $verificationToken) !== false) {
                $verified = true;
                break;
            }
        }

        // Also check CNAME pointing to our platform
        $cnameRecords = dns_get_record($domain, DNS_CNAME);
        $cnameVerified = false;
        foreach ($cnameRecords as $record) {
            if (strpos($record['target'], config('app.url')) !== false) {
                $cnameVerified = true;
                break;
            }
        }

        return [
            'verified' => $verified || $cnameVerified,
            'method' => $verified ? 'txt' : ($cnameVerified ? 'cname' : 'none'),
            'records' => [
                'txt' => [
                    'name' => '@',
                    'value' => $txtRecord,
                    'type' => 'TXT',
                ],
                'cname' => [
                    'name' => '@',
                    'value' => parse_url(config('app.url'), PHP_URL_HOST),
                    'type' => 'CNAME',
                ],
            ],
        ];
    }

    /**
     * Provision SSL certificate.
     */
    public function provisionSsl(Tenant $tenant, string $domain): array
    {
        if (!$this->config['ssl_auto_provision']) {
            return ['status' => 'skipped', 'message' => 'Auto SSL provisioning disabled'];
        }

        try {
            $provider = $this->config['ssl_provider'];
            
            return match ($provider) {
                'letsencrypt' => $this->provisionLetsEncrypt($domain),
                'cloudflare' => $this->provisionCloudflareSsl($domain),
                'custom' => $this->provisionCustomSsl($domain),
                default => ['status' => 'error', 'message' => 'Unknown SSL provider'],
            };
        } catch (\Throwable $e) {
            Log::error('SSL provisioning failed', [
                'tenant_id' => $tenant->id,
                'domain' => $domain,
                'error' => $e->getMessage(),
            ]);
            
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Provision SSL via Let's Encrypt.
     */
    protected function provisionLetsEncrypt(string $domain): array
    {
        // This would use certbot or ACME client
        // Simplified implementation
        return [
            'status' => 'provisioned',
            'provider' => 'letsencrypt',
            'expires_at' => now()->addDays(90)->toISOString(),
        ];
    }

    /**
     * Provision SSL via Cloudflare.
     */
    protected function provisionCloudflareSsl(string $domain): array
    {
        if (empty($this->config['cloudflare_api_token']) || empty($this->config['cloudflare_zone_id'])) {
            return ['status' => 'error', 'message' => 'Cloudflare credentials not configured'];
        }

        // Would use Cloudflare API to enable SSL
        return [
            'status' => 'provisioned',
            'provider' => 'cloudflare',
            'expires_at' => now()->addYear()->toISOString(),
        ];
    }

    /**
     * Provision custom SSL.
     */
    protected function provisionCustomSsl(string $domain): array
    {
        // Custom SSL would be uploaded manually
        return [
            'status' => 'pending',
            'provider' => 'custom',
            'message' => 'Upload certificate and private key manually',
        ];
    }

    /**
     * Apply theme to tenant.
     */
    public function applyTheme(Tenant $tenant, string $theme): array
    {
        $validThemes = ['light', 'dark', 'auto'];
        
        if (!in_array($theme, $validThemes)) {
            throw new \InvalidArgumentException('Invalid theme');
        }

        $tenant->update([
            'white_label_config' => array_merge($tenant->white_label_config ?? [], [
                'theme' => $theme,
            ]),
        ]);

        // Generate CSS variables
        $this->generateThemeCss($tenant, $theme);

        return ['theme' => $theme];
    }

    /**
     * Generate theme CSS variables.
     */
    protected function generateThemeCss(Tenant $tenant, string $theme): void
    {
        $config = $this->getTenantConfig($tenant);
        $colors = $config['colors'] ?? [];
        
        $css = ":root {\n";
        
        if ($theme === 'dark') {
            $css .= "  --color-bg-primary: #0f172a;\n";
            $css .= "  --color-bg-secondary: #1e293b;\n";
            $css .= "  --color-text-primary: #f1f5f9;\n";
            $css .= "  --color-text-secondary: #94a3b8;\n";
            $css .= "  --color-border: #334155;\n";
        } else {
            $css .= "  --color-bg-primary: #ffffff;\n";
            $css .= "  --color-bg-secondary: #f8fafc;\n";
            $css .= "  --color-text-primary: #0f172a;\n";
            $css .= "  --color-text-secondary: #475569;\n";
            $css .= "  --color-border: #e2e8f0;\n";
        }

        $css .= "  --color-primary: {$colors['primary']};\n";
        $css .= "  --color-secondary: {$colors['secondary']};\n";
        $css .= "  --color-success: {$colors['success']};\n";
        $css .= "  --color-warning: {$colors['warning']};\n";
        $css .= "  --color-danger: {$colors['danger']};\n";
        $css .= "  --font-heading: {$config['fonts']['heading']};\n";
        $css .= "  --font-body: {$config['fonts']['body']};\n";
        $css .= "}\n";

        // Save to storage
        Storage::disk('public')->put(
            $this->config['custom_css_path'] . "/tenant_{$tenant->id}/theme.css",
            $css
        );
    }

    /**
     * Save custom CSS.
     */
    public function saveCustomCss(Tenant $tenant, string $css): void
    {
        $tenant->update([
            'white_label_config' => array_merge($tenant->white_label_config ?? [], [
                'custom_css' => $css,
            ]),
        ]);

        Storage::disk('public')->put(
            $this->config['custom_css_path'] . "/tenant_{$tenant->id}/custom.css",
            $css
        );
    }

    /**
     * Save custom JavaScript.
     */
    public function saveCustomJs(Tenant $tenant, string $js): void
    {
        $tenant->update([
            'white_label_config' => array_merge($tenant->white_label_config ?? [], [
                'custom_js' => $js,
            ]),
        );

        Storage::disk('public')->put(
            $this->config['custom_js_path'] . "/tenant_{$tenant->id}/custom.js",
            $js
        );
    }

    /**
     * Process and store assets.
     */
    protected function processAssets(Tenant $tenant, array $assets): void
    {
        $processed = [];
        
        foreach ($assets as $asset) {
            if (isset($asset['content']) && $asset['content']) {
                // Base64 encoded content
                $content = base64_decode($asset['content']);
                $filename = $asset['name'] ?? Str::random(16) . '.png';
                $path = $this->config['assets_path'] . "/tenant_{$tenant->id}/" . $filename;
                
                Storage::disk('public')->put($path, $content);
                $asset['url'] = Storage::url($path);
            }
            
            $processed[] = $asset;
        }

        // Update tenant config
        $config = $tenant->white_label_config ?? [];
        $config['assets'] = array_merge($config['assets'] ?? [], $processed);
        $tenant->update(['white_label_config' => $config]);
    }

    /**
     * Save email templates.
     */
    protected function saveEmailTemplates(Tenant $tenant, array $templates): void
    {
        $config = $tenant->white_label_config ?? [];
        $config['email_templates'] = $templates;
        $tenant->update(['white_label_config' => $config]);
    }

    /**
     * Get tenant's custom CSS URL.
     */
    public function getCustomCssUrl(Tenant $tenant): ?string
    {
        $path = $this->config['custom_css_path'] . "/tenant_{$tenant->id}/custom.css";
        
        if (Storage::disk('public')->exists($path)) {
            return Storage::url($path);
        }

        // Check for theme CSS
        $themePath = $this->config['custom_css_path'] . "/tenant_{$tenant->id}/theme.css";
        if (Storage::disk('public')->exists($themePath)) {
            return Storage::url($themePath);
        }

        return null;
    }

    /**
     * Get tenant's custom JS URL.
     */
    public function getCustomJsUrl(Tenant $tenant): ?string
    {
        $path = $this->config['custom_js_path'] . "/tenant_{$tenant->id}/custom.js";
        
        if (Storage::disk('public')->exists($path)) {
            return Storage::url($path);
        }

        return null;
    }

    /**
     * Get tenant's asset URLs.
     */
    public function getAssetUrls(Tenant $tenant): array
    {
        $config = $tenant->white_label_config ?? [];
        $assets = $config['assets'] ?? [];
        
        $urls = [];
        foreach ($assets as $asset) {
            if (!empty($asset['url'])) {
                $urls[$asset['type']] = $asset['url'];
            }
        }

        return $urls;
    }

    /**
     * Get email template.
     */
    public function getEmailTemplate(Tenant $tenant, string $type): ?array
    {
        $config = $tenant->white_label_config ?? [];
        return $config['email_templates'][$type] ?? null;
    }

    /**
     * Render email template.
     */
    public function renderEmailTemplate(Tenant $tenant, string $type, array $data = []): array
    {
        $template = $this->getEmailTemplate($tenant, $type);
        
        if (!$template) {
            // Fall back to default template
            return $this->getDefaultEmailTemplate($type, $data);
        }

        $html = $this->renderTemplate($template['html'] ?? '', $data);
        $text = $this->renderTemplate($template['text'] ?? '', $data);

        return [
            'subject' => $this->renderTemplate($template['subject'] ?? '', $data),
            'html' => $html,
            'text' => $text,
        ];
    }

    /**
     * Get default email template.
     */
    protected function getDefaultEmailTemplate(string $type, array $data): array
    {
        $defaults = [
            'welcome' => [
                'subject' => 'Welcome to {{brand_name}}!',
                'html' => '<h1>Welcome to {{brand_name}}!</h1><p>Hi {{name}},</p><p>Welcome to {{brand_name}}. We\'re excited to have you on board.</p>',
            ],
            'verification' => [
                'subject' => 'Verify your email address',
                'html' => '<h1>Verify your email</h1><p>Click <a href="{{verification_url}}">here</a> to verify your email.</p>',
            ],
            'password_reset' => [
                'subject' => 'Reset your password',
                'html' => '<h1>Reset your password</h1><p>Click <a href="{{reset_url}}">here</a> to reset your password.</p>',
            ],
        ];

        $template = $defaults[$type] ?? [
            'subject' => 'Notification from {{brand_name}}',
            'html' => '<p>{{message}}</p>',
        ];

        return $this->renderTemplate($template, $data);
    }

    /**
     * Render template with data.
     */
    protected function renderTemplate(string $template, array $data): string
    {
        // Add default variables
        $data = array_merge([
            'brand_name' => config('app.name'),
            'app_url' => config('app.url'),
            'support_email' => config('mail.from.address'),
            'year' => date('Y'),
        ], $data);

        // Simple template rendering
        foreach ($data as $key => $value) {
            $template = str_replace("{{$key}}", $value, $template);
        }

        return $template;
    }

    /**
     * Get login page configuration.
     */
    public function getLoginPageConfig(Tenant $tenant): array
    {
        $config = $tenant->white_label_config ?? [];
        $loginConfig = $config['login_page'] ?? [];

        return array_merge([
            'brand_name' => $config['brand_name'] ?? config('app.name'),
            'logo_url' => $config['logo_url'] ?? null,
            'favicon_url' => $config['favicon_url'] ?? null,
            'background_image' => $loginConfig['background_image'] ?? '',
            'custom_message' => $loginConfig['custom_message'] ?? '',
            'show_powered_by' => $loginConfig['show_powered_by'] ?? true,
            'show_remember_me' => $loginConfig['show_remember_me'] ?? true,
            'show_forgot_password' => $loginConfig['show_forgot_password'] ?? true,
            'show_sso_buttons' => $loginConfig['show_sso_buttons'] ?? true,
            'custom_css' => $config['custom_css'] ?? '',
            'colors' => $config['colors'] ?? [],
            'theme' => $config['theme'] ?? 'light',
        ], $loginConfig);
    }

    /**
     * Check domain status.
     */
    public function checkDomainStatus(Tenant $tenant): array
    {
        $config = $tenant->white_label_config ?? [];
        $domain = $config['custom_domain'] ?? null;

        if (!$domain) {
            return [
                'configured' => false,
                'message' => 'No custom domain configured',
            ];
        }

        $verification = $this->verifyDomainOwnership($domain);
        
        // Check SSL
        $sslInfo = $this->checkSsl($domain);

        return [
            'configured' => true,
            'domain' => $domain,
            'verified' => $config['domain_verified'] ?? false,
            'verification_method' => 'dns',
            'ssl' => $sslInfo,
            'dns_records' => $this->getRequiredDnsRecords($domain),
        ];
    }

    /**
     * Check SSL certificate.
     */
    protected function checkSsl(string $domain): array
    {
        $url = "https://{$domain}";
        
        try {
            $response = Http::timeout(10)->get($url);
            
            // Would need to check certificate details
            // This is simplified
            return [
                'valid' => $response->successful(),
                'expires_at' => null,
                'issuer' => 'Unknown',
            ];
        } catch (\Throwable) {
            return [
                'valid' => false,
                'error' => 'Could not connect via HTTPS',
            ];
        }
    }

    /**
     * Get required DNS records for domain.
     */
    protected function getRequiredDnsRecords(string $domain): array
    {
        $host = parse_url(config('app.url'), PHP_URL_HOST);
        
        return [
            [
                'type' => 'CNAME',
                'name' => '@',
                'value' => $host,
                'ttl' => 3600,
                'description' => 'Points to platform',
            ],
            [
                'type' => 'TXT',
                'name' => '@',
                'value' => "webixray-verification=" . Str::random(16),
                'ttl' => 3600,
                'description' => 'Domain verification',
            ],
            [
                'type' => 'CNAME',
                'name' => 'www',
                'value' => $host,
                'ttl' => 3600,
                'description' => 'WWW subdomain',
            ],
        ];
    }

    /**
     * Get tenant's white-label status.
     */
    public function getStatus(Tenant $tenant): array
    {
        $config = $tenant->white_label_config ?? [];
        
        return [
            'enabled' => $this->config['enabled'] && !empty($config['enabled']),
            'custom_domain' => $config['custom_domain'] ?? null,
            'domain_verified' => $config['domain_verified'] ?? false,
            'ssl_status' => $config['ssl_status'] ?? 'none',
            'theme' => $config['theme'] ?? 'light',
            'brand_name' => $config['brand_name'] ?? $tenant->name,
            'custom_css_url' => $this->getCustomCssUrl($tenant),
            'custom_js_url' => $this->getCustomJsUrl($tenant),
            'assets' => $this->getAssetUrls($tenant),
            'email_templates' => array_keys($config['email_templates'] ?? []),
            'login_page_configured' => !empty($config['login_page']),
        ];
    }

    /**
     * Delete white-label configuration.
     */
    public function deleteConfiguration(Tenant $tenant): void
    {
        // Delete custom files
        $cssPath = $this->config['custom_css_path'] . "/tenant_{$tenant->id}";
        $jsPath = $this->config['custom_js_path'] . "/tenant_{$tenant->id}";
        $assetsPath = $this->config['assets_path'] . "/tenant_{$tenant->id}";

        foreach ([$cssPath, $jsPath, $assetsPath] as $path) {
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->deleteDirectory($path);
            }
        }

        // Clear config
        $tenant->update([
            'white_label_config' => [
                'enabled' => false,
            ],
        ]);
    }
}
