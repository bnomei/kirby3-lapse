<?php

@include_once __DIR__ . '/vendor/autoload.php';

Kirby::plugin('bnomei/lapse', [
    'options' => [
        'cache' => true,
        'expires' => (60*24), // minutes
        'debugforce' => true,
        'field-as-object' => false
    ],
]);

if (!class_exists('Bnomei\Lapse')) {
    require_once __DIR__ . '/classes/lapse.php';
}

if (!function_exists('lapse')) {
    function lapse(string $key, $value = null, $expires = null, $force = null)
    {
        return \Bnomei\Lapse::lapse($key, $value, $expires, $force);
    }
}
