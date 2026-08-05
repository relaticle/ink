<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Relaticle\Ink\Models\Post;

/**
 * A browser ends a <script> block at the first literal "</script>", wherever it
 * appears — including inside a JSON string. Slice the rendered component the way
 * the browser would, so a title that closes the tag early surfaces as truncated
 * JSON rather than silently passing.
 */
function inkRenderedJsonLd(Post $post): string
{
    $html = Blade::render('<x-ink::structured-data :post="$post" />', ['post' => $post]);

    $opening = '<script type="application/ld+json">';
    $start = mb_strpos($html, $opening);

    expect($start)->not->toBeFalse();

    $bodyStart = (int) $start + mb_strlen($opening);

    return trim(mb_substr($html, $bodyStart, (int) mb_strpos($html, '</script>', $bodyStart) - $bodyStart));
}

it('does not let a post title break out of the JSON-LD script block', function () {
    $title = 'Breakout </script><script>window.pwned=true</script>';

    $post = Post::factory()->published()->create([
        'title' => $title,
        'slug' => 'breakout-post',
        'content' => 'Body copy.',
    ]);

    $block = inkRenderedJsonLd($post);
    $decoded = json_decode($block, true);

    expect(json_last_error())->toBe(JSON_ERROR_NONE, "JSON-LD block was truncated: {$block}")
        ->and($decoded['headline'])->toBe($title);
});

it('keeps JSON-LD parseable when the excerpt closes the script tag', function () {
    $post = Post::factory()->published()->create([
        'title' => 'Normal title',
        'slug' => 'excerpt-breakout',
        'excerpt' => 'Ends early </script> and keeps going',
        'content' => 'Body copy.',
    ]);

    $block = inkRenderedJsonLd($post);
    $decoded = json_decode($block, true);

    expect(json_last_error())->toBe(JSON_ERROR_NONE, "JSON-LD block was truncated: {$block}")
        ->and($decoded['description'])->toBe('Ends early </script> and keeps going');
});

it('round-trips markup characters in the headline', function () {
    $title = 'Tips & Tricks for <Laravel> "developers"';

    $post = Post::factory()->published()->create([
        'title' => $title,
        'slug' => 'tips-and-tricks',
        'content' => 'Body copy.',
    ]);

    $decoded = json_decode(inkRenderedJsonLd($post), true);

    expect(json_last_error())->toBe(JSON_ERROR_NONE)
        ->and($decoded['headline'])->toBe($title);
});
