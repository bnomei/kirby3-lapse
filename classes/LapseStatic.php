<?php

namespace Bnomei;

use Closure;
use Kirby\Toolkit\A;

class LapseStatic
{
    public static array $cache = [];

    public static function getOrSet(mixed $key, Closure $closure): mixed
    {
        $key = Lapse::singleton()->keyFromObject($key);

        if ($value = A::get(static::$cache, $key)) {
            return $value;
        }

        static::$cache[$key] = $closure();

        return static::$cache[$key];
    }
}
