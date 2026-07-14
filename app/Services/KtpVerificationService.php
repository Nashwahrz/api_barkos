<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class KtpVerificationService
{
    /**
     * Verify if the given image contains KTP or KTM text using OCR.space.
     * 
     * @param string $absoluteImagePath
     * @return bool
     * @throws \Exception
     */
    public function verify($absoluteImagePath): bool
    {
        $apiKey = env('OCR_SPACE_API_KEY');
        
        if (!$apiKey) {
            Log::error('OCR_SPACE_API_KEY is not set in .env');
            throw new \Exception('Sistem verifikasi KTP/KTM sedang tidak tersedia karena konfigurasi API Key OCR belum lengkap. Hubungi Admin.');
        }

        try {
            $response = Http::attach(
                'file', file_get_contents($absoluteImagePath), 'ktp_document.jpg'
            )->post('https://api.ocr.space/parse/image', [
                'apikey' => $apiKey,
                'language' => 'eng',
                'isOverlayRequired' => 'false',
                'scale' => 'true',
                'OCREngine' => '2'
            ]);

            $result = $response->json();

            if (isset($result['IsErroredOnProcessing']) && $result['IsErroredOnProcessing']) {
                throw new \Exception($result['ErrorMessage'][0] ?? 'Terjadi kesalahan pada pemrosesan dokumen.');
            }

            if (empty($result['ParsedResults'])) {
                return false;
            }

            $text = strtoupper($result['ParsedResults'][0]['ParsedText'] ?? '');
            Log::info("OCR Result: " . str_replace("\n", " ", $text));

            // Periksa kata kunci KTP atau KTM
            $keywords = [
                'KARTU TANDA PENDUDUK',
                'KARTU TANDA MAHASISWA',
                'PROVINSI',
                'NIK',
                'KEMENTERIAN',
                'KARTU MAHASISWA',
                'REPUBLIK INDONESIA'
            ];

            foreach ($keywords as $keyword) {
                if (strpos($text, $keyword) !== false) {
                    return true;
                }
            }

            return false;

        } catch (\Exception $e) {
            Log::error('OCR API Error: ' . $e->getMessage());
            throw new \Exception('Gagal terhubung ke layanan AI pengecekan dokumen.');
        }
    }
}
