<div
    x-data="{
        viewMode: 'grid',
        renamePath: @entangle('rename_path'),
        renameName: @entangle('rename_name'),
    }"
>
    <div class="w-full">
        {{-- Top Toolbar --}}
        <div class="flex justify-between items-end gap-4 mb-4">
            <div>
                <flux:button.group class="mb-0">
                    <flux:button wire:loading.attr="disabled" wire:target="upload"  type="button" size="sm" icon="arrow-up-tray" @click="$refs.upload.click()" tooltip="upload file" class="mb-0" />
                    <flux:modal.trigger name="createFolderModal">
                        <flux:button type="button" size="sm" icon="folder-plus" tooltip="create folder" class="mb-0 rounded-none border-s-none" />
                    </flux:modal.trigger>
                    <flux:button type="button" size="sm" icon="squares-2x2" tooltip="grid view" class="mb-0" @click="viewMode = 'grid'" />
                    <flux:button type="button" size="sm" icon="bars-4" tooltip="list view" class="mb-0" @click="viewMode = 'list'" />

                    <flux:dropdown>
                        <flux:button type="button" size="sm" icon="bars-arrow-down" tooltip="sort" class="rounded-s-none border-s-none mb-0" />
                        <flux:menu>
                            <flux:menu.item :icon="$sort_by == 'name' ? 'check' : null" wire:click="sortBy('name')">{{ __('filename') }}</flux:menu.item>
                            <flux:menu.item :icon="$sort_by == 'last_modified_at' ? 'check' : null" wire:click="sortBy('last_modified_at')">{{ __('date') }}</flux:menu.item>
                            <flux:menu.item :icon="$sort_by == 'size' ? 'check' : null" wire:click="sortBy('size')">{{ __('size') }}</flux:menu.item>
                            <flux:menu.item :icon="$sort_by == 'type' ? 'check' : null" wire:click="sortBy('type')">{{ __('type') }}</flux:menu.item>
                        </flux:menu>
                    </flux:dropdown>
                </flux:button.group>
            </div>

            <div>
                <div wire:loading wire:target="upload_files">
                    <flux:icon.loading class="inline" /> {{ __('uploading files') }}...
                </div>

                <flux:error name="upload_files" />
            </div>

            <div>
                <flux:input wire:model.live.debounce.250ms="search_term" size="sm" icon="magnifying-glass" placeholder="{{ __('search') }}..." />
            </div>
        </div>

        {{-- hidden file input --}}
        <input
            wire:model="upload_files"
            x-ref="upload"
            type="file"
            multiple
            class="hidden"
        />

        {{-- breadcrumbs --}}
        <div class="rounded-md p-1 bg-zinc-100 border border-zinc-800/10 dark:bg-white/10 dark:border-white/20">
            <flux:breadcrumbs>
                <flux:breadcrumbs.item>
                    <flux:button type="button" size="sm" variant="ghost" icon="home" wire:click="setPath('{{ $home_path }}')" />
                </flux:breadcrumbs.item>
                @foreach($this->breadcrumbs as $breadcrumb_name => $breadcrumb_path)
                    <flux:breadcrumbs.item>
                        <flux:button type="button" size="sm" variant="ghost" wire:click="setPath('{{ $breadcrumb_path }}')">
                            {{ $breadcrumb_name }}
                        </flux:button>
                    </flux:breadcrumbs.item>
                @endforeach
            </flux:breadcrumbs>
        </div>

        {{-- loader --}}
        <div class="w-full h-[4px] mt-1 mb-4">
            <div wire:loading class="w-full bg-red-200 h-[4px] overflow-hidden">
                <div class="bg-red-600 rounded-full h-[4px] animate-[swing_1s_infinite_linear] overflow-hidden origin-[0%_50%]"></div>
            </div>
        </div>

        {{-- grid --}}
        <div :class="viewMode === 'grid' ? 'flex flex-wrap items-stretch gap-4' : 'divide-y divide-zinc-800/10 dark:divide-white/20'">
            <div x-show="viewMode === 'list'" class="flex bg-zinc-100 dark:bg-white/10 flex-row w-full">
                <flux:text class="grow capitalize font-semibold p-1">{{ __('filename') }}</flux:text>
                <flux:text class="w-32 capitalize font-semibold p-1">{{ __('filetype') }}</flux:text>
                <flux:text class="w-32 capitalize font-semibold p-1">{{ __('size') }}</flux:text>
                <flux:text class="w-36 capitalize font-semibold p-1">{{ __('date') }}</flux:text>
                <flux:text class="w-28 capitalize font-semibold p-1"></flux:text>
            </div>

            @foreach($items as $item)
                <div
                    wire:key="item_{{ $item['id'] }}"
                    id="item_{{ $item['id'] }}"
                    class="flex"
                    :class="viewMode === 'grid' ? 'flex-col justify-between gap-1 border rounded text-center w-36 bg-zinc-100 dark:bg-white/10 border-zinc-800/10 dark:border-white/20' : 'flex-row w-full'"
                >
                    <div x-show="viewMode === 'grid'">
                        @if ($item['type'] == 'folder')
                            <div wire:click="setPath('{{ $item['path'] }}')" class="mb-1 cursor-pointer">
                                <flux:icon.folder class="size-20 mx-auto" />
                            </div>
                        @else
                            <div
                                class="mb-1 cursor-pointer"
                                @if ($mode != 'gallery')
                                    wire:click="select('{{ $item['path'] }}')"
                                @endif
                            >
                                @if (Str::startsWith($item['type'], 'image/'))
                                    <img
                                        src="{{ $item['url'] }}"
                                        class="w-full h-auto mx-auto"
                                        alt="Image file"
                                    />
                                @else
                                    <flux:icon.document class="size-20 mx-auto" />
                                @endif
                            </div>
                        @endif

                        <flux:text class="truncate px-1" title="{{ $item['name'] }}">{{ $item['name'] }}</flux:text>
                    </div>

                    @if ($item['type'] == 'folder')
                        <div x-show="viewMode !== 'grid'" class="flex flex-row items-center p-1 grow cursor-pointer hover:bg-zinc-100 dark:hover:bg-white/10" wire:click="setPath('{{ $item['path'] }}')">
                            <div class="px-1">
                                <flux:icon.folder class="size-4" />
                            </div>
                            <flux:text class="grow">{{ $item['name'] }}</flux:text>
                        </div>
                    @else
                        <div
                            x-show="viewMode !== 'grid'"
                            class="p-1 grow cursor-pointer flex flex-row items-center hover:bg-zinc-100 dark:hover:bg-white/10"
                            @if ($mode != 'gallery')
                                wire:click="select('{{ $item['path'] }}')"
                            @endif
                        >
                            <div class="px-1">
                                @if (Str::startsWith($item['type'], 'image/'))
                                    <img
                                        src="{{ $item['url'] }}"
                                        class="w-4 h-auto mx-auto"
                                        alt="Image file"
                                    />
                                @else
                                    <flux:icon.document class="size-4" />
                                @endif
                            </div>
                            <flux:text class="grow">{{ $item['name'] }}</flux:text>
                        </div>
                    @endif

                    <flux:text x-show="viewMode !== 'grid'" class="p-1 w-32 truncate" title="{{ $item['type'] }}">{{ $item['type'] }}</flux:text>
                    <flux:text x-show="viewMode !== 'grid'" class="p-1 w-32">{{ $item['size'] ?? '0' }}KB</flux:text>
                    <flux:text x-show="viewMode !== 'grid'" class="p-1 w-36">{{ $item['last_modified_at'] ?? '' }}</flux:text>
                    <div class="flex flex-wrap gap-2" {{--:class="{'w-28 justify-end': viewMode === 'list'}"--}} :class="viewMode === 'grid' ? 'justify-center' : 'w-28 justify-end'">
                        @if ($item['type'] != 'folder')
                            <flux:button wire:key="downloadItemBtn_{{ $item['id'] }}" size="xs" variant="ghost" icon="arrow-down-tray" tooltip="{{ __('download') }}" wire:click="download('{{ $item['path'] }}')" />
                        @endif
                        <flux:button
                            wire:key="renameItemBtn_{{ $item['id'] }}"
                            size="xs"
                            variant="ghost"
                            icon="pencil"
                            tooltip="{{ __('rename') }}"
                            x-on:click="
                                renamePath = '{{ $item['path'] }}';
                                renameName = '{{ $item['name'] }}';

                                $flux.modal('renameModal').show();
                            "
                        />
                        <flux:button wire:key="deleteItemBtn_{{ $item['id'] }}" size="xs" variant="ghost" icon="trash" tooltip="{{ __('delete') }}" wire:click="delete('{{ $item['path'] }}')" wire:confirm="{{ __('are you sure?') }}" />
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- create folder modal --}}
    <flux:modal name="createFolderModal" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg" class="capitalize">{{ __('create folder') }}</flux:heading>
            </div>

            <flux:field>
                <flux:input wire:model="new_folder_name" label="{{ __('name') }}" class="required" />
                <flux:description>{{ __('no spaces, only letters, numbers, dashes or underscores') }}</flux:description>
            </flux:field>


            <div class="flex">
                <flux:spacer />
                <flux:button wire:click="createFolder" type="button" variant="primary" icon="check">{{ __('create folder') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- renamer modal --}}
    <flux:modal name="renameModal" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg" class="capitalize">{{ __('rename item') }}</flux:heading>
            </div>

            <flux:field>
                <flux:input
                    x-model="renameName"
                    wire:model="rename_name"
                    label="{{ __('new name') }}"
                    class="required"
                />
                <flux:description>{{ __('no spaces, only letters, numbers, dashes or underscores') }}</flux:description>
            </flux:field>

            <div class="flex">
                <flux:spacer />
                <flux:button wire:click="rename" type="button" variant="primary" icon="check">{{ __('rename item') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
