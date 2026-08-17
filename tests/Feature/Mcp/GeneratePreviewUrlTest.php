<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Request as McpRequest;
use Laravel\Mcp\Response as McpResponse;
use Relaticle\Ink\InkServiceProvider;
use Relaticle\Ink\Mcp\BlogServer;
use Relaticle\Ink\Mcp\Tools\GeneratePreviewUrlTool;
use Relaticle\Ink\Models\Post;

function bootInkForPreview(bool $publicRoutes, bool $mcp): void
{
    config()->set('ink.features.public_routes', $publicRoutes);
    config()->set('ink.features.mcp', $mcp);
    config()->set('ink.layout', 'tests::layouts.empty');

    app()->register(InkServiceProvider::class, force: true);
    app()->getProvider(InkServiceProvider::class)->packageBooted();
    Route::getRoutes()->refreshNameLookups();
}

test('the preview route is registered when mcp is enabled even with public routes off', function () {
    bootInkForPreview(publicRoutes: false, mcp: true);

    expect(Route::has('blog.preview'))->toBeTrue();
});

test('the preview route is absent when both public routes and mcp are off', function () {
    bootInkForPreview(publicRoutes: false, mcp: false);

    expect(Route::has('blog.preview'))->toBeFalse();
});

test('generate-preview-url-tool returns a working signed url when public routes are off and mcp is on', function () {
    bootInkForPreview(publicRoutes: false, mcp: true);

    $post = Post::factory()->create();
    $this->actingAs(tokenUser());

    $response = (new GeneratePreviewUrlTool)->handle(new McpRequest(['id' => $post->id]));

    expect($response)->toBeInstanceOf(McpResponse::class);

    $url = (string) $response->content();

    expect($url)->toContain('/blog/preview/'.$post->id);

    $this->get($url)->assertOk();
});

test('generate-preview-url-tool returns a working signed url when public routes are on', function () {
    bootInkForPreview(publicRoutes: true, mcp: true);

    $post = Post::factory()->create();
    $this->actingAs(tokenUser());

    $response = (new GeneratePreviewUrlTool)->handle(new McpRequest(['id' => $post->id]));

    $url = (string) $response->content();

    $this->get($url)->assertOk();
});

test('generate-preview-url-tool reports a clear error instead of a masked 500 when the preview route is truly absent', function () {
    bootInkForPreview(publicRoutes: false, mcp: false);

    $post = Post::factory()->create();

    BlogServer::actingAs(tokenUser())
        ->tool(GeneratePreviewUrlTool::class, ['id' => $post->id])
        ->assertDontSee('An internal server error occurred.')
        ->assertSee('preview route is not registered');
});

test('generate-preview-url-tool reports post not found for an unknown id', function () {
    bootInkForPreview(publicRoutes: false, mcp: true);

    BlogServer::actingAs(tokenUser())
        ->tool(GeneratePreviewUrlTool::class, ['id' => 999999])
        ->assertSee('Post not found.');
});
