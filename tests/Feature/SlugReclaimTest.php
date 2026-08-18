<?php

declare(strict_types=1);

use Relaticle\Ink\Models\Category;
use Relaticle\Ink\Models\Post;
use Relaticle\Ink\Models\Tag;

/**
 * The factory assigns a random slug of its own, which would bypass the
 * generator entirely. Omitting the attribute is what makes HasSlug run.
 */
function postTitled(string $title): Post
{
    return Post::factory()->create(['title' => $title, 'slug' => null]);
}

test('a new post reclaims the slug of a trashed post with the same title', function () {
    $original = postTitled('Hello World');

    expect($original->slug)->toBe('hello-world');

    $original->delete();

    expect(postTitled('Hello World')->slug)->toBe('hello-world');
});

test('a new post still suffixes past a live post with the same title', function () {
    postTitled('Hello World');

    expect(postTitled('Hello World')->slug)->toBe('hello-world-1');
});

test('restoring a post whose slug was reclaimed re-suffixes the restored post', function () {
    $original = postTitled('Hello World');
    $original->delete();

    $replacement = postTitled('Hello World');
    expect($replacement->slug)->toBe('hello-world');

    $original->restore();

    expect($original->fresh()->slug)->toBe('hello-world-1')
        ->and($replacement->fresh()->slug)->toBe('hello-world');
});

test('categories reclaim slugs from trashed categories', function () {
    $original = Category::factory()->create(['name' => 'Engineering']);
    expect($original->slug)->toBe('engineering');

    $original->delete();

    expect(Category::factory()->create(['name' => 'Engineering'])->slug)->toBe('engineering');
});

test('tags reclaim slugs from trashed tags', function () {
    $original = Tag::factory()->create(['name' => 'Laravel']);
    expect($original->slug)->toBe('laravel');

    $original->delete();

    expect(Tag::factory()->create(['name' => 'Laravel'])->slug)->toBe('laravel');
});

test('restoring a category whose slug was reclaimed re-suffixes the restored category', function () {
    $original = Category::factory()->create(['name' => 'Engineering']);
    $original->delete();

    Category::factory()->create(['name' => 'Engineering']);

    $original->restore();

    expect($original->fresh()->slug)->toBe('engineering-1');
});

test('a restored record keeps its slug when nothing reclaimed it', function () {
    $post = postTitled('Hello World');
    $post->delete();

    $post->restore();

    expect($post->fresh()->slug)->toBe('hello-world');
});
