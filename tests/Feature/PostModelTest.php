<?php

declare(strict_types=1);

use Relaticle\Ink\Models\Category;
use Relaticle\Ink\Models\Post;

test('readingTime computes minutes from content word count', function () {
    $post = Post::factory()->create([
        'content' => str_repeat('word ', 600),
    ]);

    expect($post->readingTime())->toBe(3);
});

test('readingTime is at least 1 minute for any content', function () {
    $post = Post::factory()->create(['content' => 'short']);

    expect($post->readingTime())->toBe(1);
});

test('relatedPosts returns same-category published posts excluding self', function () {
    $cat = Category::factory()->create();
    $self = Post::factory()->published()->create(['category_id' => $cat->id]);
    $a = Post::factory()->published()->create(['category_id' => $cat->id]);
    $b = Post::factory()->published()->create(['category_id' => $cat->id]);
    $other = Post::factory()->published()->create(['category_id' => null]);

    $related = $self->relatedPosts(limit: 5)->get();

    expect($related->pluck('id')->all())
        ->toContain($a->id)
        ->toContain($b->id)
        ->not->toContain($self->id)
        ->not->toContain($other->id);
});

test('relatedPosts returns empty when post has no category', function () {
    $self = Post::factory()->published()->create(['category_id' => null]);
    Post::factory()->published()->count(3)->create();

    expect($self->relatedPosts()->get())->toBeEmpty();
});

test('toHtml adds loading=lazy and decoding=async to every rendered image', function () {
    $post = Post::factory()->create([
        'content' => "Intro text.\n\n![First image](https://example.test/one.png)\n\nMiddle text.\n\n![Second image](https://example.test/two.png)",
    ]);

    $html = $post->toHtml();

    expect(substr_count($html, '<img loading="lazy" decoding="async" '))->toBe(2)
        ->and($html)->toContain('src="https://example.test/one.png"')
        ->and($html)->toContain('src="https://example.test/two.png"')
        ->and($html)->toContain('alt="First image"');
});
