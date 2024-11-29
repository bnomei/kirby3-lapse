<?php

return [
    'debug' => false,
    'languages' => true,

    'hooks' => [
        'page.update:after' => function (Kirby\Cms\Page $newPage, Kirby\Cms\Page $oldPage) {
            lapse(
                [$newPage], // automatic key
                function () use ($newPage) {
                    return [
                        $newPage->uri() => (kirby()->language() ? kirby()->language()->code() : 'NONE').' => '.$newPage->modified('c'),
                    ];
                }
            );
        },
    ],
];
