<?php

namespace App\Http\Controllers\Enterprise;

use App\Http\Controllers\Controller;
use App\Http\Requests\Enterprise\SubmitListingRequest;
use App\Http\Requests\Enterprise\SubmitReviewRequest;
use App\Services\Enterprise\MarketplaceService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MarketplaceController extends Controller
{
    protected MarketplaceService $marketplaceService;

    public function __construct(MarketplaceService $marketplaceService)
    {
        $this->marketplaceService = $marketplaceService;
    }

    /**
     * Submit a new listing.
     */
    public function submitListing(SubmitListingRequest $request): JsonResponse
    {
        $developer = auth()->user();
        $listing = $this->marketplaceService->submitListing($developer, $request->validated());

        return response()->json([
            'message' => 'Listing submitted',
            'data' => $listing,
        ], 201);
    }

    /**
     * Update a listing.
     */
    public function updateListing(int $listingId, SubmitListingRequest $request): JsonResponse
    {
        $developer = auth()->user();
        $listing = $this->marketplaceService->updateListing($listingId, $developer, $request->validated());

        return response()->json([
            'message' => 'Listing updated',
            'data' => $listing,
        ]);
    }

    /**
     * Submit listing for review.
     */
    public function submitForReview(int $listingId): JsonResponse
    {
        $developer = auth()->user();
        $result = $this->marketplaceService->submitForReview($listingId, $developer);

        return response()->json($result);
    }

    /**
     * Get a listing.
     */
    public function getListing(int $listingId): JsonResponse
    {
        $listing = $this->marketplaceService->getListing($listingId);

        if (!$listing) {
            return response()->json([
                'error' => 'Listing not found',
            ], 404);
        }

        return response()->json([
            'data' => $listing,
        ]);
    }

    /**
     * Get marketplace listings (public).
     */
    public function getListings(Request $request): JsonResponse
    {
        $filters = $request->only(['category', 'type', 'price_type', 'min_price', 'max_price', 'tags', 'search', 'featured', 'verified']);
        $pagination = $request->only(['page', 'per_page', 'sort_by', 'sort_order']);

        return response()->json($this->marketplaceService->getListings($filters, $pagination));
    }

    /**
     * Get developer's listings.
     */
    public function getDeveloperListings(Request $request): JsonResponse
    {
        $developer = auth()->user();
        $pagination = $request->only(['page', 'per_page']);

        return response()->json($this->marketplaceService->getDeveloperListings($developer->id, $pagination));
    }

    /**
     * Purchase/install a listing for current tenant.
     */
    public function purchaseListing(int $listingId, Request $request): JsonResponse
    {
        $tenant = auth()->user()->tenant;
        $paymentData = $request->only(['method', 'payment_method_id']);
        
        $result = $this->marketplaceService->purchaseListing($tenant, $listingId, $paymentData);

        if (!$result['success']) {
            return response()->json(['error' => $result['error']], 400);
        }

        return response()->json([
            'message' => 'Listing purchased and installed',
            'data' => $result,
        ]);
    }

    /**
     * Install a listing for tenant (admin).
     */
    public function installForTenant(int $listingId, Request $request): JsonResponse
    {
        $request->validate([
            'tenant_id' => 'required|exists:tenants,id',
        ]);

        $tenant = \App\Models\Tenant::findOrFail($request->tenant_id);
        $result = $this->marketplaceService->installForTenant($listingId, $tenant);

        if (!$result['success']) {
            return response()->json(['error' => $result['error']], 400);
        }

        return response()->json([
            'message' => 'Listing installed for tenant',
            'data' => $result,
        ]);
    }

    /**
     * Get tenant's installations.
     */
    public function getTenantInstallations(Request $request): JsonResponse
    {
        $tenant = auth()->user()->tenant;
        
        $installations = \DB::table('marketplace_installations')
            ->where('tenant_id', $tenant->id)
            ->join('marketplace_listings', 'marketplace_installations.listing_id', '=', 'marketplace_listings.id')
            ->select('marketplace_installations.*', 'marketplace_listings.title', 'marketplace_listings.type', 'marketplace_listings.category')
            ->orderBy('marketplace_installations.installed_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json($installations);
    }

    /**
     * Submit a review for a listing.
     */
    public function submitReview(int $listingId, SubmitReviewRequest $request): JsonResponse
    {
        $user = auth()->user();
        $result = $this->marketplaceService->submitReview($user, $listingId, $request->validated());

        if (!$result['success']) {
            return response()->json(['error' => $result['error']], 400);
        }

        return response()->json([
            'message' => 'Review submitted',
            'data' => $result,
        ]);
    }

    /**
     * Get listing reviews.
     */
    public function getListingReviews(int $listingId, Request $request): JsonResponse
    {
        $pagination = $request->only(['page', 'per_page']);
        
        return response()->json($this->marketplaceService->getListingReviews($listingId, $pagination));
    }

    /**
     * Get developer earnings.
     */
    public function getEarnings(Request $request): JsonResponse
    {
        $developer = auth()->user();
        $period = $request->get('period', 'month');
        
        return response()->json([
            'data' => $this->marketplaceService->getDeveloperEarnings($developer->id, $period),
        ]);
    }

    /**
     * Get marketplace statistics.
     */
    public function getStats(): JsonResponse
    {
        return response()->json([
            'data' => $this->marketplaceService->getMarketplaceStats(),
        ]);
    }

    /**
     * Search marketplace.
     */
    public function search(Request $request): JsonResponse
    {
        $params = [
            'q' => $request->get('q', ''),
            'filters' => $request->get('filters', []),
            'pagination' => $request->only(['page', 'per_page', 'sort_by', 'sort_order']),
        ];

        return response()->json($this->marketplaceService->search($params));
    }

    /**
     * Get featured listings.
     */
    public function getFeatured(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 10);
        
        return response()->json([
            'data' => $this->marketplaceService->getFeaturedListings($limit),
        ]);
    }

    /**
     * Get trending listings.
     */
    public function getTrending(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 10);
        $period = $request->get('period', 'week');
        
        return response()->json([
            'data' => $this->marketplaceService->getTrendingListings($limit, $period),
        ]);
    }

    /**
     * Approve listing (admin/moderator).
     */
    public function approveListing(int $listingId, Request $request): JsonResponse
    {
        $moderator = auth()->user();
        $options = $request->only(['featured', 'verified', 'publish_immediately']);
        
        $result = $this->marketplaceService->approveListing($listingId, $moderator, $options);

        return response()->json($result);
    }

    /**
     * Reject listing (admin/moderator).
     */
    public function rejectListing(int $listingId, Request $request): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $moderator = auth()->user();
        $result = $this->marketplaceService->rejectListing($listingId, $moderator, $request->reason);

        return response()->json($result);
    }

    /**
     * Get pending review listings (admin/moderator).
     */
    public function getPendingReviews(Request $request): JsonResponse
    {
        $listings = \DB::table('marketplace_listings')
            ->where('status', 'pending_review')
            ->with('developer:id,name,email')
            ->orderBy('submitted_for_review_at', 'asc')
            ->paginate($request->get('per_page', 20));

        return response()->json($listings);
    }
}
