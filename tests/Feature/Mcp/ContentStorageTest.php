<?php

declare(strict_types=1);

use Relaticle\Ink\Mcp\BlogServer;
use Relaticle\Ink\Mcp\Tools\CreatePostTool;
use Relaticle\Ink\Models\Category;
use Relaticle\Ink\Models\Post;

test('a post created through MCP stores markdown, not rendered HTML', function () {
    $category = Category::create(['name' => 'Guides', 'slug' => 'guides']);

    BlogServer::actingAs(tokenUser())->tool(CreatePostTool::class, [
        'title' => 'Markdown storage',
        'content' => "## Heading\n\nSome **bold** copy.",
        'excerpt' => 'An excerpt.',
        'category_id' => $category->id,
    ])->assertOk();

    $post = Post::query()->where('title', 'Markdown storage')->sole();

    expect($post->content)->toBe("## Heading\n\nSome **bold** copy.")
        ->and($post->content)->not->toContain('<h2>');
});

test('MCP and model write paths render identically', function () {
    $category = Category::create(['name' => 'Guides', 'slug' => 'guides']);
    $markdown = "## Heading\n\nSome **bold** copy.";

    BlogServer::actingAs(tokenUser())->tool(CreatePostTool::class, [
        'title' => 'Via MCP',
        'content' => $markdown,
        'excerpt' => 'An excerpt.',
        'category_id' => $category->id,
    ])->assertOk();

    $viaMcp = Post::query()->where('title', 'Via MCP')->sole();
    $viaModel = Post::factory()->published()->create(['content' => $markdown]);

    expect($viaMcp->toHtml())->toBe($viaModel->toHtml());
});

test('it reports a misconfigured host instead of guessing an author', function () {
    config()->set('ink.author_model', stdClass::class);
    $category = Category::create(['name' => 'Guides', 'slug' => 'guides']);

    BlogServer::actingAs(tokenUser())->tool(CreatePostTool::class, [
        'title' => 'No author',
        'content' => '## Heading',
        'excerpt' => 'An excerpt.',
        'category_id' => $category->id,
    ])->assertSee('Configure Ink::resolveAuthorUsing()');
});
