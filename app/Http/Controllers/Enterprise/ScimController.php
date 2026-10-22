<?php

namespace App\Http\Controllers\Enterprise;

use App\Http\Controllers\Controller;
use App\Http\Requests\Enterprise\ScimRequest;
use App\Services\Enterprise\ScimService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ScimController extends Controller
{
    protected ScimService $scimService;

    public function __construct(ScimService $scimService)
    {
        $this->scimService = $scimService;
    }

    /**
     * Get SCIM service provider configuration.
     */
    public function serviceProviderConfig(): JsonResponse
    {
        return response()->json($this->scimService->getServiceProviderConfig());
    }

    /**
     * Get resource types.
     */
    public function resourceTypes(): JsonResponse
    {
        return response()->json($this->scimService->getResourceTypes());
    }

    /**
     * Get schemas.
     */
    public function schemas(): JsonResponse
    {
        return response()->json($this->scimService->getSchemas());
    }

    /**
     * List users.
     */
    public function listUsers(Request $request): JsonResponse
    {
        $params = $request->only(['startIndex', 'count', 'filter', 'attributes', 'excludedAttributes', 'sortBy', 'sortOrder']);
        
        return response()->json($this->scimService->listUsers($params));
    }

    /**
     * Get a single user.
     */
    public function getUser(string $id): JsonResponse
    {
        $user = $this->scimService->getUser($id);
        
        if (!$user) {
            return response()->json([
                'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
                'status' => 404,
                'detail' => 'User not found',
            ], 404);
        }

        return response()->json($user);
    }

    /**
     * Get user by external ID.
     */
    public function getUserByExternalId(string $externalId): JsonResponse
    {
        $user = $this->scimService->getUserByExternalId($externalId);
        
        if (!$user) {
            return response()->json([
                'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
                'status' => 404,
                'detail' => 'User not found',
            ], 404);
        }

        return response()->json($user);
    }

    /**
     * Create a new user.
     */
    public function createUser(ScimRequest $request): JsonResponse
    {
        $user = $this->scimService->createUser($request->validated());
        
        return response()->json($user, 201);
    }

    /**
     * Update a user (PUT - full replace).
     */
    public function replaceUser(string $id, ScimRequest $request): JsonResponse
    {
        $user = $this->scimService->replaceUser($id, $request->validated());
        
        return response()->json($user);
    }

    /**
     * Partially update a user (PATCH).
     */
    public function patchUser(string $id, ScimRequest $request): JsonResponse
    {
        $user = $this->scimService->patchUser($id, $request->validated());
        
        return response()->json($user);
    }

    /**
     * Delete a user.
     */
    public function deleteUser(string $id): JsonResponse
    {
        $this->scimService->deleteUser($id);
        
        return response()->json(null, 204);
    }

    /**
     * List groups.
     */
    public function listGroups(Request $request): JsonResponse
    {
        $params = $request->only(['startIndex', 'count']);
        
        return response()->json($this->scimService->listGroups($params));
    }

    /**
     * Get a single group.
     */
    public function getGroup(string $id): JsonResponse
    {
        $group = $this->scimService->getGroup($id);
        
        if (!$group) {
            return response()->json([
                'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
                'status' => 404,
                'detail' => 'Group not found',
            ], 404);
        }

        return response()->json($group);
    }

    /**
     * Create a group.
     */
    public function createGroup(ScimRequest $request): JsonResponse
    {
        $group = $this->scimService->createGroup($request->validated());
        
        return response()->json($group, 201);
    }

    /**
     * Update a group (PUT).
     */
    public function replaceGroup(string $id, ScimRequest $request): JsonResponse
    {
        $group = $this->scimService->replaceGroup($id, $request->validated());
        
        return response()->json($group);
    }

    /**
     * Delete a group.
     */
    public function deleteGroup(string $id): JsonResponse
    {
        $this->scimService->deleteGroup($id);
        
        return response()->json(null, 204);
    }

    /**
     * Bulk operations.
     */
    public function bulkOperations(Request $request): JsonResponse
    {
        $operations = $request->input('Operations', []);
        
        return response()->json($this->scimService->bulkOperations($operations));
    }
}
