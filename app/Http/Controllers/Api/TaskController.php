<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * @group Tasks
 *
 * APIs for managing tasks and work items.
 */
class TaskController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Check if user has permission to view tasks
        if (!$request->user()->can('view tasks')) {
            return response()->json([
                'message' => 'Unauthorized: Insufficient permissions'
            ], 403);
        }

        $tasks = Task::with(['assignedUser'])
            ->when($request->filled('status'), function ($query) use ($request) {
                return $query->where('status', $request->input('status'));
            })
            ->when($request->filled('priority'), function ($query) use ($request) {
                return $query->where('priority', $request->input('priority'));
            })
            ->when($request->filled('assigned_to'), function ($query) use ($request) {
                return $query->where('assigned_to', $request->input('assigned_to'));
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->input('search');
                return $query->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            })
            ->orderBy($request->input('sort_by', 'created_at'), 
                     $request->input('sort_order', 'desc'))
            ->paginate($request->input('per_page', 15));

        return response()->json($tasks);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Check if user has permission to create tasks
        if (!$request->user()->can('create tasks')) {
            return response()->json([
                'message' => 'Unauthorized: Insufficient permissions'
            ], 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => ['required', 'string', Rule::in(['todo', 'in_progress', 'review', 'completed'])],
            'priority' => ['required', 'string', Rule::in(['low', 'medium', 'high', 'urgent'])],
            'due_date' => 'nullable|date',
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        // Ensure the task belongs to the current tenant
        $validated['tenant_id'] = $request->user()->tenant_id;

        $task = Task::create($validated);

        return response()->json($task->load('assignedUser'), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        // Check if user has permission to view tasks
        if (!auth()->user()->can('view tasks')) {
            return response()->json([
                'message' => 'Unauthorized: Insufficient permissions'
            ], 403);
        }

        $task = Task::with('assignedUser')->findOrFail($id);

        return response()->json($task);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        // Check if user has permission to update tasks
        if (!$request->user()->can('update tasks')) {
            return response()->json([
                'message' => 'Unauthorized: Insufficient permissions'
            ], 403);
        }

        $task = Task::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'status' => ['sometimes', 'required', 'string', Rule::in(['todo', 'in_progress', 'review', 'completed'])],
            'priority' => ['sometimes', 'required', 'string', Rule::in(['low', 'medium', 'high', 'urgent'])],
            'due_date' => 'nullable|date',
            'assigned_to' => 'nullable|exists:users,id',
            'completed_at' => 'nullable|date',
        ]);

        // Ensure the task belongs to the current tenant (security check)
        $validated['tenant_id'] = $request->user()->tenant_id;

        $task->update($validated);

        return response()->json($task->load('assignedUser'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        // Check if user has permission to delete tasks
        if (!auth()->user()->can('delete tasks')) {
            return response()->json([
                'message' => 'Unauthorized: Insufficient permissions'
            ], 403);
        }

        $task = Task::findOrFail($id);
        $task->delete();

        return response()->json(null, 204);
    }
}