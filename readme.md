# Kirby 3 Lapse

![GitHub release](https://img.shields.io/github/release/bnomei/kirby3-lapse.svg?maxAge=1800) ![License](https://img.shields.io/github/license/mashape/apistatus.svg) ![Kirby Version](https://img.shields.io/badge/Kirby-3%2B-black.svg)

Cache any data until set expiration time.

This plugin is free but if you use it in a commercial project please consider to [make a donation 🍻](https://www.paypal.me/bnomei/5).

## Usage

**Example 1: get/set**
```php
$key = md($page->url()); // unique key
// to delay data creation until need use a callback. do not use a plain array or object.
$data = function () {
    return [1, 2, 3];
}
// Bnomei\Lapse::lapse() or just lapse()
$data = lapse($key, $data);
```

**Example 2: with custom expriation time**
```php
// EXPIRE
// if ommited config settings apply
$expires = 5; // in minutes. 
$data = lapse($key, $data, $expires);
```

**Example 3: without the variables**
```php
// compressed code
$data = lapse(md($page->url()), function () use ($kirby, $site, $page) {
    // create some data. could be array, could be object, anything really.
    return [
        'title' => $page->title()->value(),
        'url' => $page->url(),
    ];
}, 5);
```

**force**
```php
$force = true;
$data = lapse($key, $data, $expires, $force);
```

**flush**
```php
Bnomei\Lapse::flush();
```

## Settings 

All settings need `bnomei.lapse.` as prefix.

**expires** default: `60*24` in minutes

> This Plugin uses minutes not seconds since K3 Cache does that as well. So be carefull in that regard when migrating from Jens' plugin.


## Disclaimer

This plugin is provided "as is" with no guarantee. Use it at your own risk and always test it yourself before using it in a production environment. If you find any issues, please [create a new issue](https://github.com/bnomei/kirby3-lapse/issues/new).

## License

[MIT](https://opensource.org/licenses/MIT)

It is discouraged to use this plugin in any project that promotes racism, sexism, homophobia, animal abuse, violence or any other form of hate speech.

## Credits

based on V2 version of
- https://github.com/jenstornell/kirby-time-cache
