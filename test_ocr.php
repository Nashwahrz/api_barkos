<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;

$apiKey = env('OCR_SPACE_API_KEY');
echo "Menggunakan API Key: " . $apiKey . "\n";

$response = Http::post('https://api.ocr.space/parse/image', [
    'apikey' => $apiKey,
    'url' => 'https://upload.wikimedia.org/wikipedia/commons/8/87/KTP_Pribadi.jpg',
    'language' => 'eng'
]);

$result = $response->json();

if (isset($result['IsErroredOnProcessing']) && $result['IsErroredOnProcessing']) {
    echo "Error: " . $result['ErrorMessage'][0] . "\n";
} else {
    echo "Berhasil terhubung ke OCR.space!\n";
    if (!empty($result['ParsedResults'])) {
        $text = strtoupper($result['ParsedResults'][0]['ParsedText']);
        echo "Teks yang terbaca:\n" . str_replace("\n", " ", $text) . "\n";
    }
}
