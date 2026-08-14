<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Relaticle\Ink\Mcp\BlogServer;
use Relaticle\Ink\Mcp\Tools\UploadImageTool;

// A genuine 1x1 transparent PNG.
const PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

// A genuine 1x1 transparent GIF.
const GIF_BASE64 = 'R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==';

beforeEach(function () {
    Storage::fake('public');
});

/** Runs the tool and returns the structured `path` it reports, asserting the call succeeded. */
function uploadAndCapturePath(array $arguments): string
{
    $path = null;

    BlogServer::actingAs(tokenUser())->tool(UploadImageTool::class, $arguments)
        ->assertOk()
        ->assertStructuredContent(function ($json) use (&$path) {
            $json->has('path')
                ->has('url')
                ->has('markdown')
                ->where('path', function ($value) use (&$path) {
                    $path = $value;

                    return true;
                })
                ->etc();
        });

    return $path;
}

test('uploading a base64 image stores it and returns path, url and markdown', function () {
    $path = uploadAndCapturePath(['data' => PNG_BASE64, 'alt' => 'A test image']);

    expect($path)->toStartWith('ink/')
        ->and($path)->toEndWith('.png');

    Storage::disk('public')->assertExists($path);
});

test('uploading an image via a fetchable url stores it', function () {
    Http::fake([
        'https://example.test/photo.png' => Http::response(base64_decode(PNG_BASE64), 200, ['Content-Type' => 'image/png']),
    ]);

    $path = uploadAndCapturePath(['url' => 'https://example.test/photo.png']);

    expect($path)->toStartWith('ink/');
    Storage::disk('public')->assertExists($path);
});

test('an svg is rejected even though it looks like a valid image file', function () {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="1" height="1"><script>alert(1)</script></svg>';

    BlogServer::actingAs(tokenUser())->tool(UploadImageTool::class, [
        'data' => base64_encode($svg),
    ])->assertSee('Unsupported image type');

    expect(Storage::disk('public')->allFiles('ink'))->toBeEmpty();
});

test('an image over the configured max size is rejected', function () {
    config()->set('ink.uploads.max_bytes', 10);

    BlogServer::actingAs(tokenUser())->tool(UploadImageTool::class, [
        'data' => PNG_BASE64,
    ])->assertSee('exceeds the maximum upload size');

    expect(Storage::disk('public')->allFiles('ink'))->toBeEmpty();
});

test('the stored extension is derived from sniffed content, not a spoofed filename', function () {
    $path = uploadAndCapturePath(['data' => PNG_BASE64, 'filename' => 'totally-a-photo.jpg']);

    expect($path)->toEndWith('.png');
});

test('a gif is accepted', function () {
    $path = uploadAndCapturePath(['data' => GIF_BASE64]);

    expect($path)->toEndWith('.gif');
});

test('providing neither url nor data is rejected', function () {
    BlogServer::actingAs(tokenUser())->tool(UploadImageTool::class, [])
        ->assertHasErrors();
});

test('providing both url and data is rejected', function () {
    BlogServer::actingAs(tokenUser())->tool(UploadImageTool::class, [
        'url' => 'https://example.test/photo.png',
        'data' => PNG_BASE64,
    ])->assertSee('not both');
});

test('an unauthenticated caller is rejected', function () {
    BlogServer::tool(UploadImageTool::class, ['data' => PNG_BASE64])
        ->assertSee('Unauthenticated.');
});

test('a token without posts:create is rejected', function () {
    BlogServer::actingAs(tokenUser(['posts:read']))
        ->tool(UploadImageTool::class, ['data' => PNG_BASE64])
        ->assertSee('Token missing required ability: posts:create');
});
