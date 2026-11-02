<div>
    <x-cms.alert />

    @if (! $hasWorkspace)
        <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            Pilih workspace perusahaan pada bilah atas untuk mengelola Playlist.
            Daftar Playlist tampil setelah workspace dipilih.
        </div>
    @endif

    <div class="mb-5 flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
        <input type="search" wire:model.live.debounce.350ms="search"
            placeholder="Cari Playlist..."
            class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 sm:w-80" />

        @can('playlists.create')
            @if ($hasWorkspace)
                <button type="button" wire:click="openCreate"
                    class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-700">
                    + Playlist Baru
                </button>
            @endif
        @endcan
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($playlists as $playlist)
            <div wire:key="playlist-{{ $playlist->id }}"
                class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="flex h-36 items-center justify-center overflow-hidden bg-gray-900 p-4">
                    <div style="width: 100%; max-width: 220px; aspect-ratio: {{ $playlist->canvas_width }} / {{ $playlist->canvas_height }}; background-color: {{ $playlist->background_color }};"
                        class="flex max-h-28 min-h-3 items-center justify-center border border-white/20 text-xs text-gray-400">
                        Canvas
                    </div>
                </div>
                <div class="space-y-3 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="truncate font-semibold text-gray-900">{{ $playlist->name }}</h3>
                            <p class="mt-1 line-clamp-2 text-xs text-gray-500">
                                {{ $playlist->description ?: 'Tanpa deskripsi' }}
                            </p>
                        </div>
                        <span class="rounded px-2 py-1 text-xs font-semibold {{ $playlist->is_active ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                            {{ $playlist->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                    <div class="flex justify-between text-xs text-gray-500">
                        <span>{{ $playlist->canvas_width }} × {{ $playlist->canvas_height }} px</span>
                        <span>{{ $playlist->pages_count }} page(s)</span>
                    </div>
                    <div class="border-t border-gray-100 pt-3">
                        <div class="flex flex-wrap gap-2">
                            <a href="{{ route('admin.playlists.studio', $playlist->id) }}" class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700">Open Studio</a>
                            @can('playlists.edit')
                                <button type="button" wire:click="edit({{ $playlist->id }})"
                                    class="rounded-lg border border-blue-200 px-3 py-2 text-sm font-medium text-blue-700 hover:bg-blue-50">
                                    Edit Settings
                                </button>
                            @endcan
                            @can('playlists.delete')
                                <button type="button" wire:click="delete({{ $playlist->id }})"
                                    wire:confirm="Delete this playlist and all its pages and zones?"
                                    class="rounded-lg border border-red-200 px-3 py-2 text-sm font-medium text-red-600 hover:bg-red-50">
                                    Hapus
                                </button>
                            @endcan
                        </div>
                        
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full rounded-xl border border-gray-200 bg-white p-10 text-center text-gray-500">
                {{ $hasWorkspace ? 'Belum ada Playlist. Klik Playlist Baru untuk membuat tayangan pertama.' : 'Pilih workspace perusahaan terlebih dahulu.' }}
            </div>
        @endforelse
    </div>

    <div class="mt-5">{{ $playlists->links() }}</div>

    @if ($showModal)
        <div class="fixed inset-0 z-[9998] flex items-center justify-center bg-black/50 p-4"
            wire:click="closeModal">
            <div wire:click.stop class="max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-2xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">
                            {{ $playlistId ? 'Ubah Playlist' : 'Buat Playlist' }}
                        </h2>
                        <p class="text-sm text-gray-500">Nama, resolusi kanvas, dan warna latar.</p>
                    </div>
                    <button type="button" wire:click="closeModal" class="text-2xl text-gray-500 hover:text-gray-900">×</button>
                </div>

                <form wire:submit="save">
                    <div class="space-y-4 px-6 py-5">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Nama</label>
                            <input type="text" wire:model="name" maxlength="150" required
                                class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
                                placeholder="Contoh: Promosi Lobi" />
                            @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Deskripsi</label>
                            <textarea wire:model="description" rows="2"
                                class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
                                placeholder="Opsional"></textarea>
                            @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-semibold text-gray-700">Preset Kanvas</label>
                            <div class="flex flex-wrap gap-2">
                                @foreach ([[1920,1080,'FHD 16:9'],[1080,1920,'Portrait'],[1920,384,'LED Banner'],[4864,512,'Ultra Wide']] as $preset)
                                    <button type="button" wire:click="setCanvasPreset({{ $preset[0] }}, {{ $preset[1] }})"
                                        class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs hover:border-blue-500 hover:text-blue-600">
                                        {{ $preset[2] }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Width (px)</label>
                                <input type="number" min="64" max="16384" wire:model="canvasWidth" required
                                    class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500" />
                                @error('canvasWidth') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Height (px)</label>
                                <input type="number" min="64" max="16384" wire:model="canvasHeight" required
                                    class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500" />
                                @error('canvasHeight') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <label class="text-sm font-medium text-gray-700">Warna latar</label>
                            <input type="color" wire:model="backgroundColor" class="h-9 w-12 cursor-pointer rounded border border-gray-300" />
                            <span class="text-xs text-gray-500">{{ $backgroundColor }}</span>
                            @error('backgroundColor') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" wire:model="isActive" class="rounded border-gray-300 text-blue-600" />
                            Playlist aktif
                        </label>
                    </div>
                    <div class="flex justify-end gap-2 border-t border-gray-200 px-6 py-4">
                        <button type="button" wire:click="closeModal"
                            class="rounded-lg border border-gray-300 px-4 py-2 text-sm hover:bg-gray-50">Batal</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="save"
                            class="rounded-lg bg-blue-600 px-5 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                            <span wire:loading.remove wire:target="save">Simpan</span>
                            <span wire:loading wire:target="save">Saving...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
