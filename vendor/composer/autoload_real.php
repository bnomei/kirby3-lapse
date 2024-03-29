<?php

// autoload_real.php @generated by Composer

class ComposerAutoloaderInitd9ec4f590bd3ba6e2de6935ec3aa4800
{
    private static $loader;

    public static function loadClassLoader($class)
    {
        if ('Composer\Autoload\ClassLoader' === $class) {
            require __DIR__ . '/ClassLoader.php';
        }
    }

    /**
     * @return \Composer\Autoload\ClassLoader
     */
    public static function getLoader()
    {
        if (null !== self::$loader) {
            return self::$loader;
        }

        require __DIR__ . '/platform_check.php';

        spl_autoload_register(array('ComposerAutoloaderInitd9ec4f590bd3ba6e2de6935ec3aa4800', 'loadClassLoader'), true, true);
        self::$loader = $loader = new \Composer\Autoload\ClassLoader(\dirname(__DIR__));
        spl_autoload_unregister(array('ComposerAutoloaderInitd9ec4f590bd3ba6e2de6935ec3aa4800', 'loadClassLoader'));

        require __DIR__ . '/autoload_static.php';
        call_user_func(\Composer\Autoload\ComposerStaticInitd9ec4f590bd3ba6e2de6935ec3aa4800::getInitializer($loader));

        $loader->register(true);

        return $loader;
    }
}
