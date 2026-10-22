<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * @group Campaigns
 *
 * APIs for managing marketing campaigns.
 */
class CampaignController extends Controller
{
    /**
     * Display a listing of the campaigns.
     */
    public function index(Request $request)
    {
        $query = Campaign::query();

        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by type if provided
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Search by name if provided
        if ($request->has('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%");
        }

        // Paginate results
        $campaigns = $query->latest()->paginate($request->input('per_page', 15));

        return response()->json($campaigns);
    }

    /**
     * Store a newly created campaign in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:campaigns,slug',
            'description' => 'nullable|string',
            'status' => ['required', Rule::in(['draft', 'active', 'paused', 'completed', 'cancelled'])],
            'type' => ['nullable', Rule::in(['social_media', 'email', 'search', 'display', 'video'])],
            'budget' => 'required|numeric|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'targeting' => 'nullable|array',
            'settings' => 'nullable|array',
        ]);

        // Ensure the campaign belongs to the current tenant
        $validated['tenant_id'] = $request->user()->tenant_id;

        $campaign = Campaign::create($validated);

        return response()->json($campaign, 201);
    }

    /**
     * Display the specified campaign.
     */
    public function show(Campaign $campaign)
    {
        return response()->json($campaign);
    }

    /**
     * Update the specified campaign in storage.
     */
    public function update(Request $request, Campaign $campaign)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'slug' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('campaigns')->ignore($campaign->id)],
            'description' => 'sometimes|nullable|string',
            'status' => ['sometimes', 'required', Rule::in(['draft', 'active', 'paused', 'completed', 'cancelled'])],
            'type' => ['sometimes', 'nullable', Rule::in(['social_media', 'email', 'search', 'display', 'video'])],
            'budget' => 'sometimes|required|numeric|min:0',
            'start_date' => 'sometimes|nullable|date',
            'end_date' => 'sometimes|nullable|date|after_or_equal:start_date',
            'targeting' => 'sometimes|nullable|array',
            'settings' => 'sometimes|nullable|array',
        ]);

        $campaign->update($validated);

        return response()->json($campaign);
    }

    /**
     * Remove the specified campaign from storage.
     */
    public function destroy(Campaign $campaign)
    {
        $campaign->delete();

        return response()->json(null, 204);
    }
}
