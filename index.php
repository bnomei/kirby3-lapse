<?php

@include_once __DIR__ . '/vendor/autoload.php';

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
