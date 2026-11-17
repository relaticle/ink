<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Relaticle\Ink\Http\Controllers\BlogController;
use Relaticle\Ink\Ink;

$prefix = config('ink.prefix', 'ink');
$middleware = config('ink.middleware', ['web']);

foreach (Ink::locales() as $locale) {
    $isDefault = $locale === Ink::defaultLocale();

    Route::prefix($isDefault ? $prefix : "{$locale}/{$prefix}")
        ->middleware($middleware)
        ->group(function () use ($locale, $isDefault): void {
            $register = function (string $uri, string $action, string $key) use ($locale, $isDefault): void {
                $route = Route::get($uri, [BlogController::class, $action])->name(Ink::routeName($key, $locale));

                if (! $isDefault) {
                    $route->defaults('locale', $locale);
                }
            };

            $register('/', 'index', 'index');

            // The tag route stays unconditional: TagsTest resolves route('blog.tag', ...) with the
            // feature OFF, which would throw RouteNotFoundException if the route disappeared. The
            // runtime abort_unless in tag() remains the gate.
            $register('/tag/{slug}', 'tag', 'tag');

            $register('/category/{slug}', 'category', 'category');

            // blog.preview is registered separately in routes/preview.php. It loads whenever
            // either public_routes or mcp is enabled, since GeneratePreviewUrlTool needs it
            // even while the rest of the public blog is dark.

            if (config('ink.features.feed', false)) {
                $register('/feed', 'feed', 'feed');
            }

            $register('/{slug}', 'show', 'show');
        });
}
