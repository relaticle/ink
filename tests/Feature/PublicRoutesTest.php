<?php

declare(strict_types=1);

use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Relaticle\Ink\InkServiceProvider;
use Relaticle\Ink\Models\Category;
use Relaticle\Ink\Models\Post;

beforeEach(function () {
    config()->set('ink.features.public_routes', true);
    config()->set('ink.layout', 'tests::layouts.empty');

    // Re-boot the package so routes register with the just-set config flag.
    $this->app->register(InkServiceProvider::class, force: true);
    $this->app->getProvider(InkServiceProvider::class)->packageBooted();
});

test('public index route returns published posts when feature enabled', function () {
    Post::factory()->published()->create(['title' => 'Hello world']);

    $this->get(route('blog.index'))
        ->assertOk()
        ->assertSeeText('Hello world');
});

test('public index route is not registered when feature disabled', function () {
    config()->set('ink.features.public_routes', false);

    // Simulate fresh boot with feature off
    $this->refreshApplication();
    config()->set('ink.features.public_routes', false);

    expect(Route::has('blog.index'))->toBeFalse();
});

test('public show route returns the post by slug', function () {
    Post::factory()->published()->create([
        'title' => 'My Post',
        'slug' => 'my-post',
        'content' => 'Hello body content',
    ]);

    $this->get(route('blog.show', 'my-post'))
        ->assertOk()
        ->assertSeeText('My Post');
});

test('public show 404s on draft post', function () {
    Post::factory()->draft()->create(['slug' => 'unpublished']);

    $this->get(route('blog.show', 'unpublished'))->assertNotFound();
});

test('public show 404s on scheduled (future) post', function () {
    Post::factory()->scheduled()->create(['slug' => 'tomorrow']);

    $this->get(route('blog.show', 'tomorrow'))->assertNotFound();
});

test('public category route lists posts in that category', function () {
    $cat = Category::factory()->create(['name' => 'News']);
    Post::factory()->published()->create([
        'title' => 'In category',
        'category_id' => $cat->id,
    ]);
    Post::factory()->published()->create(['title' => 'Out of category']);

    $this->get(route('blog.category', $cat->slug))
        ->assertOk()
        ->assertSeeText('In category')
        ->assertDontSeeText('Out of category');
});

test('preview route renders draft when signature valid', function () {
    $post = Post::factory()->draft()->create([
        'title' => 'Draft preview',
        'slug' => 'draft-preview',
    ]);

    $url = URL::temporarySignedRoute(
        'blog.preview', now()->addHour(), ['post' => $post->id]
    );

    $this->get($url)
        ->assertOk()
        ->assertSeeText('Draft preview');
});

test('preview route 403s without signature', function () {
    $post = Post::factory()->draft()->create();

    $this->get(route('blog.preview', $post))->assertForbidden();
});

test('feed route returns RSS XML when feed feature enabled', function () {
    config()->set('ink.features.feed', true);

    // Clear the routes booted by beforeEach() before re-registering: otherwise the
    // catch-all `/{slug}` from that first (feed-disabled) boot stays first in line and
    // intercepts `/feed` before the newly-added, correctly-gated feed route is reached.
    app('router')->setRoutes(new RouteCollection);
    $this->app->register(InkServiceProvider::class, force: true);
    $this->app->getProvider(InkServiceProvider::class)->packageBooted();
    Route::getRoutes()->refreshNameLookups();

    Post::factory()->published()->create(['title' => 'Hello feed']);

    $response = $this->get(route('blog.feed'));
    $response->assertOk();
    expect($response->headers->get('Content-Type'))
        ->toStartWith('application/rss+xml');
    expect($response->getContent())->toContain('<rss');
    expect($response->getContent())->toContain('Hello feed');
});

test('feed route does not exist when feed feature disabled', function () {
    config()->set('ink.features.feed', false);

    expect(Route::has('blog.feed'))->toBeFalse();

    $this->get('/blog/feed')->assertNotFound();
});
