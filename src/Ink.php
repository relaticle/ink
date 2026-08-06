<?php

declare(strict_types=1);

namespace Relaticle\Ink;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

final class Ink
{
    private static ?Closure $authorResolver = null;

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

    public static function flushState(): void
    {
        self::$authorResolver = null;
    }
}
