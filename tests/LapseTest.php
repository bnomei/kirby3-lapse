<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Bnomei\Lapse;
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
        $this->assertEquals(0, \option('bnomei.lapse.expires'));
        $this->assertEquals(null, \option('bnomei.lapse.indexLimit'));
    }

    public function testJanitorJobs()
    {
        $jobs = \option('bnomei.lapse.jobs');
        $this->assertIsArray($jobs);
        $this->assertCount(2, $jobs);

        foreach ($jobs as $job) {
            $this->assertTrue(is_callable($job));
            $this->assertIsBool($job());
        }
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
        $this->assertEquals('1606868144', Bnomei\Lapse::hash('key'));
        $this->assertEquals('1606868144', Bnomei\Lapse::singleton()->hashKey('key'));
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
        $this->assertEquals('abc123', $lapse->keyFromObject(new \Kirby\Toolkit\Collection([
            'a', 'b', 'c', 1, 2, 3
        ])));

        // field
        $field = new \Kirby\Cms\Field(null, 'test', 'abc123');
        $this->assertEquals('test3473062748', $lapse->keyFromObject($field));
        $this->assertEquals('abc123', $lapse->getOrSet('test', $field));

        $this->assertEquals('abc123', $lapse->getOrSet($field, $field));
        $magic = Bnomei\Lapse::hash('test3473062748');
        $this->assertEquals('913319500', $magic);
        $this->assertEquals('abc123', $lapse->getOrSet($magic));
    }

    public function testDebug()
    {
        $lapse = new Bnomei\Lapse([
            'debug' => true,
        ]);

        $this->assertEquals(true, $lapse->option('debug'));

        $this->assertEquals(null, $lapse->getOrSet('key'));

        $lapse->getOrSet('key', 'value');
        $this->assertEquals(null, $lapse->getOrSet('key'));
        $this->assertEquals('value', $lapse->getOrSet('key', 'value'));
    }

    public function testRemove()
    {
        $lapse = new Bnomei\Lapse();

        $lapse->getOrSet('key', 'value');
        $this->assertEquals('value', $lapse->getOrSet('key'));

        $lapse->remove('key');
        $this->assertEquals(null, $lapse->getOrSet('key'));
    }

    public function testRemoveWithIndexLimit()
    {
        $limit = 100;
        $lapse = new Bnomei\Lapse([
            'indexLimit' => $limit,
        ]);

        for ($i = 0; $i < $limit; $i++) {
            $lapse->getOrSet('key' . $i, 'value' . $i);
            $this->assertEquals('value' . $i, $lapse->getOrSet('key' . $i));
            usleep(1);
        }

        $rnd = rand(0, $limit - 1);
        $lapse->remove('key' . $rnd);
        $this->assertEquals(null, $lapse->getOrSet('key' . $rnd));

        for ($i = $limit; $i < $limit * 2; $i++) {
            $lapse->getOrSet('key' . $i, 'value' . $i);
            $this->assertEquals('value' . $i, $lapse->getOrSet('key' . $i));
            usleep(1);
        }

        $this->assertEquals($limit, $lapse->updateIndex(null, $limit));

        $this->assertEquals(2, $lapse->updateIndex(null, 2));
        foreach ([2 * $limit - 1, 2 * $limit - 2] as $i) {
            $this->assertEquals('value' . $i, $lapse->getOrSet('key' . $i));
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

        // fake autoid function for filter
        if (!function_exists('autoid')) {
            function autoid()
            {
                $home = page('home');
                return new \Kirby\Toolkit\Collection([
                    [
                        'pageid' => (string) $home->id(),
                        'modified' => $home->modified(),
                        'autoid' => $home->autoid()->value(),
                        'type' => 'page',
                    ],
                ]);
            }
        }

        $this->assertEquals(
            $home->modified(),
            autoid()->filterBy('autoid', 'abim0u8f')->first()['modified']
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
}
