{{-- DECLARATION block. $rows: [[role, signature|null, notApplicable?], …] --}}
<div style="page-break-inside:avoid"><div class="banner-peach nb">{{ config('moderation_form.consensus') }}</div>
<table class="nb" style="margin-top:0">
  @foreach ($rows as $r)
    @php [$role, $s] = $r; $na = $r[2] ?? false; @endphp
    <tr class="g2 b c"><td style="width:46%;text-align:left">NAME: {{ $role }} (please print)</td><td style="width:30%">SIGNATURE</td><td>DATE</td></tr>
    <tr>
      <td class="sigcell b" style="text-align:left">{{ $na ? 'Not applicable' : ($s['name'] ?? '') }}</td>
      <td class="sigcell">@if ($s)<img src="{{ $s['image'] }}">@endif</td>
      <td class="sigcell">{{ $s['date'] ?? '' }}</td>
    </tr>
  @endforeach
</table>
</div>
