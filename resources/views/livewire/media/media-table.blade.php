<div>
    <x-cms.alert />

    @can('media.create')
        @if (!$isAllCompanies)
            <div class="mb-6 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <h2 class="mb-4 text-lg font-semibold text-gray-900">
                    Upload Media
                </h2>

                <form wire:submit.prevent="saveMedia" enctype="multipart/form-data">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <x-cms.input label="Title" name="title" wire:model="title" placeholder="Example: Promo July 2026" />

                        <div>
                            <label for="media-file" class="mb-2 block text-sm font-medium text-gray-900">
                                File
                            </label>

                            <input id="media-file" name="file" type="file" wire:model="file"
                                accept=".jpg,.jpeg,.png,.webp,.gif,.mp4,.mov,.avi,.pdf"
                                class="w-full rounded-lg border border-gray-300 p-2 text-sm">

                            <p class="mt-1 text-xs text-gray-500">
                                Allowed: JPG, PNG, WEBP, GIF, MP4, MOV, AVI, PDF. Max 50MB.
                            </p>

                            @error('file')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror

                            <div wire:loading wire:target="file" class="mt-2 text-sm text-blue-600">
                                Preparing file upload...
                            </div>

                            <div wire:loading wire:target="saveMedia" class="mt-2 text-sm text-blue-600">
                                Saving media...
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 flex justify-end">
                        <x-cms.button type="submit" wire:loading.attr="disabled" wire:target="file,saveMedia">
                            Upload
                        </x-cms.button>
                    </div>
                </form>
            </div>
        @else
            <div class="mb-6 rounded-lg border border-yellow-200 bg-yellow-50 p-4 text-sm text-yellow-800">
                You are viewing all companies. Select a workspace first before uploading new media.
            </div>
        @endif
    @endcan

    <div class="mb-4 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div class="flex flex-col gap-3 md:flex-row">
            <input type="text" wire:model.live.debounce.500ms="search" placeholder="Search media..."
                class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 md:w-80">

            <select wire:model.live="type"
                class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 md:w-48">
                <option value="">All Types</option>
                <option value="image">Image</option>
                <option value="video">Video</option>
                <option value="pdf">PDF</option>
                <option value="other">Other</option>
            </select>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        @forelse ($mediaAssets as $media)
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="flex h-44 items-center justify-center bg-gray-100">
                    @if ($media->type === 'image')
                        <img src="{{ $media->url }}" alt="{{ $media->title }}" class="h-full w-full object-cover">
                    @elseif ($media->type === 'video')
                        <video src="{{ $media->url }}" class="h-full w-full object-cover" controls muted></video>
                    @elseif ($media->type === 'pdf')
                        <div class="text-center">
                            <div class="text-4xl">📄</div>
                            <div class="mt-2 text-sm font-medium text-gray-600">PDF</div>
                        </div>
                    @else
                        <div class="text-center">
                            <div class="text-4xl">📁</div>
                            <div class="mt-2 text-sm font-medium text-gray-600">File</div>
                        </div>
                    @endif
                </div>

                <div class="p-4">
                    <div class="truncate font-semibold text-gray-900">
                        {{ $media->title }}
                    </div>

                    <div class="mt-1 truncate text-sm text-gray-500">
                        {{ $media->original_name }}
                    </div>

                    <div class="mt-3 flex items-center justify-between text-xs text-gray-500">
                        <span class="uppercase">{{ $media->type }}</span>
                        <span>{{ $media->size_formatted }}</span>
                    </div>

                    @if ($isAllCompanies)
                        <div class="mt-2 text-xs text-gray-500">
                            Company: {{ $media->company?->name ?? '-' }}
                        </div>
                    @endif

                    <div class="mt-4 flex items-center justify-between gap-2">
                        <a href="{{ $media->url }}" target="_blank"
                            class="rounded-lg bg-gray-100 px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-200">
                            Preview
                        </a>

                        @can('media.delete')
                            <button type="button" wire:click="delete({{ $media->id }})"
                                wire:confirm="Are you sure you want to delete this media?"
                                class="rounded-lg bg-red-100 px-3 py-2 text-xs font-medium text-red-700 hover:bg-red-200">
                                Delete
                            </button>
                        @endcan
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full rounded-xl border border-gray-200 bg-white p-8 text-center text-gray-500">
                No media found.
            </div>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $mediaAssets->links() }}
    </div>
</div>
