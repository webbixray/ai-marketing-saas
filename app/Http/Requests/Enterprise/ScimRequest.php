<?php

namespace App\Http\Requests\Enterprise;

use Illuminate\Foundation\Http\FormRequest;

class ScimRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Check for SCIM bearer token authentication
        $token = $this->bearerToken();
        $config = config('enterprise.scim');
        
        if (!$config || !$config['enabled']) {
            return false;
        }
        
        return hash_equals($config['bearer_token'] ?? '', $token ?? '');
    }

    public function rules(): array
    {
        $method = $this->method();
        
        if ($method === 'POST') {
            return $this->createRules();
        }
        
        if (in_array($method, ['PUT', 'PATCH'])) {
            return $this->updateRules();
        }
        
        return [];
    }

    protected function createRules(): array
    {
        return [
            'schemas' => 'required|array',
            'schemas.*' => 'string',
            'userName' => 'required|string|email|max:255',
            'name' => 'nullable|array',
            'name.givenName' => 'nullable|string|max:100',
            'name.familyName' => 'nullable|string|max:100',
            'name.formatted' => 'nullable|string|max:255',
            'displayName' => 'nullable|string|max:255',
            'emails' => 'required|array|min:1',
            'emails.*.value' => 'required|email',
            'emails.*.type' => 'nullable|in:work,home,other',
            'emails.*.primary' => 'nullable|boolean',
            'phoneNumbers' => 'nullable|array',
            'phoneNumbers.*.value' => 'nullable|string',
            'phoneNumbers.*.type' => 'nullable|in:work,home,mobile,fax,pager,other',
            'phoneNumbers.*.primary' => 'nullable|boolean',
            'addresses' => 'nullable|array',
            'addresses.*.formatted' => 'nullable|string',
            'addresses.*.streetAddress' => 'nullable|string',
            'addresses.*.locality' => 'nullable|string',
            'addresses.*.region' => 'nullable|string',
            'addresses.*.postalCode' => 'nullable|string',
            'addresses.*.country' => 'nullable|string',
            'addresses.*.type' => 'nullable|in:work,home,other',
            'addresses.*.primary' => 'nullable|boolean',
            'active' => 'boolean',
            'groups' => 'nullable|array',
            'groups.*.value' => 'nullable|string',
            'groups.*.type' => 'nullable|in:direct,indirect',
            'roles' => 'nullable|array',
            'roles.*.value' => 'nullable|string',
            'urn:ietf:params:scim:schemas:extension:enterprise:2.0:User' => 'nullable|array',
            'urn:ietf:params:scim:schemas:extension:enterprise:2.0:User.employeeNumber' => 'nullable|string|max:50',
            'urn:ietf:params:scim:schemas:extension:enterprise:2.0:User.costCenter' => 'nullable|string|max:100',
            'urn:ietf:params:scim:schemas:extension:enterprise:2.0:User.organization' => 'nullable|string|max:255',
            'urn:ietf:params:scim:schemas:extension:enterprise:2.0:User.division' => 'nullable|string|max:100',
            'urn:ietf:params:scim:schemas:extension:enterprise:2.0:User.department' => 'nullable|string|max:100',
            'urn:ietf:params:scim:schemas:extension:enterprise:2.0:User.manager' => 'nullable|array',
            'urn:ietf:params:scim:schemas:extension:enterprise:2.0:User.manager.value' => 'nullable|string',
            'urn:ietf:params:scim:schemas:extension:enterprise:2.0:User.manager.displayName' => 'nullable|string',
        ];
    }

    protected function updateRules(): array
    {
        // For PATCH, we expect Operations array
        if ($this->method() === 'PATCH') {
            return [
                'schemas' => 'required|array',
                'Operations' => 'required|array|min:1',
                'Operations.*.op' => 'required|in:add,replace,remove',
                'Operations.*.path' => 'required|string',
                'Operations.*.value' => 'nullable',
            ];
        }
        
        // For PUT, same as create but all optional except userName
        return array_merge($this->createRules(), [
            'userName' => 'required|string|email|max:255',
        ]);
    }
}
