<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Promotion;
use Illuminate\Support\Facades\Http;

$serverKey = config('midtrans.server_key');

$promo = Promotion::where('payment_status', 'pending')->latest()->first();

if (!$promo) {
    echo "Tidak ada promosi dengan status pending.\n";
    exit;
}

$orderId = $promo->order_id;
$statusCode = '200';
// Midtrans gross_amount in webhook usually has .00, let's match the string format or exact value
$grossAmount = number_format($promo->amount_paid, 2, '.', '');
if (strpos($grossAmount, '.00') !== false && env('MIDTRANS_IS_PRODUCTION', false) == false) {
    // sometimes sandbox gross amount is formatted differently, but standard is with 2 decimals
}

$signatureKey = hash("sha512", $orderId . $statusCode . $grossAmount . $serverKey);

$payload = [
    'order_id' => $orderId,
    'status_code' => $statusCode,
    'gross_amount' => $grossAmount,
    'signature_key' => $signatureKey,
    'transaction_status' => 'settlement',
];

echo "Mengirim webhook simulasi untuk Order ID: {$orderId}...\n";

$response = Http::post('http://localhost:8000/api/midtrans/webhook', $payload);

echo "Status Response: " . $response->status() . "\n";
echo "Body: " . $response->body() . "\n";
