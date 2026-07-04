<?php

declare(strict_types=1);

namespace Ms2CategorySort\Infrastructure;

final class Autoload
{
    /** @var bool */
    private static $registered = false;

    public static function register(string $srcRoot): void
    {
        if (self::$registered) {
            return;
        }

        spl_autoload_register(static function (string $class) use ($srcRoot): void {
            $prefix = 'Ms2CategorySort\\';
            if (strpos($class, $prefix) !== 0) {
                return;
            }

            $relative = substr($class, strlen($prefix));
            $file = $srcRoot . '/' . str_replace('\\', '/', $relative) . '.php';
            if (is_readable($file)) {
                require_once $file;
            }
        });

        self::$registered = true;
    }
}
