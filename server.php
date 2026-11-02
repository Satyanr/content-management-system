<?php

// Laravel's development router normally bypasses PHP for existing static files.
// Deny legacy storage links before that shortcut, without moving/removing files.
$publicPath = __DIR__.'/public';
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '');
$normalize = static fn ($path) => PHP_OS_FAMILY === 'Windows' ? strtolower(str_replace('\\', '/', $path)) : str_replace('\\', '/', $path);
$storageRoot = realpath($publicPath.'/storage');
$target = realpath($publicPath.$uri);
if (preg_match('#^/storage(?:/|$)#i', $uri) || ($storageRoot && $target && ($normalize($target) === $normalize($storageRoot) || str_starts_with($normalize($target), $normalize($storageRoot).'/')))) {
    http_response_code(403);
    header('Cache-Control: no-store');
    echo 'Direct public storage access is disabled.';

    return true;
}
chdir($publicPath);
// Propagate false so PHP's built-in server actually serves static assets.
return require __DIR__.'/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php';
