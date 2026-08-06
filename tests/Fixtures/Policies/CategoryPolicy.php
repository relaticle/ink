<?php

declare(strict_types=1);

namespace Relaticle\Ink\Tests\Fixtures\Policies;

class CategoryPolicy
{
    public static bool $allow = true;

    public function viewAny(): bool
    {
        return self::$allow;
    }

    public function view(): bool
    {
        return self::$allow;
    }

    public function create(): bool
    {
        return self::$allow;
    }

    public function update(): bool
    {
        return self::$allow;
    }

    public function delete(): bool
    {
        return self::$allow;
    }

    public function restore(): bool
    {
        return self::$allow;
    }
}
