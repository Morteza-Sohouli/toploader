<?php

$env = static function (string $name, string $default = ''): string {
    $value = getenv($name);

    if ($value !== false) {
        return $value;
    }

    return isset($_ENV[$name]) ? (string) $_ENV[$name] : $default;
};

return [
    'driver'    => 'mysql',
    'host'      => $env('MYSQL_HOST', 'mysql'),
    'database'  => $env('MYSQL_DATABASE', 'topload'),
    'username'  => $env('MYSQL_USER', 'root'),
    'password'  => $env('MYSQL_PASSWORD'),
    'charset'   => 'utf8',
    'collation' => 'utf8_unicode_ci',
    'prefix'    => '',
];
