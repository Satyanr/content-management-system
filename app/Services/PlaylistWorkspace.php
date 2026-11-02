<?php

namespace App\Services;

use App\Models\Playlist;
use Illuminate\Support\Facades\Gate;

class PlaylistWorkspace
{
    public function find(int $id, bool $lock = false): Playlist
    {
        Gate::authorize('playlists.view');
        $workspace = app(WorkspaceService::class);
        abort_if($workspace->companyId() === null, 403, 'Select a company workspace before opening Studio.');
        abort_unless($workspace->company()?->is_active, 403, 'Workspace is unavailable.');

        return Playlist::query()->forCompany($workspace->companyId())
            ->when($lock, fn ($query) => $query->lockForUpdate())->findOrFail($id);
    }
}
