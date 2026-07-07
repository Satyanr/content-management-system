<div>
    <x-cms.alert />

    <div class="mb-6 border-b border-gray-200 bg-white">
        <div class="flex gap-8">
            <button
                type="button"
                wire:click="setTab('my_media')"
                class="border-b-2 px-4 py-3 text-sm font-medium
                {{ $activeTab === 'my_media' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}"
            >
                My Media
            </button>

            <button
                type="button"
                wire:click="setTab('upload')"
                class="border-b-2 px-4 py-3 text-sm font-medium
                {{ $activeTab === 'upload' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}"
            >
                Upload
            </button>

            <button
                type="button"
                wire:click="setTab('shared')"
                class="border-b-2 px-4 py-3 text-sm font-medium
                {{ $activeTab === 'shared' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}"
            >
                Shared with me
            </button>
        </div>
    </div>

    @if ($isAllCompanies)
        <div class="mb-6 rounded-lg border border-yellow-200 bg-yellow-50 p-4 text-sm text-yellow-800">
            You are viewing all companies. Select a workspace first before uploading new media.
        </div>
    @endif

    @if ($activeTab === 'upload')
        @can('media.create')
            @if (! $isAllCompanies)
                @if (! $files)
                    <div class="mx-auto mt-10 max-w-6xl">
                        <label
                            for="media-upload-files"
                            class="relative flex min-h-[420px] cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-blue-600 bg-blue-50/60 px-8 py-12 text-center hover:bg-blue-50"
                        >
                            <input
                                id="media-upload-files"
                                type="file"
                                wire:model="files"
                                multiple
                                accept=".jpg,.jpeg,.png,.webp,.gif,.bmp,.mp4,.mov,.avi,.mpeg,.wmv,.pdf"
                                class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
                            >

                            <div class="pointer-events-none mb-5 flex h-24 w-24 items-center justify-center rounded-2xl bg-white shadow-sm">
                                <div class="text-5xl text-blue-600">
                                    🖼️
                                </div>
                            </div>

                            <h2 class="pointer-events-none text-xl font-semibold text-gray-900">
                                Click or drag file to this area to upload
                            </h2>

                            <div class="pointer-events-none mt-10 grid max-w-4xl grid-cols-1 gap-8 text-left text-sm text-gray-600 md:grid-cols-2">
                                <ul class="list-disc space-y-3 pl-5">
                                    <li>Image support format jpeg, bmp, png, gif</li>
                                    <li>The image supports a size range of 0 to 20M</li>
                                    <li>Single upload of materials cannot exceed 100</li>
                                </ul>

                                <ul class="list-disc space-y-3 pl-5">
                                    <li>Video support formats mp4, avi, mpeg, mov, wmv</li>
                                    <li>Current system limit is 50MB per file</li>
                                    <li>Upload folder later will be added with folder management</li>
                                </ul>
                            </div>
                        </label>

                        @error('files')
                            <p class="mt-4 text-center text-sm text-red-600">{{ $message }}</p>
                        @enderror

                        @error('files.*')
                            <p class="mt-4 text-center text-sm text-red-600">{{ $message }}</p>
                        @enderror

                        <div wire:loading wire:target="files" class="mx-auto mt-6 max-w-6xl">
                            <div class="mb-1 flex justify-between text-sm text-gray-600">
                                <span>Preparing selected files...</span>
                            </div>

                            <div class="h-3 overflow-hidden rounded-full bg-gray-200">
                                <div class="h-3 w-1/2 animate-pulse rounded-full bg-blue-600"></div>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="pt-4">
                        <div class="mb-10 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <x-cms.button
                                    wire:click="saveFiles"
                                    wire:loading.attr="disabled"
                                    wire:target="files,saveFiles"
                                >
                                    Upload
                                </x-cms.button>
                            </div>

                            <div class="flex items-center gap-3">
                                <button
                                    type="button"
                                    wire:click="startAllUploads"
                                    class="rounded border border-blue-300 px-6 py-2 text-sm text-blue-700 hover:bg-blue-50
                                    {{ ! $isUploadPaused ? 'opacity-40' : '' }}"
                                >
                                    Start All
                                </button>

                                <button
                                    type="button"
                                    wire:click="pauseAllUploads"
                                    class="rounded border border-blue-300 px-6 py-2 text-sm text-blue-700 hover:bg-blue-50
                                    {{ $isUploadPaused ? 'opacity-40' : '' }}"
                                >
                                    Pause All
                                </button>
                            </div>
                        </div>

                        <div class="mb-3 text-sm font-semibold text-gray-900">
                            @if ($isUploadPaused)
                                Paused {{ count($files) }} / {{ count($files) }}
                            @else
                                Uploading {{ count($files) }} / {{ count($files) }}
                            @endif
                        </div>

                        <div class="space-y-4">
                            @foreach ($files as $file)
                                @php
                                    $extension = strtolower($file->getClientOriginalExtension());
                                    $isVideo = in_array($extension, ['mp4', 'avi', 'mpeg', 'mov', 'wmv'], true);
                                    $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp'], true);
                                @endphp

                                <div class="flex items-center gap-6 rounded-xl border border-gray-200 bg-white px-8 py-4 shadow-sm">
                                    <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-3xl text-blue-600">
                                        @if ($isVideo)
                                            🎥
                                        @elseif ($isImage)
                                            🖼️
                                        @elseif ($extension === 'pdf')
                                            📄
                                        @else
                                            📁
                                        @endif
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <div class="truncate text-sm font-medium text-gray-900">
                                            {{ $file->getClientOriginalName() }}
                                        </div>

                                        <div class="mt-1 text-xs text-gray-500">
                                            {{ strtoupper($extension) }}
                                        </div>
                                    </div>

                                    <div class="w-80">
                                        <div class="mb-1 text-center text-sm text-gray-900">
                                            @if ($isUploadPaused)
                                                Paused
                                            @else
                                                <span wire:loading.remove wire:target="saveFiles">Ready</span>
                                                <span wire:loading wire:target="saveFiles">Saving...</span>
                                            @endif
                                        </div>

                                        <div class="h-4 overflow-hidden rounded bg-gray-100">
                                            @if ($isUploadPaused)
                                                <div class="h-4 rounded bg-yellow-400" style="width: 35%"></div>
                                            @else
                                                <div wire:loading.remove wire:target="saveFiles" class="h-4 rounded bg-gray-200" style="width: 100%"></div>
                                                <div wire:loading wire:target="saveFiles" class="h-4 animate-pulse rounded bg-blue-600" style="width: 65%"></div>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="flex w-32 items-center justify-end gap-5 text-3xl text-gray-500">
                                        <button
                                            type="button"
                                            wire:click="pauseAllUploads"
                                            class="hover:text-blue-600"
                                            title="Pause"
                                        >
                                            ⏸
                                        </button>

                                        <button
                                            type="button"
                                            wire:click="clearFiles"
                                            class="hover:text-red-600"
                                            title="Cancel"
                                        >
                                            ×
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div wire:loading wire:target="saveFiles" class="mt-5 text-sm text-blue-600">
                            Saving files to Content Library...
                        </div>
                    </div>
                @endif
            @endif
        @endcan
    @endif

    @if ($activeTab === 'my_media')
        <div class="mb-4 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div class="flex flex-col gap-3 md:flex-row">
                <input
                    type="text"
                    wire:model.live.debounce.500ms="search"
                    placeholder="Search in library..."
                    class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 md:w-80"
                >

                <select
                    wire:model.live="type"
                    class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 md:w-48"
                >
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
                            <img
                                src="{{ $media->url }}"
                                alt="{{ $media->title }}"
                                class="h-full w-full object-cover"
                            >
                        @elseif ($media->type === 'video')
                            <video
                                src="{{ $media->url }}"
                                class="h-full w-full object-cover"
                                controls
                                muted
                            ></video>
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
                            <a
                                href="{{ $media->url }}"
                                target="_blank"
                                class="rounded-lg bg-gray-100 px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-200"
                            >
                                Preview
                            </a>

                            @can('media.delete')
                                <button
                                    type="button"
                                    wire:click="delete({{ $media->id }})"
                                    wire:confirm="Are you sure you want to delete this media?"
                                    class="rounded-lg bg-red-100 px-3 py-2 text-xs font-medium text-red-700 hover:bg-red-200"
                                >
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
    @endif

    @if ($activeTab === 'shared')
        <div class="rounded-xl border border-gray-200 bg-white p-10 text-center text-gray-500">
            Shared media will be added later.
        </div>
    @endif
</div>