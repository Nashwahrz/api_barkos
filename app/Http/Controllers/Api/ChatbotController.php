<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ChatbotController extends Controller
{
    public function chat(Request $request)
    {
        try {
        $request->validate([
            'message' => 'required|string',
        ]);

        $userMessage = $request->input('message');
        $userLat    = $request->input('lat');
        $userLng    = $request->input('lng');
        $history    = $request->input('history', []);

        $cleanUserMsg = strtolower(preg_replace('/[^a-zA-Z0-9\s]/', '', $userMessage));
        $isCommand = preg_match('/^(halo|hai|pagi|siang|sore|malam|ping|test|tes|woy|hy)$/i', trim($cleanUserMsg))
                  || preg_match('/(list|daftar|tampilkan).*(baru|terbaru|terakhir)/', $cleanUserMsg)
                  || preg_match('/(dekat|terdekat|jarak)/', $cleanUserMsg)
                  || preg_match('/(semua|seluruh|daftar|list|katalog).* (barang|produk|item)|(barang|produk) (apa saja|yg ada|yang ada)/', $cleanUserMsg)
                  || preg_match('/(cara|bagaimana|gimana|panduan|tutorial|langkah)/', $cleanUserMsg);

        $allUserText = $userMessage;

        // Ambil konteks dari 2 pesan terakhir user di history JIKA bukan command spesifik
        if (!$isCommand && is_array($history)) {
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
            'barang', 'barangnya', 'dengan', 'jarak', 'dekat', 'jauh', 'lokasi', 'posisi', 'paling',
            'terdekat', 'sekitar', 'mana', 'dimana', 'baru', 'terbaru', 'terakhir', 'semua', 'seluruh',
            'saat', 'kini', 'skrg', 'dulu', 'hari', 'toko', 'lapak'
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
                    $q->where(function ($subQ) use ($word) {
                        $subQ->where('nama_barang', 'like', "%{$word}%")
                             ->orWhere('deskripsi',   'like', "%{$word}%")
                             ->orWhereHas('category', function ($cq) use ($word) {
                                 $cq->where('name', 'like', "%{$word}%");
                             });
                    });
                }
            })->with('category')->latest()->limit(15)->get();

            // Jika ada keyword, biarkan kosong jika tidak ketemu (jangan tampilkan semua)
            $products = $filtered;
        } else {
            // Keywords kosong (salam/pertanyaan umum), tampilkan semua produk
            $products = $baseQuery->with('category')->latest()->limit(50)->get();
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
                $osrmSuccess = false;
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
                                    $osrmSuccess = true;
                                }
                            }
                        }
                    }
                } catch (\Exception $e) {
                    // Ignore, fallback to haversine
                }

                // Fallback ke Haversine jika OSRM gagal
                if (!$osrmSuccess) {
                    foreach ($products as $p) {
                        if ($p->latitude && $p->longitude) {
                            $lat1 = deg2rad((float)$userLat);
                            $lon1 = deg2rad((float)$userLng);
                            $lat2 = deg2rad((float)$p->latitude);
                            $lon2 = deg2rad((float)$p->longitude);
                            
                            $dLat = $lat2 - $lat1;
                            $dLon = $lon2 - $lon1;
                            
                            $a = sin($dLat/2) * sin($dLat/2) + cos($lat1) * cos($lat2) * sin($dLon/2) * sin($dLon/2);
                            $c = 2 * asin(sqrt($a));
                            $distanceKm = 6371 * $c;
                            
                            $osrmDistances[$p->id] = round($distanceKm, 1);
                        }
                    }
                }
            }
        }

        // Format produk untuk dikirim ke backend AI prompt
        $productList = [];
        $productListString = "";
        
        if ($products->isEmpty()) {
            $productListString = "Saat ini tidak ada barang yang sesuai dengan pencarian di database Lapak Kos.";
        } else {
            $productListString = "";
            foreach ($products as $p) {
                $distance = $osrmDistances[$p->id] ?? null;
                $jarakText = $distance !== null ? ", Jarak: {$distance} km" : "";
                $price = number_format($p->harga, 0, ',', '.');
                $url = "/products/{$p->id}";
                
                $namaBarang = mb_convert_encoding($p->nama_barang, 'UTF-8', 'UTF-8');
                $kondisi = mb_convert_encoding($p->kondisi, 'UTF-8', 'UTF-8');
                $kategori = mb_convert_encoding($p->category?->name ?? '', 'UTF-8', 'UTF-8');
                
                $desc = substr(trim(preg_replace('/\s+/', ' ', $p->deskripsi ?? '')), 0, 150);
                $desc = mb_convert_encoding($desc, 'UTF-8', 'UTF-8');

                $productListString .= "- [{$namaBarang}]({$url}) (Kondisi: {$kondisi}{$jarakText}) - Rp {$price}\n";
                $productListString .= "  Detail: {$desc}...\n";
                
                $productList[] = [
                    'id'       => $p->id,
                    'name'     => $namaBarang,
                    'price'    => (int) ($p->harga ?? 0),
                    'kondisi'  => $kondisi,
                    'desc'     => $desc,
                    'category' => $kategori,
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
            . "1. Kamu TIDAK punya data barang bawaan. WAJIB panggil fungsi cari_produk untuk mendapatkan data barang setiap kali user bertanya, mencari, membandingkan, atau menanyakan ketersediaan barang apa pun -- termasuk permintaan umum seperti 'apa saja yang ada' (panggil dengan keyword kosong untuk kasus ini).\n"
            . "2. DILARANG KERAS MENGARANG ATAU MENAMBAHKAN BARANG YANG TIDAK ADA DI HASIL FUNGSI cari_produk!\n"
            . "3. JIKA HASIL PENCARIAN KOSONG, KATAKAN: 'Maaf, barang tersebut belum ada di database Lapak Kos saat ini.' JANGAN MENGARANG HARGA ATAU NAMA BARANG.\n"
            . "4. Saat menyebutkan barang dari hasil pencarian, WAJIB sertakan link markdown-nya (contoh: [Kipas Angin](/products/5)).\n"
            . "5. Jika user menanyakan jarak namun {$locationRules}\n";

        $suggestions = null;

        $fallbackResponse = function($msg) use ($products, $productListString, $keywords, &$suggestions, $osrmDistances, $hasLocation) {
            // Bersihkan tanda baca untuk pengecekan kata
            $cleanMsg = strtolower(preg_replace('/[^a-zA-Z0-9\s]/', '', $msg));
            
            // 1. Cek intent Sapaan / Greeting
            $isGreeting = preg_match('/^(halo|hai|pagi|siang|sore|malam|ping|test|tes|woy|hy)$/i', trim($cleanMsg));
            if ($isGreeting) {
                $suggestions = ['List barang terbaru', 'Barang terdekat dari sini', 'Semua list barang'];
                return "*(Mode Offline)* 🤖\nHalo! Saat ini koneksi Miu ke server AI sedang terputus. Tapi tenang, kamu tetap bisa mencari barang atau bertanya panduan Lapak Kos di sini!";
            }

            // 1.5 Cek intent Barang Terdekat
            $isTerdekat = preg_match('/(dekat|terdekat|jarak)/', $cleanMsg);
            if ($isTerdekat) {
                if (!$hasLocation) {
                    return "*(Mode Offline)* 🤖\nMiu butuh tau lokasi kamu nih! Coba klik tombol 📍 (Pin Lokasi) di sebelah kotak ketik chat ya, biar Miu bisa menghitung jarak barang terdekat.";
                }

                if (empty($osrmDistances)) {
                    return "*(Mode Offline)* 🤖\nMaaf, Miu gagal menghitung jarak. Mungkin server peta sedang sibuk atau barang-barang ini belum diatur lokasinya.";
                }

                $nearestProducts = $products->filter(function($p) use ($osrmDistances) {
                    return isset($osrmDistances[$p->id]);
                })->sortBy(function($p) use ($osrmDistances) {
                    return $osrmDistances[$p->id];
                })->take(5);

                if ($nearestProducts->isEmpty()) {
                    return "*(Mode Offline)* 🤖\nBelum ada barang di sekitar yang bisa dihitung jaraknya.";
                }
                
                $str = "";
                foreach ($nearestProducts as $p) {
                    $distance = $osrmDistances[$p->id];
                    $price = number_format($p->harga, 0, ',', '.');
                    $url = "/products/{$p->id}";
                    $namaBarang = mb_convert_encoding($p->nama_barang, 'UTF-8', 'UTF-8');
                    $kondisi = mb_convert_encoding($p->kondisi, 'UTF-8', 'UTF-8');
                    $str .= "- [{$namaBarang}]({$url}) (Kondisi: {$kondisi}, Jarak: {$distance} km) - Rp {$price}\n";
                }
                return "*(Mode Offline)* 🤖\nBerikut 5 barang terdekat dari lokasimu:\n\n" . $str . "\n💡 *Untuk jarak rute yang lebih pasti, silakan cek langsung di halaman detail produk ya!*";
            }

            // 2. Cek intent List Barang Terbaru (5 hari terakhir)
            $isListTerbaru = preg_match('/(list|daftar|tampilkan).*(baru|terbaru|terakhir)/', $cleanMsg);
            if ($isListTerbaru) {
                $latestProducts = $products->filter(function($p) {
                    return $p->created_at && $p->created_at->diffInDays(now()) <= 5;
                });
                
                if ($latestProducts->isEmpty()) {
                    return "*(Mode Offline)* 🤖\nBelum ada barang baru yang ditambahkan dalam 5 hari terakhir.";
                }
                
                $str = "";
                foreach ($latestProducts as $p) {
                    $distance = $osrmDistances[$p->id] ?? null;
                    $jarakText = $distance !== null ? ", Jarak: {$distance} km" : "";
                    $price = number_format($p->harga, 0, ',', '.');
                    $url = "/products/{$p->id}";
                    $namaBarang = mb_convert_encoding($p->nama_barang, 'UTF-8', 'UTF-8');
                    $kondisi = mb_convert_encoding($p->kondisi, 'UTF-8', 'UTF-8');
                    $desc = substr(trim(preg_replace('/\s+/', ' ', $p->deskripsi ?? '')), 0, 150);
                    $str .= "- [{$namaBarang}]({$url}) (Kondisi: {$kondisi}{$jarakText}) - Rp {$price}\n  Detail: {$desc}...\n";
                }
                return "*(Mode Offline)* 🤖\nBerikut daftar barang terbaru dalam 5 hari terakhir:\n\n" . $str;
            }

            // 3. Cek intent FAQ (HANYA JIKA ada kata tanya panduan)
            $isFaq = preg_match('/(cara|bagaimana|gimana|panduan|tutorial|langkah)/', $cleanMsg);
            
            $isBeli   = $isFaq && preg_match('/(beli|pesan|order|bayar|check out|checkout|belanja)/', $cleanMsg);
            $isJual   = $isFaq && preg_match('/(jual|dagang|tambah|posting|pasang|lapak)/', $cleanMsg);
            $isProfil = $isFaq && preg_match('/(profil|edit|ubah|ganti|password|sandi|akun|lokasi|pin)/', $cleanMsg);
            
            if ($isBeli) return "*(Mode Offline)* 🤖\n**Cara Membeli Barang:**\n1. Cari barang di 'Beranda' atau 'Katalog'.\n2. Klik barang yang diminati.\n3. Pilih 'Ajukan Penawaran' untuk nego, atau 'Chat Penjual'.\n4. Jika deal, selesaikan transaksi.";
            if ($isJual) return "*(Mode Offline)* 🤖\n**Cara Menjual Barang:**\n1. Tekan tombol 'Mulai Jual'.\n2. Masuk ke Dashboard Penjual -> 'Lapak Saya'.\n3. Tambah produk beserta foto.\n4. Tunggu pembeli menghubungi kamu!";
            if ($isProfil) return "*(Mode Offline)* 🤖\n**Cara Mengedit Profil:**\nBuka menu 'Profil' di pojok kanan atas. Di sana kamu bisa mengubah Nama, Foto, Password, dan titik lokasi kosmu (Pin Lokasi).";
            
            // 4. Cek intent Semua List Barang
            $isListAll = preg_match('/(semua|seluruh|daftar|list|katalog).* (barang|produk|item)|(barang|produk) (apa saja|yg ada|yang ada)/', $cleanMsg);
            if ($isListAll || (empty($keywords) && !$products->isEmpty())) {
                return "*(Mode Offline)* 🤖\nTentu! Berikut adalah semua daftar barang yang tersedia di Lapak Kos saat ini:\n\n" . $productListString;
            }

            // 5. Jika mencari barang spesifik dan ketemu
            if (!$products->isEmpty()) {
                return "*(Mode Offline)* 🤖\nBerikut hasil pencarian barang yang paling relevan dengan permintaanmu:\n\n" . $productListString;
            }
            
            // 6. Fallback terakhir jika tidak ada barang dan bukan FAQ
            $suggestions = ['List barang terbaru', 'Barang terdekat dari sini', 'Semua list barang'];
            return "*(Mode Offline)* 🤖\nMaaf, Miu kurang mengerti maksudmu karena saat ini Miu sedang dalam mode offline.\n\nKamu bisa mencoba beberapa perintah berikut:\n- 📦 **List barang terbaru** (melihat barang yang baru diunggah)\n- 📍 **Barang terdekat dari sini** (mencari barang di sekitar kosmu)\n- 🛍️ **Semua list barang** (melihat keseluruhan katalog)\n- ❓ Atau tanyakan panduan seperti **Cara membeli barang** atau **Cara mengedit profil**.";
        };

        $apiKey = config('services.gemini.key');
        if (empty($apiKey)) {
            $fallbackText = $fallbackResponse($userMessage);
            return response()->json([
                'text'        => $fallbackText,
                'products'    => $productList,
                'hasLocation' => $hasLocation,
                'suggestions' => $suggestions,
            ]);
        }
        $geminiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key={$apiKey}";

        // Tool definition: Gemini decides itself WHEN and with WHAT keyword to search,
        // instead of us pre-guessing intent with regex/stopwords. Keeps token usage low
        // because we only fetch + send product data when the model actually asks for it.
        $tools = [[
            'functionDeclarations' => [[
                'name' => 'cari_produk',
                'description' => 'Mencari barang bekas yang dijual di Lapak Kos berdasarkan kata kunci nama/kategori barang. Panggil setiap kali user ingin melihat, mencari, membandingkan, atau menanyakan ketersediaan barang. Kosongkan keyword untuk menampilkan barang terbaru secara umum.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'keyword' => [
                            'type' => 'STRING',
                            'description' => 'Kata kunci nama/jenis barang, contoh: "kipas angin". Kosongkan ("") jika user hanya minta ditampilkan barang secara umum.',
                        ],
                    ],
                ],
            ]],
        ]];

        // Runs the actual DB search on-demand (called only when Gemini invokes the tool).
        $runProductSearch = function (?string $keyword) use ($hasLocation, $userLat, $userLng) {
            $baseQuery = \App\Models\Product::where('status_terjual', false);
            $keyword = trim((string) $keyword);

            if ($keyword !== '') {
                $terms = array_filter(explode(' ', $keyword));
                $found = (clone $baseQuery)->where(function ($q) use ($terms) {
                    foreach ($terms as $word) {
                        $q->where(function ($subQ) use ($word) {
                            $subQ->where('nama_barang', 'like', "%{$word}%")
                                 ->orWhere('deskripsi', 'like', "%{$word}%")
                                 ->orWhereHas('category', function ($cq) use ($word) {
                                     $cq->where('name', 'like', "%{$word}%");
                                 });
                        });
                    }
                })->with('category')->latest()->limit(10)->get();
            } else {
                $found = $baseQuery->with('category')->latest()->limit(10)->get();
            }

            $distances = [];
            if ($hasLocation && $found->isNotEmpty()) {
                $coords = "{$userLng},{$userLat}";
                $validIds = [];
                foreach ($found as $p) {
                    if ($p->latitude && $p->longitude) {
                        $coords .= ";{$p->longitude},{$p->latitude}";
                        $validIds[] = $p->id;
                    }
                }
                if (count($validIds) > 0) {
                    $osrmSuccess = false;
                    try {
                        $osrmResponse = Http::timeout(5)->get("https://router.project-osrm.org/table/v1/driving/{$coords}?sources=0&annotations=distance");
                        if ($osrmResponse->successful()) {
                            $osrmData = $osrmResponse->json();
                            if (isset($osrmData['distances'][0])) {
                                foreach ($validIds as $idx => $pId) {
                                    $d = $osrmData['distances'][0][$idx + 1] ?? null;
                                    if ($d !== null) {
                                        $distances[$pId] = round($d / 1000, 1);
                                        $osrmSuccess = true;
                                    }
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        // Ignore, fallback to haversine below
                    }

                    if (!$osrmSuccess) {
                        foreach ($found as $p) {
                            if ($p->latitude && $p->longitude) {
                                $lat1 = deg2rad((float) $userLat);
                                $lon1 = deg2rad((float) $userLng);
                                $lat2 = deg2rad((float) $p->latitude);
                                $lon2 = deg2rad((float) $p->longitude);
                                $dLat = $lat2 - $lat1;
                                $dLon = $lon2 - $lon1;
                                $a = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dLon / 2) ** 2;
                                $distances[$p->id] = round(6371 * 2 * asin(sqrt($a)), 1);
                            }
                        }
                    }
                }
            }

            $items = $found->map(function ($p) use ($distances) {
                return [
                    'id'        => $p->id,
                    'nama'      => mb_convert_encoding($p->nama_barang, 'UTF-8', 'UTF-8'),
                    'harga'     => (int) $p->harga,
                    'kondisi'   => mb_convert_encoding($p->kondisi, 'UTF-8', 'UTF-8'),
                    'kategori'  => mb_convert_encoding($p->category?->name ?? '', 'UTF-8', 'UTF-8'),
                    'deskripsi' => substr(trim(preg_replace('/\s+/', ' ', $p->deskripsi ?? '')), 0, 150),
                    'jarak_km'  => $distances[$p->id] ?? null,
                    'url'       => "/products/{$p->id}",
                ];
            })->values()->all();

            return $items;
        };

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
        $finalProductList = $productList; // default: fallback ke hasil pencarian awal jika AI tidak memanggil tool

        try {
            $response = Http::timeout(15)->post($geminiUrl, [
                'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
                'contents' => $contents,
                'tools' => $tools,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $parts = $data['candidates'][0]['content']['parts'] ?? [];

                $functionCall = null;
                foreach ($parts as $part) {
                    if (isset($part['functionCall'])) {
                        $functionCall = $part['functionCall'];
                        break;
                    }
                }

                if ($functionCall) {
                    // Gemini decided it needs product data — run the real search now.
                    $keywordArg = $functionCall['args']['keyword'] ?? '';
                    $items = $runProductSearch($keywordArg);

                    $functionResponse = [
                        'name'     => 'cari_produk',
                        'response' => ['items' => $items],
                    ];
                    if (isset($functionCall['id'])) {
                        $functionResponse['id'] = $functionCall['id'];
                    }

                    $contents[] = ['role' => 'model', 'parts' => [['functionCall' => $functionCall]]];
                    $contents[] = [
                        'role'  => 'user',
                        'parts' => [['functionResponse' => $functionResponse]],
                    ];

                    $secondResponse = Http::timeout(15)->post($geminiUrl, [
                        'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
                        'contents' => $contents,
                        'tools' => $tools,
                    ]);

                    if ($secondResponse->successful()) {
                        $secondData = $secondResponse->json();
                        if (isset($secondData['candidates'][0]['content']['parts'][0]['text'])) {
                            $aiText = $secondData['candidates'][0]['content']['parts'][0]['text'];
                            $finalProductList = array_map(fn($it) => [
                                'id'       => $it['id'],
                                'name'     => $it['nama'],
                                'price'    => $it['harga'],
                                'kondisi'  => $it['kondisi'],
                                'desc'     => $it['deskripsi'],
                                'category' => $it['kategori'],
                                'distance' => $it['jarak_km'],
                                'url'      => $it['url'],
                            ], $items);
                        } else {
                            \Illuminate\Support\Facades\Log::warning('Gemini second call: no text in response', ['body' => $secondResponse->body()]);
                            $aiText = $fallbackResponse($userMessage);
                        }
                    } else {
                        \Illuminate\Support\Facades\Log::warning('Gemini second call failed', ['status' => $secondResponse->status(), 'body' => $secondResponse->body()]);
                        $aiText = $fallbackResponse($userMessage);
                    }
                } elseif (isset($parts[0]['text'])) {
                    // Chit-chat / FAQ answered directly without needing product data.
                    $aiText = $parts[0]['text'];
                } else {
                    \Illuminate\Support\Facades\Log::warning('Gemini first call: no functionCall or text found', ['body' => $response->body()]);
                    $aiText = $fallbackResponse($userMessage);
                }
            } else {
                // Fallback untuk semua error Gemini (400, 401, quota habis, dll)
                \Illuminate\Support\Facades\Log::warning('Gemini first call failed', ['status' => $response->status(), 'body' => $response->body()]);
                $aiText = $fallbackResponse($userMessage);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Chatbot Gemini call exception', ['error' => $e->getMessage()]);
            $aiText = $fallbackResponse($userMessage);
        }

        return response()->json([
            'text'        => $aiText,
            'products'    => $finalProductList,
            'hasLocation' => $hasLocation,
            'suggestions' => $suggestions ?? null,
        ]);

        } catch (\Illuminate\Validation\ValidationException $ve) {
            throw $ve; // biarkan Laravel handle validasi error (422)
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('ChatbotController error: ' . $e->getMessage());
            return response()->json([
                'text'        => 'Maaf, Miu sedang gangguan teknis. Coba lagi beberapa saat ya! 🙏',
                'products'    => [],
                'hasLocation' => false,
            ]);
        }
    }
}
