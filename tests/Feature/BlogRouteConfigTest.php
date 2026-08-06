<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Relaticle\Ink\InkServiceProvider;

function bootBlogRoutes(array $config = []): void
{
    config()->set('ink.features.public_routes', true);
    config()->set('ink.layout', 'tests::layouts.empty');

    foreach ($config as $key => $value) {
        config()->set($key, $value);
    }

    app()->register(InkServiceProvider::class, force: true);
    app()->getProvider(InkServiceProvider::class)->packageBooted();
    Route::getRoutes()->refreshNameLookups();
}

test('a non-numeric preview segment does not reach route model binding', function () {
    bootBlogRoutes();

    // Without whereNumber this casts to bigint and throws a QueryException — a 500
    // on a public, unauthenticated route.
    $this->get('/blog/preview/not-a-post-id')->assertNotFound();
});

test('the feed route is absent when the feature is off', function () {
    bootBlogRoutes(['ink.features.feed' => false]);

    expect(Route::has('blog.feed'))->toBeFalse();
});

test('the feed route is present when the feature is on', function () {
    bootBlogRoutes(['ink.features.feed' => true]);

    expect(Route::has('blog.feed'))->toBeTrue();
});

test('configured middleware is applied to the blog routes', function () {
    bootBlogRoutes(['ink.middleware' => ['web', 'throttle:7,1']]);

    $middleware = Route::getRoutes()->getByName('blog.index')->gatherMiddleware();

    expect($middleware)->toContain('throttle:7,1');
});
