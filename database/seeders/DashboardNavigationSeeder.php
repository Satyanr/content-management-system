<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Seeder;

class DashboardNavigationSeeder extends Seeder
{
    public function run(): void
    {
        $parent = Menu::where('title', 'CMS Core')->whereNull('parent_id')->first();
        foreach ([['Activity Logs', 'admin.activity-logs.index', 'activity', 'activity_logs.view', 9], ['Login History', 'admin.login-histories.index', 'history', 'login_histories.view', 10]] as [$title, $route, $icon, $permission, $sort]) {
            // Existing menu edits (including disabled or relocated entries) always win.
            if (! Menu::where('route', $route)->exists()) {
                Menu::create(['title' => $title, 'route' => $route, 'icon' => $icon, 'permission' => $permission, 'sort_order' => $sort, 'parent_id' => $parent?->id, 'is_active' => true]);
            }
        }
    }
}
