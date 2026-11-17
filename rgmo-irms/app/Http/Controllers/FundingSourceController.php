<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\FundingSource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FundingSourceController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', FundingSource::class);
        $filters = $request->validate([
            'search' => 'nullable|string|max:255',
            'status' => ['nullable', Rule::in(FundingSource::statuses())],
        ]);
        $fundingSources = FundingSource::query()
            ->search($filters['search'] ?? null)
            ->status($filters['status'] ?? null)
            ->withCount('procurements')
            ->orderBy('code')
            ->paginate(15)
            ->withQueryString();

        return view('funding-sources.index', compact('fundingSources', 'filters'));
    }

    public function create()
    {
        $this->authorize('create', FundingSource::class);

        return view('funding-sources.create', ['fundingSource' => new FundingSource]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', FundingSource::class);
        $data = $this->validateFundingSource($request);
        $fundingSource = DB::transaction(function () use ($data, $request) {
            $fundingSource = FundingSource::create($data);
            AuditLog::log($request->user()->id, 'create', 'funding-sources', FundingSource::class, $fundingSource->id, null, $fundingSource->toArray());

            return $fundingSource;
        }, 5);

        return redirect()->route('funding-sources.show', $fundingSource)->with('success', 'Funding source created successfully.');
    }

    public function show(FundingSource $fundingSource)
    {
        $this->authorize('view', $fundingSource);
        $procurements = $fundingSource->procurements()->with('supplier')
            ->latest()->paginate(10)->withQueryString();

        return view('funding-sources.show', compact('fundingSource', 'procurements'));
    }

    public function edit(FundingSource $fundingSource)
    {
        $this->authorize('update', $fundingSource);

        return view('funding-sources.edit', compact('fundingSource'));
    }

    public function update(Request $request, FundingSource $fundingSource)
    {
        $this->authorize('update', $fundingSource);
        $data = $this->validateFundingSource($request, $fundingSource);
        DB::transaction(function () use ($fundingSource, $data, $request) {
            $lockedSource = FundingSource::lockForUpdate()->findOrFail($fundingSource->id);
            $oldValues = $lockedSource->toArray();
            $lockedSource->update($data);
            AuditLog::log($request->user()->id, 'update', 'funding-sources', FundingSource::class, $lockedSource->id, $oldValues, $lockedSource->fresh()->toArray());
        }, 5);

        return redirect()->route('funding-sources.show', $fundingSource)->with('success', 'Funding source updated successfully.');
    }

    public function deactivate(Request $request, FundingSource $fundingSource)
    {
        $this->authorize('deactivate', $fundingSource);
        $this->changeStatus($request, $fundingSource, FundingSource::STATUS_INACTIVE, 'deactivate');

        return back()->with('success', 'Funding source deactivated successfully. Existing purchase and stock records are preserved.');
    }

    public function reactivate(Request $request, FundingSource $fundingSource)
    {
        $this->authorize('reactivate', $fundingSource);
        $this->changeStatus($request, $fundingSource, FundingSource::STATUS_ACTIVE, 'reactivate');

        return back()->with('success', 'Funding source reactivated successfully. It is available for new purchases.');
    }

    private function validateFundingSource(Request $request, ?FundingSource $fundingSource = null): array
    {
        $normalized = [];
        foreach (['name', 'description'] as $field) {
            if (is_string($request->input($field))) {
                $normalized[$field] = trim($request->input($field));
            }
        }
        if ($fundingSource === null && is_string($request->input('code'))) {
            $normalized['code'] = mb_strtoupper(trim($request->input('code')));
        }
        $request->merge($normalized);

        $codeRules = $fundingSource
            ? ['bail', 'sometimes', 'required', 'string', Rule::in([$fundingSource->code])]
            : [
                'bail', 'required', 'string', 'max:80', 'regex:/\A[A-Z0-9][A-Z0-9 ._\/\-]*\z/',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (FundingSource::whereRaw('LOWER(code) = ?', [mb_strtolower($value)])->exists()) {
                        $fail('The funding source code has already been taken.');
                    }
                },
            ];

        $data = $request->validate([
            'code' => $codeRules,
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in(FundingSource::statuses())],
        ], [
            'code.in' => 'The funding source code cannot be changed. Create a new funding source instead.',
            'code.regex' => 'Use letters, numbers, spaces, periods, underscores, slashes or hyphens, starting with a letter or number.',
        ]);

        if ($fundingSource !== null) {
            unset($data['code']);
        }

        return $data;
    }

    private function changeStatus(Request $request, FundingSource $fundingSource, string $status, string $action): void
    {
        DB::transaction(function () use ($request, $fundingSource, $status, $action) {
            $lockedSource = FundingSource::lockForUpdate()->findOrFail($fundingSource->id);
            if ($lockedSource->status === $status) {
                return;
            }

            $oldValues = $lockedSource->toArray();
            $lockedSource->update(['status' => $status]);
            AuditLog::log($request->user()->id, $action, 'funding-sources', FundingSource::class, $lockedSource->id, $oldValues, $lockedSource->fresh()->toArray());
        }, 5);
    }
}
