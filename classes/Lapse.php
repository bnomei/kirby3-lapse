<?php

declare(strict_types=1);

namespace Bnomei;

use Closure;
use Exception;
use Kirby\Cache\Cache;
use Kirby\Cms\File;
use Kirby\Cms\FileVersion;
use Kirby\Cms\Page;
use Kirby\Cms\Site;
use Kirby\Content\Field;
use Kirby\Toolkit\A;
use Kirby\Toolkit\Iterator;

class Lapse
{
    private const SALT = 'L4P$e';

    private const INDEX = 'LAPSE_INDEX';

    private const INDEX_LIMIT = 500;

    private ?Cache $cache = null;

    private function cache(): Cache
    {
        if (! $this->cache) {
            $this->cache = kirby()->cache('bnomei.lapse');
        }

        return $this->cache;
    }

    private array $options;

    public function __construct(array $options = [])
    {
        $this->options = array_merge([
            'expires' => option('bnomei.lapse.expires', 0),
            'debug' => option('debug'),
            'indexLimit' => option('bnomei.lapse.indexLimit', null),
            'autoid' => function_exists('autoid') && function_exists('modified'),
            'boost' => function_exists('boost') && function_exists('modified'),
        ], $options);

        if ($this->option('debug')) {
            $this->flush();
        }
    }

    public function option(?string $key = null): mixed
    {
        if ($key) {
            return A::get($this->options, $key);
        }

        return $this->options;
    }

    public function set(mixed $key, mixed $value = null, ?int $expires = null): mixed
    {
        return $this->getAndSetIfMissingOrExpired($key, $value, $expires);
    }

    public function getAndSetIfMissingOrExpired(mixed $key, mixed $value = null, ?int $expires = null): mixed
    {
        if ($this->option('debug')) {
            try {
                return $this->serialize($value);
            } catch (LapseCancelException $e) {
                return null;
            }
        }

        if (! is_string($key)) {
            $key = $this->keyFromObject($key);
            $key = $this->hashKey($key);
        }
        $response = $this->cache()->get($key);
        if ($response || ! $value) {
            return $response;
        }

        try {
            $response = $this->serialize($value); // might throw LapseCancelException
            $expires = $expires ?? $this->option('expires');
            $this->cache()->set(strval($key), $response, intval($expires));
            $this->updateIndex($key, $this->option('indexLimit')); // @phpstan-ignore-line
        } catch (LapseCancelException $e) {
            // do not cache exceptions
            return null;
        }

        return $response;
    }

    public function get(mixed $key): mixed
    {
        if (! is_string($key)) {
            $key = $this->keyFromObject($key);
            $key = $this->hashKey($key);
        }

        return $this->cache()->get($key);
    }

    /**
     * Removes a single cache file
     */
    public function remove(mixed $key): bool
    {
        if (! is_string($key)) {
            $key = $this->keyFromObject($key);
            $key = $this->hashKey($key);
        }
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

    public function serialize(mixed $value): mixed
    {
        if (! $value) {
            return null;
        }
        $value = $value instanceof Closure ? $value() : $value;

        if (is_array($value)) {
            return array_map(function ($item) {
                return $this->serialize($item);
            }, $value);
        }

        if ($value instanceof Field) {
            return $value->value();
        }

        return $value;
    }

    public function keyFromObject(mixed $key): string
    {
        if (is_string($key)) {
            return $key;
        }

        if (is_int($key) || is_bool($key) || is_numeric($key)) {
            return strval($key);
        }

        if (is_array($key) || $key instanceof Iterator) {
            $items = [];
            foreach ($key as $item) {
                $items[] = $this->keyFromObject($item);
            }

            return implode($items);
        }

        if ($key instanceof Site ||
            $key instanceof Page ||
            $key instanceof File ||
            $key instanceof FileVersion
        ) {
            $modified = '';
            // lookup modified zero-cost...
            // do NOT read file from disk:  && $key->autoid()->isNotEmpty()
            if (function_exists('modified')) {
                // @codeCoverageIgnoreStart
                // use obj not string so autoid can index if needed
                $modified = modified($key);
                // autoid will check file on disk if needed
                /*
                if (!$modified) {
                    $modified = $key->modified();
                }
                */
                // @codeCoverageIgnoreEnd
            } else {
                // ... or check file on disk now
                if ($key instanceof Site) {
                    // site->modified() would be ALL content files
                    $modified = site()->modifiedTimestamp(); // @phpstan-ignore-line
                } elseif ($key instanceof FileVersion) {
                    $modified = $key->original()->modified(); // @phpstan-ignore-line
                } else {
                    $modified = $key->modified();
                }
            }

            // also factor in modified for default language in case there are non-translatable fields
            // BUT do not use as key but concat so it creates a caches for each language.
            // this has nothing to do with uuids or autoid or boost but only with what one would expect
            // the automatic key of lapse per object to be.
            if (kirby()->multilang()) {
                if ($key instanceof Site) {
                    /* TODO
                    $siteFile = site()->storage()->contentFile(
                        site()->storage()->defaultVersion(),
                        kirby()->defaultLanguage()?->code()
                    )[0];
                    $modified = $modified.filemtime($siteFile);
                    */
                    $modified = $modified.time().kirby()->defaultLanguage()?->code();
                } else {
                    $modified = $modified.$key->modified(kirby()->defaultLanguage()?->code()); // @phpstan-ignore-line
                }
            }

            return $key->id().$modified;
        }

        if (is_object($key) && get_class($key) == Field::class) {
            return $key->key().hash('xxh3', strval($key->value()));
        }

        return strval($key);
    }

    public function hashKey(string $key): string
    {
        $hash = hash('xxh3', $key.self::SALT);
        if (kirby()->multilang()) {
            $hash .= '-'.kirby()->language()?->code();
        }

        return $hash;
    }

    public function updateIndex(?string $key = null, ?int $indexLimit = null): ?int
    {
        if (! $indexLimit) {
            return null;
        }
        $index = $this->cache()->get(self::INDEX, []);
        if ($key) {
            $index[] = [$key, microtime(true)];
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

    public function prune(): bool
    {
        return $this->updateIndex(null, self::INDEX_LIMIT) <= self::INDEX_LIMIT;
    }

    /**
     * Removes all cache files created by this plugin
     */
    public function flush(): bool
    {
        $success = false;
        try {
            $success = $this->cache()->flush();
        } catch (Exception $e) {
            //
        }

        return $success;
    }

    private static ?self $singleton = null;

    public static function singleton(): self
    {
        if (self::$singleton === null) {
            self::$singleton = new self;
        }

        return self::$singleton;
    }

    public static function io(mixed $key, mixed $value = null, ?int $expires = null): mixed
    {
        return self::singleton()->getAndSetIfMissingOrExpired($key, $value, $expires);
    }

    public static function gt(mixed $key): mixed
    {
        return self::singleton()->get($key);
    }

    public static function rm(mixed $key): bool
    {
        return self::singleton()->remove($key);
    }

    public static function hash(mixed $key): string
    {
        $lapse = self::singleton();
        if (! is_string($key)) {
            $key = $lapse->keyFromObject($key);
            $key = $lapse->hashKey($key);
        }

        return $lapse->hashKey($key);
    }
}
