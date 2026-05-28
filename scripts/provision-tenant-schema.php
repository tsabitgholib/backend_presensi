<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$schema = $argv[1] ?? null;
$seed = in_array('--seed', $argv, true);

if (!$schema) {
    fwrite(STDERR, "Usage: php scripts/provision-tenant-schema.php <tenant_schema> [--seed]\n");
    exit(1);
}

if (!preg_match('/^[a-z_][a-z0-9_]*$/', $schema)) {
    fwrite(STDERR, "Invalid schema name: {$schema}\n");
    exit(1);
}

$defaultSchema = config('tenancy.default_schema', 'public');

DB::statement(sprintf('CREATE SCHEMA IF NOT EXISTS "%s"', $schema));

config([
    'database.default' => 'pgsql',
    'database.connections.pgsql.search_path' => "{$schema},{$defaultSchema}",
]);

DB::purge('pgsql');
DB::reconnect('pgsql');

Artisan::call('migrate', [
    '--database' => 'pgsql',
    '--force' => true,
]);

if ($seed) {
    Artisan::call('db:seed', [
        '--database' => 'pgsql',
        '--force' => true,
    ]);
}

echo Artisan::output();
echo "Tenant schema '{$schema}' provisioned successfully.\n";
