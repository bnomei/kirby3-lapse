<?php

Kirby::plugin('bnomei/lapse', [
    'options' => [
        'cache' => true,
        'expires' => (60*24), // minutes
    ],
]);

if (!class_exists('Bnomei\Lapse')) {
    require_once __DIR__ . '/classes/lapse.php';
}

if (!function_exists('lapse')) {
    function lapse(string $key, $value = null, $expires = null, $force = false)
    {
        return \Bnomei\Lapse::lapse($key, $value, $expires, $force);
    }
}
