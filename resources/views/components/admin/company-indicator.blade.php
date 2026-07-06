@php
    use App\Models\Company;
    use App\Services\WorkspaceService;

    $user = auth()->user();

    $isSuperAdmin = $user?->hasRole('super-admin') ?? false;

    $workspaceService = app(WorkspaceService::class);

    $workspaceCompanyId = $workspaceService->companyId();

    $company = $isSuperAdmin ? $workspaceService->company() : $user?->company;

    $companies = $isSuperAdmin ? Company::query()->orderBy('name', 'asc')->get() : collect();
@endphp

<div class="hidden items-center gap-2 lg:flex">
    @if ($isSuperAdmin)
        <form method="POST" action="{{ route('admin.workspace.change') }}">
            @csrf

            <select name="company_id" onchange="this.form.submit()"
                class="h-9 w-56 rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-700 focus:border-blue-500 focus:ring-blue-500">
                <option value="">All Companies</option>

                @foreach ($companies as $item)
                    <option value="{{ $item->id }}" @selected((int) $workspaceCompanyId === (int) $item->id)>
                        {{ $item->name }} / {{ $item->code }}
                    </option>
                @endforeach
            </select>
        </form>
    @else
        <div class="flex h-9 items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3">
            <span class="text-sm font-medium text-gray-700">
                {{ $company?->name ?? 'No Company' }}
            </span>

            @if ($company?->is_active)
                <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs text-green-700">
                    Active
                </span>
            @else
                <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs text-red-700">
                    Inactive
                </span>
            @endif
        </div>
    @endif
</div>
