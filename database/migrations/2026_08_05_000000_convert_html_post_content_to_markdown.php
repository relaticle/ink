<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use League\HTMLToMarkdown\HtmlConverter;

return new class extends Migration
{
    /**
     * Before 2.x the MCP tools rendered markdown to HTML before storing it, while the
     * Filament editor stored raw markdown. Convert the HTML rows so `content` means one
     * thing. Rows that do not convert cleanly are left alone and reported — silently
     * mangling a published post is worse than reporting it.
     */
    public function up(): void
    {
        $table = config('ink.tables.posts', 'blog_posts');
        // ATX headings ('## FAQ') match what the Filament markdown editor produces;
        // the converter would otherwise emit Setext ('FAQ\n---').
        $converter = new HtmlConverter([
            'strip_tags' => false,
            'hard_break' => true,
            'header_style' => 'atx',
        ]);
        $skipped = [];

        DB::table($table)->orderBy('id')->chunkById(100, function ($rows) use ($table, $converter, &$skipped): void {
            foreach ($rows as $row) {
                if (! $this->looksLikeHtml((string) $row->content)) {
                    continue;
                }

                try {
                    $markdown = trim($converter->convert((string) $row->content));
                } catch (Throwable) {
                    $skipped[] = $row->id;

                    continue;
                }

                if ($markdown === '') {
                    $skipped[] = $row->id;

                    continue;
                }

                DB::table($table)->where('id', $row->id)->update(['content' => $markdown]);
            }
        });

        if ($skipped !== []) {
            echo 'ink: could not convert content for post ids '.implode(', ', $skipped).' — review manually.'.PHP_EOL;
        }
    }

    private function looksLikeHtml(string $content): bool
    {
        return (bool) preg_match('/^\s*<(p|h[1-6]|ul|ol|pre|blockquote)\b/i', $content);
    }
};
