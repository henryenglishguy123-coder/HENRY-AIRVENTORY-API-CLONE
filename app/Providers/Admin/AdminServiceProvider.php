<?php

namespace App\Providers\Admin;

use App\Models\Admin\AdminMenu;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AdminServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        View::composer('admin.partials.sidebar', function ($view) {
            $menus = Cache::rememberForever('admin_sidebar_menus', function () {
                return AdminMenu::with(['children' => function ($q) {
                    $q->with('children')->where('status', '1');
                }])
                    ->where('parent_id', 0)
                    ->where('status', '1')
                    ->orderBy('order', 'asc')
                    ->get();
            });

            $view->with('menus', $menus);
        });

    }
}
