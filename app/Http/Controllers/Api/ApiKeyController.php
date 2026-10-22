<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * @group API Keys
 *
 * APIs for managing API keys for service-to-service authentication.
 */
class ApiKeyController extends Controller
{
    /**
     * Display a listing of the API keys.
     */
    public function index(Request $request)
    {
        $query = ApiKey::with('creator:id,name,email');

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Search by name
        if ($request->has('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%");
        }

        $keys = $query->latest()->paginate($request->input('per_page', 15));

        // Hide the hash in listings
        $keys->getCollection()->transform(function ($key) {
            $key->key_hash = null;
            return $key;
        });

        return response()->json($keys);
    }

    /**
     * Store a newly created API key.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'scopes' => 'nullable|array',
            'scopes.*' => 'string',
            'ip_whitelist' => 'nullable|array',
            'ip_whitelist.*' => 'string',
            'expires_at' => 'nullable|date|after:now',
            'description' => 'nullable|string',
        ]);

        // Generate the API key
        $keyData = ApiKey::generateKey();

        $validated['tenant_id'] = $request->user()->tenant_id;
        $validated['user_id'] = $request->user()->id;
        $validated['key_hash'] = Hash::make($keyData['raw_key']);
        $validated['key_prefix'] = $keyData['key_prefix'];
        $validated['is_active'] = true;

        $apiKey = ApiKey::create($validated);

        // Return the raw key only once
        return response()->json([
            'api_key' => $apiKey,
            'raw_key' => $keyData['raw_key'], // Only shown once!
            'message' => 'API key created. Save the raw_key now - it will not be shown again.',
        ], 201);
    }

    /**
     * Display the specified API key.
     */
    public function show(ApiKey $apiKey)
    {
        $apiKey->load('creator:id,name,email');
        $apiKey->key_hash = null; // Never expose hash

        return response()->json($apiKey);
    }

    /**
     * Update the specified API key.
     */
    public function update(Request $request, ApiKey $apiKey)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'scopes' => 'nullable|array',
            'scopes.*' => 'string',
            'ip_whitelist' => 'nullable|array',
            'ip_whitelist.*' => 'string',
            'expires_at' => 'nullable|date|after:now',
            'description' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $apiKey->update($validated);
        $apiKey->key_hash = null;

        return response()->json($apiKey);
    }

    /**
     * Regenerate the API key (revoke old, create new).
     */
    public function regenerate(ApiKey $apiKey)
    {
        $keyData = ApiKey::generateKey();

        $apiKey->update([
            'key_hash' => Hash::make($keyData['raw_key']),
            'key_prefix' => $keyData['key_prefix'],
            'last_used_at' => null,
            'last_used_ip' => null,
        ]);

        return response()->json([
            'api_key' => $apiKey,
            'raw_key' => $keyData['raw_key'],
            'message' => 'API key regenerated. Save the raw_key now - it will not be shown again.',
        ]);
    }

    /**
     * Remove the specified API key.
     */
    public function destroy(ApiKey $apiKey)
    {
        $apiKey->delete();

        return response()->json(null, 204);
    }

    /**
     * Get available scopes.
     */
    public function scopes()
    {
        return response()->json([
            'scopes' => [
                '*' => 'Full access to all resources',
                'campaigns:read' => 'Read campaigns',
                'campaigns:write' => 'Create/update campaigns',
                'campaigns:delete' => 'Delete campaigns',
                'clients:read' => 'Read clients',
                'clients:write' => 'Create/update clients',
                'clients:delete' => 'Delete clients',
                'tasks:read' => 'Read tasks',
                'tasks:write' => 'Create/update tasks',
                'tasks:delete' => 'Delete tasks',
                'reports:read' => 'Read reports/analytics',
                'billing:read' => 'Read billing info',
                'billing:write' => 'Manage billing',
                'notifications:read' => 'Read notifications',
                'notifications:write' => 'Manage notifications',
                'users:read' => 'Read users',
                'users:write' => 'Manage users',
                'webhooks:read' => 'Read webhooks',
                'webhooks:write' => 'Manage webhooks',
            ],
        ]);
    }
}
