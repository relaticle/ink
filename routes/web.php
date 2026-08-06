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

    // whereNumber matters: {post} binds by id, so a non-numeric segment would otherwise
    // reach the database and throw, 500ing a public route.
    Route::get('/preview/{post}', [BlogController::class, 'preview'])
        ->middleware('signed')
        ->whereNumber('post')
        ->name('blog.preview');

    if (config('ink.features.feed', false)) {
        Route::get('/feed', [BlogController::class, 'feed'])->name('blog.feed');
    }

    Route::get('/{slug}', [BlogController::class, 'show'])->name('blog.show');
});
