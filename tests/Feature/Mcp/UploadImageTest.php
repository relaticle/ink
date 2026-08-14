<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Relaticle\Ink\Mcp\BlogServer;
use Relaticle\Ink\Mcp\Tools\UploadImageTool;
use Relaticle\Ink\Tests\Fixtures\CountingStream;

// A genuine 1x1 transparent PNG.
const PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

// A genuine 1x1 transparent GIF.
const GIF_BASE64 = 'R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==';

// A genuine 1x1 red JPEG.
const JPEG_BASE64 = '/9j/4AAQSkZJRgABAQEAYABgAAD//gA7Q1JFQVRPUjogZ2QtanBlZyB2MS4wICh1c2luZyBJSkcgSlBFRyB2NjIpLCBxdWFsaXR5ID0gOTAK/9sAQwADAgIDAgIDAwMDBAMDBAUIBQUEBAUKBwcGCAwKDAwLCgsLDQ4SEA0OEQ4LCxAWEBETFBUVFQwPFxgWFBgSFBUU/9sAQwEDBAQFBAUJBQUJFA0LDRQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQU/8AAEQgAAQABAwEiAAIRAQMRAf/EAB8AAAEFAQEBAQEBAAAAAAAAAAABAgMEBQYHCAkKC//EALUQAAIBAwMCBAMFBQQEAAABfQECAwAEEQUSITFBBhNRYQcicRQygZGhCCNCscEVUtHwJDNicoIJChYXGBkaJSYnKCkqNDU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6g4SFhoeIiYqSk5SVlpeYmZqio6Slpqeoqaqys7S1tre4ubrCw8TFxsfIycrS09TV1tfY2drh4uPk5ebn6Onq8fLz9PX29/j5+v/EAB8BAAMBAQEBAQEBAQEAAAAAAAABAgMEBQYHCAkKC//EALURAAIBAgQEAwQHBQQEAAECdwABAgMRBAUhMQYSQVEHYXETIjKBCBRCkaGxwQkjM1LwFWJy0QoWJDThJfEXGBkaJicoKSo1Njc4OTpDREVGR0hJSlNUVVZXWFlaY2RlZmdoaWpzdHV2d3h5eoKDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uLj5OXm5+jp6vLz9PX29/j5+v/aAAwDAQACEQMRAD8A+dKKKK/DD/VM/9k=';

// A genuine 1x1 green WEBP.
const WEBP_BASE64 = 'UklGRkAAAABXRUJQVlA4IDQAAAAQAgCdASoBAAEAAMASJaACdLoB+AH6AAPIAP7uvZ//cvafonw/7qK//lB+EYg2/+ViAAAA';

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

test('the default max upload size stays safely under a typical post_max_size floor once base64-inflated', function () {
    // The base64 `data` path is bound by PHP's post_max_size before this app-level
    // cap ever runs — an oversized payload is rejected by PHP itself with a raw
    // warning in an HTTP 200, invisible to this suite by nature. This test instead
    // locks the *default*'s safety margin: base64-inflated (the same ~4/3 estimate
    // decode() itself uses), it must stay comfortably under a common 5M
    // post_max_size floor, so the shipped default doesn't reintroduce the gap.
    $maxBytes = config('ink.uploads.max_bytes');
    $worstCaseEncodedBytes = ((int) ceil($maxBytes / 3)) * 4 + 4;

    expect($worstCaseEncodedBytes)->toBeLessThan(5 * 1024 * 1024);
});

test('an image over the configured max size is rejected', function () {
    config()->set('ink.uploads.max_bytes', 10);

    BlogServer::actingAs(tokenUser())->tool(UploadImageTool::class, [
        'data' => PNG_BASE64,
    ])->assertSee('exceeds the maximum upload size');

    expect(Storage::disk('public')->allFiles('ink'))->toBeEmpty();
});

test('an oversized base64 payload is rejected by its encoded length before it is decoded', function () {
    config()->set('ink.uploads.max_bytes', 100);

    // A real, validly-encoded payload whose decoded size (150 bytes) sits just over
    // the 100-byte cap. The encoded-length pre-check in decode() must reject this
    // before base64_decode() ever runs on it.
    $justOverCap = base64_encode(str_repeat('x', 150));

    BlogServer::actingAs(tokenUser())->tool(UploadImageTool::class, [
        'data' => $justOverCap,
    ])->assertSee('exceeds the maximum upload size');

    expect(Storage::disk('public')->allFiles('ink'))->toBeEmpty();
});

test('a url response advertising an oversized Content-Length is rejected before the body is downloaded', function () {
    config()->set('ink.uploads.max_bytes', 1024);

    Http::fake(function ($request) {
        if ($request->method() === 'HEAD') {
            return Http::response('', 200, ['Content-Length' => '999999']);
        }

        return Http::response(str_repeat('x', 999999), 200, ['Content-Type' => 'image/png']);
    });

    BlogServer::actingAs(tokenUser())->tool(UploadImageTool::class, [
        'url' => 'https://example.test/huge.png',
    ])->assertSee('exceeds the maximum upload size');

    Http::assertNotSent(fn ($request) => $request->method() === 'GET');
    expect(Storage::disk('public')->allFiles('ink'))->toBeEmpty();
});

test('a url response that never advertises Content-Length is still rejected once downloaded and measured', function () {
    config()->set('ink.uploads.max_bytes', 1024);

    Http::fake([
        'https://example.test/huge-no-length.png' => Http::response(str_repeat('x', 999999), 200, ['Content-Type' => 'image/png']),
    ]);

    BlogServer::actingAs(tokenUser())->tool(UploadImageTool::class, [
        'url' => 'https://example.test/huge-no-length.png',
    ])->assertSee('exceeds the maximum upload size');

    expect(Storage::disk('public')->allFiles('ink'))->toBeEmpty();
});

test('a streamed url response with no Content-Length aborts reading once the cap is exceeded, without consuming the full body', function () {
    // Http::fake() otherwise buffers the whole response body as a plain string up
    // front, which would make it impossible to prove black-box that the capped
    // reader actually stops early rather than reading to the end — every assertion
    // on the outcome alone would pass identically either way. A lazily-generating
    // stream double makes the mechanism itself observable (see CountingStream's own
    // docblock), the same reasoning tests/Feature/BlogRouteConfigTest.php already
    // uses to assert on a compiled route's constraint directly where black-box HTTP
    // assertions alone can't distinguish a present vs. absent guard.
    config()->set('ink.uploads.max_bytes', 1024);

    $stream = new CountingStream(50 * 1024 * 1024);

    Http::fake(function ($request) use ($stream) {
        if ($request->method() === 'HEAD') {
            // No Content-Length: forces the pre-check to fall through to the
            // streamed GET, which is the path under test here.
            return Http::response('', 200);
        }

        return Http::response($stream, 200, ['Content-Type' => 'image/png']);
    });

    BlogServer::actingAs(tokenUser())->tool(UploadImageTool::class, [
        'url' => 'https://example.test/huge-stream.png',
    ])->assertSee('exceeds the maximum upload size');

    expect($stream->bytesRead)
        ->toBeGreaterThanOrEqual(1024)
        ->toBeLessThan(50 * 1024 * 1024);
});

test('the stored extension is derived from sniffed content, not a spoofed filename', function () {
    $path = uploadAndCapturePath(['data' => PNG_BASE64, 'filename' => 'totally-a-photo.jpg']);

    expect($path)->toEndWith('.png');
});

test('a gif is accepted', function () {
    $path = uploadAndCapturePath(['data' => GIF_BASE64]);

    expect($path)->toEndWith('.gif');
});

test('a jpeg is accepted', function () {
    $path = uploadAndCapturePath(['data' => JPEG_BASE64]);

    expect($path)->toEndWith('.jpg');
});

test('a webp is accepted', function () {
    $path = uploadAndCapturePath(['data' => WEBP_BASE64]);

    expect($path)->toEndWith('.webp');
});

test('a non-http(s) url scheme is rejected', function () {
    BlogServer::actingAs(tokenUser())->tool(UploadImageTool::class, [
        'url' => 'ftp://example.test/passwd',
    ])->assertSee('Only http and https URLs are supported.');

    Http::assertNothingSent();
    expect(Storage::disk('public')->allFiles('ink'))->toBeEmpty();
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
