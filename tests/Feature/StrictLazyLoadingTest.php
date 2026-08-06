<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Relaticle\Ink\InkServiceProvider;
use Relaticle\Ink\Models\Category;
use Relaticle\Ink\Models\Post;

/**
 * Hosts commonly enable Model::preventLazyLoading() in non-production. Unlike the
 * single-row show/preview fetches, these paths hydrate collections, so the guard
 * genuinely arms — a missing eager-load here is a 500 for those hosts.
 */
beforeEach(function () {
    config()->set('ink.features.public_routes', true);
    config()->set('ink.features.feed', true);
    config()->set('ink.layout', 'tests::layouts.empty');

    $this->app->register(InkServiceProvider::class, force: true);
    $this->app->getProvider(InkServiceProvider::class)->packageBooted();
    Route::getRoutes()->refreshNameLookups();
});

test('the feed renders under strict lazy loading', function () {
    // Ink ships two feed templates that disagree: pages/feed never outputs <category>,
    // while the x-ink::feed component does. Hosts use the component, so that is the
    // path that must be covered — the page alone would never touch the relation.
    config()->set('ink.views.feed', 'tests::host-feed');

    $category = Category::create(['name' => 'Guides', 'slug' => 'guides']);
    Post::factory(2)->published()->create(['category_id' => $category->id]);

    Model::preventLazyLoading();

    try {
        $this->get(route('blog.feed'))
            ->assertOk()
            ->assertSee('<category>Guides</category>', escape: false);
    } finally {
        Model::preventLazyLoading(false);
    }
});

test('related posts render under strict lazy loading', function () {
    $category = Category::create(['name' => 'Guides', 'slug' => 'guides']);
    $post = Post::factory()->published()->create(['slug' => 'strict-post', 'category_id' => $category->id]);
    Post::factory(2)->published()->create(['category_id' => $category->id]);

    Model::preventLazyLoading();

    try {
        // Touch category on every related post, exactly as a renderer would.
        $categories = $post->relatedPosts()->get()->map(fn (Post $related) => $related->category?->name);

        expect($categories)->toHaveCount(2)
            ->and($categories->filter())->toHaveCount(2);
    } finally {
        Model::preventLazyLoading(false);
    }
});
