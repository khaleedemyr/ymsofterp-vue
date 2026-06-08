<script setup>
import { useInventoryCardSerialRows } from '@/composables/useInventoryCardSerialRows';

const props = defineProps({
  cards: { type: Array, default: () => [] },
  saldoAwal: { type: Object, default: null },
  saldoAwalDate: { type: String, default: '' },
  colspan: { type: Number, default: 6 },
  showSaldoAwal: { type: Boolean, default: true },
  formatQty: { type: Function, required: true },
  formatSaldoQty: { type: Function, required: true },
  formatReference: { type: Function, default: null },
  compact: { type: Boolean, default: false },
});

const { isGroupExpanded, toggleGroup, serialLines } = useInventoryCardSerialRows();

function displayReference(card) {
  if (typeof props.formatReference === 'function') {
    return props.formatReference(card);
  }
  if (!card?.reference_type) return '-';
  return card.reference_type + (card.reference_id ? ' #' + card.reference_id : '');
}

function rowClass(index, card) {
  if (index === props.cards.length - 1) {
    return 'bg-amber-50 font-semibold text-slate-900 ring-1 ring-inset ring-amber-200/80';
  }
  if (card?.is_grouped) {
    return 'cursor-pointer bg-violet-50/40 hover:bg-violet-50 transition';
  }
  return 'hover:bg-slate-50/80 transition';
}

function cellClass(extra = '') {
  const base = props.compact
    ? 'px-3 py-2.5 text-xs text-slate-700'
    : 'px-4 py-3 text-sm text-slate-700';
  return `${base} ${extra}`.trim();
}
</script>

<template>
  <template v-if="showSaldoAwal && saldoAwal">
    <tr class="bg-sky-50 font-semibold text-sky-950">
      <td :class="cellClass()">{{ saldoAwalDate || '-' }}</td>
      <td :class="cellClass('text-right tabular-nums')">-</td>
      <td :class="cellClass('text-right tabular-nums')">-</td>
      <td :class="cellClass('text-right tabular-nums')">{{ formatSaldoQty(saldoAwal) }}</td>
      <td :class="cellClass()">
        <span class="inline-flex rounded-md bg-sky-100 px-2 py-0.5 text-[11px] font-bold uppercase tracking-wide text-sky-800">Saldo Awal</span>
      </td>
      <td :class="cellClass('text-slate-500')">Saldo akhir bulan sebelumnya</td>
    </tr>
  </template>

  <template v-for="(card, index) in cards" :key="card.group_key || card.id || index">
    <tr :class="rowClass(index, card)" @click="card.is_grouped ? toggleGroup(card, $event) : null">
      <td :class="cellClass()">
        <div class="flex items-center gap-2">
          <span
            v-if="card.is_grouped"
            class="inline-flex h-6 w-6 items-center justify-center rounded-md bg-violet-100 text-violet-700"
          >
            <i :class="isGroupExpanded(card) ? 'fa-solid fa-chevron-down text-[10px]' : 'fa-solid fa-chevron-right text-[10px]'"></i>
          </span>
          <span class="font-medium">{{ card.date ? new Date(card.date).toLocaleDateString('id-ID') : '-' }}</span>
        </div>
      </td>
      <td :class="cellClass('text-right tabular-nums text-emerald-700')">{{ formatQty(card, 'in') }}</td>
      <td :class="cellClass('text-right tabular-nums text-rose-700')">{{ formatQty(card, 'out') }}</td>
      <td :class="cellClass('text-right tabular-nums font-medium text-slate-900')">{{ formatSaldoQty(card) }}</td>
      <td :class="cellClass()">
        <span class="inline-flex max-w-[220px] truncate rounded-md bg-slate-100 px-2 py-1 text-[11px] font-medium text-slate-700" :title="displayReference(card)">
          {{ displayReference(card) }}
        </span>
      </td>
      <td :class="cellClass()">
        <div class="flex flex-wrap items-center gap-2">
          <span class="text-slate-600">{{ card.description || '-' }}</span>
          <span
            v-if="card.is_grouped && card.serial_count"
            class="inline-flex items-center gap-1 rounded-full bg-violet-600 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white shadow-sm"
          >
            <i class="fa-solid fa-barcode text-[9px]"></i>
            {{ card.serial_count }} SN
          </span>
        </div>
      </td>
    </tr>

    <tr v-if="card.is_grouped && isGroupExpanded(card)">
      <td :colspan="colspan" :class="compact ? 'px-3 py-3 bg-violet-50/60' : 'px-4 py-4 bg-violet-50/60'">
        <div class="overflow-hidden rounded-xl border border-violet-200 bg-white shadow-sm">
          <div class="border-b border-violet-100 bg-gradient-to-r from-violet-600 to-purple-600 px-4 py-2 text-xs font-bold uppercase tracking-wider text-white">
            Detail Nomor Seri
          </div>
          <table class="min-w-full divide-y divide-violet-100 text-xs">
            <thead class="bg-violet-50/80">
              <tr>
                <th class="px-4 py-2.5 text-left font-bold uppercase tracking-wide text-violet-900">Nomor Seri</th>
                <th class="px-4 py-2.5 text-right font-bold uppercase tracking-wide text-violet-900">Masuk</th>
                <th class="px-4 py-2.5 text-right font-bold uppercase tracking-wide text-violet-900">Keluar</th>
                <th class="px-4 py-2.5 text-left font-bold uppercase tracking-wide text-violet-900">Keterangan</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-violet-50">
              <tr v-for="line in serialLines(card)" :key="line.id || line.serial_number" class="hover:bg-violet-50/50">
                <td class="px-4 py-2.5 font-mono text-sm font-semibold text-violet-900">{{ line.serial_number || '-' }}</td>
                <td class="px-4 py-2.5 text-right tabular-nums text-emerald-700">{{ formatQty(line, 'in') }}</td>
                <td class="px-4 py-2.5 text-right tabular-nums text-rose-700">{{ formatQty(line, 'out') }}</td>
                <td class="px-4 py-2.5 text-slate-600">{{ line.description || '-' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </td>
    </tr>
  </template>
</template>
