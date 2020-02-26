<?php

declare(strict_types=1);

namespace Bnomei;

use Iterator;
use Kirby\Cache\Cache;
use Kirby\Cms\Field;
use Kirby\Cms\File;
use Kirby\Cms\FileVersion;
use Kirby\Cms\Page;
use Kirby\Toolkit\A;

final class Lapse
{
    /*
     * @var string
     */
    private const SALT = 'L4P$e';

    /*
     * @var string
     */
    private const INDEX = 'LAPSE_INDEX';

    /*
     * @var int
     */
    private const INDEX_LIMIT = 500;

    /*
     * @var \Kirby\Cache\Cache
     */
    private $cache;

    private function cache(): Cache
    {
        if (!$this->cache) {
            $this->cache = kirby()->cache('bnomei.lapse');
        }
        return $this->cache;
    }

    /*
     * @var array
     */
    private $options;

    public function __construct(array $options = [])
    {
        $this->options = array_merge([
            'expires' => option('bnomei.lapse.expires', 0),
            'debug' => option('debug'),
            'languageCode' => kirby()->language() ? kirby()->language()->code() : '',
            'indexLimit' => option('bnomei.lapse.indexLimit', null),
            'autoid' => function_exists('autoid') && function_exists('modified'),
        ], $options);

        if ($this->option('debug')) {
            $this->flush();
        }
    }

    public function option(?string $key = null)
    {
        if ($key) {
            return A::get($this->options, $key);
        }
        return $this->options;
    }

    public function getOrSet($key, $value = null, $expires = null)
    {
        if ($this->option('debug')) {
            return $this->serialize($value);
        }

        if (!is_string($key)) {
            $key = $this->keyFromObject($key);
            $key = $this->hashKey($key);
        }
        $response = $this->cache()->get($key);
        if ($response || !$value) {
            return $response;
        }

        $response = $this->serialize($value);
        $expires = $expires ?? $this->option('expires');
        $this->cache()->set(strval($key), $response, intval($expires));
        $this->updateIndex($key, $this->option('indexLimit'));

        return $response;
    }

    private static function isCallable($value): bool
    {
        // do not call global helpers just methods or closures
        return !is_string($value) && is_callable($value);
    }

    /**
     * Removes a single cache file
     *
     * @param string $key
     *
     * @return bool
     */
    public function remove(string $key): bool
    {
        if ($this->option('indexLimit')) {
            $index = $this->cache()->get(self::INDEX, []);
            $idx = array_search($key, array_column($index, 0));
            if ($idx !== false) {
                unset($index[$idx]);
            }
            $this->cache()->set(self::INDEX, $index);
        }
        return $this->cache()->remove($key);
    }

    /**
     * @param $value
     * @return mixed
     */
    public function serialize($value)
    {
        if (! $value) {
            return null;
        }
        $value = self::isCallable($value) ? $value() : $value;

        if (is_array($value)) {
            $items = [];
            foreach ($value as $key => $item) {
                $items[$key] = $this->serialize($item);
            }
            return $items;
        }

        if (is_a($value, 'Kirby\Cms\Field')) {
            return $value->value();
        }

        return $value;
    }

    /**
     * @param $key
     * @return string
     */
    public function keyFromObject($key): string
    {
        if (is_string($key)) {
            return $key;
        }

        if (is_int($key) || is_bool($key) || is_numeric($key)) {
            return strval($key);
        }

        if (is_array($key) || $key instanceof \Kirby\Toolkit\Iterator) {
            $items = [];
            foreach ($key as $item) {
                $items[] = $this->keyFromObject($item);
            }
            return implode($items);
        }

        if (is_object($key) && (
                $key instanceof \Kirby\Cms\Site ||
                $key instanceof \Kirby\Cms\Page ||
                $key instanceof \Kirby\Cms\File ||
                $key instanceof \Kirby\Cms\FileVersion
            )
        ) {
            $modified = '';
            // lookup modified zero-cost...
            if ($this->option('autoid') && $key->autoid()->isNotEmpty()) {
                // @codeCoverageIgnoreStart
                $modified = modified($key->autoid()->value());
                if (!$modified) {
                    $modified = $key->modified();
                }
                // @codeCoverageIgnoreEnd
            } else {
                // ... or check file on disk now
                $modified = $key->modified();
            }
            return $key->id() . $modified;
        }

        if (is_object($key) && in_array(get_class($key), [Field::class])) {
            return $key->key() . crc32($key->value());
        }

        return strval($key);
    }

    /**
     * @param string $key
     * @return string
     */
    public function hashKey(string $key): string
    {
        return strval(crc32($key . $this->option('languageCode') . self::SALT));
    }

    /**
     * @param string|null $key
     * @param null $indexLimit
     * @return int|null
     */
    public function updateIndex(?string $key = null, $indexLimit = null): ?int
    {
        if (!$indexLimit) {
            return null;
        }
        $index = $this->cache()->get(self::INDEX, []);
        if ($key) {
            array_push($index, [$key, microtime(true)]);
        }

        if (count($index) > $indexLimit) {
            // sort by time
            array_multisort(array_column($index, 1), SORT_DESC, $index);
            // get keys to remove
            $remove = array_column(array_slice($index, $indexLimit), 0);
            foreach ($remove as $keyToRemove) {
                $this->cache()->remove($keyToRemove);
            }
            // keep those not removed
            $index = array_slice($index, 0, $indexLimit);
        }
        $this->cache()->set(self::INDEX, $index);
        return count($index);
    }

    /**
     * @return bool
     */
    public function clean(): bool
    {
        return $this->updateIndex(null, self::INDEX_LIMIT) <= self::INDEX_LIMIT;
    }

    /**
     * Removes all cache files created by this plugin
     * @return bool
     */
    public function flush(): bool
    {
        return $this->cache()->flush();
    }

    /*
     * @var \Bnomei\Lapse
     */
    private static $singleton;

    public static function singleton()
    {
        if (! self::$singleton) {
            self::$singleton = new self();
        }
        return self::$singleton;
    }

    /**
     * @param $key
     * @param null $value
     * @param null $expires
     * @return array|mixed|null
     */
    public static function io($key, $value = null, $expires = null)
    {
        return self::singleton()->getOrSet($key, $value, $expires);
    }

    /**
     * @param $key
     * @return string
     */
    public static function hash($key)
    {
        return self::singleton()->hashKey($key);
    }
}
