<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$apiKey = env('GEMINI_API_KEY');
$url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-lite:generateContent?key=' . $apiKey;

$payload = [
    'systemInstruction' => [
        'parts' => [['text' => 'system prompt']]
    ],
    'contents' => [
        [
            'role' => 'user',
            'parts' => [['text' => 'halo']]
        ]
    ]
];

$response = \Illuminate\Support\Facades\Http::timeout(15)->post($url, $payload);
echo "Status: " . $response->status() . "\n";
echo "Body: " . $response->body() . "\n";
echo "Status: " . $response->status() . "\n";
echo "Body: " . $response->body() . "\n";
