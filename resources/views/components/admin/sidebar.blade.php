@php
    use Illuminate\Support\Facades\Route;
    use App\Services\SidebarMenuService;

    $menus = app(SidebarMenuService::class)->getMenus();

    $getHref = function ($route) {
        if (!$route) {
            return '#';
        }

        return Route::has($route) ? route($route) : '#';
    };

    $isActive = function ($route) {
        if (!$route || !Route::has($route)) {
            return false;
        }

        return request()->routeIs($route);
    };
@endphp

<aside id="admin-sidebar"
    class="fixed top-0 left-0 z-40 w-64 h-screen pt-20 transition-transform -translate-x-full bg-gray-900 border-r border-gray-800 md:translate-x-0"
    aria-label="Sidebar">

    <div class="h-full px-3 pb-4 overflow-y-auto bg-gray-900">
        <ul class="space-y-2 font-medium">

            @foreach ($menus as $menu)
                @php
                    $children = $menu->children;
                @endphp

                @if ($children->count() > 0)
                    <li>
                        <div class="px-2 py-2 text-xs font-semibold text-gray-400 uppercase">
                            {{ $menu->title }}
                        </div>

                        <ul class="space-y-1">
                            @foreach ($children as $child)
                                <li>
                                    <a href="{{ $getHref($child->route) }}"
                                        class="flex items-center p-2 pl-4 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white
                                       {{ $isActive($child->route) ? 'bg-gray-700 text-white' : '' }}">
                                        <span>{{ $child->title }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </li>
                @else
                    <li>
                        <a href="{{ $getHref($menu->route) }}"
                            class="flex items-center p-2 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white
                           {{ $isActive($menu->route) ? 'bg-gray-700 text-white' : '' }}">
                            <span>{{ $menu->title }}</span>
                        </a>
                    </li>
                @endif
            @endforeach

        </ul>
    </div>
</aside>
