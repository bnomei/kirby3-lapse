<?php

namespace Bnomei;

class Lapse
{
    private static $indexname = null;
    private static $cache = null;
    private static function cache(): \Kirby\Cache\Cache
    {
        if (!static::$cache) {
            static::$cache = kirby()->cache('bnomei.lapse');
        }
        // create new index table on new version of plugin
        if (!static::$indexname) {
            static::$indexname = 'index'.str_replace('.', '', kirby()->plugin('bnomei/lapse')->version()[0]);
        }
        return static::$cache;
    }

    public static function lapse(string $key, $value = null, $expires = null, $force = null)
    {
        if ($force == null && option('debug') && option('bnomei.lapse.debugforce')) {
            $force = true;
        }
        $response = $force ? null : static::cache()->get($key);
        if (!$response) {
            $response = is_callable($value) ? $value() : $value;
            $responseNormalized = $response;
            if (!option('bnomei.lapse.field-as-object')) {
                if(is_array($response)) {
                    $responseNormalized = array_map(function ($d) {
                        if (is_a($d, 'Kirby\Cms\Field')) {
                            return ''.$d->value();
                        }
                        return $d;
                    }, $response);
                } else if(is_a($response, 'Kirby\Cms\Field')) {
                    $responseNormalized = $response->value();
                }
            }
            static::cache()->set(
                $key,
                $responseNormalized,
                (($expires) ? $expires : option('bnomei.lapse.expires'))
            );
        }
        return $response;
    }

    public static function flush()
    {
        static::cache()->flush();
    }
}
