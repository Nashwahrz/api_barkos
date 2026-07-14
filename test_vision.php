<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Google\Cloud\Vision\V1\Client\ImageAnnotatorClient;

$credentialPath = storage_path('app/google-credentials.json');
putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $credentialPath);

try {
    echo "Mencoba menginisialisasi ImageAnnotatorClient...\n";
    $imageAnnotator = new ImageAnnotatorClient();
    echo "Berhasil! Kredensial valid dan API siap digunakan.\n";
    $imageAnnotator->close();
} catch (\Exception $e) {
    echo "Gagal: " . $e->getMessage() . "\n";
}
