<?php

namespace BestConnectOnboarding\Core;

defined('ABSPATH') || exit;

class Loader
{

    public static function register(): void
    {
        spl_autoload_register([__CLASS__, 'autoload']);
    }

    private static function autoload(string $class): void
    {
        $prefix = 'BestConnectOnboarding\\';

        if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
            return;
        }

        $relative_class = substr($class, strlen($prefix));
        $file           = BCOB_PATH . 'src/' . str_replace('\\', DIRECTORY_SEPARATOR, $relative_class) . '.php';

        if (is_readable($file)) {
            require_once $file;
        }
    }
}
