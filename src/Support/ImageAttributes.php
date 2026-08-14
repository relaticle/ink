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

                // Attribute *values* can contain anything, including the literal
                // text `loading=`; blank them out before looking for the names.
                $names = preg_replace('/"[^"]*"|\'[^\']*\'/', '', $attributes) ?? $attributes;

                $additions = '';

                if (! preg_match('/\bloading\s*=/i', $names)) {
                    $additions .= ' loading="lazy"';
                }

                if (! preg_match('/\bdecoding\s*=/i', $names)) {
                    $additions .= ' decoding="async"';
                }

                return $additions === ''
                    ? $matches[0]
                    : '<img'.$additions.$attributes.'>';
            },
            $html,
        ) ?? $html;
    }
}
