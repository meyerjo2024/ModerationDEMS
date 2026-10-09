@props(['rows'])
<div class="overflow-hidden rounded-2xl border border-slate-200/60 dark:border-white/10">
  <table class="w-full text-left text-sm">
    <thead class="bg-slate-50/80 text-[11px] uppercase tracking-wider text-slate-500 dark:bg-white/5 dark:text-zinc-400"><tr>
      <th class="px-4 py-2.5 font-semibold">Question type</th><th class="px-3 py-2.5 text-right font-semibold">Weight</th><th class="px-3 py-2.5 text-center font-semibold">HEQF</th><th class="px-3 py-2.5 text-center font-semibold">Aligned</th><th class="hidden px-4 py-2.5 font-semibold sm:table-cell">Comment</th>
    </tr></thead>
    <tbody class="divide-y divide-slate-200/60 dark:divide-white/10">
      @foreach ($rows as $r)
        <tr><td class="px-4 py-2.5 font-medium">{{ $r['type'] }}</td><td class="px-3 py-2.5 text-right tabular-nums">{{ $r['weighting'] + 0 }}%</td><td class="px-3 py-2.5 text-center tabular-nums">{{ $r['heqf_level'] }}</td>
          <td class="px-3 py-2.5"><span class="flex justify-center"><x-icon :name="$r['aligned'] ? 'check' : 'x'" class="h-4 w-4 {{ $r['aligned'] ? 'text-emerald-500' : 'text-rose-500' }}" /></span></td>
          <td class="hidden px-4 py-2.5 text-slate-500 dark:text-zinc-400 sm:table-cell">{{ $r['comment'] ?? '' }}</td></tr>
      @endforeach
    </tbody>
    <tfoot><tr class="bg-slate-50/60 text-xs font-semibold dark:bg-white/5"><td class="px-4 py-2">Total</td><td class="px-3 py-2 text-right tabular-nums">{{ round(collect($rows)->sum('weighting'), 2) }}%</td><td colspan="3"></td></tr></tfoot>
  </table>
</div>
