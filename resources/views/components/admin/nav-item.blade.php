@props(['item'])
<li>
    @if (count($item['children']))
        <div x-data="{ expanded: {{ $item['active'] ? 'true' : 'false' }} }">
            <button type="button" @click="expanded = !expanded; if (compact) toggleSidebar()" :aria-expanded="expanded" title="{{ $item['label'] }}" class="admin-nav-link w-full justify-between {{ $item['active'] ? 'is-active' : '' }}">
                <span class="flex min-w-0 items-center gap-3"><x-studio-icon :name="$item['icon']" class="h-5 w-5"/><span class="nav-label truncate">{{ $item['label'] }}</span></span><x-studio-icon name="chevron" class="nav-label"/>
            </button>
            <ul x-show="expanded && !compact" class="ml-3 mt-1 space-y-1 border-l border-slate-600 pl-2">
                @if ($item['href'])<li><a href="{{ $item['href'] }}" class="admin-nav-link" @click="closeDrawer()">Buka {{ $item['label'] }}</a></li>@endif
                @foreach ($item['children'] as $child)<x-admin.nav-item :item="$child"/>@endforeach
            </ul>
        </div>
    @elseif ($item['href'])
        <a href="{{ $item['href'] }}" @click="closeDrawer()" title="{{ $item['label'] }}" @if ($item['active']) aria-current="page" @endif class="admin-nav-link {{ $item['active'] ? 'is-active' : '' }}"><x-studio-icon :name="$item['icon']" class="h-5 w-5"/><span class="nav-label truncate">{{ $item['label'] }}</span></a>
    @endif
</li>
