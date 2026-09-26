<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/bootstrap.php';

const DATABASE_CONNECT_ATTEMPTS = 30;
const DATABASE_CONNECT_DELAY_SECONDS = 2;

for ($attempt = 1; $attempt <= DATABASE_CONNECT_ATTEMPTS; $attempt++) {
    try {
        Capsule::connection()->getPdo();
        break;
    } catch (Throwable $exception) {
        if ($attempt === DATABASE_CONNECT_ATTEMPTS) {
            fwrite(STDERR, "Database did not become ready: {$exception->getMessage()}\n");
            exit(1);
        }

        fwrite(STDERR, "Waiting for database ({$attempt}/" . DATABASE_CONNECT_ATTEMPTS . ")...\n");
        sleep(DATABASE_CONNECT_DELAY_SECONDS);
    }
}

$schema = Capsule::schema();
$created = [];

if (!$schema->hasTable('user')) {
    $schema->create('user', static function (Blueprint $table): void {
        $table->increments('id');
        $table->string('username')->unique();
        $table->string('password');
        $table->string('allowedFileTypes')->default('jpg,png,pdf');
        $table->boolean('is_admin')->default(false);
        $table->timestamps();
    });
    $created[] = 'user';
}

if (!$schema->hasTable('file')) {
    $schema->create('file', static function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('owner')->nullable()->index();
        $table->string('name');
        $table->string('type', 100)->index();
        $table->unsignedBigInteger('size');
        $table->text('path');
        $table->string('host')->nullable()->index();
        $table->timestamps();
        $table->index('created_at');
    });
    $created[] = 'file';
}

if (!$schema->hasTable('download_log')) {
    $schema->create('download_log', static function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('file_id')->index();
        $table->string('ip_address', 45)->nullable();
        $table->text('user_agent')->nullable();
        $table->timestamp('created_at')->useCurrent()->index();
    });
    $created[] = 'download_log';
}

if (!$schema->hasTable('delete_request')) {
    $schema->create('delete_request', static function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('file_id')->index();
        $table->unsignedInteger('user_id')->index();
        $table->text('reason')->nullable();
        $table->string('status', 20)->default('pending')->index();
        $table->text('admin_note')->nullable();
        $table->timestamps();
        $table->index('created_at');
    });
    $created[] = 'delete_request';
}

if ($created === []) {
    fwrite(STDOUT, "Database schema already exists.\n");
} else {
    fwrite(STDOUT, 'Created database tables: ' . implode(', ', $created) . ".\n");
}
