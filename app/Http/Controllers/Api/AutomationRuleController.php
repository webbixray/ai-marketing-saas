<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AutomationRule;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * @group Automation
 *
 * APIs for managing automation rules: trigger/condition/action pipelines
 * that react to tenant events (campaign created, low performance, etc.).
 */
class AutomationRuleController extends Controller
{
    /**
     * Display a listing of the automation rules for the current tenant.
     */
    public function index(Request $request)
    {
        $query = AutomationRule::query();

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->filled('trigger')) {
            $query->forTrigger((string) $request->input('trigger'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $rules = $query
            ->orderBy('priority', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return response()->json($rules);
    }

    /**
     * Store a newly created automation rule.
     */
    public function store(Request $request)
    {
        $validated = $request->validate($this->storeRules());

        // Ensure the rule belongs to the current tenant
        $validated['tenant_id'] = $request->user()->tenant_id;
        $validated['created_by'] = $request->user()->id;
        $validated['is_active'] = $validated['is_active'] ?? true;

        $rule = AutomationRule::create($validated);

        return response()->json($rule, 201);
    }

    /**
     * Display the specified automation rule.
     */
    public function show(AutomationRule $rule)
    {
        if (! $rule->exists) {
            abort(404);
        }

        return response()->json($rule->loadMissing(['creator', 'updater']));
    }

    /**
     * Update the specified automation rule.
     */
    public function update(Request $request, AutomationRule $rule)
    {
        if (! $rule->exists) {
            abort(404);
        }

        $validated = $request->validate($this->updateRules());

        $rule->update(array_merge($validated, [
            'updated_by' => $request->user()->id,
        ]));

        return response()->json($rule->fresh());
    }

    /**
     * Remove the specified automation rule (soft delete).
     */
    public function destroy(AutomationRule $rule)
    {
        if (! $rule->exists) {
            abort(404);
        }

        $rule->delete();

        return response()->json(null, 204);
    }

    /**
     * Validation rules for creating a rule.
     */
    private function storeRules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'triggers' => ['required', 'array', 'min:1'],
            'triggers.*' => 'required|string|max:255',
            'trigger_filters' => 'nullable|array',
            'conditions' => ['required', 'array', 'min:1'],
            'condition_logic' => ['required', 'string', Rule::in(['AND', 'OR'])],
            'actions' => ['required', 'array', 'min:1'],
            'actions.*' => 'required|array',
            'priority' => 'nullable|integer|min:0',
            'is_active' => 'sometimes|boolean',
        ];
    }

    /**
     * Validation rules for updating a rule (all fields optional).
     */
    private function updateRules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'triggers' => ['sometimes', 'required', 'array', 'min:1'],
            'triggers.*' => 'required|string|max:255',
            'trigger_filters' => 'nullable|array',
            'conditions' => ['sometimes', 'required', 'array', 'min:1'],
            'condition_logic' => ['sometimes', 'required', 'string', Rule::in(['AND', 'OR'])],
            'actions' => ['sometimes', 'required', 'array', 'min:1'],
            'actions.*' => 'required|array',
            'priority' => 'nullable|integer|min:0',
            'is_active' => 'sometimes|boolean',
        ];
    }
}
