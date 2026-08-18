<?php

declare(strict_types=1);

use Relaticle\Ink\Mcp\BlogServer;
use Relaticle\Ink\Mcp\Tools\CreatePostTool;
use Relaticle\Ink\Mcp\Tools\UpdatePostTool;
use Relaticle\Ink\Models\Category;
use Relaticle\Ink\Models\Post;

test('create-post-tool accepts an explicit slug', function () {
    BlogServer::actingAs(tokenUser())->tool(CreatePostTool::class, [
        'title' => 'Some Long Marketing Title',
        'slug' => 'short-slug',
        'content' => '## Hello',
        'excerpt' => 'An excerpt.',
        'category_id' => Category::factory()->create()->id,
    ])->assertOk();

    expect(Post::query()->where('title', 'Some Long Marketing Title')->sole()->slug)
        ->toBe('short-slug');
});

test('create-post-tool derives the slug from the title when none is given', function () {
    BlogServer::actingAs(tokenUser())->tool(CreatePostTool::class, [
        'title' => 'Derived From Title',
        'content' => '## Hello',
        'excerpt' => 'An excerpt.',
        'category_id' => Category::factory()->create()->id,
    ])->assertOk();

    expect(Post::query()->where('title', 'Derived From Title')->sole()->slug)
        ->toBe('derived-from-title');
});

test('create-post-tool rejects a slug already held by a live post', function () {
    Post::factory()->create(['slug' => 'taken']);

    BlogServer::actingAs(tokenUser())->tool(CreatePostTool::class, [
        'title' => 'Colliding post',
        'slug' => 'taken',
        'content' => '## Hello',
        'excerpt' => 'An excerpt.',
        'category_id' => Category::factory()->create()->id,
    ])->assertHasErrors();

    expect(Post::query()->where('title', 'Colliding post')->exists())->toBeFalse();
});

test('update-post-tool renames a slug', function () {
    $post = Post::factory()->create(['slug' => 'original-1']);

    BlogServer::actingAs(tokenUser())->tool(UpdatePostTool::class, [
        'id' => $post->id,
        'slug' => 'original',
    ])->assertOk();

    expect($post->fresh()->slug)->toBe('original');
});

test('update-post-tool rejects a slug held by another live post', function () {
    Post::factory()->create(['slug' => 'taken']);
    $post = Post::factory()->create(['slug' => 'mine']);

    BlogServer::actingAs(tokenUser())->tool(UpdatePostTool::class, [
        'id' => $post->id,
        'slug' => 'taken',
    ])->assertHasErrors();

    expect($post->fresh()->slug)->toBe('mine');
});

test('update-post-tool accepts a slug only a trashed post still holds', function () {
    Post::factory()->create(['slug' => 'reclaimable'])->delete();
    $post = Post::factory()->create(['slug' => 'reclaimable-1']);

    BlogServer::actingAs(tokenUser())->tool(UpdatePostTool::class, [
        'id' => $post->id,
        'slug' => 'reclaimable',
    ])->assertOk();

    expect($post->fresh()->slug)->toBe('reclaimable');
});

test('update-post-tool lets a post keep its own slug', function () {
    $post = Post::factory()->create(['slug' => 'unchanged']);

    BlogServer::actingAs(tokenUser())->tool(UpdatePostTool::class, [
        'id' => $post->id,
        'slug' => 'unchanged',
        'title' => 'New Title',
    ])->assertOk();

    expect($post->fresh()->slug)->toBe('unchanged');
});
