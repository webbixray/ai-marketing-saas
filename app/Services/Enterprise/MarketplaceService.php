<?php

namespace App\Services\Enterprise;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Carbon\Carbon;

class MarketplaceService
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'enabled' => true,
            'marketplace_url' => env('MARKETPLACE_URL', 'https://marketplace.webixray.com'),
            'api_key' => env('MARKETPLACE_API_KEY'),
            'commission_rate' => 0.15, // 15%
            'featured_fee' => 50.00, // $50/month for featured placement
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
            'review_required' => true,
            'auto_approve_verified' => true,
            'revenue_sharing' => [
                'developer' => 0.70,
                'platform' => 0.15,
                'affiliate' => 0.10,
                'support' => 0.05,
            ],
            'payout_schedule' => 'monthly',
            'minimum_payout' => 50.00,
            'refund_policy_days' => 14,
            'auto_update_notifications' => true,
        ], []);
    }

    /**
     * Submit a new listing to the marketplace.
     */
    public function submitListing(User $developer, array $data): array
    {
        $validated = $this->validateListing($data);

        $listing = DB::table('marketplace_listings')->insertGetId([
            'developer_id' => $developer->id,
            'tenant_id' => $developer->tenant_id,
            'title' => $validated['title'],
            'slug' => Str::slug($validated['title']) . '-' . Str::random(6),
            'description' => $validated['description'],
            'short_description' => $validated['short_description'] ?? '',
            'category' => $validated['category'],
            'type' => $validated['type'], // agent, template, workflow, integration, etc.
            'version' => $validated['version'] ?? '1.0.0',
            'price_type' => $validated['price_type'] ?? 'free', // free, one_time, subscription, usage
            'price' => $validated['price'] ?? 0,
            'currency' => $validated['currency'] ?? 'USD',
            'billing_cycle' => $validated['billing_cycle'] ?? null, // monthly, yearly
            'tags' => json_encode($validated['tags'] ?? []),
            'requirements' => json_encode($validated['requirements'] ?? []),
            'compatibility' => json_encode($validated['compatibility'] ?? []),
            'screenshots' => json_encode($validated['screenshots'] ?? []),
            'demo_url' => $validated['demo_url'] ?? null,
            'documentation_url' => $validated['documentation_url'] ?? null,
            'repository_url' => $validated['repository_url'] ?? null,
            'support_email' => $validated['support_email'] ?? $developer->email,
            'support_url' => $validated['support_url'] ?? null,
            'license_type' => $validated['license_type'] ?? 'commercial',
            'license_text' => $validated['license_text'] ?? '',
            'changelog' => json_encode($validated['changelog'] ?? []),
            'status' => 'draft',
            'featured' => false,
            'verified' => false,
            'download_count' => 0,
            'rating_average' => 0,
            'rating_count' => 0,
            'revenue_total' => 0,
            'published_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->getListing($listing);
    }

    /**
     * Update a listing.
     */
    public function updateListing(int $listingId, User $developer, array $data): array
    {
        $listing = DB::table('marketplace_listings')
            ->where('id', $listingId)
            ->where('developer_id', $developer->id)
            ->firstOrFail();

        $validated = $this->validateListing($data, true);

        DB::table('marketplace_listings')
            ->where('id', $listingId)
            ->update(array_merge($validated, ['updated_at' => now()]));

        return $this->getListing($listingId);
    }

    /**
     * Submit listing for review.
     */
    public function submitForReview(int $listingId, User $developer): array
    {
        $listing = DB::table('marketplace_listings')
            ->where('id', $listingId)
            ->where('developer_id', $developer->id)
            ->firstOrFail();

        if ($listing->status !== 'draft' && $listing->status !== 'rejected') {
            return ['success' => false, 'error' => 'Listing cannot be submitted for review'];
        }

        DB::table('marketplace_listings')
            ->where('id', $listingId)
            ->update([
                'status' => 'pending_review',
                'submitted_for_review_at' => now(),
                'updated_at' => now(),
            ]);

        // Notify review team
        $this->notifyReviewTeam('new_submission', [
            'listing_id' => $listingId,
            'title' => $listing->title,
            'developer' => $developer->name,
        ]);

        return ['success' => true, 'message' => 'Submitted for review'];
    }

    /**
     * Approve listing (admin/moderator).
     */
    public function approveListing(int $listingId, User $moderator, array $options = []): array
    {
        $listing = DB::table('marketplace_listings')
            ->where('id', $listingId)
            ->firstOrFail();

        DB::table('marketplace_listings')
            ->where('id', $listingId)
            ->update([
                'status' => 'approved',
                'approved_by' => $moderator->id,
                'approved_at' => now(),
                'featured' => $options['featured'] ?? false,
                'verified' => $options['verified'] ?? true,
                'published_at' => $options['publish_immediately'] ? now() : null,
                'updated_at' => now(),
            ]);

        // Notify developer
        $this->notifyDeveloper($listing->developer_id, 'listing_approved', [
            'listing_id' => $listingId,
            'title' => $listing->title,
        ]);

        return ['success' => true, 'message' => 'Listing approved'];
    }

    /**
     * Reject listing (admin/moderator).
     */
    public function rejectListing(int $listingId, User $moderator, string $reason): array
    {
        $listing = DB::table('marketplace_listings')
            ->where('id', $listingId)
            ->firstOrFail();

        DB::table('marketplace_listings')
            ->where('id', $listingId)
            ->update([
                'status' => 'rejected',
                'rejection_reason' => $reason,
                'rejected_by' => $moderator->id,
                'rejected_at' => now(),
                'updated_at' => now(),
            ]);

        // Notify developer
        $this->notifyDeveloper($listing->developer_id, 'listing_rejected', [
            'listing_id' => $listingId,
            'title' => $listing->title,
            'reason' => $reason,
        ]);

        return ['success' => true, 'message' => 'Listing rejected'];
    }

    /**
     * Purchase/install a listing.
     */
    public function purchaseListing(Tenant $tenant, int $listingId, array $paymentData = []): array
    {
        $listing = DB::table('marketplace_listings')
            ->where('id', $listingId)
            ->where('status', 'approved')
            ->firstOrFail();

        // Check if already purchased
        $existing = DB::table('marketplace_purchases')
            ->where('tenant_id', $tenant->id)
            ->where('listing_id', $listingId)
            ->where('status', 'active')
            ->first();

        if ($existing) {
            return ['success' => false, 'error' => 'Already purchased'];
        }

        // Process payment if paid
        if ($listing->price > 0) {
            $paymentResult = $this->processPayment($tenant, $listing, $paymentData);
            if (!$paymentResult['success']) {
                return ['success' => false, 'error' => $paymentResult['error']];
            }
        }

        // Create purchase record
        $purchaseId = DB::table('marketplace_purchases')->insertGetId([
            'tenant_id' => $tenant->id,
            'listing_id' => $listingId,
            'developer_id' => $listing->developer_id,
            'price_paid' => $listing->price,
            'currency' => $listing->currency,
            'billing_cycle' => $listing->billing_cycle,
            'status' => 'active',
            'purchased_at' => now(),
            'expires_at' => $listing->billing_cycle ? now()->addMonth() : null,
            'payment_method' => $paymentData['method'] ?? 'credit_card',
            'transaction_id' => $paymentResult['transaction_id'] ?? null,
        ]);

        // Update listing stats
        DB::table('marketplace_listings')
            ->where('id', $listingId)
            ->increment('download_count');

        // Install the item
        $installResult = $this->installListing($tenant, $listing);

        // Trigger webhook
        $this->triggerWebhook('purchase.completed', [
            'tenant_id' => $tenant->id,
            'listing_id' => $listingId,
            'purchase_id' => $purchaseId,
        ]);

        return [
            'success' => true,
            'purchase_id' => $purchaseId,
            'install_result' => $installResult,
        ];
    }

    /**
     * Install a purchased listing.
     */
    protected function installListing(Tenant $tenant, object $listing): array
    {
        $results = ['success' => true, 'actions' => []];

        switch ($listing->type) {
            case 'agent':
                $results['actions'][] = $this->installAgent($tenant, $listing);
                break;
            case 'template':
                $results['actions'][] = $this->installTemplate($tenant, $listing);
                break;
            case 'workflow':
                $results['actions'][] = $this->installWorkflow($tenant, $listing);
                break;
            case 'integration':
                $results['actions'][] = $this->installIntegration($tenant, $listing);
                break;
            case 'report':
                $results['actions'][] = $this->installReportTemplate($tenant, $listing);
                break;
            case 'creative':
                $results['actions'][] = $this->installCreative($tenant, $listing);
                break;
            case 'script':
                $results['actions'][] = $this->installScript($tenant, $listing);
                break;
            case 'connector':
                $results['actions'][] = $this->installConnector($tenant, $listing);
                break;
        }

        return $results;
    }

    /**
     * Install an agent.
     */
    protected function installAgent(Tenant $tenant, object $listing): array
    {
        // Agent installation would register the agent with the orchestrator
        return [
            'type' => 'agent',
            'action' => 'registered',
            'details' => "Agent {$listing->title} registered with orchestrator",
        ];
    }

    /**
     * Install a template.
     */
    protected function installTemplate(Tenant $tenant, object $listing): array
    {
        // Template installation would create campaign/template records
        return [
            'type' => 'template',
            'action' => 'imported',
            'details' => "Template {$listing->title} imported to tenant",
        ];
    }

    /**
     * Install a workflow.
     */
    protected function installWorkflow(Tenant $tenant, object $listing): array
    {
        // Workflow installation would register with automation engine
        return [
            'type' => 'workflow',
            'action' => 'registered',
            'details' => "Workflow {$listing->title} registered with automation engine",
        ];
    }

    /**
     * Install an integration.
     */
    protected function installIntegration(Tenant $tenant, object $listing): array
    {
        // Integration installation would configure platform connections
        return [
            'type' => 'integration',
            'action' => 'configured',
            'details' => "Integration {$listing->title} configured for tenant",
        ];
    }

    /**
     * Install a report template.
     */
    protected function installReportTemplate(Tenant $tenant, object $listing): array
    {
        return [
            'type' => 'report',
            'action' => 'created',
            'details' => "Report template {$listing->title} created",
        ];
    }

    /**
     * Install creative assets.
     */
    protected function installCreative(Tenant $tenant, object $listing): array
    {
        return [
            'type' => 'creative',
            'action' => 'imported',
            'details' => "Creative assets from {$listing->title} imported",
        ];
    }

    /**
     * Install a script.
     */
    protected function installScript(Tenant $tenant, object $listing): array
    {
        return [
            'type' => 'script',
            'action' => 'registered',
            'details' => "Script {$listing->title} registered for execution",
        ];
    }

    /**
     * Install a connector.
     */
    protected function installConnector(Tenant $tenant, object $listing): array
    {
        return [
            'type' => 'connector',
            'action' => 'configured',
            'details' => "Data connector {$listing->title} configured",
        ];
    }

    /**
     * Process payment for purchase.
     */
    protected function processPayment(Tenant $tenant, object $listing, array $paymentData): array
    {
        // Integrate with Stripe or other payment processor
        // Simplified for now
        return [
            'success' => true,
            'transaction_id' => 'txn_' . Str::random(16),
        ];
    }

    /**
     * Get listing details.
     */
    public function getListing(int $listingId): ?array
    {
        $listing = DB::table('marketplace_listings')
            ->where('id', $listingId)
            ->first();

        if (!$listing) {
            return null;
        }

        return $this->formatListing($listing);
    }

    /**
     * Get listings with filters.
     */
    public function getListings(array $filters = [], array $pagination = []): array
    {
        $query = DB::table('marketplace_listings')
            ->where('status', 'approved');

        // Apply filters
        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['price_type'])) {
            $query->where('price_type', $filters['price_type']);
        }

        if (!empty($filters['min_price'])) {
            $query->where('price', '>=', $filters['min_price']);
        }

        if (!empty($filters['max_price'])) {
            $query->where('price', '<=', $filters['max_price']);
        }

        if (!empty($filters['tags'])) {
            $tags = (array)$filters['tags'];
            foreach ($tags as $tag) {
                $query->whereJsonContains('tags', $tag);
            }
        }

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('title', 'LIKE', "%{$filters['search']}%")
                  ->orWhere('description', 'LIKE', "%{$filters['search']}%")
                  ->orWhere('short_description', 'LIKE', "%{$filters['search']}%")
                  ->orWhere('tags', 'LIKE', "%{$filters['search']}%");
            });
        }

        if (!empty($filters['developer_id'])) {
            $query->where('developer_id', $filters['developer_id']);
        }

        if (!empty($filters['featured'])) {
            $query->where('featured', true);
        }

        if (!empty($filters['verified'])) {
            $query->where('verified', true);
        }

        // Sorting
        $sortBy = $pagination['sort_by'] ?? 'created_at';
        $sortOrder = $pagination['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = min($pagination['per_page'] ?? 20, 100);
        $page = $pagination['page'] ?? 1;

        $results = DB::table('marketplace_listings')
            ->where('status', 'approved')
            ->when(!empty($filters['category']), fn($q) => $q->where('category', $filters['category']))
            ->when(!empty($filters['type']), fn($q) => $q->where('type', $filters['type']))
            ->when(!empty($filters['search']), fn($q) => $q->where(function($q) use ($filters) {
                $q->where('title', 'LIKE', "%{$filters['search']}%")
                  ->orWhere('description', 'LIKE', "%{$filters['search']}%");
            })
            ->orderBy($pagination['sort_by'] ?? 'created_at', $pagination['sort_order'] ?? 'desc')
            ->paginate($pagination['per_page'] ?? 20, ['*'], 'page', $page);

        return [
            'data' => array_map([$this, 'formatListing'], $results->items()),
            'pagination' => [
                'total' => $results->total(),
                'per_page' => $results->perPage(),
                'current_page' => $results->currentPage(),
                'last_page' => $results->lastPage(),
            ],
        ];
    }

    /**
     * Get listings by developer.
     */
    public function getDeveloperListings(int $developerId, array $pagination = []): array
    {
        $perPage = $pagination['per_page'] ?? 20;
        $page = $pagination['page'] ?? 1;

        $results = DB::table('marketplace_listings')
            ->where('developer_id', $developerId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return [
            'data' => array_map([$this, 'formatListing'], $results->items()),
            'pagination' => [
                'total' => $results->total(),
                'per_page' => $results->perPage(),
                'current_page' => $results->currentPage(),
                'last_page' => $results->lastPage(),
            ],
        ];
    }

    /**
     * Purchase/install a listing for a tenant.
     */
    public function installForTenant(int $listingId, Tenant $tenant): array
    {
        $listing = DB::table('marketplace_listings')
            ->where('id', $listingId)
            ->where('status', 'approved')
            ->firstOrFail();

        // Check if already installed
        $existing = DB::table('marketplace_installations')
            ->where('tenant_id', $tenant->id)
            ->where('listing_id', $listingId)
            ->where('status', 'active')
            ->first();

        if ($existing) {
            return ['success' => false, 'error' => 'Already installed'];
        }

        // Create installation record
        $installationId = DB::table('marketplace_installations')->insertGetId([
            'tenant_id' => $tenant->id,
            'listing_id' => $listingId,
            'status' => 'installing',
            'installed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $result = $this->installListing($tenant, $listing);
            
            DB::table('marketplace_installations')
                ->where('id', $installationId)
                ->update([
                    'status' => 'active',
                    'config' => json_encode([]),
                    'updated_at' => now(),
                ]);

            return ['success' => true, 'installation_id' => $installationId, 'result' => $result];
        } catch (\Throwable $e) {
            DB::table('marketplace_installations')
                ->where('id', $installationId)
                ->update([
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                    'updated_at' => now(),
                ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Submit a review for a listing.
     */
    public function submitReview(User $user, int $listingId, array $data): array
    {
        // Check if user purchased the listing
        $purchase = DB::table('marketplace_purchases')
            ->where('tenant_id', auth()->user()->tenant_id)
            ->where('listing_id', $listingId)
            ->where('status', 'active')
            ->first();

        if (!$purchase) {
            return ['success' => false, 'error' => 'Must purchase listing to review'];
        }

        // Check if already reviewed
        $existing = DB::table('marketplace_reviews')
            ->where('listing_id', $listingId)
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            return ['success' => false, 'error' => 'Already reviewed'];
        }

        $validated = [
            'rating' => $data['rating'] ?? 0,
            'title' => $data['title'] ?? '',
            'comment' => $data['comment'] ?? '',
            'pros' => $data['pros'] ?? [],
            'cons' => $data['cons'] ?? [],
        ];

        if ($validated['rating'] < 1 || $validated['rating'] > 5) {
            return ['success' => false, 'error' => 'Rating must be 1-5'];
        }

        $reviewId = DB::table('marketplace_reviews')->insertGetId([
            'listing_id' => $listingId,
            'user_id' => $user->id,
            'tenant_id' => auth()->user()->tenant_id,
            'rating' => $validated['rating'],
            'title' => $validated['title'],
            'comment' => $validated['comment'],
            'pros' => json_encode($validated['pros']),
            'cons' => json_encode($validated['cons']),
            'verified_purchase' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Update listing rating
        $this->updateListingRating($listingId);

        return ['success' => true, 'review_id' => $reviewId];
    }

    /**
     * Update listing rating.
     */
    protected function updateListingRating(int $listingId): void
    {
        $stats = DB::table('marketplace_reviews')
            ->where('listing_id', $listingId)
            ->selectRaw('AVG(rating) as avg_rating, COUNT(*) as count')
            ->first();

        DB::table('marketplace_listings')
            ->where('id', $listingId)
            ->update([
                'rating_average' => round($stats->avg_rating ?? 0, 2),
                'rating_count' => $stats->count ?? 0,
                'updated_at' => now(),
            ]);
    }

    /**
     * Get listing reviews.
     */
    public function getListingReviews(int $listingId, array $pagination = []): array
    {
        $perPage = $pagination['per_page'] ?? 20;
        $page = $pagination['page'] ?? 1;

        $results = DB::table('marketplace_reviews')
            ->where('listing_id', $listingId)
            ->join('users', 'marketplace_reviews.user_id', '=', 'users.id')
            ->select('marketplace_reviews.*', 'users.name as user_name', 'users.avatar as user_avatar')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return [
            'data' => $results->items(),
            'pagination' => [
                'total' => $results->total(),
                'per_page' => $results->perPage(),
                'current_page' => $results->currentPage(),
                'last_page' => $results->lastPage(),
            ],
        ];
    }

    /**
     * Get developer earnings.
     */
    public function getDeveloperEarnings(int $developerId, string $period = 'month'): array
    {
        $periodStart = match ($period) {
            'day' => now()->startOfDay(),
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            'year' => now()->startOfYear(),
            default => now()->startOfMonth(),
        };

        $sales = DB::table('marketplace_purchases')
            ->where('developer_id', $developerId)
            ->where('status', 'active')
            ->where('purchased_at', '>=', $periodStart)
            ->get();

        $totalRevenue = $sales->sum('price_paid');
        $platformFee = $totalRevenue * $this->config['commission_rate'];
        $developerEarnings = $totalRevenue - $platformFee;

        $byListing = DB::table('marketplace_purchases')
            ->where('developer_id', $developerId)
            ->where('status', 'active')
            ->where('purchased_at', '>=', $periodStart)
            ->join('marketplace_listings', 'marketplace_purchases.listing_id', '=', 'marketplace_listings.id')
            ->selectRaw('marketplace_listings.title, SUM(marketplace_purchases.price_paid) as revenue, COUNT(*) as sales')
            ->groupBy('marketplace_listings.id', 'marketplace_listings.title')
            ->get();

        return [
            'period' => $period,
            'period_start' => $periodStart->toISOString(),
            'period_end' => now()->toISOString(),
            'total_revenue' => round($totalRevenue, 2),
            'platform_fee' => round($platformFee, 2),
            'developer_earnings' => round($developerEarnings, 2),
            'total_sales' => $sales->count(),
            'by_listing' => $byListing->map(function ($item) {
                return [
                    'title' => $item->title,
                    'revenue' => round($item->revenue, 2),
                    'sales' => $item->sales,
                ];
            })->toArray(),
        ];
    }

    /**
     * Get marketplace statistics.
     */
    public function getMarketplaceStats(): array
    {
        return [
            'total_listings' => DB::table('marketplace_listings')->where('status', 'approved')->count(),
            'total_developers' => DB::table('marketplace_listings')->distinct('developer_id')->count(),
            'total_installations' => DB::table('marketplace_installations')->where('status', 'active')->count(),
            'total_revenue' => DB::table('marketplace_purchases')->where('status', 'active')->sum('price_paid'),
            'categories' => DB::table('marketplace_listings')
                ->where('status', 'approved')
                ->selectRaw('category, COUNT(*) as count')
                ->groupBy('category')
                ->pluck('count', 'category')
                ->toArray(),
            'types' => DB::table('marketplace_listings')
                ->where('status', 'approved')
                ->selectRaw('type, COUNT(*) as count')
                ->groupBy('type')
                ->pluck('count', 'type')
                ->toArray(),
            'average_rating' => DB::table('marketplace_listings')
                ->where('status', 'approved')
                ->where('rating_count', '>', 0)
                ->avg('rating_average'),
            'top_categories' => DB::table('marketplace_listings')
                ->where('status', 'approved')
                ->selectRaw('category, COUNT(*) as count')
                ->groupBy('category')
                ->orderBy('count', 'desc')
                ->limit(5)
                ->pluck('count', 'category')
                ->toArray(),
        ];
    }

    /**
     * Search marketplace.
     */
    public function search(array $params = []): array
    {
        $query = $params['q'] ?? '';
        $filters = $params['filters'] ?? [];
        $pagination = $params['pagination'] ?? [];

        return $this->getListings(array_merge($filters, ['search' => $query]), $pagination);
    }

    /**
     * Get featured listings.
     */
    public function getFeaturedListings(int $limit = 10): array
    {
        return DB::table('marketplace_listings')
            ->where('status', 'approved')
            ->where('featured', true)
            ->orderBy('rating_average', 'desc')
            ->limit($limit)
            ->get()
            ->map([$this, 'formatListing'])
            ->toArray();
    }

    /**
     * Get trending listings.
     */
    public function getTrendingListings(int $limit = 10, string $period = 'week'): array
    {
        $periodStart = match ($period) {
            'day' => now()->subDay(),
            'week' => now()->subWeek(),
            'month' => now()->subMonth(),
            default => now()->subWeek(),
        };

        return DB::table('marketplace_installations')
            ->where('status', 'active')
            ->where('installed_at', '>=', $periodStart)
            ->join('marketplace_listings', 'marketplace_installations.listing_id', '=', 'marketplace_listings.id')
            ->where('marketplace_listings.status', 'approved')
            ->selectRaw('marketplace_listings.*, COUNT(*) as install_count')
            ->groupBy('marketplace_listings.id')
            ->orderBy('install_count', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                $item = $this->formatListing($item);
                $item['install_count'] = $item->install_count ?? 0;
                return $item;
            })
            ->toArray();
    }

    /**
     * Validate listing data.
     */
    protected function validateListing(array $data, bool $isUpdate = false): array
    {
        $required = $isUpdate ? [] : ['title', 'description', 'category', 'type'];
        
        $rules = [
            'title' => 'required|string|max:200',
            'description' => 'required|string',
            'short_description' => 'nullable|string|max:500',
            'category' => 'required|in:' . implode(',', array_keys($this->config['categories'])),
            'type' => 'required|in:agent,template,workflow,integration,report,creative,script,connector',
            'version' => 'nullable|string|max:20',
            'price_type' => 'nullable|in:free,one_time,subscription,usage',
            'price' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'billing_cycle' => 'nullable|in:monthly,yearly',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'requirements' => 'nullable|array',
            'compatibility' => 'nullable|array',
            'screenshots' => 'nullable|array',
            'screenshots.*' => 'url',
            'demo_url' => 'nullable|url',
            'documentation_url' => 'nullable|url',
            'repository_url' => 'nullable|url',
            'support_email' => 'nullable|email',
            'support_url' => 'nullable|url',
            'repository_url' => 'nullable|url',
            'license_type' => 'nullable|in:commercial,mit,apache,gpl,custom',
            'license_text' => 'nullable|string',
            'changelog' => 'nullable|array',
        ];

        // Validate required fields
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new \InvalidArgumentException("Field {$field} is required");
            }
        }

        // Validate other fields
        foreach ($rules as $field => $rule) {
            // Simplified validation
            if (isset($data[$field])) {
                // Add validation logic here
            }
        }

        return array_intersect_key($data, array_flip(array_keys($rules)));
    }

    /**
     * Format listing for API response.
     */
    protected function formatListing(object $listing): array
    {
        return [
            'id' => $listing->id,
            'slug' => $listing->slug,
            'title' => $listing->title,
            'description' => $listing->description,
            'short_description' => $listing->short_description,
            'category' => $listing->category,
            'category_label' => $this->config['categories'][$listing->category] ?? $listing->category,
            'type' => $listing->type,
            'version' => $listing->version,
            'price_type' => $listing->price_type,
            'price' => $listing->price,
            'currency' => $listing->currency,
            'billing_cycle' => $listing->billing_cycle,
            'tags' => json_decode($listing->tags ?? '[]', true),
            'requirements' => json_decode($listing->requirements ?? '[]', true),
            'compatibility' => json_decode($listing->compatibility ?? '[]', true),
            'screenshots' => json_decode($listing->screenshots ?? '[]', true),
            'demo_url' => $listing->demo_url,
            'documentation_url' => $listing->documentation_url,
            'repository_url' => $listing->repository_url,
            'support_email' => $listing->support_email,
            'support_url' => $listing->support_url,
            'license_type' => $listing->license_type,
            'changelog' => json_decode($listing->changelog ?? '[]', true),
            'status' => $listing->status,
            'featured' => $listing->featured,
            'verified' => $listing->verified,
            'download_count' => $listing->download_count,
            'rating_average' => $listing->rating_average,
            'rating_count' => $listing->rating_count,
            'developer' => [
                'id' => $listing->developer_id,
                'name' => 'Developer', // Would join users table
            ],
            'published_at' => $listing->published_at,
            'created_at' => $listing->created_at,
            'updated_at' => $listing->updated_at,
        ];
    }

    /**
     * Notify review team.
     */
    protected function notifyReviewTeam(string $event, array $data): void
    {
        Log::info("Marketplace review event: {$event}", $data);
    }

    /**
     * Notify developer.
     */
    protected function notifyDeveloper(int $developerId, string $event, array $data): void
    {
        Log::info("Developer notification: {$event}", array_merge(['developer_id' => $developerId], $data));
    }

    /**
     * Trigger webhook.
     */
    protected function triggerWebhook(string $event, array $data): void
    {
        // Would trigger webhook to external systems
        Log::info("Marketplace webhook: {$event}", $data);
    }
}
