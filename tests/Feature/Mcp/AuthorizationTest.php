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

    BlogServer::actingAs(tokenUser())
        ->tool(ListPostsTool::class)
        ->assertOk();
});

test('a caller the policy denies may not list posts', function () {
    PostPolicy::$allow = false;

    BlogServer::actingAs(tokenUser())
        ->tool(ListPostsTool::class)
        ->assertSee('This action is unauthorized.');
});

test('a caller the policy denies may not create a post', function () {
    PostPolicy::$allow = false;
    $category = Category::create(['name' => 'Guides', 'slug' => 'guides']);

    BlogServer::actingAs(tokenUser())
        ->tool(CreatePostTool::class, blogArgs($category))
        ->assertSee('This action is unauthorized.');
});

test('an unauthenticated caller is rejected', function () {
    BlogServer::tool(ListPostsTool::class)
        ->assertSee('Unauthenticated.');
});

test('a token without the required ability is rejected even when the policy allows', function () {
    PostPolicy::$allow = true;
    $category = Category::create(['name' => 'Guides', 'slug' => 'guides']);

    BlogServer::actingAs(tokenUser(['posts:read']))
        ->tool(CreatePostTool::class, blogArgs($category))
        ->assertSee('Token missing required ability: posts:create');
});

test('a caller on a non-default guard is resolved and authorized', function () {
    PostPolicy::$allow = true;

    config()->set('auth.guards.staff', ['driver' => 'session', 'provider' => 'users']);
    config()->set('ink.mcp.guard', 'staff');

    BlogServer::actingAs(tokenUser(), 'staff')
        ->tool(ListPostsTool::class)
        ->assertOk();
});

test('a caller absent from the configured guard is rejected', function () {
    PostPolicy::$allow = true;

    config()->set('auth.guards.staff', ['driver' => 'session', 'provider' => 'users']);
    config()->set('ink.mcp.guard', 'staff');

    // Authenticated on the default guard only — the configured guard sees nobody.
    BlogServer::actingAs(tokenUser())
        ->tool(ListPostsTool::class)
        ->assertSee('Unauthenticated.');
});
