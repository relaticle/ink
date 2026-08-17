<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Relaticle\Ink\Mcp\BlogServer;
use Relaticle\Ink\Mcp\Tools\CreatePostTool;
use Relaticle\Ink\Mcp\Tools\UpdatePostTool;
use Relaticle\Ink\Mcp\Tools\UploadImageTool;
use Relaticle\Ink\Models\Category;
use Relaticle\Ink\Models\Post;
use Relaticle\Ink\Tests\Fixtures\Models\TokenUser;

const FEATURED_IMAGE_PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

beforeEach(function () {
    Storage::fake('public');
});

/** Uploads with the given caller so both calls in a test share one user (author_id/email is unique). */
function uploadedImagePath(TokenUser $user): string
{
    $path = null;

    BlogServer::actingAs($user)->tool(UploadImageTool::class, ['data' => FEATURED_IMAGE_PNG_BASE64])
        ->assertOk()
        ->assertStructuredContent(function ($json) use (&$path) {
            $json->where('path', function ($value) use (&$path) {
                $path = $value;

                return true;
            })->etc();
        });

    return $path;
}

test('create-post-tool accepts a featured_image path returned by upload-image', function () {
    $user = tokenUser();
    $category = Category::factory()->create();
    $path = uploadedImagePath($user);

    BlogServer::actingAs($user)->tool(CreatePostTool::class, [
        'title' => 'Illustrated post',
        'content' => '## Hello',
        'excerpt' => 'An excerpt.',
        'category_id' => $category->id,
        'featured_image' => $path,
    ])->assertOk();

    $post = Post::query()->where('title', 'Illustrated post')->sole();

    expect($post->featured_image)->toBe($path);
});

test('create-post-tool rejects a featured_image path that was not produced by upload-image', function () {
    $category = Category::factory()->create();

    BlogServer::actingAs(tokenUser())->tool(CreatePostTool::class, [
        'title' => 'Bad image post',
        'content' => '## Hello',
        'excerpt' => 'An excerpt.',
        'category_id' => $category->id,
        'featured_image' => '../../etc/passwd',
    ])->assertHasErrors();

    expect(Post::query()->where('title', 'Bad image post')->exists())->toBeFalse();
});

test('create-post-tool rejects a featured_image path outside the uploads directory even if it exists on disk', function () {
    $category = Category::factory()->create();
    Storage::disk('public')->put('other/not-ours.png', 'x');

    BlogServer::actingAs(tokenUser())->tool(CreatePostTool::class, [
        'title' => 'Escaped image post',
        'content' => '## Hello',
        'excerpt' => 'An excerpt.',
        'category_id' => $category->id,
        'featured_image' => 'other/not-ours.png',
    ])->assertHasErrors();
});

test('update-post-tool sets the featured_image', function () {
    $user = tokenUser();
    $post = Post::factory()->create(['featured_image' => null]);
    $path = uploadedImagePath($user);

    BlogServer::actingAs($user)->tool(UpdatePostTool::class, [
        'id' => $post->id,
        'featured_image' => $path,
    ])->assertOk();

    expect($post->fresh()->featured_image)->toBe($path);
});

test('update-post-tool clears the featured_image when null is passed', function () {
    $user = tokenUser();
    $path = uploadedImagePath($user);
    $post = Post::factory()->create(['featured_image' => $path]);

    BlogServer::actingAs($user)->tool(UpdatePostTool::class, [
        'id' => $post->id,
        'featured_image' => null,
    ])->assertOk();

    expect($post->fresh()->featured_image)->toBeNull();
});

test('update-post-tool leaves featured_image untouched when the param is omitted', function () {
    $user = tokenUser();
    $path = uploadedImagePath($user);
    $post = Post::factory()->create(['featured_image' => $path]);

    BlogServer::actingAs($user)->tool(UpdatePostTool::class, [
        'id' => $post->id,
        'title' => 'Retitled',
    ])->assertOk();

    expect($post->fresh()->featured_image)->toBe($path);
});

test('update-post-tool rejects a featured_image path that does not exist on the uploads disk', function () {
    $post = Post::factory()->create();

    BlogServer::actingAs(tokenUser())->tool(UpdatePostTool::class, [
        'id' => $post->id,
        'featured_image' => 'ink/does-not-exist.png',
    ])->assertHasErrors();

    expect($post->fresh()->featured_image)->toBeNull();
});

// Flysystem's local adapter normalizes `..` segments by default
// (allow_relative_path_traversal defaults to true), so a value that merely
// *starts with* "ink/" as a string can still resolve outside it once
// normalized. These paths all start with the confined prefix as a string but
// must still be rejected once resolved.
dataset('traversal shaped featured_image paths', [
    'pop back out to a sibling directory' => ['ink/x/../../other/secret.png'],
    'single .. still escaping the directory' => ['ink/../other/secret.png'],
    'over-popping (more .. than depth)' => ['ink/../../other/secret.png'],
    'leading ..' => ['../ink/secret.png'],
]);

test('create-post-tool rejects traversal-shaped featured_image paths, even when the resolved target exists', function (string $path) {
    Storage::disk('public')->put('other/secret.png', 'not yours');

    $category = Category::factory()->create();

    BlogServer::actingAs(tokenUser())->tool(CreatePostTool::class, [
        'title' => 'Traversal attempt '.$path,
        'content' => '## Hello',
        'excerpt' => 'An excerpt.',
        'category_id' => $category->id,
        'featured_image' => $path,
    ])
        ->assertHasErrors(['must be a path returned by upload-image'])
        ->assertDontSee('An internal server error occurred.');

    expect(Post::query()->where('title', 'Traversal attempt '.$path)->exists())->toBeFalse();
})->with('traversal shaped featured_image paths');

test('update-post-tool rejects traversal-shaped featured_image paths, even when the resolved target exists', function (string $path) {
    Storage::disk('public')->put('other/secret.png', 'not yours');

    $post = Post::factory()->create(['featured_image' => null]);

    BlogServer::actingAs(tokenUser())->tool(UpdatePostTool::class, [
        'id' => $post->id,
        'featured_image' => $path,
    ])
        ->assertHasErrors(['must be a path returned by upload-image'])
        ->assertDontSee('An internal server error occurred.');

    expect($post->fresh()->featured_image)->toBeNull();
})->with('traversal shaped featured_image paths');

// laravel/mcp does not validate tools/call arguments against the tool's advertised
// JSON schema before invoking it — arguments are the raw decoded JSON straight into
// Laravel\Mcp\Request, so a caller (malicious, or just a confused agent) can send any
// JSON type for `featured_image`. Laravel's Validator does not bail on a failing
// built-in `string` rule by default, so a custom rule closure declared alongside it
// still runs — reachable through the real tool-call entry point, not just the
// validator directly.
dataset('non-string featured_image values', [
    'integer' => [12345],
    'float' => [1.5],
    'boolean' => [true],
    'array' => [['a', 'b']],
]);

test('create-post-tool rejects a non-string featured_image cleanly instead of a masked error', function (mixed $value) {
    $category = Category::factory()->create();

    BlogServer::actingAs(tokenUser())->tool(CreatePostTool::class, [
        'title' => 'Non-string image',
        'content' => '## Hello',
        'excerpt' => 'An excerpt.',
        'category_id' => $category->id,
        'featured_image' => $value,
    ])
        ->assertHasErrors()
        ->assertDontSee('An internal server error occurred.');

    expect(Post::query()->where('title', 'Non-string image')->exists())->toBeFalse();
})->with('non-string featured_image values');

test('update-post-tool rejects a non-string featured_image cleanly instead of a masked error', function (mixed $value) {
    $post = Post::factory()->create(['featured_image' => null]);

    BlogServer::actingAs(tokenUser())->tool(UpdatePostTool::class, [
        'id' => $post->id,
        'featured_image' => $value,
    ])
        ->assertHasErrors()
        ->assertDontSee('An internal server error occurred.');

    expect($post->fresh()->featured_image)->toBeNull();
})->with('non-string featured_image values');
