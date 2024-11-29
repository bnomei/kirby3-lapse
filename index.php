<?php

@include_once __DIR__.'/vendor/autoload.php';

if (! class_exists('Bnomei\Lapse')) {
    require_once __DIR__.'/classes/Lapse.php';
    require_once __DIR__.'/classes/LapseStatic.php';
}

if (! function_exists('lapse')) {
    function lapse(mixed $key, mixed $value = null, ?int $expires = null): mixed
    {
        if ($value) {
            return \Bnomei\Lapse::singleton()->set($key, $value, $expires);
        }

        return \Bnomei\Lapse::singleton()->get($key);
    }
}

if (! function_exists('lapseStatic')) {
    function lapseStatic(mixed $key, Closure $closure, ?int $expires = null): mixed
    {
        // NOTE: $expires is kept as param to make swapping between lapse and lapseStatic more easily
        return \Bnomei\LapseStatic::getOrSet($key, $closure);
    }
}

Kirby::plugin('bnomei/lapse', [
    'options' => [
        'cache' => true,
        'expires' => 0,
        'indexLimit' => null,
    ],
    'commands' => [ // https://github.com/getkirby/cli
        'lapse:prune' => [
            'description' => 'Prune cache Lapse plugin',
            'args' => [],
            'command' => static function ($cli) {
                return \Bnomei\Lapse::singleton()->prune();
            },
        ],
        'lapse:flush' => [
            'description' => 'Flush cache of Lapse plugin',
            'args' => [],
            'command' => static function ($cli) {
                return \Bnomei\Lapse::singleton()->flush();
            },
        ],
    ],
    'siteMethods' => [
        'modifiedTimestamp' => function (): int {
            // TODO
            // $time = filemtime(site()->storage()->contentFiles(site()->storage()->defaultVersion())[0]);
            $time = time();

            return $time !== false ? $time : time(); // @phpstan-ignore-line
        },
    ],
]);
