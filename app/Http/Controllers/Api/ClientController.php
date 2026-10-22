<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

/**
 * @group Clients
 *
 * APIs for managing clients in the marketing agency.
 */
class ClientController extends Controller
{
    /**
     * Display a listing of the clients.
     */
    public function index(Request $request)
    {
        $query = Client::query();

        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by account manager if provided
        if ($request->has('account_manager_id')) {
            $query->where('account_manager_id', $request->account_manager_id);
        }

        // Search by name if provided
        if ($request->has('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%");
        }

        // Include relationships if requested
        if ($request->boolean('with_account_manager')) {
            $query->with('accountManager');
        }
        if ($request->boolean('with_campaigns')) {
            $query->with('campaigns');
        }

        // Paginate results
        $clients = $query->latest()->paginate($request->input('per_page', 15));

        return response()->json($clients);
    }

    /**
     * Store a newly created client in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:clients,slug',
            'email' => 'nullable|string|email|max:255',
            'phone' => 'nullable|string|max:50',
            'website' => 'nullable|string|max:255|url',
            'industry' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'branding' => 'nullable|array',
            'social_accounts' => 'nullable|array',
            'ad_accounts' => 'nullable|array',
            'timezone' => 'nullable|string|max:50',
            'currency' => 'nullable|string|size:3',
            'status' => ['nullable', Rule::in(['active', 'onboarded', 'paused', 'archived'])],
            'contract_start_date' => 'nullable|date',
            'contract_end_date' => 'nullable|date|after_or_equal:contract_start_date',
            'monthly_budget' => 'nullable|numeric|min:0',
            'billing_details' => 'nullable|array',
            'account_manager_id' => 'nullable|exists:users,id',
            'team_members' => 'nullable|array',
            'settings' => 'nullable|array',
            'notes' => 'nullable|string',
        ]);

        // Ensure the client belongs to the current tenant
        $validated['tenant_id'] = $request->user()->tenant_id;

        // Auto-generate slug if not provided
        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $client = Client::create($validated);

        return response()->json($client, 201);
    }

    /**
     * Display the specified client.
     */
    public function show(Client $client)
    {
        return response()->json($client->load(['accountManager', 'campaigns']));
    }

    /**
     * Update the specified client in storage.
     */
    public function update(Request $request, Client $client)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'slug' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('clients')->ignore($client->id)],
            'email' => 'sometimes|nullable|string|email|max:255',
            'phone' => 'sometimes|nullable|string|max:50',
            'website' => 'sometimes|nullable|string|max:255|url',
            'industry' => 'sometimes|nullable|string|max:100',
            'description' => 'sometimes|nullable|string',
            'branding' => 'sometimes|nullable|array',
            'social_accounts' => 'sometimes|nullable|array',
            'ad_accounts' => 'sometimes|nullable|array',
            'timezone' => 'sometimes|nullable|string|max:50',
            'currency' => 'sometimes|nullable|string|size:3',
            'status' => ['sometimes', 'required', Rule::in(['active', 'onboarded', 'paused', 'archived'])],
            'contract_start_date' => 'sometimes|nullable|date',
            'contract_end_date' => 'sometimes|nullable|date|after_or_equal:contract_start_date',
            'monthly_budget' => 'sometimes|nullable|numeric|min:0',
            'billing_details' => 'sometimes|nullable|array',
            'account_manager_id' => 'sometimes|nullable|exists:users,id',
            'team_members' => 'sometimes|nullable|array',
            'settings' => 'sometimes|nullable|array',
            'notes' => 'sometimes|nullable|string',
        ]);

        $client->update($validated);

        return response()->json($client);
    }

    /**
     * Remove the specified client from storage.
     */
    public function destroy(Client $client)
    {
        $client->delete();

        return response()->json(null, 204);
    }

    /**
     * Get campaigns for a specific client.
     */
    public function campaigns(Client $client, Request $request)
    {
        $query = $client->campaigns();

        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by platform if provided
        if ($request->has('platform')) {
            $query->where('platform', $request->platform);
        }

        // Search by name if provided
        if ($request->has('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%");
        }

        $campaigns = $query->latest()->paginate($request->input('per_page', 15));

        return response()->json($campaigns);
    }

    /**
     * Get performance summary for a client.
     */
    public function performance(Client $client)
    {
        $campaigns = $client->campaigns()->get();

        $summary = [
            'total_campaigns' => $campaigns->count(),
            'active_campaigns' => $campaigns->where('status', 'active')->count(),
            'total_budget' => $campaigns->sum('budget'),
            'total_spent' => $campaigns->sum('spent'),
            'total_impressions' => $campaigns->sum('impressions'),
            'total_clicks' => $campaigns->sum('clicks'),
            'total_conversions' => $campaigns->sum('conversions'),
            'avg_ctr' => $campaigns->avg('ctr'),
            'avg_cpc' => $campaigns->avg('cpc'),
            'avg_cpa' => $campaigns->avg('cpa'),
            'avg_roas' => $campaigns->avg('roas'),
        ];

        return response()->json([
            'client' => $client->only(['id', 'name', 'slug']),
            'performance' => $summary,
        ]);
    }
}
