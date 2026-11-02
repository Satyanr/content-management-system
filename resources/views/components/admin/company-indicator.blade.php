@php
    $workspaceService = app(\App\Services\WorkspaceService::class);
    $workspaceCompanyId = $workspaceService->companyId();
    $isSuperAdmin = auth()->user()->hasRole('super-admin');
    $company = $workspaceService->company();
@endphp
<div class="flex min-w-0 sm:justify-end">
    @if ($isSuperAdmin)
        <form method="POST" action="{{ route('admin.workspace.change') }}" class="w-full sm:max-w-64">
            @csrf
            <label class="sr-only" for="workspace-company">Workspace aktif</label>
            <select id="workspace-company" name="company_id" onchange="this.form.submit()" class="min-h-11 w-full rounded-xl border-slate-200 bg-slate-50 text-sm text-slate-700 focus:border-blue-500 focus:ring-blue-500">
                <option value="">Semua Perusahaan</option>
                @foreach (\App\Models\Company::query()->orderBy('name')->get() as $item)<option value="{{ $item->id }}" @selected((int) $workspaceCompanyId === (int) $item->id)>{{ $item->name }} / {{ $item->code }}</option>@endforeach
            </select>
        </form>
    @else
        <div class="flex min-h-11 min-w-0 items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm"><x-studio-icon name="building" class="text-slate-400"/><span class="truncate text-slate-700">{{ $company?->name ?? 'Tanpa perusahaan' }}</span><span class="shrink-0 text-xs {{ $company?->is_active ? 'text-emerald-700' : 'text-red-700' }}">{{ $company?->is_active ? 'Aktif' : 'Nonaktif' }}</span></div>
    @endif
</div>
