<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Relaticle\Ink\Http\Controllers\BlogController;

$prefix = config('ink.prefix', 'ink');
$middleware = config('ink.middleware', ['web']);

Route::prefix($prefix)->middleware($middleware)->group(function (): void {
    // The constraint matters: {post} binds by id, so a non-numeric segment would
    // otherwise reach the database and throw, 500ing a public route. It's bounded to
    // 18 digits (safe max for a signed 64-bit id) rather than whereNumber()'s unbounded
    // [0-9]+, which would let an arbitrarily long digit string reach the query and
    // overflow a bigint column on Postgres.
    //
    // Registered whenever either public_routes or mcp is enabled (see
    // InkServiceProvider::packageBooted()): the route is signed-middleware'd and
    // unguessable, so it's safe to expose while the rest of the blog is dark — that's
    // exactly the pre-launch workflow GeneratePreviewUrlTool exists for.
    Route::get('/preview/{post}', [BlogController::class, 'preview'])
        ->middleware('signed')
        ->where('post', '[0-9]{1,18}')
        ->name('blog.preview');
});
