@component('mail::message')
# Produk Baru Diunggah: {{ $product->nama_barang }}

Halo Admin,

Penjual **{{ $product->user->nama ?? 'seorang penjual' }}** ({{ $product->user->email ?? '-' }}) baru saja mengunggah produk baru di Lapak Kos.

@component('mail::panel')
**Nama Barang:** {{ $product->nama_barang }}<br>
**Harga:** Rp {{ number_format($product->harga, 0, ',', '.') }}<br>
**Kondisi:** {{ $product->kondisi }}<br>
**Kategori:** {{ $product->category->nama ?? '-' }}<br>
**Deskripsi:** {{ Str::limit($product->deskripsi, 100) }}
@endcomponent

@component('mail::button', ['url' => config('services.frontend_url') . '/products/' . $product->id_produk])
Lihat Detail Produk
@endcomponent

Email ini otomatis dikirim ke Anda sebagai Super Admin untuk pemantauan produk baru.

Terima kasih,<br>
{{ config('app.name') }}
@endcomponent
