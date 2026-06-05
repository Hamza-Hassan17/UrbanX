<?php

// SECURITY: Delete this file immediately after use.

// Bootstrap Laravel so Artisan is available
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$output = [];

$commands = [
    'Config Cache Clear' => 'config:clear',
    'Route Cache Clear'  => 'route:clear',
    'View Cache Clear'   => 'view:clear',
    'App Cache Clear'    => 'cache:clear',
    'Event Cache Clear'  => 'event:clear',
];

foreach ($commands as $label => $command) {
    try {
        $exitCode = Artisan::call($command);
        $output[$label] = $exitCode === 0 ? 'OK' : 'Failed (exit ' . $exitCode . ')';
    } catch (\Throwable $e) {
        $output[$label] = 'Error: ' . $e->getMessage();
    }
}

// Also manually delete compiled cache files
$filesToDelete = [
    '../bootstrap/cache/config.php',
    '../bootstrap/cache/routes-v7.php',
    '../bootstrap/cache/events.php',
    '../bootstrap/cache/packages.php',
    '../bootstrap/cache/services.php',
];

foreach ($filesToDelete as $file) {
    $realPath = realpath(__DIR__ . '/' . $file);
    if ($realPath && file_exists($realPath)) {
        unlink($realPath);
        $output['Deleted: ' . basename($realPath)] = 'OK';
    }
}

header('Content-Type: application/json');
echo json_encode(['status' => 'done', 'results' => $output], JSON_PRETTY_PRINT);
