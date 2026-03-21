<?php

declare(strict_types=1);

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string encode(mixed ...$numbers)
 * @method static array decode(string $hash)
 * @method static string encodeHex(string $str)
 * @method static string decodeHex(string $hash)
 * @method static \Hashids\Hashids connection(string|null $name = null)
 */
class Hashids extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'hashids';
    }
}
