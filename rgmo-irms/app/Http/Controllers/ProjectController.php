<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\User;
use App\Support\InputRules;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Project::class);

        $filters = $request->validate(InputRules::projectFilters());
        $projects = Project::query()
            ->with('managers')
            ->withCount(['resourceUsages' => fn ($query) => $query->visibleTo(auth()->user())])
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

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create', Project::class);

        return view('projects.create', [
            'project' => new Project(['status' => Project::STATUS_ACTIVE]),
            'managers' => $this->managerOptions(),
            'requesters' => $this->requesterOptions(),
            'statuses' => Project::statuses(),
            'selectedManagers' => [],
            'selectedRequesters' => [],
        ]);
    }

    /**
     * Store a newly created resource.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Project::class);

        $validated = $this->validatedProject($request);
        $managerIds = $validated['manager_ids'] ?? [];
        $requesterIds = $validated['requester_ids'] ?? [];
        unset($validated['manager_ids'], $validated['requester_ids']);

        $project = Project::create($validated);
        $this->syncParticipants($project, $managerIds, $requesterIds);

        return redirect()->route('projects.show', $project)->with('success', 'Project created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Project $project, \App\Services\ProjectInventoryService $inventory)
    {
        $this->authorize('view', $project);

        $project->load([
            'managers', 'requesters',
            'resourceUsages' => fn ($query) => $query->visibleTo(auth()->user()),
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
            'projectInventory' => $inventory->balances($project, auth()->user()),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Project $project)
    {
        $this->authorize('update', $project);

        return view('projects.edit', [
            'project' => $project->load('managers', 'requesters'),
            'managers' => $this->managerOptions(),
            'requesters' => $this->requesterOptions(),
            'statuses' => Project::statuses(),
            'selectedManagers' => $project->managers->pluck('id')->all(),
            'selectedRequesters' => $project->requesters->pluck('id')->all(),
        ]);
    }

    /**
     * Update the specified resource.
     */
    public function update(Request $request, Project $project)
    {
        $this->authorize('update', $project);

        $validated = $this->validatedProject($request, $project);
        $managerIds = $validated['manager_ids'] ?? [];
        $requesterIds = $validated['requester_ids'] ?? [];
        unset($validated['manager_ids'], $validated['requester_ids']);

        $project->update($validated);
        $this->syncParticipants($project, $managerIds, $requesterIds);

        return redirect()->route('projects.show', $project)->with('success', 'Project updated successfully.');
    }

    /**
     * Remove the specified resource.
     */
    public function destroy(Project $project)
    {
        $this->authorize('delete', $project);

        $project->delete();

        return redirect()->route('projects.index')->with('success', 'Project archived successfully.');
    }

    /**
     * Handle validated project.
     */
    private function validatedProject(Request $request, ?Project $project = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'not_regex:/<[^>]*>/', Rule::unique('projects', 'code')->ignore($project)],
            'status' => ['required', Rule::in(Project::statuses())],
            'start_date' => ['nullable', 'date', 'after_or_equal:0001-01-01', 'before_or_equal:9999-12-31'],
            'end_date' => ['nullable', 'date', 'after_or_equal:0001-01-01', 'before_or_equal:9999-12-31', Rule::when($request->filled('start_date'), 'after_or_equal:start_date')],
            'description' => ['nullable', 'string', 'max:5000'],
            'manager_ids' => ['nullable', 'array', 'max:100'],
            'manager_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('users', 'id')->where(function ($query) {
                    $query->where('role', User::ROLE_PROJECT_MANAGER)
                        ->where('status', User::STATUS_ACTIVE);
                }),
            ],
            'requester_ids' => ['nullable', 'array', 'max:100'],
            'requester_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')->where(fn ($query) => $query->where('status', User::STATUS_ACTIVE)->whereIn('role', [User::ROLE_STAFF, User::ROLE_PROJECT_MANAGER]))],
        ]);
    }

    /**
     * Handle manager options.
     */
    private function managerOptions()
    {
        return User::query()
            ->where('role', User::ROLE_PROJECT_MANAGER)
            ->where('status', User::STATUS_ACTIVE)
            ->orderBy('name')
            ->get();
    }

    private function requesterOptions()
    {
        return User::query()->whereIn('role', [User::ROLE_STAFF, User::ROLE_PROJECT_MANAGER])
            ->where('status', User::STATUS_ACTIVE)->orderBy('name')->get();
    }

    private function syncParticipants(Project $project, array $managerIds, array $requesterIds): void
    {
        $participants = collect($requesterIds)->mapWithKeys(fn ($id) => [(int) $id => ['role' => 'requester']])->all();
        foreach ($managerIds as $id) {
            $participants[(int) $id] = ['role' => 'manager'];
        }
        $project->participants()->sync($participants);
    }
}
