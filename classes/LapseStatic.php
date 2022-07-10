<?php

namespace Bnomei;

use Closure;
use Kirby\Toolkit\A;

class LapseStatic
{
    static array $cache = [];

    static function getOrSet($key, Closure $closure)
    {
        $key = Lapse::singleton()->keyFromObject($key);

        if ($value = A::get(static::$cache, $key, null)) {
            return $value;
        }

        if (!is_string($closure) && is_callable($closure)) {
            static::$cache[$key] = $closure();
        }

        return static::$cache[$key];
    }
}

