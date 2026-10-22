<?php

namespace App\Http\Requests\Enterprise;

use Illuminate\Foundation\Http\FormRequest;

class SubmitListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->hasPermissionTo('submit_marketplace_listing');
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:200',
            'description' => 'required|string',
            'short_description' => 'nullable|string|max:500',
            'category' => 'required|in:agents,templates,integrations,workflows,reports,creative,scripts,connectors',
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
            'license_type' => 'nullable|in:commercial,mit,apache,gpl,custom',
            'license_text' => 'nullable|string',
            'changelog' => 'nullable|array',
        ];
    }
}
