<?php

declare(strict_types=1);

use Relaticle\Ink\Mcp\BlogServer;
use Relaticle\Ink\Mcp\Tools\CreatePostTool;
use Relaticle\Ink\Mcp\Tools\ListPostsTool;
use Relaticle\Ink\Models\Category;
use Relaticle\Ink\Tests\Fixtures\Policies\PostPolicy;

function blogArgs(Category $category): array
{
    return [
        'title' => 'From MCP',
        'content' => '## Hello',
        'excerpt' => 'An excerpt.',
        'category_id' => $category->id,
    ];
}

test('a caller the policy allows may list posts', function () {
    PostPolicy::$allow = true;

    BlogServer::actingAs(testUser())
        ->tool(ListPostsTool::class)
        ->assertOk();
});

test('a caller the policy denies may not list posts', function () {
    PostPolicy::$allow = false;

    BlogServer::actingAs(testUser())
        ->tool(ListPostsTool::class)
        ->assertSee('This action is unauthorized.');
});

test('a caller the policy denies may not create a post', function () {
    PostPolicy::$allow = false;
    $category = Category::create(['name' => 'Guides', 'slug' => 'guides']);

    BlogServer::actingAs(testUser())
        ->tool(CreatePostTool::class, blogArgs($category))
        ->assertSee('This action is unauthorized.');
});

test('an unauthenticated caller is rejected', function () {
    BlogServer::tool(ListPostsTool::class)
        ->assertSee('Unauthenticated.');
});
