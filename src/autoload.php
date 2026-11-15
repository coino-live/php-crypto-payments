<?php
// For projects without Composer: require 'coinolive-php/src/autoload.php';
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'CoinoLive\\')) {
        $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, 10)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});
