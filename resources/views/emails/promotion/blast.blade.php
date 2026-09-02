<x-mail::message>
# Promo dari {{ $product->user->nama ?? 'Lapak Kos' }}

Ada penawaran dari **{{ $product->user->nama ?? 'Lapak Kos' }}** yang mungkin Anda minati.

@if($promotion->jenis_iklan === 'image')
![Gambar Produk]({{ url('storage/' . $promotion->url_media_iklan) }})
@else
**[Video Promo Tersedia!]**
@endif

**{{ $promotion->judul_iklan ?? $product->nama_barang }}**

> {{ $product->deskripsi }}

**Harga:** Rp {{ number_format($product->harga, 0, ',', '.') }}

<x-mail::button :url="config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')) . '/products/' . $product->id">
Lihat Produk
</x-mail::button>

Terima kasih,<br>
Tim {{ config('app.name') }}

<small>Anda menerima email ini karena terdaftar di {{ config('app.name') }}. Balas email ini dengan subjek "unsubscribe" untuk berhenti menerima promosi.</small>
</x-mail::message>
