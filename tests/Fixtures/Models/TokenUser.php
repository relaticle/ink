<?php

declare(strict_types=1);

namespace Relaticle\Ink\Tests\Fixtures\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Testbench's stock user has no Sanctum tokens, so the token-ability axis cannot be
 * exercised through it. Deliberately has no factory(): PostFactory falls back to a
 * direct users insert when the author model lacks one, which is what we want here.
 */
class TokenUser extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'users';

    protected $guarded = [];
}
