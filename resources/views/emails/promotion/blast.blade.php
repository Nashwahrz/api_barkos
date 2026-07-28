<x-mail::message>
# Promo Spesial untuk Anda! 🎉

Ada penawaran menarik nih dari **{{ $product->user->name ?? 'Lapak Kos' }}**.

@if($promotion->ad_type === 'image')
![Gambar Produk]({{ url('storage/' . $promotion->ad_media_url) }})
@else
**[Video Promo Tersedia!]**
@endif

**{{ $promotion->ad_title ?? $product->nama_barang }}**

> {{ $product->deskripsi }}

**Harga Spesial:** Rp {{ number_format($product->harga, 0, ',', '.') }}

<x-mail::button :url="config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')) . '/products/' . $product->id">
Lihat Produk Sekarang
</x-mail::button>

Jangan sampai kehabisan, yuk cek sekarang juga!

Terima kasih,<br>
Tim {{ config('app.name') }}
</x-mail::message>
