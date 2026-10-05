@props([
    'url',
    'color' => 'primary',
    'align' => 'center',
])
<table class="action" align="{{ $align }}" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="{{ $align }}">
<table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="{{ $align }}">
<table border="0" cellpadding="0" cellspacing="0" role="presentation">
<tr>
{{-- Outlook for Windows ignores the link's borders and padding (the
     theme draws the button with them), so the cell carries the colour
     and, for Outlook only, the padding. --}}
<td bgcolor="{{ ['success' => '#16a34a', 'error' => '#dc2626', 'green' => '#16a34a', 'red' => '#dc2626'][$color] ?? '#c9a84c' }}" style="border-radius: 4px; mso-padding-alt: 8px 18px;">
<a href="{{ $url }}" class="button button-{{ $color }}" target="_blank" rel="noopener">{!! $slot !!}</a>
</td>
</tr>
</table>
</td>
</tr>
</table>
</td>
</tr>
</table>
