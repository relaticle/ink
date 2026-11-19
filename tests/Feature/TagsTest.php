<?php

declare(strict_types=1);

use Filament\Forms\Components\FileUpload;
use Relaticle\Ink\Filament\Resources\PostResource;
use Relaticle\Ink\InkServiceProvider;
use Relaticle\Ink\Models\Post;
use Relaticle\Ink\Models\Tag;

test('a post can have many tags', function () {
    $post = Post::factory()->published()->create();
    $a = Tag::factory()->create(['name' => 'Laravel']);
    $b = Tag::factory()->create(['name' => 'Filament']);

    $post->tags()->attach([$a->id, $b->id]);

    expect($post->fresh()->tags)->toHaveCount(2);
    expect($a->fresh()->posts)->toHaveCount(1);
});

test('tag slug is auto-generated from name', function () {
    $tag = Tag::factory()->create(['name' => 'Hello World']);

    expect($tag->slug)->toBe('hello-world');
});

test('tag slug remains stable on rename', function () {
    $tag = Tag::factory()->create(['name' => 'Initial Name']);
    $originalSlug = $tag->slug;

    $tag->update(['name' => 'Renamed Tag']);

    expect($tag->fresh()->slug)->toBe($originalSlug);
});

test('detaching tags removes pivot rows but keeps post and tag', function () {
    $post = Post::factory()->published()->create();
    $tag = Tag::factory()->create();

    $post->tags()->attach($tag);
    expect($post->fresh()->tags)->toHaveCount(1);

    $post->tags()->detach($tag);
    expect($post->fresh()->tags)->toHaveCount(0);
    expect(Tag::find($tag->id))->not->toBeNull();
});

test('deleting a post cascades pivot rows', function () {
    $post = Post::factory()->published()->create();
    $tag = Tag::factory()->create();
    $post->tags()->attach($tag);

    $post->forceDelete();

    expect(DB::table('blog_post_tag')->count())->toBe(0);
});

test('tag archive route 404s when tags feature disabled', function () {
    config()->set('ink.features.tags', false);
    config()->set('ink.features.public_routes', true);
    config()->set('ink.layout', 'tests::layouts.empty');
    $this->app->register(InkServiceProvider::class, force: true);
    $this->app->getProvider(InkServiceProvider::class)->packageBooted();

    $tag = Tag::factory()->create(['name' => 'Disabled']);

    $this->get(route('blog.tag', $tag->slug))->assertNotFound();
});

test('tag archive route lists posts attached to that tag when feature enabled', function () {
    config()->set('ink.features.tags', true);
    config()->set('ink.features.public_routes', true);
    config()->set('ink.layout', 'tests::layouts.empty');
    $this->app->register(InkServiceProvider::class, force: true);
    $this->app->getProvider(InkServiceProvider::class)->packageBooted();

    $tag = Tag::factory()->create(['name' => 'Laravel']);
    $included = Post::factory()->published()->create(['title' => 'Tagged post']);
    $included->tags()->attach($tag);
    Post::factory()->published()->create(['title' => 'Untagged post']);

    $this->get(route('blog.tag', $tag->slug))
        ->assertOk()
        ->assertSeeText('Tagged post')
        ->assertDontSeeText('Untagged post');
});

test('PostResource featured image field uses plain FileUpload by default', function () {
    config()->set('ink.features.media_library', false);

    $field = (new ReflectionMethod(PostResource::class, 'featuredImageField'))
        ->invoke(null);

    expect($field)->toBeInstanceOf(FileUpload::class);
});

test('PostResource featured image field falls back to FileUpload when MediaLibrary class missing', function () {
    // The Spatie class doesn't ship with this test environment, so even with
    // the flag on, we should fall back to the plain component (no crash).
    config()->set('ink.features.media_library', true);

    $field = (new ReflectionMethod(PostResource::class, 'featuredImageField'))
        ->invoke(null);

    expect($field)->toBeInstanceOf(FileUpload::class);
});

it('refuses an svg featured image', function () {
    config()->set('ink.features.media_library', false);

    $field = (new ReflectionMethod(PostResource::class, 'featuredImageField'))
        ->invoke(null);

    expect($field->getAcceptedFileTypes())
        ->toBe(['image/jpeg', 'image/png', 'image/gif', 'image/webp'])
        ->not->toContain('image/svg+xml');
});
