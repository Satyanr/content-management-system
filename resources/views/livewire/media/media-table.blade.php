<div>
    <x-cms.alert />

    <div class="mb-6 border-b border-gray-200 bg-white">
        <div class="flex gap-8">
            <button type="button" wire:click="setTab('my_media')"
                class="border-b-2 px-4 py-3 text-sm font-medium
                {{ $activeTab === 'my_media' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                My Media
            </button>

            <button type="button" wire:click="setTab('upload')"
                class="border-b-2 px-4 py-3 text-sm font-medium
                {{ $activeTab === 'upload' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                Upload
            </button>

            <button type="button" wire:click="setTab('shared')"
                class="border-b-2 px-4 py-3 text-sm font-medium
                {{ $activeTab === 'shared' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
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
            @if (!$isAllCompanies)
                <div x-data="{
                    uploadItems: [],
                    uploading: false,
                    progress: 0,
                    status: 'idle',
                    message: '',
                    xhr: null,
                
                    maxUploadMb: 200,
                    maxFiles: 100,
                
                    readableSize(bytes) {
                        return Math.round(bytes / 1024 / 1024 * 100) / 100 + ' MB';
                    },
                
                    fileIconByName(name) {
                        name = name.toLowerCase();
                
                        if (name.endsWith('.mp4') || name.endsWith('.mov') || name.endsWith('.avi') || name.endsWith('.mpeg') || name.endsWith('.mpg') || name.endsWith('.wmv') || name.endsWith('.m4v')) {
                            return '🎥';
                        }
                
                        if (name.endsWith('.jpg') || name.endsWith('.jpeg') || name.endsWith('.png') || name.endsWith('.webp') || name.endsWith('.gif') || name.endsWith('.bmp')) {
                            return '🖼️';
                        }
                
                        if (name.endsWith('.pdf')) {
                            return '📄';
                        }
                
                        return '📁';
                    },
                
                    openFilePicker() {
                        if (this.uploading) {
                            alert('Please wait until current upload is finished.');
                            return;
                        }
                
                        if (this.$refs.fileInput) {
                            this.$refs.fileInput.value = '';
                            this.$refs.fileInput.click();
                        }
                    },
                
                    uploadedCount() {
                        return this.uploadItems.filter((item) => item.status === 'success').length;
                    },
                
                    selectFiles(event) {
                        const selectedFiles = Array.from(event.target.files);
                
                        this.message = '';
                        this.progress = 0;
                
                        if (selectedFiles.length === 0) {
                            return;
                        }
                
                        if (selectedFiles.length > this.maxFiles) {
                            this.status = 'error';
                            this.message = 'Maximum upload is ' + this.maxFiles + ' files.';
                
                            alert(this.message);
                
                            event.target.value = '';
                            return;
                        }
                
                        const maxBytes = this.maxUploadMb * 1024 * 1024;
                
                        const oversizedFiles = selectedFiles.filter((file) => {
                            return file.size > maxBytes;
                        });
                
                        if (oversizedFiles.length > 0) {
                            const fileNames = oversizedFiles
                                .map((file) => file.name + ' (' + this.readableSize(file.size) + ')')
                                .join('\n');
                
                            this.status = 'error';
                            this.message = 'File terlalu besar. Maksimal ' + this.maxUploadMb + 'MB per file.';
                
                            alert(
                                'File terlalu besar.\n\n' +
                                'Maksimal: ' + this.maxUploadMb + 'MB per file.\n\n' +
                                'File yang melebihi limit:\n' + fileNames
                            );
                
                            event.target.value = '';
                            return;
                        }
                
                        const batchId = Date.now();
                
                        selectedFiles.forEach((file, index) => {
                            this.uploadItems.push({
                                id: batchId + '-' + index,
                                batchId: batchId,
                                file: file,
                                name: file.name,
                                size: file.size,
                                icon: this.fileIconByName(file.name),
                                progress: 0,
                                status: 'uploading',
                                message: 'Uploading media...'
                            });
                        });
                
                        this.status = 'uploading';
                        this.message = 'Uploading media...';
                
                        this.$nextTick(() => {
                            this.uploadFiles(selectedFiles, batchId);
                        });
                    },
                
                    uploadFiles(files, batchId) {
                        if (!files.length || this.uploading) {
                            return;
                        }
                
                        this.uploading = true;
                        this.status = 'uploading';
                        this.progress = 0;
                        this.message = 'Uploading media...';
                
                        const formData = new FormData();
                
                        files.forEach((file) => {
                            formData.append('files[]', file);
                        });
                
                        this.xhr = new XMLHttpRequest();
                
                        this.xhr.open('POST', '{{ route('admin.media-assets.upload') }}', true);
                        this.xhr.setRequestHeader('X-CSRF-TOKEN', '{{ csrf_token() }}');
                        this.xhr.setRequestHeader('Accept', 'application/json');
                
                        this.xhr.upload.onprogress = (event) => {
                            if (event.lengthComputable) {
                                this.progress = Math.round((event.loaded / event.total) * 100);
                
                                this.uploadItems = this.uploadItems.map((item) => {
                                    if (item.batchId === batchId) {
                                        item.progress = this.progress;
                                    }
                
                                    return item;
                                });
                            }
                        };
                
                        this.xhr.onload = () => {
                            this.uploading = false;
                
                            if (this.xhr.status >= 200 && this.xhr.status < 300) {
                                this.status = 'success';
                                this.progress = 100;
                                this.message = 'Upload successful';
                
                                this.uploadItems = this.uploadItems.map((item) => {
                                    if (item.batchId === batchId) {
                                        item.progress = 100;
                                        item.status = 'success';
                                        item.message = 'Upload successful';
                                    }
                
                                    return item;
                                });
                
                                return;
                            }
                
                            let responseMessage = 'Upload failed.';
                
                            try {
                                const response = JSON.parse(this.xhr.responseText);
                
                                if (response.message) {
                                    responseMessage = response.message;
                                }
                
                                if (response.errors) {
                                    responseMessage = Object.values(response.errors).flat().join(' ');
                                }
                            } catch (error) {
                                responseMessage = 'Upload failed with server error.';
                            }
                
                            this.status = 'error';
                            this.message = responseMessage;
                
                            this.uploadItems = this.uploadItems.map((item) => {
                                if (item.batchId === batchId) {
                                    item.status = 'error';
                                    item.message = responseMessage;
                                }
                
                                return item;
                            });
                
                            alert(responseMessage);
                        };
                
                        this.xhr.onerror = () => {
                            this.uploading = false;
                            this.status = 'error';
                            this.message = 'Network or server error during upload.';
                
                            this.uploadItems = this.uploadItems.map((item) => {
                                if (item.batchId === batchId) {
                                    item.status = 'error';
                                    item.message = this.message;
                                }
                
                                return item;
                            });
                
                            alert(this.message);
                        };
                
                        this.xhr.onabort = () => {
                            this.uploading = false;
                            this.status = 'cancelled';
                            this.message = 'Upload cancelled.';
                
                            this.uploadItems = this.uploadItems.map((item) => {
                                if (item.batchId === batchId) {
                                    item.status = 'cancelled';
                                    item.message = 'Upload cancelled';
                                }
                
                                return item;
                            });
                        };
                
                        this.xhr.send(formData);
                    },
                
                    removeItem(id) {
                        this.uploadItems = this.uploadItems.filter((item) => item.id !== id);
                
                        if (this.uploadItems.length === 0) {
                            this.status = 'idle';
                            this.message = '';
                            this.progress = 0;
                        }
                    },
                
                    resetUpload() {
                        if (this.xhr && this.uploading) {
                            this.xhr.abort();
                        }
                
                        this.uploading = false;
                        this.uploadItems = [];
                        this.progress = 0;
                        this.status = 'idle';
                        this.message = '';
                
                        if (this.$refs.fileInput) {
                            this.$refs.fileInput.value = '';
                        }
                    },
                
                    goToMyMedia() {
                        this.resetUpload();
                        $wire.setTab('my_media');
                        $wire.$refresh();
                    }
                }" class="pt-8">
                    <input type="file" multiple x-ref="fileInput"
                        accept=".jpg,.jpeg,.png,.webp,.gif,.bmp,.mp4,.mov,.avi,.mpeg,.mpg,.wmv,.m4v,.pdf" class="hidden"
                        x-on:change="selectFiles">

                    <template x-if="uploadItems.length === 0">
                        <div class="mx-auto max-w-6xl">
                            <div x-on:click="openFilePicker"
                                class="relative flex min-h-[420px] cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-blue-600 bg-blue-50/60 px-8 py-12 text-center hover:bg-blue-50">
                                <div
                                    class="pointer-events-none mb-5 flex h-24 w-24 items-center justify-center rounded-2xl bg-white shadow-sm">
                                    <div class="text-5xl text-blue-600">
                                        🖼️
                                    </div>
                                </div>

                                <h2 class="pointer-events-none text-xl font-semibold text-gray-900">
                                    Click or drag file to this area to upload
                                </h2>

                                <p class="pointer-events-none mt-3 text-sm text-gray-500">
                                    File will upload automatically after selected.
                                </p>

                                <div
                                    class="pointer-events-none mt-10 grid max-w-4xl grid-cols-1 gap-8 text-left text-sm text-gray-600 md:grid-cols-2">
                                    <ul class="list-disc space-y-3 pl-5">
                                        <li>Image support: jpg, jpeg, png, webp, gif, bmp</li>
                                        <li>PDF file is supported</li>
                                        <li>Maximum 100 files per upload</li>
                                    </ul>

                                    <ul class="list-disc space-y-3 pl-5">
                                        <li>Video support: mp4, avi, mpeg, mov, wmv, m4v</li>
                                        <li>Current max file size: 200MB</li>
                                        <li>For larger video, chunk upload will be added later</li>
                                    </ul>
                                </div>
                            </div>

                            <div x-show="status === 'error'"
                                class="mt-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700"
                                x-text="message"></div>
                        </div>
                    </template>

                    <template x-if="uploadItems.length > 0">
                        <div>
                            <div class="mb-6 flex items-center justify-between">
                                <div>
                                    <h2 class="text-lg font-semibold text-gray-900">
                                        Upload Media
                                    </h2>

                                    <p class="mt-1 text-sm text-gray-500">
                                        File will be uploaded automatically after selected.
                                    </p>
                                </div>

                                <div class="flex items-center gap-2">
                                    <button type="button" x-on:click="openFilePicker"
                                        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                                        x-bind:disabled="uploading">
                                        + Upload Media
                                    </button>

                                    <button type="button" x-on:click="resetUpload"
                                        class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                        Clear
                                    </button>

                                    <button type="button" x-on:click="goToMyMedia"
                                        class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-medium text-blue-700 hover:bg-blue-100">
                                        View in My Media
                                    </button>
                                </div>
                            </div>

                            <div class="mb-3 text-sm font-semibold text-gray-900">
                                <span x-show="uploading">
                                    Uploading <span x-text="uploadItems.length"></span> file(s)
                                </span>

                                <span x-show="!uploading">
                                    Uploaded <span x-text="uploadedCount()"></span> / <span
                                        x-text="uploadItems.length"></span>
                                </span>
                            </div>

                            <div class="space-y-4">
                                <template x-for="item in uploadItems" x-bind:key="item.id">
                                    <div
                                        class="flex items-center gap-6 rounded-xl border border-gray-200 bg-white px-8 py-4 shadow-sm">
                                        <div
                                            class="flex h-16 w-16 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-3xl text-blue-600">
                                            <span x-text="item.icon"></span>
                                        </div>

                                        <div class="min-w-0 flex-1">
                                            <div class="truncate text-sm font-medium text-gray-900" x-text="item.name">
                                            </div>
                                            <div class="mt-1 text-xs text-gray-500" x-text="readableSize(item.size)"></div>
                                        </div>

                                        <div class="flex w-[420px] items-center gap-5">
                                            <template x-if="item.status === 'uploading'">
                                                <div class="w-full">
                                                    <div class="mb-1 text-center text-sm text-gray-900"
                                                        x-text="item.progress + '%'"></div>

                                                    <div class="h-3 overflow-hidden rounded bg-gray-100">
                                                        <div class="h-3 rounded bg-blue-600 transition-all"
                                                            x-bind:style="'width: ' + item.progress + '%'"></div>
                                                    </div>
                                                </div>
                                            </template>

                                            <template x-if="item.status === 'success'">
                                                <div class="flex w-full items-center justify-center gap-3 text-green-600">
                                                    <div
                                                        class="flex h-8 w-8 items-center justify-center rounded-full bg-green-500 text-white">
                                                        ✓
                                                    </div>

                                                    <span class="text-sm font-medium text-gray-900">
                                                        Upload successful
                                                    </span>
                                                </div>
                                            </template>

                                            <template x-if="item.status === 'error'">
                                                <div class="w-full rounded-lg bg-red-50 px-4 py-2 text-sm text-red-700"
                                                    x-text="item.message"></div>
                                            </template>

                                            <template x-if="item.status === 'cancelled'">
                                                <div class="w-full rounded-lg bg-gray-50 px-4 py-2 text-sm text-gray-700">
                                                    Upload cancelled
                                                </div>
                                            </template>
                                        </div>

                                        <button type="button" x-on:click="removeItem(item.id)"
                                            class="flex h-10 w-10 shrink-0 items-center justify-center text-4xl leading-none text-gray-500 hover:text-red-600"
                                            title="Remove">
                                            ×
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            @endif
        @endcan
    @endif

    @if ($activeTab === 'my_media')
        <div class="mb-4 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div class="flex flex-col gap-3 md:flex-row">
                <input type="text" wire:model.live.debounce.500ms="search" placeholder="Search in library..."
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

            <div class="flex flex-wrap gap-2">
                <button type="button" wire:click="selectCurrentPage"
                    class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Select Page
                </button>

                @if (count($selectedMediaIds) > 0)
                    <button type="button" wire:click="clearSelection"
                        class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Clear
                    </button>

                    @can('media.delete')
                        <button type="button" wire:click="deleteSelected" wire:confirm="Delete selected media?"
                            class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">
                            Delete Selected
                        </button>
                    @endcan
                @endif
            </div>
        </div>

        @if (count($selectedMediaIds) > 0)
            <div class="mb-4 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-700">
                {{ count($selectedMediaIds) }} media selected.
            </div>
        @endif

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @forelse ($mediaAssets as $media)
                @php
                    $isSelected = in_array((string) $media->id, $selectedMediaIds, true);
                @endphp

                <div x-data="{ clickTimer: null }"
                    x-on:click="
            if (clickTimer) {
                clearTimeout(clickTimer);
                clickTimer = null;
                $wire.openPreview({{ $media->id }});
                return;
            }

            clickTimer = setTimeout(() => {
                $wire.toggleSelect({{ $media->id }});
                clickTimer = null;
            }, 220);
        "
                    class="group relative z-0 cursor-pointer overflow-visible rounded-xl border bg-white shadow-sm transition-all duration-200 hover:z-30 hover:-translate-y-0.5 hover:shadow-lg
        {{ $isSelected ? 'border-blue-500 ring-2 ring-blue-100' : 'border-gray-200 hover:border-blue-300' }}">
                    <div
                        class="relative flex h-44 items-center justify-center overflow-hidden rounded-t-xl bg-gray-100">
                        <button type="button" wire:click.stop="toggleSelect({{ $media->id }})"
                            class="absolute left-3 top-3 z-20 flex h-8 w-8 items-center justify-center rounded-lg border shadow-sm transition-all duration-200
                {{ $isSelected ? 'border-blue-600 bg-blue-600 opacity-100' : 'border-white/80 bg-black/30 opacity-0 group-hover:opacity-100' }}">
                            @if ($isSelected)
                                <svg class="h-5 w-5 text-white" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd"
                                        d="M16.704 5.29a1 1 0 010 1.42l-7.25 7.25a1 1 0 01-1.42 0l-3.25-3.25a1 1 0 111.42-1.42l2.54 2.54 6.54-6.54a1 1 0 011.42 0z"
                                        clip-rule="evenodd" />
                                </svg>
                            @endif
                        </button>

                        <div
                            class="absolute inset-0 z-10 bg-blue-600/0 transition-all duration-200 group-hover:bg-blue-600/5
                {{ $isSelected ? 'bg-blue-600/10' : '' }}">
                        </div>

                        @if ($media->type === 'image')
                            <img src="{{ $media->url }}" alt="{{ $media->title }}"
                                class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105">
                        @elseif ($media->type === 'video')
                            <div
                                class="relative flex h-full w-full items-center justify-center overflow-hidden bg-gray-950">
                                <div class="absolute inset-0 bg-gradient-to-br from-gray-800 via-gray-950 to-black">
                                </div>

                                <div class="relative z-10 text-center text-white">
                                    <div
                                        class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-white/20 text-2xl transition group-hover:scale-110">
                                        ▶
                                    </div>

                                    <div class="text-sm font-semibold">
                                        {{ strtoupper($media->extension) }} Video
                                    </div>
                                </div>

                                <div
                                    class="absolute bottom-2 left-2 rounded bg-black/60 px-2 py-1 text-xs font-medium text-white">
                                    {{ strtoupper($media->extension) }}
                                </div>
                            </div>
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

                    <div class="relative p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <div class="truncate font-semibold text-gray-900">
                                    {{ $media->title }}
                                </div>

                                <div class="mt-1 truncate text-sm text-gray-500">
                                    {{ $media->original_name }}
                                </div>
                            </div>

                            <div x-data="{ open: false }" class="relative z-[100] shrink-0" x-on:click.stop
                                @click.outside="open = false">
                                <button type="button" x-on:click="open = ! open"
                                    class="flex h-8 w-8 items-center justify-center rounded-lg text-xl text-gray-500 hover:bg-gray-100 hover:text-gray-900">
                                    ⋮
                                </button>

                                <div x-show="open" x-transition
                                    class="absolute right-0 top-full z-[9999] mt-2 w-44 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-xl"
                                    style="display: none;">
                                    <button type="button" wire:click="openPreview({{ $media->id }})"
                                        x-on:click="open = false"
                                        class="block w-full px-4 py-3 text-left text-sm text-gray-700 hover:bg-gray-100">
                                        Preview
                                    </button>

                                    <a href="{{ $media->url }}" download x-on:click="open = false"
                                        class="block w-full px-4 py-3 text-left text-sm text-gray-700 hover:bg-gray-100">
                                        Download
                                    </a>

                                    @can('media.delete')
                                        <button type="button" wire:click="delete({{ $media->id }})"
                                            wire:confirm="Are you sure you want to delete this media?"
                                            x-on:click="open = false"
                                            class="block w-full px-4 py-3 text-left text-sm text-red-600 hover:bg-red-50">
                                            Delete
                                        </button>
                                    @endcan
                                </div>
                            </div>
                        </div>

                        <div class="mt-3 flex items-center justify-between text-xs text-gray-500">
                            <span class="rounded bg-gray-100 px-2 py-1 uppercase">
                                {{ $media->type }}
                            </span>

                            <span>{{ $media->size_formatted }}</span>
                        </div>

                        @if ($isAllCompanies)
                            <div class="mt-2 text-xs text-gray-500">
                                Company: {{ $media->company?->name ?? '-' }}
                            </div>
                        @endif
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

    @if ($showPreview && $previewMedia)
        <div class="fixed inset-0 z-[9999] overflow-hidden bg-black/75 backdrop-blur-sm" wire:click="closePreview"
            x-data x-on:keydown.escape.window="$wire.closePreview()">
            <div class="absolute left-4 top-4 z-30 flex items-center gap-3 text-white">
                <button type="button" wire:click.stop="closePreview"
                    class="flex h-10 w-10 items-center justify-center rounded-full bg-black/50 text-2xl hover:bg-black/70">
                    ×
                </button>

                <div>
                    <div class="max-w-[480px] truncate text-sm font-semibold">
                        {{ $previewMedia->title }}
                    </div>

                    <div class="text-xs text-gray-300">
                        {{ strtoupper($previewMedia->extension) }} · {{ $previewMedia->size_formatted }}
                    </div>
                </div>
            </div>

            <div class="absolute right-4 top-4 z-30">
                <a href="{{ $previewMedia->url }}" download wire:click.stop
                    class="rounded-lg bg-white px-4 py-2 text-sm font-medium text-gray-900 shadow hover:bg-gray-100">
                    Download
                </a>
            </div>

            <button type="button" wire:click.stop="previewPrevious"
                class="absolute left-5 top-1/2 z-30 flex h-12 w-12 -translate-y-1/2 items-center justify-center rounded-full bg-black/50 text-4xl text-white hover:bg-black/70">
                ‹
            </button>

            <button type="button" wire:click.stop="previewNext"
                class="absolute right-5 top-1/2 z-30 flex h-12 w-12 -translate-y-1/2 items-center justify-center rounded-full bg-black/50 text-4xl text-white hover:bg-black/70">
                ›
            </button>

            <div class="flex h-full w-full items-center justify-center px-20 pb-32 pt-20">
                <div wire:click.stop class="relative flex max-h-[76vh] max-w-[86vw] items-center justify-center">
                    @if ($previewMedia->type === 'image')
                        <img src="{{ $previewMedia->url }}" alt="{{ $previewMedia->title }}"
                            class="max-h-[76vh] max-w-[86vw] object-contain shadow-2xl">
                    @elseif ($previewMedia->type === 'video')
                        @if (in_array(strtolower($previewMedia->extension), ['mp4', 'webm']))
                            <div wire:key="preview-video-wrapper-{{ $previewMedia->id }}-{{ $previewToken }}"
                                class="relative bg-black shadow-2xl" x-data="{ loading: true }">
                                <div x-show="loading"
                                    class="absolute inset-0 z-10 flex items-center justify-center bg-black">
                                    <div
                                        class="h-10 w-10 animate-spin rounded-full border-4 border-white/30 border-t-white">
                                    </div>
                                </div>

                                <video src="{{ $previewMedia->url }}" class="max-h-[76vh] max-w-[86vw] bg-black"
                                    controls playsinline preload="auto" x-on:loadedmetadata="loading = false"
                                    x-on:canplay="loading = false" x-on:error="loading = false"></video>
                            </div>
                        @else
                            <div
                                class="flex h-[360px] w-[640px] max-w-[86vw] items-center justify-center rounded-xl bg-gray-900 text-center text-white shadow-2xl">
                                <div>
                                    <div
                                        class="mx-auto mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-white/20 text-4xl">
                                        ▶
                                    </div>

                                    <div class="text-lg font-semibold">
                                        {{ strtoupper($previewMedia->extension) }} Video
                                    </div>

                                    <div class="mt-2 text-sm text-gray-300">
                                        Browser preview may not support this format.
                                    </div>

                                    <a href="{{ $previewMedia->url }}" target="_blank" wire:click.stop
                                        class="mt-5 inline-block rounded-lg bg-white px-4 py-2 text-sm font-medium text-gray-900 hover:bg-gray-100">
                                        Open File
                                    </a>
                                </div>
                            </div>
                        @endif
                    @elseif ($previewMedia->type === 'pdf')
                        <iframe src="{{ $previewMedia->url }}"
                            class="h-[76vh] w-[78vw] rounded-lg bg-white shadow-2xl"></iframe>
                    @else
                        <div class="rounded-xl bg-white p-8 text-center shadow-2xl">
                            <div class="text-5xl">📁</div>

                            <div class="mt-3 font-semibold text-gray-900">
                                {{ $previewMedia->title }}
                            </div>

                            <a href="{{ $previewMedia->url }}" target="_blank" wire:click.stop
                                class="mt-5 inline-block rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                                Open File
                            </a>
                        </div>
                    @endif
                </div>
            </div>

            @if (isset($previewList) && $previewList->count())
                <div wire:click.stop
                    class="absolute bottom-6 left-1/2 z-30 w-[80vw] -translate-x-1/2 overflow-x-auto rounded-xl bg-black/50 p-3 backdrop-blur">
                    <div class="flex gap-3">
                        @foreach ($previewList as $item)
                            <button type="button" wire:click="openPreview({{ $item->id }})"
                                class="relative h-16 w-28 shrink-0 overflow-hidden rounded-lg border transition
                            {{ $previewMedia->id === $item->id ? 'border-blue-500 ring-2 ring-blue-400' : 'border-white/20 hover:border-white/70' }}">
                                @if ($item->type === 'image')
                                    <img src="{{ $item->url }}" class="h-full w-full object-cover"
                                        alt="{{ $item->title }}">
                                @elseif ($item->type === 'video')
                                    <div class="flex h-full w-full items-center justify-center bg-gray-950 text-white">
                                        <div
                                            class="flex h-8 w-8 items-center justify-center rounded-full bg-white/20 text-sm">
                                            ▶
                                        </div>
                                    </div>
                                @elseif ($item->type === 'pdf')
                                    <div class="flex h-full w-full items-center justify-center bg-white text-2xl">
                                        📄
                                    </div>
                                @else
                                    <div class="flex h-full w-full items-center justify-center bg-white text-2xl">
                                        📁
                                    </div>
                                @endif
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @endif
</div>
