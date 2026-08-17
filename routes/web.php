<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Relaticle\Ink\Http\Controllers\BlogController;

$prefix = config('ink.prefix', 'ink');
$middleware = config('ink.middleware', ['web']);

Route::prefix($prefix)->middleware($middleware)->group(function (): void {
    Route::get('/', [BlogController::class, 'index'])->name('blog.index');

    // The tag route stays unconditional: TagsTest resolves route('blog.tag', ...) with the
    // feature OFF, which would throw RouteNotFoundException if the route disappeared. The
    // runtime abort_unless in tag() remains the gate.
    Route::get('/tag/{slug}', [BlogController::class, 'tag'])->name('blog.tag');

    Route::get('/category/{slug}', [BlogController::class, 'category'])->name('blog.category');

    // blog.preview is registered separately in routes/preview.php — it loads whenever
    // either public_routes or mcp is enabled, since GeneratePreviewUrlTool needs it
    // even while the rest of the public blog is dark.

    if (config('ink.features.feed', false)) {
        Route::get('/feed', [BlogController::class, 'feed'])->name('blog.feed');
    }

    Route::get('/{slug}', [BlogController::class, 'show'])->name('blog.show');
});
