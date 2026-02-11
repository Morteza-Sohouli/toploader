<?php

return [
    'driver'    => 'mysql',
    'host'      => $_ENV['MYSQL_HOST'] ?? 'mysql',
    'database'  => $_ENV['MYSQL_DATABASE'] ?? 'topload',
    'username'  => $_ENV['MYSQL_USER'] ?? 'root',
    'password'  => $_ENV['MYSQL_PASSWORD'] ?? '',
    'charset'   => 'utf8',
    'collation' => 'utf8_unicode_ci',
    'prefix'    => '',
];
