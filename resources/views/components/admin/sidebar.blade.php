@php
    $menus = \App\Models\Menu::with('children')
        ->whereNull('parent_id')
        ->where('is_active', true)
        ->orderBy('sort_order')
        ->get();
@endphp

<aside id="admin-sidebar"
    class="fixed top-0 left-0 z-40 w-64 h-screen pt-20 transition-transform -translate-x-full bg-gray-900 border-r border-gray-800 md:translate-x-0"
    aria-label="Sidebar">

    <div class="h-full px-3 pb-4 overflow-y-auto bg-gray-900">
        <ul class="space-y-2 font-medium">

            @foreach ($menus as $menu)
                @php
                    $canView = empty($menu->permission) || auth()->user()->can($menu->permission);
                    $children = $menu->children->where('is_active', true);
                @endphp

                @if ($canView)
                    @if ($children->count())
                        <li>
                            <div class="px-2 py-2 text-xs font-semibold text-gray-400 uppercase">
                                {{ $menu->title }}
                            </div>

                            <ul class="space-y-1">
                                @foreach ($children as $child)
                                    @php
                                        $childCanView =
                                            empty($child->permission) || auth()->user()->can($child->permission);
                                        $isActive = $child->route && request()->routeIs($child->route);
                                    @endphp

                                    @if ($childCanView)
                                        <li>
                                            <a href="{{ $child->route ? route($child->route) : '#' }}"
                                                class="flex items-center p-2 pl-4 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white
                                               {{ $isActive ? 'bg-gray-700 text-white' : '' }}">
                                                <span>{{ $child->title }}</span>
                                            </a>
                                        </li>
                                    @endif
                                @endforeach
                            </ul>
                        </li>
                    @else
                        @php
                            $isActive = $menu->route && request()->routeIs($menu->route);
                        @endphp

                        <li>
                            <a href="{{ $menu->route ? route($menu->route) : '#' }}"
                                class="flex items-center p-2 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white
                               {{ $isActive ? 'bg-gray-700 text-white' : '' }}">
                                <span>{{ $menu->title }}</span>
                            </a>
                        </li>
                    @endif
                @endif
            @endforeach

        </ul>
    </div>
</aside>
