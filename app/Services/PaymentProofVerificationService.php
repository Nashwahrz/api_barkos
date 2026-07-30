<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymentProofVerificationService
{
    /**
     * Run OCR on a payment proof image and check whether the expected amount
     * appears anywhere in the extracted text.
     *
     * @param string $absoluteImagePath
     * @param float $expectedAmount
     * @return array{matched: bool, text: string}
     * @throws \Exception
     */
    public function verify(string $absoluteImagePath, float $expectedAmount): array
    {
        $apiKey = config('services.ocr_space.api_key');

        if (!$apiKey) {
            Log::error('OCR_SPACE_API_KEY is not set in .env');
            throw new \Exception('Sistem verifikasi bukti transfer sedang tidak tersedia karena konfigurasi API Key OCR belum lengkap.');
        }

        try {
            $response = Http::timeout(20)->attach(
                'file', file_get_contents($absoluteImagePath), 'payment_proof.jpg'
            )->post('https://api.ocr.space/parse/image', [
                'apikey' => $apiKey,
                'language' => 'eng',
                'isOverlayRequired' => 'false',
                'scale' => 'true',
                'OCREngine' => '2'
            ]);

            $result = $response->json();

            if (isset($result['IsErroredOnProcessing']) && $result['IsErroredOnProcessing']) {
                throw new \Exception($result['ErrorMessage'][0] ?? 'Terjadi kesalahan pada pemrosesan bukti transfer.');
            }

            if (empty($result['ParsedResults'])) {
                return ['matched' => false, 'text' => ''];
            }

            $text = $result['ParsedResults'][0]['ParsedText'] ?? '';

            return ['matched' => $this->amountAppearsIn($text, $expectedAmount), 'text' => $text];
        } catch (\Exception $e) {
            Log::error('OCR API Error: ' . $e->getMessage());
            throw new \Exception('Gagal terhubung ke layanan AI pengecekan bukti transfer.');
        }
    }

    /**
     * Check whether the expected nominal amount appears in the OCR text,
     * tolerating common formatting variants (thousand separators, "Rp" prefix).
     */
    private function amountAppearsIn(string $text, float $expectedAmount): bool
    {
        $normalizedText = preg_replace('/[^0-9]/', '', $text);
        $expectedDigits = (string) (int) round($expectedAmount);

        return $normalizedText !== '' && str_contains($normalizedText, $expectedDigits);
    }
}
