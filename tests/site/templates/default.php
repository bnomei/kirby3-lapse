<?php

    echo '<pre>';
    var_dump(kirby()->language()->code());
    var_dump($page->title()->value());
    var_dump($page->modified('c'));
    var_dump(\Bnomei\Lapse::hash([$page]));
    var_dump(\Bnomei\Lapse::gt([$page]));
