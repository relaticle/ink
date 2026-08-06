<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Relaticle\Ink\InkServiceProvider;
use Relaticle\Ink\Models\Post;

beforeEach(function () {
    config()->set('ink.features.public_routes', true);
    config()->set('ink.layout', 'tests::layouts.empty');

    $this->app->register(InkServiceProvider::class, force: true);
    $this->app->getProvider(InkServiceProvider::class)->packageBooted();
    Route::getRoutes()->refreshNameLookups();
});

test('it renders ink pages when no host view is configured', function () {
    Post::factory()->published()->create(['title' => 'Default rendering']);

    $this->get(route('blog.index'))
        ->assertOk()
        ->assertDontSee('HOST INDEX');
});

test('it renders the host view for the index when configured', function () {
    config()->set('ink.views.index', 'tests::host-index');
    Post::factory(2)->published()->create();

    $this->get(route('blog.index'))
        ->assertOk()
        ->assertSee('HOST INDEX: 2 posts');
});

test('it renders the host view for a post when configured', function () {
    config()->set('ink.views.show', 'tests::host-show');
    $post = Post::factory()->published()->create(['title' => 'Hello host', 'slug' => 'hello-host']);

    $this->get(route('blog.show', 'hello-host'))
        ->assertOk()
        ->assertSee('HOST SHOW: Hello host');
});
