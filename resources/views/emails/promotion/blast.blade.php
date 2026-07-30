<x-mail::message>
# Promo dari {{ $product->user->name ?? 'Lapak Kos' }}

Ada penawaran dari **{{ $product->user->name ?? 'Lapak Kos' }}** yang mungkin Anda minati.

@if($promotion->ad_type === 'image')
![Gambar Produk]({{ url('storage/' . $promotion->ad_media_url) }})
@else
**[Video Promo Tersedia!]**
@endif

**{{ $promotion->ad_title ?? $product->nama_barang }}**

> {{ $product->deskripsi }}

**Harga:** Rp {{ number_format($product->harga, 0, ',', '.') }}

<x-mail::button :url="config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')) . '/products/' . $product->id">
Lihat Produk
</x-mail::button>

Terima kasih,<br>
Tim {{ config('app.name') }}

<small>Anda menerima email ini karena terdaftar di {{ config('app.name') }}. Balas email ini dengan subjek "unsubscribe" untuk berhenti menerima promosi.</small>
</x-mail::message>
