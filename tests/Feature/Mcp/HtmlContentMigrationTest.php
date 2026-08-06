<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Relaticle\Ink\Models\Post;

/**
 * A migration file returns its anonymous class instance. Use `require` (never
 * `require_once` — a second include would return `true` instead of the object).
 */
function runContentMigration(): void
{
    $migration = require __DIR__.'/../../../database/migrations/2026_08_05_000000_convert_html_post_content_to_markdown.php';

    expect($migration)->toBeInstanceOf(Migration::class);

    $migration->up();
}

test('it converts HTML content written by the old MCP tools to markdown', function () {
    $post = Post::factory()->create();

    DB::table('blog_posts')->where('id', $post->id)->update([
        'content' => '<h2>FAQ</h2><p>A form plugin.</p>',
    ]);

    runContentMigration();

    expect($post->fresh()->content)->toContain('## FAQ')
        ->and($post->fresh()->content)->not->toContain('<h2>');
});

test('it leaves markdown content untouched', function () {
    $post = Post::factory()->create(['content' => "## Already markdown\n\nBody."]);

    runContentMigration();

    expect($post->fresh()->content)->toBe("## Already markdown\n\nBody.");
});
