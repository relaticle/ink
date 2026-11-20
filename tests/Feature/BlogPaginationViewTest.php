<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Relaticle\Ink\InkServiceProvider;
use Relaticle\Ink\Models\Category;
use Relaticle\Ink\Models\Post;
use Relaticle\Ink\Models\Tag;

beforeEach(function () {
    config()->set('ink.features.public_routes', true);
    config()->set('ink.per_page', 2);
    config()->set('ink.layout', 'tests::layouts.empty');

    $this->app->register(InkServiceProvider::class, force: true);
    $this->app->getProvider(InkServiceProvider::class)->packageBooted();
    Route::getRoutes()->refreshNameLookups();
});

it('renders numbered pagination with aria-label on the blog index', function () {
    Post::factory(5)->published()->create();

    $response = $this->get('/blog');

    $response->assertOk();
    $response->assertSee('aria-label="Blog pagination"', escape: false);
    $response->assertSee('aria-label="Go to page 2"', escape: false);
});

it('marks the current page with aria-current', function () {
    Post::factory(5)->published()->create();

    $response = $this->get('/blog?page=2');

    $response->assertOk();
    $response->assertSee('aria-current="page"', escape: false);
});

it('serves the archive view with a 404 beyond the filtered last page', function () {
    config()->set('ink.features.tags', true);
    $category = Category::factory()->create();
    $tag = Tag::factory()->create();
    $post = Post::factory()->published()->create(['category_id' => $category->id]);
    $post->tags()->attach($tag);
    Post::factory(2)->published()->create();

    $this->get('/blog?page=2')->assertOk();
    $this->get('/blog?page=3')->assertNotFound()->assertViewIs('ink::pages.index');
    $this->get("/blog/category/{$category->slug}?page=2")->assertNotFound()->assertViewIs('ink::pages.category');
    $this->get("/blog/tag/{$tag->slug}?page=2")->assertNotFound()->assertViewIs('ink::pages.tag');
});

it('serves empty first pages and rejects later pages of an empty search', function () {
    $this->get('/blog')->assertOk();
    $this->get('/blog?q=missing')->assertOk();
    $this->get('/blog?q=missing&page=2')->assertNotFound()->assertViewIs('ink::pages.index');
});
