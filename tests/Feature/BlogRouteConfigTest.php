<?php

declare(strict_types=1);

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
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

test('the preview route constrains the post segment to digits and rejects non-numeric segments', function () {
    bootBlogRoutes();

    // The route-model-binding 404 below is engine-dependent: SQLite's loose type
    // affinity makes `WHERE id = 'not-a-post-id'` against a bigint column return zero
    // rows (ModelNotFoundException) whether or not the constraint is present, whereas
    // Postgres would throw a QueryException without it. Assert on the compiled route's
    // constraint directly so this test actually fails if the constraint is removed.
    $route = Route::getRoutes()->getByName('blog.preview');

    expect($route->wheres)->toHaveKey('post')
        ->and($route->wheres['post'])->toBe('[0-9]{1,18}');

    // Also lock the user-visible contract: a non-numeric segment 404s, not 500s.
    $this->get('/blog/preview/not-a-post-id')->assertNotFound();
});

test('the preview route rejects an over-long digit segment before it reaches the database', function () {
    bootBlogRoutes();

    // whereNumber()'s unbounded [0-9]+ still matches this segment, so the route would
    // match and route-model binding would query the database with it — on Postgres
    // that overflows a bigint column (SQLSTATE[22003] Numeric value out of range), a
    // public unauthenticated 500. SQLite happens to return zero rows instead of
    // throwing, so an `assertNotFound()` alone can't tell the two constraints apart
    // here — assert directly that no query for the post was even issued, which only
    // happens when the router itself rejects the segment before binding runs.
    $queries = collect();

    DB::listen(function (QueryExecuted $query) use ($queries): void {
        if (str_contains($query->sql, 'blog_posts')) {
            $queries->push($query->sql);
        }
    });

    $this->get('/blog/preview/9999999999999999999999999')->assertNotFound();

    expect($queries)->toBeEmpty();
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
