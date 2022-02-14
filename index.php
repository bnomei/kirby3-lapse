<?php

@include_once __DIR__ . '/vendor/autoload.php';

if (!class_exists('Bnomei\Lapse')) {
    require_once __DIR__ . '/classes/Lapse.php';
    require_once __DIR__ . '/classes/LapseStatic.php';
}

if (!function_exists('lapse')) {
    function lapse($key, $value = null, $expires = null)
    {
        if ($value) {
            return \Bnomei\Lapse::singleton()->set($key, $value, $expires);
        }
        return \Bnomei\Lapse::singleton()->get($key);
    }
}

if (!function_exists('lapseStatic')) {
    function lapseStatic($key, Closure $closure, $expires = null)
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
        'jobs' => [ // https://github.com/bnomei/kirby3-janitor
            'cleanLapse' => function (Kirby\Cms\Page $page = null, string $data = null) {
                return \Bnomei\Lapse::singleton()->clean();
            },
            'flushLapse' => function (Kirby\Cms\Page $page = null, string $data = null) {
                return \Bnomei\Lapse::singleton()->flush();
            },
        ],
    ],
]);
