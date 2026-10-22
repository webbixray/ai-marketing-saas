<?php

namespace App\Http\Controllers\Global;

use App\Http\Controllers\Controller;
use App\Services\Global\CdnService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CdnController extends Controller
{
    protected CdnService $cdnService;

    public function __construct(CdnService $cdnService)
    {
        $this->cdnService = $cdnService;
    }

    public function getStatus(): JsonResponse
    {
        return response()->json([
            'data' => $this->cdnService->getStatus(),
        ]);
    }

    public function purgeCache(Request $request): JsonResponse
    {
        $request->validate([
            'urls' => 'required|array|min:1',
            'urls.*' => 'url',
            'provider' => 'nullable|string|in:cloudflare,cloudfront,fastly,akamai',
        ]);

        $result = $this->cdnService->purgeCache($request->urls, [
            'provider' => $request->provider,
        ]);

        return response()->json($result);
    }

    public function purgeAllCache(Request $request): JsonResponse
    {
        $request->validate([
            'provider' => 'nullable|string|in:cloudflare,cloudfront,fastly,akamai',
        ]);

        $result = $this->cdnService->purgeAllCache([
            'provider' => $request->provider,
        ]);

        return response()->json($result);
    }

    public function configureCacheRules(Request $request): JsonResponse
    {
        $request->validate([
            'rules' => 'required|array|min:1',
            'rules.*.pattern' => 'required|string',
            'rules.*.ttl' => 'required|integer|min:0',
            'rules.*.browser_ttl' => 'nullable|integer|min:0',
            'rules.*.stale_while_revalidate' => 'nullable|integer|min:0',
        ]);

        $result = $this->cdnService->configureCacheRules($request->rules);

        return response()->json($result);
    }

    public function configureWafRules(Request $request): JsonResponse
    {
        $request->validate([
            'rules' => 'required|array|min:1',
            'rules.*.expression' => 'required|string',
            'rules.*.action' => 'required|in:block,challenge,js_challenge,managed_challenge,log,allow',
            'rules.*.description' => 'nullable|string',
            'rules.*.priority' => 'nullable|integer|min:1',
            'rules.*.paused' => 'boolean',
        ]);

        $result = $this->cdnService->configureWafRules($request->rules);

        return response()->json($result);
    }

    public function getWafEvents(Request $request): JsonResponse
    {
        $filters = $request->only(['limit', 'since', 'until', 'ray_id', 'rule_id']);
        
        return response()->json($this->cdnService->getWafEvents($filters));
    }

    public function configureRateLimiting(Request $request): JsonResponse
    {
        $request->validate([
            'url' => 'nullable|string',
            'methods' => 'nullable|array',
            'methods.*' => 'string|in:GET,POST,PUT,PATCH,DELETE,HEAD,OPTIONS',
            'threshold' => 'nullable|integer|min:1',
            'period' => 'nullable|integer|min:10',
            'action' => 'nullable|in:block,challenge,js_challenge',
            'timeout' => 'nullable|integer|min:1',
        ]);

        $result = $this->cdnService->configureRateLimiting($request->all());

        return response()->json($result);
    }

    public function getAnalytics(Request $request): JsonResponse
    {
        $filters = $request->only(['since', 'until', 'dimensions', 'metrics']);
        
        return response()->json($this->cdnService->getAnalytics($filters));
    }

    public function getCacheAnalytics(Request $request): JsonResponse
    {
        $filters = $request->only(['since', 'until']);
        
        return response()->json($this->cdnService->getCacheAnalytics($filters));
    }

    public function configureImageOptimization(Request $request): JsonResponse
    {
        $request->validate([
            'mode' => 'nullable|in:off,lossless,lossy',
            'webp' => 'nullable|in:on,off',
        ]);

        $result = $this->cdnService->configureImageOptimization($request->all());

        return response()->json($result);
    }

    public function configureCompression(Request $request): JsonResponse
    {
        $request->validate([
            'brotli' => 'nullable|boolean',
            'gzip' => 'nullable|boolean',
            'http2' => 'nullable|boolean',
            'http3' => 'nullable|boolean',
        ]);

        $result = $this->cdnService->configureCompression($request->all());

        return response()->json($result);
    }

    public function createEdgeFunction(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'script' => 'required|string',
            'routes' => 'nullable|array',
            'routes.*.pattern' => 'required|string',
            'bindings' => 'nullable|array',
        ]);

        $result = $this->cdnService->createEdgeFunction($request->all());

        return response()->json($result);
    }
}
