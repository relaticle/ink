<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Relaticle\Ink\Ink;
use Relaticle\Ink\InkServiceProvider;
use Relaticle\Ink\Models\Post;
use Relaticle\Ink\Models\Tag;

beforeEach(function () {
    config()->set('ink.features.public_routes', true);
    config()->set('ink.layout', 'tests::layouts.empty');

    $this->app->register(InkServiceProvider::class, force: true);
    $this->app->getProvider(InkServiceProvider::class)->packageBooted();
    Route::getRoutes()->refreshNameLookups();

    Ink::flushState();
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

test('the preview view receives related posts and a null edit url by default', function () {
    config()->set('ink.views.preview', 'tests::host-preview');
    $post = Post::factory()->create(['title' => 'Draft one']);

    $this->get(URL::temporarySignedRoute('blog.preview', now()->addHour(), ['post' => $post]))
        ->assertOk()
        ->assertSee('HOST PREVIEW: Draft one')
        ->assertSee('edit=none');
});

test('a host hook supplies the preview edit url', function () {
    config()->set('ink.views.preview', 'tests::host-preview');
    Ink::resolvePreviewEditUrlUsing(fn (Post $post): string => "https://admin.test/posts/{$post->id}/edit");

    $post = Post::factory()->create(['title' => 'Draft two']);

    $this->get(URL::temporarySignedRoute('blog.preview', now()->addHour(), ['post' => $post]))
        ->assertOk()
        ->assertSee("edit=https://admin.test/posts/{$post->id}/edit");
});

test('show and preview do not lazy-load tags', function () {
    config()->set('ink.views.show', 'tests::host-show');
    Model::preventLazyLoading();

    $post = Post::factory()->published()->create(['slug' => 'no-lazy']);
    $post->tags()->attach(Tag::factory()->create());

    $this->get(route('blog.show', 'no-lazy'))->assertOk();

    Model::preventLazyLoading(false);
});
