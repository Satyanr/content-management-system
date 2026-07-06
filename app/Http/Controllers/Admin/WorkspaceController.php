<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\WorkspaceService;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    public function change(Request $request, WorkspaceService $workspaceService)
    {
        if (! $request->user()?->hasRole('super-admin')) {
            abort(403);
        }

        $validated = $request->validate([
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
        ]);

        $companyId = ! empty($validated['company_id'])
            ? (int) $validated['company_id']
            : null;

        $workspaceService->setCompany($companyId);

        return back()->with('success', 'Workspace changed successfully.');
    }
}