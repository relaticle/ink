<?php

declare(strict_types=1);

use Relaticle\Ink\Models\Post;

test('it keeps the full label when a heading contains inline markup', function () {
    $post = Post::factory()->create([
        'content' => "## Why **we** built it\n\nBody.\n\n## Using `artisan` commands\n\nBody.",
    ]);

    expect(array_values($post->tableOfContents()))
        ->toBe(['Why we built it', 'Using artisan commands']);
});

test('it decodes entities exactly once', function () {
    $post = Post::factory()->create(['content' => "## Ampersands & more\n\nBody."]);

    expect(array_values($post->tableOfContents()))->toBe(['Ampersands & more']);
});

test('it keys entries by the permalink anchor id', function () {
    $post = Post::factory()->create(['content' => "## Why we built it\n\nBody."]);

    expect(array_keys($post->tableOfContents()))->toBe(['why-we-built-it']);
});

test('it returns an empty array for a post with no headings', function () {
    $post = Post::factory()->create(['content' => 'Just a paragraph.']);

    expect($post->tableOfContents())->toBe([]);
});

test('it strips the injected permalink symbol from the label', function () {
    $post = Post::factory()->create(['content' => "## Plain heading\n\nBody."]);

    expect(array_values($post->tableOfContents()))->toBe(['Plain heading']);
});

test('it accepts a non-default heading level', function () {
    $post = Post::factory()->create(['content' => "### Subsection heading\n\nBody."]);

    expect(array_values($post->tableOfContents('h3')))->toBe(['Subsection heading']);
});

test('it rejects a tag outside the h1-h6 allowlist', function () {
    $post = Post::factory()->create(['content' => "## Heading\n\nBody."]);

    $post->tableOfContents('h2|//p');
})->throws(InvalidArgumentException::class);
