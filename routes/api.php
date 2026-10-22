<?php
Log::debug("ROUTES FILE LOADED");

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CampaignController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\ApiKeyController;
use App\Http\Controllers\Api\FeatureFlagController;
use App\Http\Middleware\Authenticate;
use Spatie\Permission\Middleware\PermissionMiddleware;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are stateless, which means they are not protected by the
| stateful middleware.
|
*/

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:auth');
Route::post('/login', [AuthController::class, 'login'])->name('login')->middleware('throttle:auth');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('throttle:auth');
Route::get('/user', [AuthController::class, 'user'])->middleware('auth:sanctum');

// Email Verification Routes (no authentication required)
Route::post('/email/verification-notification', [AuthController::class, 'sendVerificationEmail'])->middleware('throttle:auth');
Route::post('/email/verify', [AuthController::class, 'verifyEmail'])->middleware('throttle:auth');
Route::post('/email/resend-verification', [AuthController::class, 'resendVerificationEmail'])->middleware(['auth:sanctum', 'throttle:auth']);

// Password Reset Routes (no authentication required)
Route::post('/password/forgot', [AuthController::class, 'sendPasswordResetLink'])->middleware('throttle:auth');
Route::post('/password/reset', [AuthController::class, 'resetPassword'])->middleware('throttle:auth');

// Health Check Endpoints (no authentication required - for load balancers/monitoring)
Route::get('/health', [HealthController::class, 'health'])->middleware('throttle:health');
Route::get('/health/live', [HealthController::class, 'live'])->middleware('throttle:health');
Route::get('/health/ready', [HealthController::class, 'ready'])->middleware('throttle:health');

Route::get('/test', function () {
    return 'test';
});

Route::apiResource('campaigns', CampaignController::class)
    ->middleware(['auth:sanctum', 'throttle:api']);

Route::middleware([Authenticate::class . ':sanctum'])->get('/auth-test', function () {
    return ['status' => 'authenticated', 'user_id' => auth()->id(), 'timestamp' => now()];
});

// Test endpoint that will trigger authentication failure
Route::middleware([Authenticate::class . ':sanctum'])->get('/auth-fail-test', function () {
    return ['status' => 'should not reach here'];
});

// Test route with standard Laravel sanctum middleware
Route::middleware('auth:sanctum')->get('/test-sanctum', function () {
    return response()->json([
        'user' => auth()->user(),
        'message' => 'Standard sanctum middleware works'
    ]);
});

// Test route with our custom middleware
Route::middleware([App\Http\Middleware\Authenticate::class . ':sanctum'])->get('/test-custom', function () {
    return response()->json([
        'user' => auth()->user(),
        'message' => 'Custom middleware works'
    ]);
});

// Test route with no middleware
Route::get('/test-no-middleware', function () {
    return response()->json([
        'user' => auth()->user(),
        'message' => 'No middleware'
    ]);
});

Route::get('/test-log', function () {
    \Illuminate\Support\Facades\Log::info('API TEST LOG ROUTE ACCESSED');
    return response()->json(['message' => 'api log test']);
});

Route::get('/no-middleware', function () {
    \Illuminate\Support\Facades\Log::info('NO MIDDLEWARE ROUTE ACCESSED');
    return response()->json(['message' => 'no middleware']);
});

// Protected route that requires authentication
Route::middleware([App\Http\Middleware\Authenticate::class . ':sanctum'])->get('/protected', function () {
    return response()->json([
        'message' => 'This is a protected route',
        'timestamp' => now()
    ]);
});

// Task API Routes - Using full class name for permission middleware (WORKING)
Route::apiResource('tasks', TaskController::class)
    ->middleware([
        'auth:sanctum',
        'throttle:api',
        PermissionMiddleware::class . ':view tasks|create tasks|update tasks|delete tasks'
    ])
    ->except(['show']);

// We'll handle show separately to demonstrate custom permission logic if needed
Route::get('tasks/{task}', [TaskController::class, 'show'])
    ->middleware([
        'auth:sanctum',
        'throttle:api',
        PermissionMiddleware::class . ':view tasks'
    ]);

// Profile Management Routes
Route::middleware([App\Http\Middleware\Authenticate::class . ':sanctum', 'throttle:api'])->group(function () {
    Log::debug('PROFILE ROUTES GROUP LOADED');
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::patch('/profile', [ProfileController::class, 'update']);
    Route::post('/change-password', [ProfileController::class, 'changePassword']);
    Route::post('/avatar', [ProfileController::class, 'uploadAvatar']);
    Route::delete('/avatar', [ProfileController::class, 'deleteAvatar']);
});

// Client Routes
Route::middleware(['auth:sanctum', 'throttle:api'])->prefix('clients')->group(function () {
    Route::get('/', [ClientController::class, 'index']);
    Route::post('/', [ClientController::class, 'store']);
    Route::get('/{client}', [ClientController::class, 'show']);
    Route::put('/{client}', [ClientController::class, 'update']);
    Route::patch('/{client}', [ClientController::class, 'update']);
    Route::delete('/{client}', [ClientController::class, 'destroy']);
    Route::get('/{client}/campaigns', [ClientController::class, 'campaigns']);
    Route::get('/{client}/performance', [ClientController::class, 'performance']);
});

// Billing Routes
Route::middleware(['auth:sanctum', 'throttle:billing'])->prefix('billing')->group(function () {
    Route::post('/subscribe', [\App\Http\Controllers\Api\BillingController::class, 'subscribe']);
    Route::get('/subscription', [\App\Http\Controllers\Api\BillingController::class, 'subscription']);
    Route::post('/swap/{plan}', [\App\Http\Controllers\Api\BillingController::class, 'swap']);
    Route::delete('/subscription', [\App\Http\Controllers\Api\BillingController::class, 'cancel']);
    Route::get('/invoices', [\App\Http\Controllers\Api\BillingController::class, 'invoices']);
    Route::get('/invoices/{invoice}', [\App\Http\Controllers\Api\BillingController::class, 'invoice']);
});

// Notification Routes
Route::middleware(['auth:sanctum', 'throttle:api'])->prefix('notifications')->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\NotificationController::class, 'index']);
    Route::get('/unread-count', [\App\Http\Controllers\Api\NotificationController::class, 'unreadCount']);
    Route::post('/{id}/read', [\App\Http\Controllers\Api\NotificationController::class, 'markAsRead']);
    Route::post('/read-all', [\App\Http\Controllers\Api\NotificationController::class, 'markAllAsRead']);
    Route::delete('/{id}', [\App\Http\Controllers\Api\NotificationController::class, 'destroy']);
});

// Reporting Routes
Route::middleware(['auth:sanctum', 'throttle:reports'])->prefix('reports')->group(function () {
    Route::get('/revenue', [\App\Http\Controllers\Api\ReportingController::class, 'revenue']);
    Route::get('/user-growth', [\App\Http\Controllers\Api\ReportingController::class, 'userGrowth']);
    Route::get('/task-stats', [\App\Http\Controllers\Api\ReportingController::class, 'taskStats']);
    Route::get('/campaign-stats', [\App\Http\Controllers\Api\ReportingController::class, 'campaignStats']);
    // New expanded reporting endpoints
    Route::get('/revenue/trend', [\App\Http\Controllers\Api\ReportingController::class, 'revenueTrend']);
    Route::get('/subscription/growth', [\App\Http\Controllers\Api\ReportingController::class, 'subscriptionGrowth']);
    Route::get('/task/completion-trend', [\App\Http\Controllers\Api\ReportingController::class, 'taskCompletionTrend']);
    Route::get('/campaign/creation-trend', [\App\Http\Controllers\Api\ReportingController::class, 'campaignCreationTrend']);
    Route::get('/user/activity', [\App\Http\Controllers\Api\ReportingController::class, 'userActivity']);
});

// Notification Preference Routes
Route::middleware(['auth:sanctum', 'throttle:api'])->prefix('notification-preferences')->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\NotificationPreferenceController::class, 'index']);
    Route::post('/', [\App\Http\Controllers\Api\NotificationPreferenceController::class, 'store']);
    Route::put('/{id}', [\App\Http\Controllers\Api\NotificationPreferenceController::class, 'update']);
    Route::delete('/{id}', [\App\Http\Controllers\Api\NotificationPreferenceController::class, 'destroy']);
});

// API Key Routes (for service-to-service authentication)
Route::middleware(['auth:sanctum', 'throttle:api'])->prefix('api-keys')->group(function () {
    Route::get('/', [ApiKeyController::class, 'index']);
    Route::post('/', [ApiKeyController::class, 'store']);
    Route::get('/scopes', [ApiKeyController::class, 'scopes']);
    Route::get('/{apiKey}', [ApiKeyController::class, 'show']);
    Route::put('/{apiKey}', [ApiKeyController::class, 'update']);
    Route::patch('/{apiKey}', [ApiKeyController::class, 'update']);
    Route::post('/{apiKey}/regenerate', [ApiKeyController::class, 'regenerate']);
    Route::delete('/{apiKey}', [ApiKeyController::class, 'destroy']);
});

// Feature Flag Routes
Route::middleware(['auth:sanctum', 'throttle:api'])->prefix('feature-flags')->group(function () {
    Route::get('/', [FeatureFlagController::class, 'index']);
    Route::post('/', [FeatureFlagController::class, 'store']);
    Route::get('/check', [FeatureFlagController::class, 'check']);
    Route::get('/enabled-for-context', [FeatureFlagController::class, 'enabledForContext']);
    Route::post('/clear-cache', [FeatureFlagController::class, 'clearCache']);
    Route::get('/{featureFlag}', [FeatureFlagController::class, 'show']);
    Route::put('/{featureFlag}', [FeatureFlagController::class, 'update']);
    Route::patch('/{featureFlag}', [FeatureFlagController::class, 'update']);
    Route::post('/{featureFlag}/enable', [FeatureFlagController::class, 'enable']);
    Route::post('/{featureFlag}/disable', [FeatureFlagController::class, 'disable']);
    Route::delete('/{featureFlag}', [FeatureFlagController::class, 'destroy']);
});

// ===========================================
// ENTERPRISE FEATURES ROUTES
// ===========================================

// SSO Routes
Route::middleware(['auth:sanctum', 'throttle:api'])->prefix('enterprise/sso')->group(function () {
    Route::get('/providers', [\App\Http\Controllers\Enterprise\SsoController::class, 'getProviders']);
    Route::get('/config', [\App\Http\Controllers\Enterprise\SsoController::class, 'getConfig']);
    Route::put('/config', [\App\Http\Controllers\Enterprise\SsoController::class, 'configure']);
    Route::delete('/config', [\App\Http\Controllers\Enterprise\SsoController::class, 'deleteConfig']);
    Route::post('/login/{provider}', [\App\Http\Controllers\Enterprise\SsoController::class, 'initiateLogin']);
    Route::post('/callback/{provider}', [\App\Http\Controllers\Enterprise\SsoController::class, 'handleCallback'])->withoutMiddleware('auth:sanctum');
    Route::get('/audit-logs', [\App\Http\Controllers\Enterprise\SsoController::class, 'getAuditLogs']);
});

// SCIM Routes
Route::middleware(['auth:sanctum', 'throttle:api'])->prefix('enterprise/scim')->group(function () {
    Route::get('/service-provider-config', [\App\Http\Controllers\Enterprise\ScimController::class, 'serviceProviderConfig']);
    Route::get('/resource-types', [\App\Http\Controllers\Enterprise\ScimController::class, 'resourceTypes']);
    Route::get('/schemas', [\App\Http\Controllers\Enterprise\ScimController::class, 'schemas']);
    Route::get('/users', [\App\Http\Controllers\Enterprise\ScimController::class, 'listUsers']);
    Route::get('/users/{id}', [\App\Http\Controllers\Enterprise\ScimController::class, 'getUser']);
    Route::post('/users', [\App\Http\Controllers\Enterprise\ScimController::class, 'createUser']);
    Route::put('/users/{id}', [\App\Http\Controllers\Enterprise\ScimController::class, 'replaceUser']);
    Route::patch('/users/{id}', [\App\Http\Controllers\Enterprise\ScimController::class, 'patchUser']);
    Route::delete('/users/{id}', [\App\Http\Controllers\Enterprise\ScimController::class, 'deleteUser']);
    Route::get('/groups', [\App\Http\Controllers\Enterprise\ScimController::class, 'listGroups']);
    Route::get('/groups/{id}', [\App\Http\Controllers\Enterprise\ScimController::class, 'getGroup']);
    Route::post('/groups', [\App\Http\Controllers\Enterprise\ScimController::class, 'createGroup']);
    Route::put('/groups/{id}', [\App\Http\Controllers\Enterprise\ScimController::class, 'replaceGroup']);
    Route::delete('/groups/{id}', [\App\Http\Controllers\Enterprise\ScimController::class, 'deleteGroup']);
    Route::post('/bulk', [\App\Http\Controllers\Enterprise\ScimController::class, 'bulkOperations']);
});

// White Label Routes
Route::middleware(['auth:sanctum', 'throttle:api'])->prefix('enterprise/white-label')->group(function () {
    Route::get('/config', [\App\Http\Controllers\Enterprise\WhiteLabelController::class, 'getConfig']);
    Route::put('/config', [\App\Http\Controllers\Enterprise\WhiteLabelController::class, 'configure']);
    Route::post('/domain', [\App\Http\Controllers\Enterprise\WhiteLabelController::class, 'setupDomain']);
    Route::get('/domain/status', [\App\Http\Controllers\Enterprise\WhiteLabelController::class, 'checkDomainStatus']);
    Route::get('/domain/dns-records', [\App\Http\Controllers\Enterprise\WhiteLabelController::class, 'getDnsRecords']);
    Route::get('/css', [\App\Http\Controllers\Enterprise\WhiteLabelController::class, 'getCss']);
    Route::get('/js', [\App\Http\Controllers\Enterprise\WhiteLabelController::class, 'getJs']);
    Route::get('/assets', [\App\Http\Controllers\Enterprise\WhiteLabelController::class, 'getAssets']);
    Route::get('/login-page', [\App\Http\Controllers\Enterprise\WhiteLabelController::class, 'getLoginPageConfig']);
    Route::get('/email-templates/{type}', [\App\Http\Controllers\Enterprise\WhiteLabelController::class, 'getEmailTemplate']);
    Route::post('/email-templates/{type}/render', [\App\Http\Controllers\Enterprise\WhiteLabelController::class, 'renderEmailTemplate']);
    Route::get('/status', [\App\Http\Controllers\Enterprise\WhiteLabelController::class, 'getStatus']);
    Route::delete('/config', [\App\Http\Controllers\Enterprise\WhiteLabelController::class, 'deleteConfig']);
});

// Audit & Compliance Routes
Route::middleware(['auth:sanctum', 'throttle:api'])->prefix('enterprise/audit')->group(function () {
    Route::get('/logs', [\App\Http\Controllers\Enterprise\AuditComplianceController::class, 'queryLogs']);
    Route::get('/statistics', [\App\Http\Controllers\Enterprise\AuditComplianceController::class, 'getStatistics']);
    Route::post('/export', [\App\Http\Controllers\Enterprise\AuditComplianceController::class, 'export']);
    Route::post('/archive', [\App\Http\Controllers\Enterprise\AuditComplianceController::class, 'archive']);
    Route::get('/high-risk', [\App\Http\Controllers\Enterprise\AuditComplianceController::class, 'getHighRiskEvents']);
    Route::get('/security', [\App\Http\Controllers\Enterprise\AuditComplianceController::class, 'getSecurityEvents']);
    Route::get('/data-access', [\App\Http\Controllers\Enterprise\AuditComplianceController::class, 'getDataAccessEvents']);
    Route::get('/data-modification', [\App\Http\Controllers\Enterprise\AuditComplianceController::class, 'getDataModificationEvents']);
    Route::post('/compliance-report', [\App\Http\Controllers\Enterprise\AuditComplianceController::class, 'generateReport']);
});

// Advanced RBAC Routes
Route::middleware(['auth:sanctum', 'throttle:api'])->prefix('enterprise/rbac')->group(function () {
    Route::post('/resources', [\App\Http\Controllers\Enterprise\AdvancedRbacController::class, 'defineResource']);
    Route::post('/roles', [\App\Http\Controllers\Enterprise\AdvancedRbacController::class, 'createRole']);
    Route::post('/roles/composite', [\App\Http\Controllers\Enterprise\AdvancedRbacController::class, 'createCompositeRole']);
    Route::post('/assign', [\App\Http\Controllers\Enterprise\AdvancedRbacController::class, 'assignRoleWithConditions']);
    Route::post('/check-permission', [\App\Http\Controllers\Enterprise\AdvancedRbacController::class, 'checkPermission']);
    Route::post('/delegate', [\App\Http\Controllers\Enterprise\AdvancedRbacController::class, 'delegatePermission']);
    Route::delete('/delegate/{delegationId}', [\App\Http\Controllers\Enterprise\AdvancedRbacController::class, 'revokeDelegation']);
    Route::get('/delegations/{userId}', [\App\Http\Controllers\Enterprise\AdvancedRbacController::class, 'getUserDelegations']);
    Route::post('/emergency-access', [\App\Http\Controllers\Enterprise\AdvancedRbacController::class, 'grantEmergencyAccess']);
    Route::delete('/emergency-access/{accessId}', [\App\Http\Controllers\Enterprise\AdvancedRbacController::class, 'revokeEmergencyAccess']);
    Route::get('/emergency-access/{userId}', [\App\Http\Controllers\Enterprise\AdvancedRbacController::class, 'getEmergencyAccess']);
    Route::post('/access-review', [\App\Http\Controllers\Enterprise\AdvancedRbacController::class, 'performAccessReview']);
    Route::get('/access-reviews', [\App\Http\Controllers\Enterprise\AdvancedRbacController::class, 'getAccessReviewHistory']);
    Route::post('/revoke-expired', [\App\Http\Controllers\Enterprise\AdvancedRbacController::class, 'revokeExpiredAccess']);
    Route::get('/effective-permissions/{userId}', [\App\Http\Controllers\Enterprise\AdvancedRbacController::class, 'getEffectivePermissions']);
    Route::get('/role-assignments/{userId}', [\App\Http\Controllers\Enterprise\AdvancedRbacController::class, 'getUserRoleAssignments']);
});

// Marketplace Routes
Route::middleware(['auth:sanctum', 'throttle:api'])->prefix('enterprise/marketplace')->group(function () {
    Route::get('/listings', [\App\Http\Controllers\Enterprise\MarketplaceController::class, 'getListings']);
    Route::get('/listings/featured', [\App\Http\Controllers\Enterprise\MarketplaceController::class, 'getFeatured']);
    Route::get('/listings/trending', [\App\Http\Controllers\Enterprise\MarketplaceController::class, 'getTrending']);
    Route::get('/listings/pending', [\App\Http\Controllers\Enterprise\MarketplaceController::class, 'getPendingReviews']);
    Route::get('/listings/{listingId}', [\App\Http\Controllers\Enterprise\MarketplaceController::class, 'getListing']);
    Route::post('/listings', [\App\Http\Controllers\Enterprise\MarketplaceController::class, 'submitListing']);
    Route::put('/listings/{listingId}', [\App\Http\Controllers\Enterprise\MarketplaceController::class, 'updateListing']);
    Route::post('/listings/{listingId}/submit-review', [\App\Http\Controllers\Enterprise\MarketplaceController::class, 'submitForReview']);
    Route::post('/listings/{listingId}/approve', [\App\Http\Controllers\Enterprise\MarketplaceController::class, 'approveListing']);
    Route::post('/listings/{listingId}/reject', [\App\Http\Controllers\Enterprise\MarketplaceController::class, 'rejectListing']);
    Route::post('/listings/{listingId}/purchase', [\App\Http\Controllers\Enterprise\MarketplaceController::class, 'purchaseListing']);
    Route::post('/listings/{listingId}/install', [\App\Http\Controllers\Enterprise\MarketplaceController::class, 'installForTenant']);
    Route::get('/installations', [\App\Http\Controllers\Enterprise\MarketplaceController::class, 'getTenantInstallations']);
    Route::post('/listings/{listingId}/reviews', [\App\Http\Controllers\Enterprise\MarketplaceController::class, 'submitReview']);
    Route::get('/listings/{listingId}/reviews', [\App\Http\Controllers\Enterprise\MarketplaceController::class, 'getListingReviews']);
    Route::get('/earnings', [\App\Http\Controllers\Enterprise\MarketplaceController::class, 'getEarnings']);
    Route::get('/stats', [\App\Http\Controllers\Enterprise\MarketplaceController::class, 'getStats']);
    Route::post('/search', [\App\Http\Controllers\Enterprise\MarketplaceController::class, 'search']);
});

// Example of API key protected routes (commented out - requires API key auth)
// Route::middleware([\App\Http\Middleware\ApiKeyAuth::class . ':reports:read', 'throttle:reports'])->prefix('reports')->group(function () {
//     Route::get('/revenue', [\App\Http\Controllers\Api\ReportingController::class, 'revenue']);
//     Route::get('/user-growth', [\App\Http\Controllers\Api\ReportingController::class, 'userGrowth']);
// });

// ===========================================
// GLOBAL SCALE FEATURES ROUTES
// ===========================================

// Multi-Region Routes
Route::middleware(['auth:sanctum', 'throttle:api'])->prefix('global/regions')->group(function () {
    Route::get('/', [\App\Http\Controllers\Global\MultiRegionController::class, 'getRegions']);
    Route::get('/active', [\App\Http\Controllers\Global\MultiRegionController::class, 'getActiveRegions']);
    Route::get('/{regionCode}', [\App\Http\Controllers\Global\MultiRegionController::class, 'getRegion']);
    Route::get('/status', [\App\Http\Controllers\Global\MultiRegionController::class, 'getRegionStatus']);
    Route::get('/health', [\App\Http\Controllers\Global\MultiRegionController::class, 'performHealthChecks']);
    Route::get('/replication', [\App\Http\Controllers\Global\MultiRegionController::class, 'getReplicationStatus']);
    Route::post('/assign-tenant', [\App\Http\Controllers\Global\MultiRegionController::class, 'assignTenantToRegion']);
    Route::get('/tenant/migrations', [\App\Http\Controllers\Global\MultiRegionController::class, 'getTenantMigrations']);
    Route::get('/migration/{migrationId}', [\App\Http\Controllers\Global\MultiRegionController::class, 'getMigrationStatus']);
    Route::get('/routing', [\App\Http\Controllers\Global\MultiRegionController::class, 'getRoutingConfig']);
    Route::post('/dr-drill', [\App\Http\Controllers\Global\MultiRegionController::class, 'initiateDrDrill']);
    Route::get('/dr-drills', [\App\Http\Controllers\Global\MultiRegionController::class, 'getDrDrillHistory']);
});

// CDN Routes
Route::middleware(['auth:sanctum', 'throttle:api'])->prefix('global/cdn')->group(function () {
    Route::get('/status', [\App\Http\Controllers\Global\CdnController::class, 'getStatus']);
    Route::post('/purge', [\App\Http\Controllers\Global\CdnController::class, 'purgeCache']);
    Route::post('/purge-all', [\App\Http\Controllers\Global\CdnController::class, 'purgeAllCache']);
    Route::post('/cache-rules', [\App\Http\Controllers\Global\CdnController::class, 'configureCacheRules']);
    Route::post('/waf-rules', [\App\Http\Controllers\Global\CdnController::class, 'configureWafRules']);
    Route::get('/waf-events', [\App\Http\Controllers\Global\CdnController::class, 'getWafEvents']);
    Route::post('/rate-limiting', [\App\Http\Controllers\Global\CdnController::class, 'configureRateLimiting']);
    Route::get('/analytics', [\App\Http\Controllers\Global\CdnController::class, 'getAnalytics']);
    Route::get('/cache-analytics', [\App\Http\Controllers\Global\CdnController::class, 'getCacheAnalytics']);
    Route::post('/image-optimization', [\App\Http\Controllers\Global\CdnController::class, 'configureImageOptimization']);
    Route::post('/compression', [\App\Http\Controllers\Global\CdnController::class, 'configureCompression']);
    Route::post('/edge-function', [\App\Http\Controllers\Global\CdnController::class, 'createEdgeFunction']);
});

// Advanced Analytics Routes
Route::middleware(['auth:sanctum', 'throttle:api'])->prefix('global/analytics')->group(function () {
    Route::post('/track', [\App\Http\Controllers\Global\AnalyticsController::class, 'trackEvent']);
    Route::get('/realtime', [\App\Http\Controllers\Global\AnalyticsController::class, 'getRealtimeMetrics']);
    Route::get('/dashboard/{dashboardId}', [\App\Http\Controllers\Global\AnalyticsController::class, 'getDashboardData']);
    Route::get('/dashboards', [\App\Http\Controllers\Global\AnalyticsController::class, 'getTenantDashboards']);
    Route::post('/dashboards', [\App\Http\Controllers\Global\AnalyticsController::class, 'createDashboard']);
    Route::put('/dashboards/{dashboardId}', [\App\Http\Controllers\Global\AnalyticsController::class, 'updateDashboard']);
    Route::delete('/dashboards/{dashboardId}', [\App\Http\Controllers\Global\AnalyticsController::class, 'deleteDashboard']);
    Route::post('/anomaly-detection', [\App\Http\Controllers\Global\AnalyticsController::class, 'runAnomalyDetection']);
    Route::post('/forecasting', [\App\Http\Controllers\Global\AnalyticsController::class, 'runForecasting']);
    Route::get('/insights', [\App\Http\Controllers\Global\AnalyticsController::class, 'generateInsights']);
    Route::post('/export', [\App\Http\Controllers\Global\AnalyticsController::class, 'exportData']);
    Route::get('/allocation', [\App\Http\Controllers\Global\AnalyticsController::class, 'getCostAllocation']);
    Route::get('/idle-resources', [\App\Http\Controllers\Global\AnalyticsController::class, 'getIdleResources']);
});

// Enterprise SSO Routes
Route::middleware(['auth:sanctum', 'throttle:api'])->prefix('global/enterprise-sso')->group(function () {
    Route::get('/providers', [\App\Http\Controllers\Global\EnterpriseSsoController::class, 'getProviders']);
    Route::post('/ldap/authenticate', [\App\Http\Controllers\Global\EnterpriseSsoController::class, 'authenticateLdap']);
    Route::post('/kerberos/authenticate', [\App\Http\Controllers\Global\EnterpriseSsoController::class, 'authenticateKerberos']);
    Route::post('/saml/initiate', [\App\Http\Controllers\Global\EnterpriseSsoController::class, 'initiateSamlAuth']);
    Route::post('/saml/callback', [\App\Http\Controllers\Global\EnterpriseSsoController::class, 'handleSamlCallback'])->withoutMiddleware('auth:sanctum');
    Route::post('/sync', [\App\Http\Controllers\Global\EnterpriseSsoController::class, 'syncDirectory']);
});

// Data Governance Routes
Route::middleware(['auth:sanctum', 'throttle:api'])->prefix('global/data-governance')->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\Global\DataGovernanceController::class, 'getDashboard']);
    Route::post('/assets', [\App\Http\Controllers\Global\DataGovernanceController::class, 'registerAsset']);
    Route::get('/assets', [\App\Http\Controllers\Global\DataGovernanceController::class, 'getAssets']);
    Route::get('/assets/{assetId}', [\App\Http\Controllers\Global\DataGovernanceController::class, 'getAsset']);
    Route::post('/assets/{assetId}/classify', [\App\Http\Controllers\Global\DataGovernanceController::class, 'classifyAsset']);
    Route::get('/assets/{assetId}/lineage', [\App\Http\Controllers\Global\DataGovernanceController::class, 'getLineage']);
    Route::post('/quality-rules', [\App\Http\Controllers\Global\DataGovernanceController::class, 'createQualityRule']);
    Route::post('/quality-checks', [\App\Http\Controllers\Global\DataGovernanceController::class, 'runQualityChecks']);
    Route::post('/masking', [\App\Http\Controllers\Global\DataGovernanceController::class, 'applyMasking']);
    Route::get('/masking/{assetId}', [\App\Http\Controllers\Global\DataGovernanceController::class, 'getMaskingRules']);
    Route::post('/retention-policies', [\App\Http\Controllers\Global\DataGovernanceController::class, 'createRetentionPolicy']);
    Route::post('/retention/apply', [\App\Http\Controllers\Global\DataGovernanceController::class, 'applyRetentionPolicies']);
    Route::post('/subject-requests', [\App\Http\Controllers\Global\DataGovernanceController::class, 'handleDataSubjectRequest']);
});

// Disaster Recovery Routes
Route::middleware(['auth:sanctum', 'throttle:api'])->prefix('global/disaster-recovery')->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\Global\DisasterRecoveryController::class, 'getDashboard']);
    Route::post('/backup-plans', [\App\Http\Controllers\Global\DisasterRecoveryController::class, 'createBackupPlan']);
    Route::get('/backup-plans', [\App\Http\Controllers\Global\DisasterRecoveryController::class, 'getBackupPlans']);
    Route::post('/backup-plans/{planId}/run', [\App\Http\Controllers\Global\DisasterRecoveryController::class, 'runBackup']);
    Route::get('/backups', [\App\Http\Controllers\Global\DisasterRecoveryController::class, 'listBackups']);
    Route::get('/backups/{backupId}', [\App\Http\Controllers\Global\DisasterRecoveryController::class, 'getBackup']);
    Route::post('/backups/{backupId}/verify', [\App\Http\Controllers\Global\DisasterRecoveryController::class, 'verifyBackup']);
    Route::post('/restore', [\App\Http\Controllers\Global\DisasterRecoveryController::class, 'restoreBackup']);
    Route::get('/restore/{restoreId}', [\App\Http\Controllers\Global\DisasterRecoveryController::class, 'getRestoreStatus']);
    Route::post('/failover', [\App\Http\Controllers\Global\DisasterRecoveryController::class, 'initiateFailover']);
    Route::get('/failovers', [\App\Http\Controllers\Global\DisasterRecoveryController::class, 'getFailoverHistory']);
    Route::post('/dr-drills', [\App\Http\Controllers\Global\DisasterRecoveryController::class, 'initiateDrDrill']);
    Route::post('/dr-drills/{drillId}/start', [\App\Http\Controllers\Global\DisasterRecoveryController::class, 'startDrDrill']);
    Route::get('/dr-drills', [\App\Http\Controllers\Global\DisasterRecoveryController::class, 'getDrDrillHistory']);
});

// Cost Optimization Routes
Route::middleware(['auth:sanctum', 'throttle:api'])->prefix('global/cost-optimization')->group(function () {
    Route::get('/overview', [\App\Http\Controllers\Global\CostOptimizationController::class, 'getCostOverview']);
    Route::get('/anomalies', [\App\Http\Controllers\Global\CostOptimizationController::class, 'detectAnomalies']);
    Route::get('/rightsizing', [\App\Http\Controllers\Global\CostOptimizationController::class, 'generateRightsizingRecommendations']);
    Route::get('/reserved-instances', [\App\Http\Controllers\Global\CostOptimizationController::class, 'generateRiRecommendations']);
    Route::get('/savings-plans', [\App\Http\Controllers\Global\CostOptimizationController::class, 'generateSavingsPlansRecommendations']);
    Route::get('/spot-instances', [\App\Http\Controllers\Global\CostOptimizationController::class, 'generateSpotRecommendations']);
    Route::get('/recommendations', [\App\Http\Controllers\Global\CostOptimizationController::class, 'getAllRecommendations']);
    Route::post('/recommendations/{recommendationId}/apply', [\App\Http\Controllers\Global\CostOptimizationController::class, 'applyRecommendation']);
    Route::post('/budgets', [\App\Http\Controllers\Global\CostOptimizationController::class, 'createBudget']);
    Route::get('/budgets', [\App\Http\Controllers\Global\CostOptimizationController::class, 'getBudgets']);
    Route::get('/budgets/check', [\App\Http\Controllers\Global\CostOptimizationController::class, 'checkBudgets']);
    Route::post('/report', [\App\Http\Controllers\Global\CostOptimizationController::class, 'generateCostReport']);
    Route::get('/allocation', [\App\Http\Controllers\Global\CostOptimizationController::class, 'getCostAllocation']);
    Route::get('/idle-resources', [\App\Http\Controllers\Global\CostOptimizationController::class, 'getIdleResources']);
});