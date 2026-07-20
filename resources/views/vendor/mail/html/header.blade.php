@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block; text-decoration: none; color: #3d4852; font-weight: bold; font-size: 24px;">
@if (trim($slot) === 'Laravel' || trim($slot) === config('app.name'))
<span style="font-size: 28px; vertical-align: middle; margin-right: 8px;">🏢</span>
<img src="{{ env('FRONTEND_URL', 'http://localhost:3000') }}/logo-lapak-kos.png" class="logo" alt="{{ config('app.name') }}" style="height: 40px; width: auto; vertical-align: middle;">
@else
{{ $slot }}
@endif
</a>
</td>
</tr>
