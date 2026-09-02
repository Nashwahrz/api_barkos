@component('mail::message')
# Laporan Baru: {{ $report->alasan }}

Halo Admin,

**{{ $report->reporter->nama ?? 'Seorang pengguna' }}** ({{ $report->reporter->email ?? '-' }}) baru saja mengirimkan laporan{{ $report->product ? ' terhadap produk berikut' : '' }}.

@component('mail::panel')
**Alasan:** {{ $report->alasan }}<br>
@if($report->deskripsi)
**Deskripsi:** {{ Str::limit($report->deskripsi, 200) }}<br>
@endif
@if($report->product)
**Produk Dilaporkan:** {{ $report->product->nama_barang }}<br>
**Penjual:** {{ $report->product->user->nama ?? '-' }}
@endif
@endcomponent

@component('mail::button', ['url' => config('services.frontend_url') . '/admin/reports'])
Lihat Laporan
@endcomponent

Email ini otomatis dikirim ke Anda sebagai Super Admin untuk pemantauan laporan pengguna.

Terima kasih,<br>
{{ config('app.name') }}
@endcomponent
