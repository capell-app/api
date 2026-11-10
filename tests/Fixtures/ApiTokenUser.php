<?php

declare(strict_types=1);

namespace Capell\Api\Tests\Fixtures;

use Capell\Tests\Fixtures\Models\User;
use Laravel\Sanctum\HasApiTokens;

final class ApiTokenUser extends User
{
    use HasApiTokens;

    protected $table = 'users';
}
