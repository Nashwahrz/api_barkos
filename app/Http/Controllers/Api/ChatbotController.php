<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ChatbotController extends Controller
{
    public function chat(Request $request)
    {
        $request->validate([
            'message' => 'required|string',
        ]);

        $userMessage = $request->input('message');
        $userLat    = $request->input('lat');
        $userLng    = $request->input('lng');
        $history    = $request->input('history', []);

        $allUserText = $userMessage;

        // Ambil konteks dari 2 pesan terakhir user di history
        if (is_array($history)) {
            $recentUserMessages = array_filter($history, function ($msg) {
                return isset($msg['role']) && $msg['role'] === 'user' && !empty($msg['text']);
            });
            $recentUserMessages = array_slice($recentUserMessages, -2);
            foreach ($recentUserMessages as $msg) {
                $allUserText .= ' ' . $msg['text'];
            }
        }

        // Extract keywords
        $stopWords = [
            'saya', 'ingin', 'mencari', 'tolong', 'carikan', 'yang', 'ada', 'buat', 'untuk', 'dan',
            'atau', 'di', 'ke', 'dari', 'apakah', 'punya', 'jual', 'mau', 'beli', 'cari', 'ini',
            'itu', 'berapa', 'harganya', 'tanya', 'dong', 'kasih', 'tau', 'tahu', 'banget', 'halo',
            'min', 'gan', 'kak', 'bang', 'mas', 'mbak', 'sis', 'bro', 'pak', 'buk', 'sekarang',
            'bisa', 'gak', 'enggak', 'tidak', 'lagi', 'sih', 'kok', 'ya', 'aja', 'saja', 'nya',
            'kalo', 'kalau', 'belum', 'apa', 'semua', 'daftar', 'list', 'tampilkan', 'produk', 'kamu',
        ];

        $words    = explode(' ', strtolower(preg_replace('/[^a-zA-Z0-9\s]/', '', $allUserText)));
        $keywords = array_filter($words, function ($word) use ($stopWords) {
            $allowedShortWords = ['mac', 'hp', 'tv', 'pc', 'ram', 'ssd', 'hdd'];
            if (strlen($word) < 4 && !in_array($word, $allowedShortWords)) return false;
            return !in_array($word, $stopWords);
        });
        $keywords = array_unique($keywords);

        // Search products in database
        $baseQuery = \App\Models\Product::where('status_terjual', false);

        if (!empty($keywords)) {
            $filtered = (clone $baseQuery)->where(function ($q) use ($keywords) {
                foreach ($keywords as $word) {
                    $q->orWhere('nama_barang', 'like', "%{$word}%")
                      ->orWhere('deskripsi',   'like', "%{$word}%")
                      ->orWhereHas('category', function ($cq) use ($word) {
                          $cq->where('name', 'like', "%{$word}%");
                      });
                }
            })->with('category')->latest()->limit(15)->get();

            // Fallback: jika keyword tidak menemukan hasil, tampilkan semua produk
            $products = $filtered->isNotEmpty() ? $filtered : $baseQuery->with('category')->latest()->limit(15)->get();
        } else {
            // Keywords kosong (salam/pertanyaan umum), tampilkan semua produk
            $products = $baseQuery->with('category')->latest()->limit(15)->get();
        }

        // OSRM Distance Calculation
        $osrmDistances = [];
        if ($userLat && $userLng && $products->isNotEmpty()) {
            $coords        = "{$userLng},{$userLat}";
            $validProducts = [];

            foreach ($products as $p) {
                if ($p->latitude && $p->longitude) {
                    $coords        .= ";{$p->longitude},{$p->latitude}";
                    $validProducts[] = $p->id;
                }
            }

            if (count($validProducts) > 0) {
                $osrmUrl = "https://router.project-osrm.org/table/v1/driving/{$coords}?sources=0&annotations=distance";
                try {
                    $osrmResponse = Http::timeout(5)->get($osrmUrl);
                    if ($osrmResponse->successful()) {
                        $osrmData = $osrmResponse->json();
                        if (isset($osrmData['distances'][0])) {
                            $distances = $osrmData['distances'][0];
                            foreach ($validProducts as $idx => $pId) {
                                $distanceMeters = $distances[$idx + 1] ?? null;
                                if ($distanceMeters !== null) {
                                    $osrmDistances[$pId] = round($distanceMeters / 1000, 1);
                                }
                            }
                        }
                    }
                } catch (\Exception $e) {
                    // Ignore, fallback to no distance
                }
            }
        }

        // Format produk untuk dikirim ke backend AI prompt
        $productList = [];
        $productListString = "";
        
        if ($products->isEmpty()) {
            $productListString = "Saat ini tidak ada barang yang sesuai dengan pencarian di database Lapak Kos.";
        } else {
            $productListString = "Daftar barang yang relevan saat ini di database Lapak Kos:\n";
            foreach ($products as $p) {
                $distance = $osrmDistances[$p->id] ?? null;
                $jarakText = $distance !== null ? ", Jarak: {$distance} km" : "";
                $price = number_format($p->harga, 0, ',', '.');
                $url = "/products/{$p->id}";
                
                $desc = substr(trim(preg_replace('/\s+/', ' ', $p->deskripsi)), 0, 150);
                $productListString .= "- [{$p->nama_barang}]({$url}) (Kondisi: {$p->kondisi}{$jarakText}) - Rp {$price}\n";
                $productListString .= "  Detail: {$desc}...\n";
                
                $productList[] = [
                    'id'       => $p->id,
                    'name'     => $p->nama_barang,
                    'price'    => (int) $p->harga,
                    'kondisi'  => $p->kondisi,
                    'desc'     => $desc,
                    'category' => $p->category?->name ?? '',
                    'distance' => $distance,
                    'url'      => $url,
                ];
            }
        }

        $hasLocation = (bool)($userLat && $userLng);
        $locationRules = $hasLocation 
            ? "lokasi sudah dibagikan, gunakan info Jarak yang ada." 
            : "koordinat belum dibagikan (tidak ada info Jarak), minta user klik tombol 📍 (Pin Lokasi).";

        $systemPrompt = "Kamu adalah Miu, asisten cerdas dari 'Lapak Kos' (marketplace barang bekas mahasiswa). Tugasmu: membantu membandingkan barang, memberi saran, dan MEREKOMENDASIKAN BARANG HANYA dari database Lapak Kos kepada pengguna, serta MENJAWAB PERTANYAAN seputar cara penggunaan website Lapak Kos.\n"
            . "Selalu jawab dengan bahasa Indonesia yang santai, ramah, dan singkat (maksimal 4-5 kalimat). Gunakan emoji secukupnya.\n\n"
            . "PANDUAN PENGGUNAAN APLIKASI LAPAK KOS (Gunakan info ini HANYA jika ditanya cara penggunaan):\n"
            . "- Cara Membeli: Cari barang di halaman 'Beranda' atau 'Katalog'. Klik barangnya, lalu pilih 'Ajukan Penawaran' untuk nego, atau 'Chat Penjual' untuk bertanya. Jika deal, selesaikan transaksi.\n"
            . "- Cara Menjual: Pastikan kamu sudah mendaftar sebagai penjual dengan mengklik tombol 'Mulai Jual'. Setelah itu, buka dashboard penjual dan pilih menu 'Lapak Saya' untuk menambah barang.\n"
            . "- Cara Mengedit Profil: Buka menu 'Profil' (ikon user). Di sana kamu bisa mengubah nama, foto, password, dan menentukan titik lokasi kosmu (Pin Lokasi).\n"
            . "- Info Menu: Terdapat menu Beranda, Katalog, Pesan (Chat), dan Profil. Khusus penjual, ada menu tambahan seperti Dashboard, Lapak Saya, Pesanan Masuk, Tawaran Masuk, Promosi, dan Rekening.\n\n"
            . "ATURAN SANGAT PENTING (HARUS DIPATUHI):\n"
            . "1. DILARANG KERAS MENGARANG ATAU MENAMBAHKAN BARANG YANG TIDAK ADA DI 'DATA BARANG LAPAK KOS' DI BAWAH INI!\n"
            . "2. JIKA BARANG YANG DITANYAKAN TIDAK ADA, KATAKAN: 'Maaf, barang tersebut belum ada di database Lapak Kos saat ini.' JANGAN MENGARANG HARGA ATAU NAMA BARANG.\n"
            . "3. Saat menyebutkan barang dari daftar, WAJIB sertakan link markdown-nya (contoh: [Kipas Angin](/products/5)).\n"
            . "4. Jika user menanyakan jarak namun {$locationRules}\n\n"
            . "DATA BARANG LAPAK KOS (HANYA GUNAKAN DATA INI UNTUK MENJAWAB PERTANYAAN TENTANG BARANG):\n"
            . "{$productListString}";

        $apiKey = env('GEMINI_API_KEY');
        $geminiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key={$apiKey}";
        
        $contents = [];
        if (is_array($history)) {
            foreach ($history as $msg) {
                if (empty($contents) && $msg['role'] === 'model') continue;
                
                $lastIdx = count($contents) - 1;
                if ($lastIdx >= 0 && $contents[$lastIdx]['role'] === $msg['role']) {
                    $contents[$lastIdx]['parts'][0]['text'] .= "\n\n" . $msg['text'];
                } else {
                    $contents[] = [
                        'role' => $msg['role'],
                        'parts' => [['text' => $msg['text']]]
                    ];
                }
            }
        }
        
        $lastIdx = count($contents) - 1;
        if ($lastIdx >= 0 && $contents[$lastIdx]['role'] === 'user') {
            $contents[$lastIdx]['parts'][0]['text'] .= "\n\n" . $userMessage;
        } else {
            $contents[] = [
                'role' => 'user',
                'parts' => [['text' => $userMessage]]
            ];
        }
        
        $aiText = "Maaf, Miu tidak mendapat jawaban dari server. Coba lagi ya! 🙏";
        
        // Fungsi fallback sederhana jika API Gemini limit / error
        $fallbackResponse = function($msg) use ($products, $productListString) {
            $msg = strtolower($msg);
            
            // 1. Jika ada barang yang cocok dengan pencarian, tampilkan barangnya
            if (!$products->isEmpty()) {
                return "*(Mode Offline Miu)* 🤖\nBerikut adalah daftar barang yang tersedia:\n\n" . $productListString;
            }
            
            // 2. Jika tidak ada barang, cek kata kunci panduan
            if (strpos($msg, 'beli') !== false || strpos($msg, 'pesan') !== false) {
                return "*(Mode Offline Miu)* 🤖\nCara Membeli: Cari barang di halaman 'Beranda', lalu klik 'Ajukan Penawaran' atau 'Chat Penjual'.";
            } elseif (strpos($msg, 'jual') !== false || strpos($msg, 'tambah') !== false) {
                return "*(Mode Offline Miu)* 🤖\nCara Menjual: Daftar jadi penjual di menu Profil, lalu buka 'Lapak Saya' untuk tambah barang.";
            } elseif (strpos($msg, 'profil') !== false || strpos($msg, 'edit') !== false || strpos($msg, 'password') !== false) {
                return "*(Mode Offline Miu)* 🤖\nCara Mengedit Profil: Buka menu 'Profil' untuk mengubah data.";
            } else {
                return "*(Mode Offline Miu)* 🤖\nKoneksi ke server AI utama sedang terputus atau API Key tidak valid.\n\nSaat ini belum ada produk di Lapak Kos. Kamu bisa mencoba fitur lain seperti 'Mulai Jual' untuk menambahkan barang pertamamu!";
            }
        };
        
        try {
            $response = Http::timeout(15)->post($geminiUrl, [
                'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
                'contents' => $contents
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                    $aiText = $data['candidates'][0]['content']['parts'][0]['text'];
                }
            } else {
                if ($response->status() === 429 || $response->serverError()) {
                    $aiText = $fallbackResponse($userMessage);
                } else {
                    $aiText = "⚠️ Error: Gagal memanggil Gemini API (Status: " . $response->status() . ")";
                }
            }
        } catch (\Exception $e) {
            $aiText = $fallbackResponse($userMessage);
        }

        return response()->json([
            'text'        => $aiText,
            'products'    => $productList,
            'hasLocation' => $hasLocation,
        ]);
    }
}
