<?php

declare(strict_types=1);

use Relaticle\Ink\Mcp\BlogServer;
use Relaticle\Ink\Mcp\Tools\CreatePostTool;
use Relaticle\Ink\Mcp\Tools\GetPostTool;
use Relaticle\Ink\Mcp\Tools\ListPostsTool;
use Relaticle\Ink\Mcp\Tools\ListTagsTool;
use Relaticle\Ink\Mcp\Tools\UpdatePostTool;
use Relaticle\Ink\Models\Category;
use Relaticle\Ink\Models\Post;
use Relaticle\Ink\Models\Tag;

beforeEach(function () {
    config()->set('ink.features.tags', true);
});

function postArgs(Category $category, array $extra = []): array
{
    return [
        'title' => 'Tagged post',
        'content' => '## Hello',
        'excerpt' => 'An excerpt.',
        'category_id' => $category->id,
        ...$extra,
    ];
}

test('create-post-tool attaches tags by name and returns them', function () {
    $category = Category::factory()->create();

    BlogServer::actingAs(tokenUser())
        ->tool(CreatePostTool::class, postArgs($category, ['tags' => ['MCP', 'Laravel']]))
        ->assertOk();

    $post = Post::where('title', 'Tagged post')->firstOrFail();

    expect($post->tags->pluck('name')->sort()->values()->all())->toBe(['Laravel', 'MCP'])
        ->and(Tag::count())->toBe(2);
});

test('create-post-tool reuses existing tags case-insensitively', function () {
    Tag::create(['name' => 'MCP']);
    $category = Category::factory()->create();

    BlogServer::actingAs(tokenUser())
        ->tool(CreatePostTool::class, postArgs($category, ['tags' => ['mcp', ' Laravel ']]))
        ->assertOk();

    expect(Tag::count())->toBe(2)
        ->and(Tag::pluck('name')->sort()->values()->all())->toBe(['Laravel', 'MCP']);
});

test('update-post-tool replaces the tag set and an empty array clears it', function () {
    $post = Post::factory()->for(Category::factory())->create();
    $post->tags()->sync([Tag::create(['name' => 'Old'])->id]);

    $user = tokenUser();

    BlogServer::actingAs($user)
        ->tool(UpdatePostTool::class, ['id' => $post->id, 'tags' => ['Fresh']])
        ->assertOk();

    expect($post->refresh()->tags->pluck('name')->all())->toBe(['Fresh']);

    BlogServer::actingAs($user)
        ->tool(UpdatePostTool::class, ['id' => $post->id, 'tags' => []])
        ->assertOk();

    expect($post->refresh()->tags)->toHaveCount(0);
});

test('update-post-tool leaves tags untouched when the parameter is omitted', function () {
    $post = Post::factory()->for(Category::factory())->create();
    $post->tags()->sync([Tag::create(['name' => 'Keep'])->id]);

    BlogServer::actingAs(tokenUser())
        ->tool(UpdatePostTool::class, ['id' => $post->id, 'title' => 'Renamed'])
        ->assertOk();

    expect($post->refresh()->tags->pluck('name')->all())->toBe(['Keep']);
});

test('get-post-tool and list-posts-tool include tag names', function () {
    $post = Post::factory()->for(Category::factory())->create();
    $post->tags()->sync([Tag::create(['name' => 'Visible'])->id]);

    $user = tokenUser();

    BlogServer::actingAs($user)
        ->tool(GetPostTool::class, ['id' => $post->id])
        ->assertOk()
        ->assertSee('Visible');

    BlogServer::actingAs($user)
        ->tool(ListPostsTool::class)
        ->assertOk()
        ->assertSee('Visible');
});

test('list-tags-tool returns the vocabulary with post counts', function () {
    $post = Post::factory()->for(Category::factory())->create();
    $tag = Tag::create(['name' => 'Guides']);
    Tag::create(['name' => 'Unused']);
    $post->tags()->sync([$tag->id]);

    BlogServer::actingAs(tokenUser())
        ->tool(ListTagsTool::class)
        ->assertOk()
        ->assertSee('Guides')
        ->assertSee('Unused');
});

test('the tags parameter is rejected when the tags feature is disabled', function () {
    config()->set('ink.features.tags', false);
    $category = Category::factory()->create();

    BlogServer::actingAs(tokenUser())
        ->tool(CreatePostTool::class, postArgs($category, ['tags' => ['MCP']]))
        ->assertHasErrors();

    expect(Post::where('title', 'Tagged post')->exists())->toBeFalse();
});

test('list-tags-tool is not registered when the tags feature is disabled', function () {
    config()->set('ink.features.tags', false);

    expect((new ListTagsTool)->shouldRegister())->toBeFalse();

    config()->set('ink.features.tags', true);

    expect((new ListTagsTool)->shouldRegister())->toBeTrue();
});

test('a non-array tags value is rejected with a clean validation error', function () {
    $category = Category::factory()->create();

    BlogServer::actingAs(tokenUser())
        ->tool(CreatePostTool::class, postArgs($category, ['tags' => 'not-a-list']))
        ->assertHasErrors();
});
