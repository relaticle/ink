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
