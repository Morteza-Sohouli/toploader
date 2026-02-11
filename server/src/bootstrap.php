<?php

use Illuminate\Database\Capsule\Manager as Capsule;

// Autoload is handled in index.php - don't load it twice

$capsule = new Capsule;

$dbConfig = require __DIR__ . '/../config/database.php';
$capsule->addConnection($dbConfig);

// Make this Capsule instance available globally via static methods... (optional)
$capsule->setAsGlobal();

// Setup the Eloquent ORM... (optional; but required for Eloquent models to work)
$capsule->bootEloquent();
