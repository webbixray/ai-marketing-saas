<?php

namespace App\Http\Controllers\Enterprise;

use App\Http\Controllers\Controller;
use App\Http\Requests\Enterprise\ConfigureWhiteLabelRequest;
use App\Services\Enterprise\WhiteLabelService;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class WhiteLabelController extends Controller
{
    protected WhiteLabelService $whiteLabelService;

    public function __construct(WhiteLabelService $whiteLabelService)
    {
        $this->whiteLabelService = $whiteLabelService;
    }

    /**
     * Get white-label configuration for current tenant.
     */
    public function getConfig(): JsonResponse
    {
        $tenant = $this->getTenant();
        $config = $this->whiteLabelService->getTenantConfig($tenant);

        return response()->json([
            'data' => $config,
        ]);
    }

    /**
     * Configure white-label for the tenant.
     */
    public function configure(ConfigureWhiteLabelRequest $request): JsonResponse
    {
        $tenant = $this->getTenant();
        
        $config = $this->whiteLabelService->configureTenant($tenant, $request->validated());

        return response()->json([
            'message' => 'White-label configuration updated',
            'data' => $config,
        ]);
    }

    /**
     * Setup custom domain.
     */
    public function setupDomain(Request $request): JsonResponse
    {
        $request->validate([
            'domain' => 'required|string|max:255',
        ]);

        $tenant = $this->getTenant();
        $result = $this->whiteLabelService->setupCustomDomain($tenant, $request->input('domain'));

        return response()->json($result);
    }

    /**
     * Check domain status.
     */
    public function checkDomainStatus(): JsonResponse
    {
        $tenant = $this->getTenant();
        $status = $this->whiteLabelService->checkDomainStatus($tenant);

        return response()->json([
            'data' => $status,
        ]);
    }

    /**
     * Get required DNS records for domain.
     */
    public function getDnsRecords(): JsonResponse
    {
        $tenant = $this->getTenant();
        $config = $tenant->white_label_config ?? [];
        $domain = $config['custom_domain'] ?? null;

        if (!$domain) {
            return response()->json([
                'error' => 'No custom domain configured',
            ], 400);
        }

        $records = $this->whiteLabelService->getRequiredDnsRecords($domain);

        return response()->json([
            'data' => $records,
        ]);
    }

    /**
     * Get theme CSS URL.
     */
    public function getCss(): JsonResponse
    {
        $tenant = $this->getTenant();
        $cssUrl = $this->whiteLabelService->getCustomCssUrl($tenant);

        return response()->json([
            'css_url' => $cssUrl,
        ]);
    }

    /**
     * Get custom JS URL.
     */
    public function getJs(): JsonResponse
    {
        $tenant = $this->getTenant();
        $jsUrl = $this->whiteLabelService->getCustomJsUrl($tenant);

        return response()->json([
            'js_url' => $jsUrl,
        ]);
    }

    /**
     * Get asset URLs.
     */
    public function getAssets(): JsonResponse
    {
        $tenant = $this->getTenant();
        $assets = $this->whiteLabelService->getAssetUrls($tenant);

        return response()->json([
            'data' => $assets,
        ]);
    }

    /**
     * Get login page configuration.
     */
    public function getLoginPageConfig(): JsonResponse
    {
        $tenant = $this->getTenant();
        $config = $this->whiteLabelService->getLoginPageConfig($tenant);

        return response()->json([
            'data' => $config,
        ]);
    }

    /**
     * Get email template.
     */
    public function getEmailTemplate(string $type): JsonResponse
    {
        $tenant = $this->getTenant();
        $template = $this->whiteLabelService->getEmailTemplate($tenant, $type);

        if (!$template) {
            return response()->json([
                'error' => 'Template not found',
            ], 404);
        }

        return response()->json([
            'data' => $template,
        ]);
    }

    /**
     * Render email template with data.
     */
    public function renderEmailTemplate(string $type, Request $request): JsonResponse
    {
        $tenant = $this->getTenant();
        $data = $request->input('data', []);
        
        $rendered = $this->whiteLabelService->renderEmailTemplate($tenant, $type, $data);

        return response()->json([
            'data' => $rendered,
        ]);
    }

    /**
     * Get white-label status.
     */
    public function getStatus(): JsonResponse
    {
        $tenant = $this->getTenant();
        $status = $this->whiteLabelService->getStatus($tenant);

        return response()->json([
            'data' => $status,
        ]);
    }

    /**
     * Delete white-label configuration.
     */
    public function deleteConfig(): JsonResponse
    {
        $tenant = $this->getTenant();
        $this->whiteLabelService->deleteConfiguration($tenant);

        return response()->json([
            'message' => 'White-label configuration deleted',
        ]);
    }

    /**
     * Get the current tenant.
     */
    protected function getTenant(): Tenant
    {
        return auth()->user()->tenant;
    }
}
