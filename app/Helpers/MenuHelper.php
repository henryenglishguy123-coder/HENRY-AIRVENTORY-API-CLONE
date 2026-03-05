<?php

use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

function isMenuActive($menu)
{
    $currentPath = trim(Request::path(), '/');
    if ($menu->url) {
        $menuUrl = url($menu->url);
        $menuPath = trim(parse_url($menuUrl, PHP_URL_PATH), '/');
        if ($currentPath === $menuPath || Str::startsWith($currentPath, $menuPath.'/')) {
            return true;
        }
    }
    foreach ($menu->children as $child) {
        if (isMenuActive($child)) {
            return true;
        }
    }

    return false;
}

function menuUrl($menu)
{
    if (! $menu->url) {
        return 'javascript:void(0);';
    }

    return Route::has($menu->url) ? route($menu->url) : url($menu->url);
}
