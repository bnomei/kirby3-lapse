# Kirby Lapse

[![Kirby 5](https://flat.badgen.net/badge/Kirby/5?color=ECC748)](https://getkirby.com)
![PHP 8.2](https://flat.badgen.net/badge/PHP/8.2?color=4E5B93&icon=php&label)
![Release](https://flat.badgen.net/packagist/v/bnomei/kirby3-lapse?color=ae81ff&icon=github&label)
![Downloads](https://flat.badgen.net/packagist/dt/bnomei/kirby3-lapse?color=272822&icon=github&label)
[![Coverage](https://flat.badgen.net/codeclimate/coverage/bnomei/kirby3-lapse?icon=codeclimate&label)](https://codeclimate.com/github/bnomei/kirby3-lapse)
[![Maintainability](https://flat.badgen.net/codeclimate/maintainability/bnomei/kirby3-lapse?icon=codeclimate&label)](https://codeclimate.com/github/bnomei/kirby3-lapse/issues)
[![Discord](https://flat.badgen.net/badge/discord/bnomei?color=7289da&icon=discord&label)](https://discordapp.com/users/bnomei)
[![Buymecoffee](https://flat.badgen.net/badge/icon/donate?icon=buymeacoffee&color=FF813F&label)](https://www.buymeacoffee.com/bnomei)
Cache any data until set expiration time (with automatic keys).

## Installation

- unzip [master.zip](https://github.com/bnomei/kirby3-lapse/archive/master.zip) as folder `site/plugins/kirby3-lapse` or
- `git submodule add https://github.com/bnomei/kirby3-lapse.git site/plugins/kirby3-lapse` or
- `composer require bnomei/kirby3-lapse`

## Usecase

The Kirby Pages-Cache can cache the output of Page Templates. It invalidates **all** cache files if any Object in the Panel is changed. This is a good choice if you do not make changes often. But if you do make changes often you need a cache that knows what has been modified and which caches to devalidate and which not. 

Sometimes you can not cache the complete Page since...
- it contains a [form](https://github.com/mzur/kirby-uniform) with a csrf hash, 
- you use [content security headers](https://github.com/bnomei/kirby3-security-headers) with nouces,
- build and cache data for a logicless templating system like [handlebars](https://github.com/bnomei/kirby3-handlebars) or
- you want to cache data from an external source like an API. 

Lapse was build to do exactly that: **Cache any data until set expiration time.**

## Usage Examples

### Example 1: get/set
```php
$key = hash('xxh3', $page->url()); // unique key
// to delay data creation until need use a callback. do not use a plain array or object.
$data = function () {
    return [1, 2, 3];
};
$data = lapse($key, $data);
```

### Example 2: with custom expiration time
```php
$key = hash('xxh3', $page->url()); // unique key
$expires = 5; // in minutes. default: 0 aka infinite
$data = function () {
    return [1, 2, 3];
};
$data = lapse($key, $data, $expires);
```

### Example 3: page object
```php
$data = lapse(hash('xxh3', $page->url()), function () use ($kirby, $site, $page) {
    // create some data
    return [
        'author' => site()->author(),
        'title' => $page->title(),
        'text' => $page->text()->kirbytext(),
        'url' => $page->url(),
    ];
});
```

### Remove by Key

```php
$key = hash('xxh3', $page->url()); // unique key
$wasRemoved = \Bnomei\Lapse::rm($key);
```

## Static Cache helper - lapseStatic()

Sometimes you need to cache things only for the current request like
when [reusing Kirbys Collections](https://forum.getkirby.com/t/remember-this-caching-in-kirby/23705/4#solution-with-a-static-cache-3)
. The `lapseStatic()`-helper makes things like that a bit easier. The closure to generate the data will only be called
once, set its return value to a static cache and every recurring call to the collection will get the cached collection
back from static memory array.

**site/plugins/example/index.php**

```php
<?php

Kirby::plugin('bnomei/example', [
  'collections' => [
    // collections have to be a closure that is why it is wrapped in a fn
    'recent-courses' => fn() => lapseStatic(
        'recent-courses', // key 
        function () { // value
            return page('courses')->children()->listed()->sortBy('name')->limit(10);
        }
    )
]);
```

## Clever keys

### Unique but not modified

Caches use a string value as key to store and later retrieve the data. The key is usually a hash of the objects id plus
some meta data like the contents language. Storing data related to a Page using the `$key = hash('xxh3', $page->url());` will
work just fine. It takes care of the language if you use a multi-language setup since the language is included in the
url. But it will expire only if you provide a fixed time or devalidate it yourself.

### Modified

The solution is to include the modification timestamp of every object related to the data. So if you store the result of
a Page Object with Images being rendered you need to include the modification timestamp of all of these. That will cause
the creation of a new cache every time your source changes.

#### Basic

```php
$keys = [ $page->url().$page->modified() ];
foreach($page->images() as $image) {
    $keys[] = $image->id().$image->modified();
}
$key = hash('xxh3', implode($keys));
```

#### Objects

Since version 2 of this plugin you can also forward any of these and the key will be magically created for you.

- Page-Objects, 
- File-Objects and FileVersion-Objects (aka Thumbs), 
- Collections or
- the Site-Object 


```php
$objects = [$page, $page->images()];
$data = lapse($objects, ...)
```

#### Multi-language support

The keys created by the plugin are [tagged with the current language](https://github.com/bnomei/kirby3-lapse/blob/master/classes/Lapse.php#L181). You will get a different cache value for each language.

#### BoostID
If you use the [Boost plugin](https://github.com/bnomei/kirby3-boost) the modification timestamps will be retrieved at almost zero-cpu-cost and not causing the file to be checked on disk.


## FAQ

### Infinite cache duration by default

Unless you set an expiration when using `lapse()` the cache file will never devalidate. This is because the plugin is intended to be used with keys defining the expiration like `$key = hash('xxh3', $page->id().$page->modified());`.

```php
$expires = 5; // in minutes. default: 0 aka infinite
$data = lapse($key, $data, $expires);
```

When using Memcache or APCu you need to limit the maximum number of caches created since you have a very limited amount if memory of 64MB at default. You can set a limit at `bnomei.lapse.indexLimit` to something like `300`. But be aware that this makes writing to the cache a tiny bit slower since the plugins internal index must be updated.

### No cache when debugging

When Kirbys global debug config is set to `true` the **complete** plugin cache will be flushed and no caches will be created. This will make you live easier – trust me.

### Kirby Field-Objects and serialization 

The plugin uses the default Kirby serialization of objects and since memory references are lost anyway all Kirby Field-Objects are stored by calling their `->value()` method. The File-Cache uses a json format.

### Migrating from v1 of this plugin

- `$force` param has been removed: use proper keys.
- all settings have been removed: they are not needed anymore like explained above.

## Performance

### Use crc32 to generate the hash

`xxh3` is the [fastest](https://php.watch/versions/8.1/xxHash) non cryptographic hashing hashing algorithm in PHP 8.1 . The keys for lapse do not need to be encrypted.

### Cache Driver
For best performance set the **global** [cache driver](https://getkirby.com/docs/reference/system/options/cache#cache-driver) to one using the servers memory not files on the harddisk (even on SSDs). Memcache or ApcuCache can be activated on most hosting environments but rarely are by default. Also see `bnomei.lapse.indexLimit` setting explained above. My [Redis Cache Driver](https://github.com/bnomei/kirby3-redis-cachedriver) and [SQLite Cache Driver](https://github.com/bnomei/kirby3-sqlite-cachedriver) are faster than other cache drivers and have no memory limit. In all other cases use the very fast [PHP Cache Driver](https://github.com/bnomei/kirby3-php-cachedriver) or APCu.

```php
return [
  'cache' => [
    'driver' => 'apcu', // php, redis, sqlite
  ],
];
```

## Examples

```php
// lapse v1 could already do this:
// store data until objects are modified with optional expire
$data = lapse(
    $page->id().$page->modified(),
    ['some', 'kind', 'of', 'data'],
    60*24*7 // one week
);

// now it can create magic cache keys from kirby objects
$data = lapse(
    $page, // key with modification date created by lapse based on object 
    function () use ($page) {
        return [
           'title' => $page->title(),
       ];
    }
);

// or from an collection of pages or files
$collection = collection('mycollection');
$data = lapse(
    $collection, // this will turn into a key taking modification date of all objects into consideration
    function () use ($collection) {
        return [ /*...*/ ];
    }
);

// or from an array combining all these
$data = lapse(
    ['myhash', $page, $page->children()->images(), $collection, $site], // will create key from array of objects
    function () use ($site, $page, $collection) {
        return [
            // will not break serialization => automatically store ->value() of fields
            'author' => $site->author(),
            'title' => $page->title(),
            'hero' => $page->children()->images()->first()->srcset()->html(),
            'count' => $collection->count(),
        ];
    }
);

// remove by dynamic key
$wasRemoved = \Bnomei\Lapse::rm(
    ['myhash', $page, $page->children()->images(), $collection, $site]
);
```

> 🏎️ if you use boost the modified lookups will be at almost zero-cpu cost.

## Cancel the caching

If you want to cancel the caching you can throw the `LapseCancelException` in the closure. This will prevent the caching of the data and return `null`.

```php
$data = lapse(
    $page,
    function () use ($page) {
        if ($page->isUnpublished()) {
            // do not cache unpublished pages
            throw new \Bnomei\LapseCancelException();
        }
        return $page->title()->value();
    }
);
// $data === null for unpublished pages
```

## Disclaimer

This plugin is provided "as is" with no guarantee. Use it at your own risk and always test it yourself before using it in a production environment. If you find any issues, please [create a new issue](https://github.com/bnomei/kirby3-lapse/issues/new).

## License

[MIT](https://opensource.org/licenses/MIT)

It is discouraged to use this plugin in any project that promotes racism, sexism, homophobia, animal abuse, violence or any other form of hate speech.

## Credits

based on V2 version of
- https://github.com/jenstornell/kirby-time-cache
