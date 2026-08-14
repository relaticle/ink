<?php

declare(strict_types=1);

use Relaticle\Ink\Enums\PostStatus;
use Relaticle\Ink\Mcp\BlogServer;
use Relaticle\Ink\Mcp\Tools\UpdateCategoryTool;
use Relaticle\Ink\Mcp\Tools\UpdatePostTool;
use Relaticle\Ink\Models\Category;
use Relaticle\Ink\Models\Post;

test('updating a post title persists the new value', function () {
    $post = Post::factory()->create(['title' => 'Old title']);

    BlogServer::actingAs(tokenUser())->tool(UpdatePostTool::class, [
        'id' => $post->id,
        'title' => 'New title',
    ])->assertOk();

    expect($post->fresh()->title)->toBe('New title');
});

test('updating a post content persists the new value', function () {
    $post = Post::factory()->create(['content' => 'Old content']);

    BlogServer::actingAs(tokenUser())->tool(UpdatePostTool::class, [
        'id' => $post->id,
        'content' => 'New content',
    ])->assertOk();

    expect($post->fresh()->content)->toBe('New content');
});

test('updating a post excerpt persists the new value', function () {
    $post = Post::factory()->create(['excerpt' => 'Old excerpt']);

    BlogServer::actingAs(tokenUser())->tool(UpdatePostTool::class, [
        'id' => $post->id,
        'excerpt' => 'New excerpt',
    ])->assertOk();

    expect($post->fresh()->excerpt)->toBe('New excerpt');
});

test('updating a post category persists the new value', function () {
    $oldCategory = Category::factory()->create();
    $newCategory = Category::factory()->create();
    $post = Post::factory()->create(['category_id' => $oldCategory->id]);

    BlogServer::actingAs(tokenUser())->tool(UpdatePostTool::class, [
        'id' => $post->id,
        'category_id' => $newCategory->id,
    ])->assertOk();

    expect($post->fresh()->category_id)->toBe($newCategory->id);
});

test('updating a post status persists the new value', function () {
    $post = Post::factory()->draft()->create();

    BlogServer::actingAs(tokenUser())->tool(UpdatePostTool::class, [
        'id' => $post->id,
        'status' => PostStatus::Published->value,
        'published_at' => now()->toIso8601String(),
    ])->assertOk();

    expect($post->fresh()->status)->toBe(PostStatus::Published);
});

test('updating a post published_at persists the new value', function () {
    $post = Post::factory()->create(['published_at' => null]);
    $publishedAt = now()->addDay()->second(0);

    BlogServer::actingAs(tokenUser())->tool(UpdatePostTool::class, [
        'id' => $post->id,
        'published_at' => $publishedAt->toIso8601String(),
    ])->assertOk();

    // Carbon::parse() on the ISO 8601 string drops the sub-second precision
    // `now()` carries, so compare at the string's own resolution rather than
    // via `equalTo()`, which would fail on microseconds no API round-trips.
    expect($post->fresh()->published_at->toIso8601String())->toBe($publishedAt->toIso8601String());
});

test('updating a post seo title and description persists the new values', function () {
    $post = Post::factory()->create();

    BlogServer::actingAs(tokenUser())->tool(UpdatePostTool::class, [
        'id' => $post->id,
        'seo_title' => 'New SEO title',
        'seo_description' => 'New SEO description',
    ])->assertOk();

    $post->fresh();

    expect($post->seo->title)->toBe('New SEO title')
        ->and($post->seo->description)->toBe('New SEO description');
});

test('updating a post advances updated_at', function () {
    $post = Post::factory()->create(['title' => 'Old title']);
    $originalUpdatedAt = $post->updated_at;

    $this->travelTo(now()->addMinute());

    BlogServer::actingAs(tokenUser())->tool(UpdatePostTool::class, [
        'id' => $post->id,
        'title' => 'New title',
    ])->assertOk();

    expect($post->fresh()->updated_at->greaterThan($originalUpdatedAt))->toBeTrue();
});

test('updating a post with a partial payload leaves other fields untouched', function () {
    $post = Post::factory()->create([
        'title' => 'Original title',
        'excerpt' => 'Original excerpt',
    ]);

    BlogServer::actingAs(tokenUser())->tool(UpdatePostTool::class, [
        'id' => $post->id,
        'title' => 'Updated title',
    ])->assertOk();

    $fresh = $post->fresh();

    expect($fresh->title)->toBe('Updated title')
        ->and($fresh->excerpt)->toBe('Original excerpt');
});

test('the update-post-tool response reflects the new values, not the stale record', function () {
    $post = Post::factory()->create(['title' => 'Old title']);

    $result = BlogServer::actingAs(tokenUser())->tool(UpdatePostTool::class, [
        'id' => $post->id,
        'title' => 'Brand new title',
    ])->assertOk();

    $result->assertSee('Brand new title')
        ->assertDontSee('Old title');
});

test('updating a category name persists the new value', function () {
    $category = Category::factory()->create(['name' => 'Old name']);

    BlogServer::actingAs(tokenUser())->tool(UpdateCategoryTool::class, [
        'id' => $category->id,
        'name' => 'New name',
    ])->assertOk();

    expect($category->fresh()->name)->toBe('New name');
});

test('the update-category-tool response reflects the new value, not the stale record', function () {
    $category = Category::factory()->create(['name' => 'Old name']);

    $result = BlogServer::actingAs(tokenUser())->tool(UpdateCategoryTool::class, [
        'id' => $category->id,
        'name' => 'Brand new name',
    ])->assertOk();

    $result->assertSee('Brand new name')
        ->assertDontSee('Old name');
});
