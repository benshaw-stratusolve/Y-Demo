@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@if (trim($slot) === 'Laravel' || trim($slot) === 'Y')
<img src="{{ config('app.url') }}/images/Y.png" alt="Y" style="height: 80px; width: 80px; object-fit: contain;">
@else
{!! $slot !!}
@endif
</a>
</td>
</tr>
