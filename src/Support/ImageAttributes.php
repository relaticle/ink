<?php

declare(strict_types=1);

namespace Relaticle\Ink\Support;

final class ImageAttributes
{
    /**
     * Every `<img>` in a rendered post, whatever attribute layout it uses.
     *
     * Quoted attribute values are consumed as units so a `>` inside `alt="a > b"`
     * does not end the tag early, and `<img\n  src=...>` (a shape a hand-written
     * raw-HTML image in post markdown can take) matches just as `<img src=...>` does.
     */
    private const IMG_TAG = '/<img\b((?:[^>"\']|"[^"]*"|\'[^\']*\')*)>/i';

    /**
     * Mark post images `loading="lazy" decoding="async"`.
     *
     * Post images sit below the fold in a text-first layout, and neither CommonMark
     * nor spatie/laravel-markdown exposes a hook for image attributes, so the
     * attributes are injected into the rendered HTML.
     *
     * An attribute an author already declared wins: writing `<img loading="eager">`
     * in a post is an explicit above-the-fold decision, and emitting a second
     * `loading` would be invalid HTML that browsers resolve in favour of the
     * *first* occurrence — silently overriding the author.
     */
    public static function markLazy(string $html): string
    {
        return preg_replace_callback(
            self::IMG_TAG,
            function (array $matches): string {
                $attributes = $matches[1];
                $names = self::attributeNames($attributes);

                $additions = '';

                if (! self::declares($names, 'loading')) {
                    $additions .= ' loading="lazy"';
                }

                if (! self::declares($names, 'decoding')) {
                    $additions .= ' decoding="async"';
                }

                return $additions === ''
                    ? $matches[0]
                    : '<img'.$additions.$attributes.'>';
            },
            $html,
        ) ?? $html;
    }

    /**
     * Blank out every attribute *value* so only name positions remain.
     *
     * Values are opaque whether they are quoted or not: a bare
     * `src=https://example.test/x.png?loading=true` carries the literal text
     * `loading=` and would otherwise read as a declared attribute. The `=` is kept
     * so a genuine `loading=` is still distinguishable from a valueless attribute.
     */
    private static function attributeNames(string $attributes): string
    {
        return preg_replace('/=\s*("[^"]*"|\'[^\']*\'|[^\s]*)/', '=', $attributes) ?? $attributes;
    }

    /**
     * A name only counts when it starts an attribute — anchored on whitespace rather
     * than `\b`, which also matches mid-name after the `-` or `:` of `data-loading`
     * or `wire:loading` and would suppress injection for an unrelated attribute.
     */
    private static function declares(string $names, string $attribute): bool
    {
        return preg_match('/(?:^|\s)'.$attribute.'\s*=/i', $names) === 1;
    }
}
