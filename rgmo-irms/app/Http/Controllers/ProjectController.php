<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Project::class);

        $filters = $request->only(['search', 'status']);
        $projects = Project::query()
            ->with('managers')
            ->withCount('resourceUsages')
            ->search($filters['search'] ?? null)
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('projects.index', [
            'projects' => $projects,
            'filters' => $filters,
            'statuses' => Project::statuses(),
        ]);
    }

    public function create()
    {
        $this->authorize('create', Project::class);

        return view('projects.create', [
            'project' => new Project(['status' => Project::STATUS_ACTIVE]),
            'managers' => $this->managerOptions(),
            'statuses' => Project::statuses(),
            'selectedManagers' => [],
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Project::class);

        $validated = $this->validatedProject($request);
        $managerIds = $validated['manager_ids'] ?? [];
        unset($validated['manager_ids']);

        $project = Project::create($validated);
        $project->managers()->sync($managerIds);

        return redirect()->route('projects.show', $project)->with('success', 'Project created successfully.');
    }

    public function show(Project $project)
    {
        $this->authorize('view', $project);

        $project->load([
            'managers',
            'resourceUsages.item.category',
            'resourceUsages.user',
        ]);

        $resourceSummary = $project->resourceUsages
            ->groupBy('inventory_item_id')
            ->map(function ($usages) {
                $first = $usages->first();

                return [
                    'item' => $first->item,
                    'quantity' => $usages->sum('quantity'),
                    'last_used_at' => $usages->max('created_at'),
                    'usage_count' => $usages->count(),
                ];
            })
            ->values();

        return view('projects.show', [
            'project' => $project,
            'resourceSummary' => $resourceSummary,
        ]);
    }

    public function edit(Project $project)
    {
        $this->authorize('update', $project);

        return view('projects.edit', [
            'project' => $project->load('managers'),
            'managers' => $this->managerOptions(),
            'statuses' => Project::statuses(),
            'selectedManagers' => $project->managers->pluck('id')->all(),
        ]);
    }

    public function update(Request $request, Project $project)
    {
        $this->authorize('update', $project);

        $validated = $this->validatedProject($request, $project);
        $managerIds = $validated['manager_ids'] ?? [];
        unset($validated['manager_ids']);

        $project->update($validated);
        $project->managers()->sync($managerIds);

        return redirect()->route('projects.show', $project)->with('success', 'Project updated successfully.');
    }

    public function destroy(Project $project)
    {
        $this->authorize('delete', $project);

        $project->delete();

        return redirect()->route('projects.index')->with('success', 'Project archived successfully.');
    }

    private function validatedProject(Request $request, ?Project $project = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', Rule::unique('projects', 'code')->ignore($project)],
            'status' => ['required', Rule::in(Project::statuses())],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'description' => ['nullable', 'string'],
            'manager_ids' => ['nullable', 'array'],
            'manager_ids.*' => [
                'integer',
                Rule::exists('users', 'id')->where(function ($query) {
                    $query->whereIn('role', [User::ROLE_PROJECT_MANAGER, User::ROLE_FIELD_PERSONNEL])
                        ->where('status', User::STATUS_ACTIVE);
                }),
            ],
        ]);
    }

    private function managerOptions()
    {
        return User::query()
            ->whereIn('role', [User::ROLE_PROJECT_MANAGER, User::ROLE_FIELD_PERSONNEL])
            ->where('status', User::STATUS_ACTIVE)
            ->orderBy('name')
            ->get();
    }
}
