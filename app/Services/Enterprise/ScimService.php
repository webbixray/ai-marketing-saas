<?php

namespace App\Services\Enterprise;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;

class ScimService
{
    protected array $config;
    protected Tenant $tenant;

    public function __construct(Tenant $tenant, array $config = [])
    {
        $this->tenant = $tenant;
        $this->config = array_merge([
            'enabled' => false,
            'base_url' => '/scim/v2',
            'bearer_token' => '',
            'schemas' => [
                'user' => 'urn:ietf:params:scim:schemas:core:2.0:User',
                'group' => 'urn:ietf:params:scim:schemas:core:2.0:Group',
            ],
            'attributes' => [
                'user' => [
                    'id',
                    'externalId',
                    'userName',
                    'name' => ['givenName', 'familyName', 'formatted'],
                    'displayName',
                    'emails' => ['value', 'type', 'primary'],
                    'phoneNumbers' => ['value', 'type', 'primary'],
                    'addresses' => ['formatted', 'streetAddress', 'locality', 'region', 'postalCode', 'country', 'type', 'primary'],
                    'active',
                    'groups' => ['value', 'display', 'type'],
                    'roles' => ['value', 'display', 'type'],
                    'entitlements' => ['value', 'type', 'primary'],
                    'locale',
                    'preferredLanguage',
                    'timezone',
                    'urn:ietf:params:scim:schemas:extension:enterprise:2.0:User' => [
                        'employeeNumber',
                        'costCenter',
                        'organization',
                        'division',
                        'department',
                        'manager' => ['value', 'displayName'],
                    ],
                ],
            ],
            'filter_attributes' => ['userName', 'externalId', 'id', 'emails.value', 'groups.value'],
            'page_size' => 100,
            'sort_order' => 'asc',
        ], []);
    }

    /**
     * Check if SCIM is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->config['enabled'] && !empty($this->config['bearer_token']);
    }

    /**
     * Get service provider configuration.
     */
    public function getServiceProviderConfig(): array
    {
        return [
            'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:ServiceProviderConfig'],
            'patch' => ['supported' => true],
            'bulk' => ['supported' => true, 'maxOperations' => 1000, 'maxPayloadSize' => 1048576],
            'filter' => ['supported' => true, 'maxResults' => $this->config['page_size']],
            'changePassword' => ['supported' => true],
            'sort' => ['supported' => true],
            'etag' => ['supported' => true],
            'authenticationSchemes' => [
                [
                    'name' => 'Bearer Token',
                    'description' => 'Authentication using Bearer Token',
                    'specUrl' => 'http://www.rfc-editor.org/info/rfc6750',
                    'documentationUrl' => '',
                    'type' => 'oauthbearertoken',
                    'primary' => true,
                ],
            ],
        ];
    }

    /**
     * Get resource types.
     */
    public function getResourceTypes(): array
    {
        return [
            [
                'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:ResourceType'],
                'id' => 'User',
                'name' => 'User',
                'endpoint' => '/Users',
                'schema' => 'urn:ietf:params:scim:schemas:core:2.0:User',
                'schemaExtensions' => [
                    [
                        'schema' => 'urn:ietf:params:scim:schemas:extension:enterprise:2.0:User',
                        'required' => false,
                    ],
                ],
            ],
            [
                'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:ResourceType'],
                'id' => 'Group',
                'name' => 'Group',
                'endpoint' => '/Groups',
                'schema' => 'urn:ietf:params:scim:schemas:core:2.0:Group',
            ],
        ];
    }

    /**
     * Get schemas.
     */
    public function getSchemas(): array
    {
        return [
            [
                'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:Schema'],
                'id' => 'urn:ietf:params:scim:schemas:core:2.0:User',
                'name' => 'User',
                'description' => 'User Account',
                'attributes' => $this->getUserAttributes(),
            ],
            [
                'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:Schema'],
                'id' => 'urn:ietf:params:scim:schemas:core:2.0:Group',
                'name' => 'Group',
                'description' => 'Group',
                'attributes' => [
                    [
                        'name' => 'displayName',
                        'type' => 'string',
                        'required' => true,
                        'uniqueness' => 'server',
                        'description' => 'Group display name',
                    ],
                    [
                        'name' => 'members',
                        'type' => 'complex',
                        'multiValued' => true,
                        'description' => 'Group members',
                        'subAttributes' => [
                            ['name' => 'value', 'type' => 'string'],
                            ['name' => 'display', 'type' => 'string', 'mutability' => 'readOnly'],
                            ['name' => 'type', 'type' => 'string', 'enum' => ['User', 'Group']],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Get user attributes for schema.
     */
    protected function getUserAttributes(): array
    {
        return [
            [
                'name' => 'userName',
                'type' => 'string',
                'required' => true,
                'uniqueness' => 'server',
                'caseExact' => false,
                'mutability' => 'readWrite',
                'returned' => 'default',
                'description' => 'Unique identifier for the User',
            ],
            [
                'name' => 'externalId',
                'type' => 'string',
                'required' => false,
                'uniqueness' => 'server',
                'caseExact' => false,
                'mutability' => 'readWrite',
                'returned' => 'default',
                'description' => 'External identifier',
            ],
            [
                'name' => 'userName',
                'type' => 'string',
                'required' => true,
                'uniqueness' => 'server',
                'caseExact' => false,
                'mutability' => 'readWrite',
                'returned' => 'default',
            ],
            [
                'name' => 'name',
                'type' => 'complex',
                'multiValued' => false,
                'required' => false,
                'subAttributes' => [
                    ['name' => 'givenName', 'type' => 'string'],
                    ['name' => 'familyName', 'type' => 'string'],
                    ['name' => 'formatted', 'type' => 'string', 'mutability' => 'readOnly'],
                ],
            ],
            [
                'name' => 'displayName',
                'type' => 'string',
                'required' => false,
                'mutability' => 'readWrite',
                'returned' => 'default',
            ],
            [
                'name' => 'emails',
                'type' => 'complex',
                'multiValued' => true,
                'required' => true,
                'subAttributes' => [
                    ['name' => 'value', 'type' => 'string'],
                    ['name' => 'type', 'type' => 'string', 'enum' => ['work', 'home', 'other']],
                    ['name' => 'primary', 'type' => 'boolean'],
                ],
            ],
            [
                'name' => 'phoneNumbers',
                'type' => 'complex',
                'multiValued' => true,
                'subAttributes' => [
                    ['name' => 'value', 'type' => 'string'],
                    ['name' => 'type', 'type' => 'string', 'enum' => ['work', 'home', 'mobile', 'fax', 'pager', 'other']],
                    ['name' => 'primary', 'type' => 'boolean'],
                ],
            ],
            [
                'name' => 'addresses',
                'type' => 'complex',
                'multiValued' => true,
                'subAttributes' => [
                    ['name' => 'formatted', 'type' => 'string'],
                    ['name' => 'streetAddress', 'type' => 'string'],
                    ['name' => 'locality', 'type' => 'string'],
                    ['name' => 'region', 'type' => 'string'],
                    ['name' => 'postalCode', 'type' => 'string'],
                    ['name' => 'country', 'type' => 'string'],
                    ['name' => 'type', 'type' => 'string', 'enum' => ['work', 'home', 'other']],
                    ['name' => 'primary', 'type' => 'boolean'],
                ],
            ],
            [
                'name' => 'active',
                'type' => 'boolean',
                'required' => true,
                'mutability' => 'readWrite',
                'returned' => 'default',
            ],
            [
                'name' => 'groups',
                'type' => 'complex',
                'multiValued' => true,
                'subAttributes' => [
                    ['name' => 'value', 'type' => 'string'],
                    ['name' => 'display', 'type' => 'string', 'mutability' => 'readOnly'],
                    ['name' => 'type', 'type' => 'string', 'enum' => ['direct', 'indirect']],
                ],
            ],
            [
                'name' => 'roles',
                'type' => 'complex',
                'multiValued' => true,
                'subAttributes' => [
                    ['name' => 'value', 'type' => 'string'],
                    ['name' => 'display', 'type' => 'string', 'mutability' => 'readOnly'],
                    ['name' => 'type', 'type' => 'string'],
                ],
            ],
            [
                'name' => 'entitlements',
                'type' => 'complex',
                'multiValued' => true,
                'subAttributes' => [
                    ['name' => 'value', 'type' => 'string'],
                    ['name' => 'type', 'type' => 'string'],
                    ['name' => 'primary', 'type' => 'boolean'],
                ],
            ],
            [
                'name' => 'locale',
                'type' => 'string',
            ],
            [
                'name' => 'preferredLanguage',
                'type' => 'string',
            ],
            [
                'name' => 'timezone',
                'type' => 'string',
            ],
        ];
    }

    /**
     * List users with pagination and filtering.
     */
    public function listUsers(array $params = []): array
    {
        $startIndex = max(1, (int)($params['startIndex'] ?? 1));
        $count = min((int)($params['count'] ?? $this->config['page_size']), 1000);
        $filter = $params['filter'] ?? null;
        $attributes = $params['attributes'] ?? null;
        $excludedAttributes = $params['excludedAttributes'] ?? null;
        $sortBy = $params['sortBy'] ?? 'userName';
        $sortOrder = $params['sortOrder'] ?? 'asc';

        $query = User::where('tenant_id', $this->tenant->id);

        // Apply filter
        if ($filter) {
            $query = $this->applyFilter($query, $filter);
        }

        // Apply sorting
        $query->orderBy($sortBy, $sortOrder);

        // Get total count
        $totalResults = $query->count();

        // Apply pagination
        $offset = $startIndex - 1;
        $users = $query->skip($offset)->take($count)->get();

        // Transform to SCIM format
        $resources = $users->map(function ($user) use ($attributes, $excludedAttributes) {
            return $this->transformUser($user, $attributes, $excludedAttributes);
        })->toArray();

        return [
            'schemas' => ['urn:ietf:params:scim:api:messages:2.0:ListResponse'],
            'totalResults' => $totalResults,
            'startIndex' => $startIndex,
            'itemsPerPage' => $count,
            'Resources' => $resources,
        ];
    }

    /**
     * Get a single user by ID.
     */
    public function getUser(string $id): ?array
    {
        $user = User::where('tenant_id', $this->tenant->id)
            ->where('id', $id)
            ->first();

        if (!$user) {
            return null;
        }

        return $this->transformUser($user);
    }

    /**
     * Get user by external ID.
     */
    public function getUserByExternalId(string $externalId): ?array
    {
        $user = User::where('tenant_id', $this->tenant->id)
            ->where('external_id', $externalId)
            ->first();

        if (!$user) {
            return null;
        }

        return $this->transformUser($user);
    }

    /**
     * Create a new user.
     */
    public function createUser(array $data): array
    {
        // Validate required fields
        if (empty($data['userName'])) {
            throw new \InvalidArgumentException('userName is required');
        }

        if (empty($data['emails']) || !is_array($data['emails'])) {
            throw new \InvalidArgumentException('At least one email is required');
        }

        $primaryEmail = collect($data['emails'])->firstWhere('primary', true)['value'] ?? $data['emails'][0]['value'] ?? null;
        if (!$primaryEmail) {
            throw new \InvalidArgumentException('Primary email is required');
        }

        // Check if user already exists
        $existing = User::where('tenant_id', $this->tenant->id)
            ->where('email', $primaryEmail)
            ->first();

        if ($existing) {
            throw new \Exception('User with this email already exists', 409);
        }

        // Create user
        $user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => $data['name']['formatted'] ?? ($data['name']['givenName'] ?? '') . ' ' . ($data['name']['familyName'] ?? ''),
            'email' => $primaryEmail,
            'external_id' => $data['externalId'] ?? null,
            'password' => Hash::make(Str::random(32)),
            'active' => $data['active'] ?? true,
        ]);

        // Assign groups if provided
        if (!empty($data['groups'])) {
            foreach ($data['groups'] as $group) {
                $team = Team::where('tenant_id', $this->tenant->id)
                    ->where('external_id', $group['value'] ?? $group['display'])
                    ->first();
                if ($team) {
                    $user->teams()->attach($team->id);
                }
            }
        }

        // Handle enterprise extension
        if (isset($data['urn:ietf:params:scim:schemas:extension:enterprise:2.0:User'])) {
            $enterprise = $data['urn:ietf:params:scim:schemas:extension:enterprise:2.0:User'];
            $user->update([
                'employee_number' => $enterprise['employeeNumber'] ?? null,
                'department' => $enterprise['department'] ?? null,
                'division' => $enterprise['division'] ?? null,
                'cost_center' => $enterprise['costCenter'] ?? null,
            ]);
        }

        return $this->transformUser($user->fresh());
    }

    /**
     * Update a user (PUT - full replace).
     */
    public function replaceUser(string $id, array $data): array
    {
        $user = User::where('tenant_id', $this->tenant->id)
            ->where('id', $id)
            ->firstOrFail();

        $this->updateUser($user, $data, true);
        return $this->transformUser($user->fresh());
    }

    /**
     * Partially update a user (PATCH).
     */
    public function patchUser(string $id, array $operations): array
    {
        $user = User::where('tenant_id', $this->tenant->id)
            ->where('id', $id)
            ->firstOrFail();

        foreach ($operations as $op) {
            $this->applyPatchOperation($user, $op);
        }

        $user->save();
        return $this->transformUser($user);
    }

    /**
     * Delete a user.
     */
    public function deleteUser(string $id): bool
    {
        $user = User::where('tenant_id', $this->tenant->id)
            ->where('id', $id)
            ->firstOrFail();

        $user->delete();
        return true;
    }

    /**
     * List groups.
     */
    public function listGroups(array $params = []): array
    {
        $startIndex = max(1, (int)($params['startIndex'] ?? 1));
        $count = min((int)($params['count'] ?? $this->config['page_size']), 1000);

        $query = Team::where('tenant_id', $this->tenant->id);
        $totalResults = $query->count();
        $groups = $query->skip($startIndex - 1)->take($count)->get();

        $resources = $groups->map(function ($group) {
            return $this->transformGroup($group);
        })->toArray();

        return [
            'schemas' => ['urn:ietf:params:scim:api:messages:2.0:ListResponse'],
            'totalResults' => $totalResults,
            'startIndex' => $startIndex,
            'itemsPerPage' => $count,
            'Resources' => $resources,
        ];
    }

    /**
     * Get a single group.
     */
    public function getGroup(string $id): ?array
    {
        $group = Team::where('tenant_id', $this->tenant->id)
            ->where('id', $id)
            ->first();

        if (!$group) {
            return null;
        }

        return $this->transformGroup($group);
    }

    /**
     * Create a group.
     */
    public function createGroup(array $data): array
    {
        if (empty($data['displayName'])) {
            throw new \InvalidArgumentException('displayName is required');
        }

        $group = Team::create([
            'tenant_id' => $this->tenant->id,
            'name' => $data['displayName'],
            'description' => $data['description'] ?? '',
            'external_id' => $data['externalId'] ?? null,
        ]);

        // Add members if provided
        if (!empty($data['members'])) {
            foreach ($data['members'] as $member) {
                $user = User::where('tenant_id', $this->tenant->id)
                    ->where('id', $member['value'])
                    ->first();
                if ($user) {
                    $user->teams()->attach($group->id);
                }
            }
        }

        return $this->transformGroup($group->fresh());
    }

    /**
     * Update a group.
     */
    public function replaceGroup(string $id, array $data): array
    {
        $group = Team::where('tenant_id', $this->tenant->id)
            ->where('id', $id)
            ->firstOrFail();

        $group->update([
            'name' => $data['displayName'] ?? $group->name,
            'description' => $data['description'] ?? $group->description,
        ]);

        // Update members if provided
        if (isset($data['members'])) {
            $this->syncGroupMembers($group, $data['members']);
        }

        return $this->transformGroup($group->fresh());
    }

    /**
     * Delete a group.
     */
    public function deleteGroup(string $id): bool
    {
        $group = Team::where('tenant_id', $this->tenant->id)
            ->where('id', $id)
            ->firstOrFail();

        $group->delete();
        return true;
    }

    /**
     * Bulk operations.
     */
    public function bulkOperations(array $operations): array
    {
        $results = [];

        foreach ($operations as $op) {
            try {
                $result = match ($op['method']) {
                    'POST' => $this->handleBulkCreate($op),
                    'PUT' => $this->handleBulkUpdate($op),
                    'PATCH' => $this->handleBulkPatch($op),
                    'DELETE' => $this->handleBulkDelete($op),
                    default => ['status' => '400', 'error' => 'Unsupported method'],
                };
                $results[] = array_merge($op, $result);
            } catch (\Throwable $e) {
                $results[] = array_merge($op, [
                    'status' => '500',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'schemas' => ['urn:ietf:params:scim:api:messages:2.0:BulkResponse'],
            'Operations' => $results,
        ];
    }

    /**
     * Transform user to SCIM format.
     */
    protected function transformUser(User $user, ?array $attributes = null, ?array $excludedAttributes = null): array
    {
        $userResource = [
            'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:User'],
            'id' => $user->id,
            'externalId' => $user->external_id,
            'userName' => $user->email,
            'name' => [
                'givenName' => $user->first_name ?? '',
                'familyName' => $user->last_name ?? '',
                'formatted' => $user->name,
            ],
            'displayName' => $user->name,
            'emails' => [
                [
                    'value' => $user->email,
                    'type' => 'work',
                    'primary' => true,
                ],
            ],
            'active' => $user->active ?? true,
            'meta' => [
                'resourceType' => 'User',
                'created' => $user->created_at->toISOString(),
                'lastModified' => $user->updated_at->toISOString(),
                'location' => config('app.url') . "/scim/v2/Users/{$user->id}",
            ],
        ];

        // Add phone numbers
        if ($user->phone || $user->mobile) {
            $phoneNumbers = [];
            if ($user->phone) {
                $phoneNumbers[] = ['value' => $user->phone, 'type' => 'work', 'primary' => true];
            }
            if ($user->mobile) {
                $phoneNumbers[] = ['value' => $user->mobile, 'type' => 'mobile'];
            }
            $userResource['phoneNumbers'] = $phoneNumbers;
        }

        // Add addresses
        if (!empty($user->address) || !empty($user->city) || !empty($user->state) || !empty($user->postal_code) || !empty($user->country)) {
            $userResource['addresses'] = [[
                'formatted' => trim(($user->address ?? '') . ', ' . ($user->city ?? '') . ', ' . ($user->state ?? '') . ' ' . ($user->postal_code ?? '') . ', ' . ($user->country ?? ''), ', '),
                'streetAddress' => $user->address,
                'locality' => $user->city,
                'region' => $user->state,
                'postalCode' => $user->postal_code,
                'country' => $user->country,
                'type' => 'work',
                'primary' => true,
            ]];
        }

        // Add groups (teams() relation only exists when the teams feature
        // schema is present; guard so provisioning reads never 500)
        if (method_exists($user, 'teams')) {
            $groups = $user->teams()->get()->map(function ($team) {
                return [
                    'value' => $team->id,
                    'display' => $team->name,
                    'type' => 'direct',
                ];
            });
            if (!empty($groups)) {
                $userResource['groups'] = $groups->toArray();
            }
        }

        // Add roles
        $roles = $user->getRoleNames()->map(function ($role) {
            return [
                'value' => $role,
                'display' => $role,
                'type' => 'role',
            ];
        });
        if (!empty($roles)) {
            $userResource['roles'] = $roles->toArray();
        }

        // Enterprise extension
        if ($this->hasEnterpriseExtension()) {
            $userResource['urn:ietf:params:scim:schemas:extension:enterprise:2.0:User'] = [
                'employeeNumber' => $user->employee_number,
                'costCenter' => $user->cost_center,
                'organization' => $this->tenant->name,
                'division' => $user->division,
                'department' => $user->department,
            ];
        }

        // Apply attribute filtering
        return $this->filterAttributes($userResource, $attributes, $excludedAttributes);
    }

    /**
     * Transform group to SCIM format.
     */
    protected function transformGroup(\App\Models\Team $group): array
    {
        $members = $group->users()->get()->map(function ($user) {
            return [
                'value' => $user->id,
                'display' => $user->name,
                'type' => 'User',
            ];
        });

        return [
            'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:Group'],
            'id' => $group->id,
            'externalId' => $group->external_id,
            'displayName' => $group->name,
            'members' => $members->toArray(),
            'meta' => [
                'resourceType' => 'Group',
                'created' => $group->created_at->toISOString(),
                'lastModified' => $group->updated_at->toISOString(),
                'location' => config('app.url') . "/scim/v2/Groups/{$group->id}",
            ],
        ];
    }

    /**
     * Apply SCIM filter.
     */
    protected function applyFilter($query, string $filter)
    {
        // Simple filter parsing: attr operator value
        // Supported: eq, co, sw, ew, pr, gt, ge, lt, le
        // Example: userName eq "john" and emails.value co "example.com"
        
        // This is a simplified implementation
        // In production, use a proper SCIM filter parser
        $filter = trim($filter);
        
        if (preg_match('/(\w+(?:\.\w+)*)\s+(eq|co|sw|ew|pr|gt|ge|lt|le)\s+"([^"]+)"/', $filter, $matches)) {
            [$full, $attr, $op, $value] = $matches;
            
            $column = $this->mapAttributeToColumn($attr);
            if (!$column) return $query;

            return match ($op) {
                'eq' => $query->where($column, $value),
                'co' => $query->where($column, 'LIKE', "%{$value}%"),
                'sw' => $query->where($column, 'LIKE', "{$value}%"),
                'ew' => $query->where($column, 'LIKE', "%{$value}"),
                'pr' => $query->whereNotNull($column),
                'gt' => $query->where($column, '>', $value),
                'ge' => $query->where($column, '>=', $value),
                'lt' => $query->where($column, '<', $value),
                'le' => $query->where($column, '<=', $value),
                default => $query,
            };
        }

        return $query;
    }

    /**
     * Map SCIM attribute to database column.
     */
    protected function mapAttributeToColumn(string $attr): ?string
    {
        $map = [
            'userName' => 'email',
            'externalId' => 'external_id',
            'displayName' => 'name',
            'givenName' => 'first_name',
            'familyName' => 'last_name',
            'active' => 'active',
            'emails.value' => 'email',
            'id' => 'id',
        ];

        return $map[$attr] ?? null;
    }

    /**
     * Filter attributes based on request.
     */
    protected function filterAttributes(array $resource, ?array $attributes, ?array $excludedAttributes): array
    {
        if ($attributes) {
            $filtered = [];
            foreach ($attributes as $attr) {
                $this->setNestedValue($filtered, $attr, data_get($resource, $attr));
            }
            return $filtered;
        }

        if ($excludedAttributes) {
            foreach ($excludedAttributes as $attr) {
                data_forget($resource, $attr);
            }
        }

        return $resource;
    }

    /**
     * Set nested value in array using dot notation.
     */
    protected function setNestedValue(array &$array, string $path, mixed $value): void
    {
        $keys = explode('.', $path);
        $current = &$array;
        
        foreach ($keys as $i => $key) {
            if ($i === count($keys) - 1) {
                $current[$key] = $value;
            } else {
                if (!isset($current[$key]) || !is_array($current[$key])) {
                    $current[$key] = [];
                }
                $current = &$current[$key];
            }
        }
    }

    /**
     * Apply patch operation.
     */
    protected function applyPatchOperation($user, array $op): void
    {
        $opType = $op['op'] ?? '';
        $path = $op['path'] ?? '';
        $value = $op['value'] ?? null;

        switch ($opType) {
            case 'add':
                $this->setNestedValue($user, $path, $value);
                break;
            case 'replace':
                $this->setNestedValue($user, $path, $value);
                break;
            case 'remove':
                data_forget($user, $path);
                break;
        }
    }

    /**
     * Sync group members.
     */
    protected function syncGroupMembers(Team $group, array $members): void
    {
        $userIds = collect($members)->pluck('value')->filter()->toArray();
        $group->users()->sync($userIds);
    }

    /**
     * Handle bulk create.
     */
    protected function handleBulkCreate(array $op): array
    {
        $data = $op['data'] ?? [];
        if (($op['path'] ?? '') === '/Users') {
            return $this->createUser($data);
        }
        return ['status' => '400', 'error' => 'Unsupported bulk create path'];
    }

    /**
     * Handle bulk update.
     */
    protected function handleBulkUpdate(array $op): array
    {
        $id = $op['path'] ?? '';
        $id = basename($id);
        $data = $op['data'] ?? [];
        return $this->replaceUser($id, $data);
    }

    /**
     * Handle bulk patch.
     */
    protected function handleBulkPatch(array $op): array
    {
        $id = $op['path'] ?? '';
        $id = basename($id);
        $operations = $op['data']['Operations'] ?? [];
        return $this->patchUser($id, $operations);
    }

    /**
     * Handle bulk delete.
     */
    protected function handleBulkDelete(array $op): array
    {
        $id = $op['path'] ?? '';
        $id = basename($id);
        $this->deleteUser($id);
        return ['status' => '204'];
    }

    protected function onInitialize(array $config): void
    {
        // This service doesn't extend BaseAgent, so onInitialize is not used
    }

    protected function onShutdown(): void
    {
    }
}
