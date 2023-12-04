<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Bnomei\Lapse;
use Kirby\Content\Field;
use Kirby\Toolkit\Collection;
use PHPUnit\Framework\TestCase;

class LapseTest extends TestCase
{
    public function setUp(): void
    {
        kirby()->cache('bnomei.lapse')->flush();
    }

    public function testConstruct()
    {
        $lapse = new Bnomei\Lapse([]);
        $this->assertInstanceOf(Bnomei\Lapse::class, $lapse);
    }

    public function testPluginDefaults()
    {
        $this->assertEquals(0, option('bnomei.lapse.expires'));
        $this->assertEquals(null, option('bnomei.lapse.indexLimit'));
    }

    public function testStaticIO()
    {
        $this->assertEquals(null, Bnomei\Lapse::io('key'));

        Bnomei\Lapse::io('key', 'value');
        $this->assertEquals('value', Bnomei\Lapse::io('key'));

        Bnomei\Lapse::singleton()->flush();
        $this->assertEquals(null, Bnomei\Lapse::io('key'));
    }

    public function testHash()
    {
        $this->assertStringStartsWith('4c9f09aa4db9a125-en', Bnomei\Lapse::hash('key'));
        $this->assertStringStartsWith('4c9f09aa4db9a125-en', Bnomei\Lapse::singleton()->hashKey('key'));
    }

    public function testKeyFromObject()
    {
        $lapse = new Bnomei\Lapse();
        $this->assertEquals(strval(null), $lapse->keyFromObject(null));
        $this->assertEquals('key', $lapse->keyFromObject('key'));
        $this->assertIsString($lapse->keyFromObject(1));
        $this->assertEquals('1', $lapse->keyFromObject(1));

        // array
        $this->assertEquals('abc123', $lapse->keyFromObject([
            'a', 'b', 'c', 1, 2, 3
        ]));

        // collection aka iterator
        $this->assertEquals('abc123', $lapse->keyFromObject(new Collection([
            'a', 'b', 'c', 1, 2, 3
        ])));

        // field
        $field = new Field(null, 'test', 'abc123');
        $this->assertEquals('test33739d7bb9744cd0', $lapse->keyFromObject($field));
        $this->assertEquals('abc123', $lapse->set('test', $field));

        $this->assertEquals('abc123', $lapse->set($field, $field));
        $magic = Bnomei\Lapse::hash('test33739d7bb9744cd0');
        $this->assertStringStartsWith('cad13c0c16c52d6d', $magic);
        $this->assertEquals('abc123', $lapse->set($magic));
    }

    public function testDebug()
    {
        $lapse = new Bnomei\Lapse([
            'debug' => true,
        ]);

        $this->assertEquals(null, $lapse->option('does not exist'));
        $this->assertIsArray($lapse->option());
        $this->assertEquals(true, $lapse->option('debug'));

        $this->assertEquals(null, $lapse->set('key'));

        $lapse->set('key', 'value');
        $this->assertEquals(null, $lapse->set('key'));
        $this->assertEquals('value', $lapse->set('key', 'value'));
    }

    public function testRemove()
    {
        $lapse = new Bnomei\Lapse();

        $lapse->set('key', 'value');
        $this->assertEquals('value', $lapse->set('key'));

        $lapse->remove('key');
        $this->assertEquals(null, $lapse->set('key'));
    }

    public function testRemoveWithIndexLimit()
    {
        $limit = 100;
        $lapse = new Bnomei\Lapse([
            'indexLimit' => $limit,
        ]);

        for ($i = 0; $i < $limit; $i++) {
            $lapse->set('key' . $i, 'value' . $i);
            $this->assertEquals('value' . $i, $lapse->set('key' . $i));
            usleep(1);
        }

        $rnd = rand(0, $limit - 1);
        $lapse->remove('key' . $rnd);
        $this->assertEquals(null, $lapse->set('key' . $rnd));

        for ($i = $limit; $i < $limit * 2; $i++) {
            $lapse->set('key' . $i, 'value' . $i);
            $this->assertEquals('value' . $i, $lapse->set('key' . $i));
            usleep(1);
        }

        $this->assertEquals($limit, $lapse->updateIndex(null, $limit));

        $this->assertEquals(2, $lapse->updateIndex(null, 2));
        foreach ([2 * $limit - 1, 2 * $limit - 2] as $i) {
            $this->assertEquals('value' . $i, $lapse->set('key' . $i));
        }
    }

    public function testModified()
    {
        $home = page('home');
        $this->assertNotNull($home);

        $site = kirby()->site();
        $this->assertNotNull($site);
        $this->assertEquals('Bnomei', $site->author()->value());

        $data = Bnomei\Lapse::io($home, function () use ($site, $home) {
            return [
                'title' => $home->title(),
                'autoid' => $home->autoid(),
                'modified' => $home->modified(),
                'author' => $site->author()->value(),
            ];
        });

        $this->assertEquals('Home', $data['title']);
        $this->assertEquals($home->modified(), $data['modified']);
        $this->assertEquals('Bnomei', $data['author']);
    }

    public function testModifiedWithAutoID()
    {
        $home = page('home');
        $this->assertNotNull($home);

        // fake modified function
        if (!function_exists('modified')) {
            function modified($autoid)
            {
                return $autoid === 'abim0u8f' ? page('home')->modified() : null;
            }
        }

        $this->assertEquals(
            $home->modified(),
            modified('abim0u8f')
        );

        $data = Bnomei\Lapse::io($home, function () use ($home) {
            return [
               'title' => $home->title(),
               'autoid' => $home->autoid(),
               'modified' => $home->modified(),
           ];
        });

        $this->assertEquals('Home', $data['title']);
        $this->assertEquals($home->autoid()->value(), $data['autoid']);
        $this->assertEquals($home->modified(), $data['modified']);
    }

    public function testDoesNotCallGlobalHelpers()
    {
        $a = Bnomei\Lapse::io('a', function () {
            return 'kirby';
        });
        $b = Bnomei\Lapse::io('b', function () {
            return ['kirby'];
        });
        $c = Bnomei\Lapse::io('c', function () {
            return ['k' => 'kirby'];
        });

        $this->assertEquals('kirby', $a);
        $this->assertEquals('kirby', $b[0]);
        $this->assertEquals('kirby', $c['k']);
    }

    public function testHelpers()
    {
        $this->assertNull(lapse('hello'));
        $this->assertEquals('world', lapse('hello', function () {
            return 'world';
        }));
        $this->assertEquals('world', lapse('hello'));
    }

    public function testExpire()
    {
        $this->assertEquals('minit', lapse('60s', function () {
            return 'minit';
        }, 1));
        sleep(61);
        $this->assertNull(lapse('60s'));
    }
}
