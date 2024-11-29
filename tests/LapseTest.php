<?php

require_once __DIR__.'/../vendor/autoload.php';

use Bnomei\Lapse;
use Bnomei\LapseCancelException;
use Kirby\Content\Field;
use Kirby\Toolkit\Collection;

beforeEach(function () {
    kirby()->cache('bnomei.lapse')->flush();
});

test('construct', function () {
    $lapse = new Bnomei\Lapse([]);
    expect($lapse)->toBeInstanceOf(Bnomei\Lapse::class);
});

test('plugin defaults', function () {
    expect(option('bnomei.lapse.expires'))->toEqual(0)
        ->and(option('bnomei.lapse.indexLimit'))->toEqual(null);
});

test('static io', function () {
    expect(Bnomei\Lapse::io('key'))->toEqual(null);

    Bnomei\Lapse::io('key', 'value');
    expect(Bnomei\Lapse::io('key'))->toEqual('value');

    Bnomei\Lapse::singleton()->flush();
    expect(Bnomei\Lapse::io('key'))->toEqual(null);
});

test('hash', function () {
    expect(Bnomei\Lapse::hash('key'))->toStartWith('4c9f09aa4db9a125-en')
        ->and(Bnomei\Lapse::singleton()->hashKey('key'))->toStartWith('4c9f09aa4db9a125-en');
});

test('key from object', function () {
    $lapse = new Bnomei\Lapse;
    expect($lapse->keyFromObject(null))->toEqual(strval(null))
        ->and($lapse->keyFromObject('key'))->toEqual('key')
        ->and($lapse->keyFromObject(1))->toBeString()
        ->and($lapse->keyFromObject(1))->toEqual('1')
        ->and($lapse->keyFromObject([
            'a', 'b', 'c', 1, 2, 3,
        ]))->toEqual('abc123')
        ->and($lapse->keyFromObject(new Collection([
            'a', 'b', 'c', 1, 2, 3,
        ])))->toEqual('abc123');

    // array

    // collection aka iterator

    // field
    $field = new Field(null, 'test', 'abc123');
    expect($lapse->keyFromObject($field))->toEqual('test33739d7bb9744cd0')
        ->and($lapse->set('test', $field))->toEqual('abc123')
        ->and($lapse->set($field, $field))->toEqual('abc123');

    $magic = Bnomei\Lapse::hash('test33739d7bb9744cd0');
    expect($magic)->toStartWith('cad13c0c16c52d6d')
        ->and($lapse->set($magic))->toEqual('abc123');
});

test('debug', function () {
    $lapse = new Bnomei\Lapse([
        'debug' => true,
    ]);

    expect($lapse->option('does not exist'))->toEqual(null)
        ->and($lapse->option())->toBeArray()
        ->and($lapse->option('debug'))->toEqual(true)
        ->and($lapse->set('key'))->toEqual(null);

    $lapse->set('key', 'value');
    expect($lapse->set('key'))->toEqual(null)
        ->and($lapse->set('key', 'value'))->toEqual('value');
});

test('remove', function () {
    $lapse = new Bnomei\Lapse;

    $lapse->set('key', 'value');
    expect($lapse->set('key'))->toEqual('value');

    $lapse->remove('key');
    expect($lapse->set('key'))->toEqual(null);
});

test('remove with index limit', function () {
    $limit = 100;
    $lapse = new Bnomei\Lapse([
        'indexLimit' => $limit,
    ]);

    for ($i = 0; $i < $limit; $i++) {
        $lapse->set('key'.$i, 'value'.$i);
        expect($lapse->set('key'.$i))->toEqual('value'.$i);
        usleep(1);
    }

    $rnd = rand(0, $limit - 1);
    $lapse->remove('key'.$rnd);
    expect($lapse->set('key'.$rnd))->toEqual(null);

    for ($i = $limit; $i < $limit * 2; $i++) {
        $lapse->set('key'.$i, 'value'.$i);
        expect($lapse->set('key'.$i))->toEqual('value'.$i);
        usleep(1);
    }

    expect($lapse->updateIndex(null, $limit))->toEqual($limit)
        ->and($lapse->updateIndex(null, 2))->toEqual(2);

    foreach ([2 * $limit - 1, 2 * $limit - 2] as $i) {
        expect($lapse->set('key'.$i))->toEqual('value'.$i);
    }
});

test('modified', function () {
    $home = page('home');
    expect($home)->not->toBeNull();

    $site = kirby()->site();
    expect($site)->not->toBeNull()
        ->and($site->author()->value())->toEqual('Bnomei');

    $data = Bnomei\Lapse::io($home, function () use ($site, $home) {
        return [
            'title' => $home->title(),
            'autoid' => $home->autoid(),
            'modified' => $home->modified(),
            'author' => $site->author()->value(),
        ];
    });

    expect($data['title'])->toEqual('Home')
        ->and($data['modified'])->toEqual($home->modified())
        ->and($data['author'])->toEqual('Bnomei');
});

test('modified with auto id', function () {
    $home = page('home');
    expect($home)->not->toBeNull();

    // fake modified function
    if (! function_exists('modified')) {
        function modified($autoid)
        {
            return $autoid === 'abim0u8f' ? page('home')->modified() : null;
        }
    }

    expect(modified('abim0u8f'))->toEqual($home->modified());

    $data = Bnomei\Lapse::io($home, function () use ($home) {
        return [
            'title' => $home->title(),
            'autoid' => $home->autoid(),
            'modified' => $home->modified(),
        ];
    });

    expect($data['title'])->toEqual('Home')
        ->and($data['autoid'])->toEqual($home->autoid()->value())
        ->and($data['modified'])->toEqual($home->modified());
});

test('does not call global helpers', function () {
    $a = Bnomei\Lapse::io('a', function () {
        return 'kirby';
    });
    $b = Bnomei\Lapse::io('b', function () {
        return ['kirby'];
    });
    $c = Bnomei\Lapse::io('c', function () {
        return ['k' => 'kirby'];
    });

    expect($a)->toEqual('kirby')
        ->and($b[0])->toEqual('kirby')
        ->and($c['k'])->toEqual('kirby');
});

test('helpers', function () {
    expect(lapse('hello'))->toBeNull()
        ->and(lapse('hello', function () {
            return 'world';
        }))->toEqual('world')
        ->and(lapse('hello'))->toEqual('world');
});

test('cancel caching', function () {
    expect(lapse('hello', function () {
        return 'world';
    }))->toEqual('world')
        ->and(lapse('hello', function () {
            return 'not called because its cached without expiration';
        }))->toEqual('world');

    Lapse::rm('hello');
    expect(lapse('hello', function () {
        throw new LapseCancelException;

        return 'bogus'; // never called
    }))->toEqual(null);
});

test('expire', function () {
    expect(lapse('60s', function () {
        return 'minit';
    }, 1))->toEqual('minit');
    sleep(61);
    expect(lapse('60s'))->toBeNull();
});
