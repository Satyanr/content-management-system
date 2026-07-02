<aside id="admin-sidebar"
       class="fixed top-0 left-0 z-40 w-64 h-screen pt-20 transition-transform -translate-x-full bg-gray-900 border-r border-gray-800 md:translate-x-0"
       aria-label="Sidebar">

    <div class="h-full px-3 pb-4 overflow-y-auto bg-gray-900">
        <ul class="space-y-2 font-medium">

            <li>
                <a href="{{ route('admin.dashboard') }}"
                   class="flex items-center p-2 rounded-lg text-white hover:bg-gray-700
                   {{ request()->routeIs('admin.dashboard') ? 'bg-gray-700' : '' }}">
                    <span>Dashboard</span>
                </a>
            </li>

            <li>
                <a href="#"
                   class="flex items-center p-2 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white">
                    <span>Users</span>
                </a>
            </li>

            <li>
                <a href="#"
                   class="flex items-center p-2 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white">
                    <span>Roles & Permissions</span>
                </a>
            </li>

            <li>
                <a href="#"
                   class="flex items-center p-2 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white">
                    <span>Content</span>
                </a>
            </li>

            <li>
                <a href="#"
                   class="flex items-center p-2 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white">
                    <span>Playlist</span>
                </a>
            </li>

            <li>
                <a href="#"
                   class="flex items-center p-2 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white">
                    <span>Scheduler</span>
                </a>
            </li>

            <li>
                <a href="#"
                   class="flex items-center p-2 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white">
                    <span>Devices</span>
                </a>
            </li>

            <li>
                <a href="#"
                   class="flex items-center p-2 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white">
                    <span>Monitoring</span>
                </a>
            </li>

            <li>
                <a href="#"
                   class="flex items-center p-2 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white">
                    <span>Settings</span>
                </a>
            </li>

        </ul>
    </div>
</aside>