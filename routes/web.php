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

    // The constraint matters: {post} binds by id, so a non-numeric segment would
    // otherwise reach the database and throw, 500ing a public route. It's bounded to
    // 18 digits (safe max for a signed 64-bit id) rather than whereNumber()'s unbounded
    // [0-9]+, which would let an arbitrarily long digit string reach the query and
    // overflow a bigint column on Postgres.
    Route::get('/preview/{post}', [BlogController::class, 'preview'])
        ->middleware('signed')
        ->where('post', '[0-9]{1,18}')
        ->name('blog.preview');

    if (config('ink.features.feed', false)) {
        Route::get('/feed', [BlogController::class, 'feed'])->name('blog.feed');
    }

    Route::get('/{slug}', [BlogController::class, 'show'])->name('blog.show');
});
