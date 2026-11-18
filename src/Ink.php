<?php

declare(strict_types=1);

namespace Relaticle\Ink;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Relaticle\Ink\Models\Post;

final class Ink
{
    private static ?Closure $authorResolver = null;

    private static ?Closure $previewEditUrlResolver = null;

    /**
     * Register how an MCP caller maps to the model that authors a post.
     *
     * Hosts whose staff are not instances of `ink.author_model` — separate guard,
     * separate table — supply the mapping here. Register it in a service provider's
     * boot(); a closure in a config file breaks `config:cache`.
     */
    public static function resolveAuthorUsing(?Closure $callback): void
    {
        self::$authorResolver = $callback;
    }

    public static function resolveAuthor(?Authenticatable $caller): ?Model
    {
        if (! $caller instanceof Authenticatable) {
            return null;
        }

        if (self::$authorResolver instanceof Closure) {
            $resolved = (self::$authorResolver)($caller);

            return $resolved instanceof Model ? $resolved : null;
        }

        $authorModel = config('ink.author_model');

        return is_string($authorModel) && $caller instanceof $authorModel && $caller instanceof Model
            ? $caller
            : null;
    }

    /**
     * Register how to build the "edit this post" link shown on the preview page.
     *
     * Which panel and which guard own the blog admin is a host decision — a package
     * that hardcodes it breaks the moment the host moves the resource.
     */
    public static function resolvePreviewEditUrlUsing(?Closure $callback): void
    {
        self::$previewEditUrlResolver = $callback;
    }

    public static function resolvePreviewEditUrl(Post $post): ?string
    {
        if (! self::$previewEditUrlResolver instanceof Closure) {
            return null;
        }

        $url = (self::$previewEditUrlResolver)($post);

        return is_string($url) && $url !== '' ? $url : null;
    }

    public static function defaultLocale(): string
    {
        return (string) config('ink.default_locale', 'en');
    }

    /** @return list<string> */
    public static function locales(): array
    {
        return array_values(array_unique([
            self::defaultLocale(),
            ...array_map('strval', (array) config('ink.locales', ['en'])),
        ]));
    }

    public static function routeName(string $key, ?string $locale = null): string
    {
        $locale ??= self::defaultLocale();

        return $locale === self::defaultLocale() ? "blog.{$key}" : "blog.{$locale}.{$key}";
    }

    /** The locale whose `$key` route is named `$routeName`, or null when it is none of them. */
    public static function localeOfRoute(?string $routeName, string $key): ?string
    {
        foreach (self::locales() as $locale) {
            if ($routeName === self::routeName($key, $locale)) {
                return $locale;
            }
        }

        return null;
    }

    public static function requestLocale(): string
    {
        $locale = request()->route()?->defaults['locale'] ?? null;

        return is_string($locale) && in_array($locale, self::locales(), true)
            ? $locale
            : self::defaultLocale();
    }

    public static function flushState(): void
    {
        self::$authorResolver = null;
        self::$previewEditUrlResolver = null;
    }
}
