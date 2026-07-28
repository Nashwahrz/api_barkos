@component('mail::message')
# Barang Baru di Lapak Kos: {{ $product->nama_barang }}

Halo!

Ada barang baru yang baru saja diunggah ke Lapak Kos. Yuk cek siapa tahu ini barang yang kamu cari-cari!

@component('mail::panel')
**Nama Barang:** {{ $product->nama_barang }}<br>
**Harga:** Rp {{ number_format($product->harga, 0, ',', '.') }}<br>
**Kondisi:** {{ $product->kondisi }}<br>
**Deskripsi:** {{ Str::limit($product->deskripsi, 100) }}
@endcomponent

Silakan klik tombol di bawah ini untuk melihat detail lengkap barang tersebut dan mengamankannya sebelum keduluan orang lain!

@component('mail::button', ['url' => config('app.frontend_url', 'http://localhost:3000') . '/products/' . $product->id])
Lihat Detail Barang
@endcomponent

Terima kasih,<br>
{{ config('app.name') }}
@endcomponent
