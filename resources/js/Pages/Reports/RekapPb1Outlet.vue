<script setup>
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  outlets: { type: Array, default: () => [] },
  selectedOutletId: { type: Number, default: 0 },
  selectedMonth: { type: String, default: '' },
  monthLabel: { type: String, default: '' },
  rows: { type: Array, default: () => [] },
  totals: { type: Object, default: () => ({}) },
  tableExists: { type: Boolean, default: true },
  tableReady: { type: Boolean, default: true },
  canSelectOutlet: { type: Boolean, default: false },
});

const outletId = ref(props.selectedOutletId || 0);
const month = ref(props.selectedMonth || new Date().toISOString().slice(0, 7));
const isLoading = ref(false);

watch(
  () => props.selectedOutletId,
  (v) => {
    outletId.value = v || 0;
  }
);
watch(
  () => props.selectedMonth,
  (v) => {
    month.value = v || new Date().toISOString().slice(0, 7);
  }
);

function formatRp(value) {
  const n = Number(value);
  if (!Number.isFinite(n)) return '0';
  return new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(n);
}

function formatInt(value) {
  const n = Number(value);
  if (!Number.isFinite(n)) return '0';
  return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(n);
}

function loadReport() {
  if (isLoading.value) return;
  isLoading.value = true;
  router.get(
    route('reports.rekap-pb1-outlet'),
    {
      outlet_id: outletId.value,
      month: month.value,
    },
    {
      preserveState: true,
      preserveScroll: true,
      onFinish: () => {
        isLoading.value = false;
      },
    }
  );
}

function exportExcel() {
  const url = route('reports.rekap-pb1-outlet.export', {
    outlet_id: outletId.value,
    month: month.value,
  });
  window.open(url, '_blank');
}

const currentOutletDisplayName = computed(() => {
  const id = outletId.value;
  const list = props.outlets || [];
  const o = list.find((x) => Number(x.id) === Number(id));
  return o?.name ?? '—';
});

const warningMessage = computed(() => {
  if (!props.tableExists) {
    return 'Tabel order_dummy belum ada di database. Buat tabel terlebih dahulu (lihat migration / dokumentasi kolom).';
  }
  if (!props.tableReady) {
    return 'Struktur order_dummy belum lengkap. Pastikan ada kolom: tanggal, id_outlet, total, disc, dpp, pb1, grand_total, pax, commfee, serta service_amount atau service.';
  }
  return '';
});
</script>

<template>
  <AppLayout>
    <div class="w-full py-8 px-3 sm:px-4 lg:px-6">
      <div class="mb-6 rounded-2xl bg-gradient-to-br from-amber-900 via-amber-800 to-yellow-900 p-6 text-white shadow-xl">
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-amber-200/90">HO Finance</p>
        <h1 class="mt-2 text-2xl font-bold tracking-tight">Rekap PB1 Outlet</h1>
        <p class="mt-1 text-sm text-amber-100/90">Ringkasan harian dari <code class="rounded bg-black/20 px-1">order_dummy</code></p>
      </div>

      <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-6 md:items-end">
          <div class="md:col-span-2">
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Outlet</label>
            <div
              v-if="!canSelectOutlet"
              class="flex min-h-[42px] w-full items-center rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm font-medium text-slate-900"
            >
              {{ currentOutletDisplayName }}
            </div>
            <select
              v-else
              v-model.number="outletId"
              class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm shadow-sm outline-none transition focus:border-amber-500 focus:ring-2 focus:ring-amber-100"
            >
              <option v-for="o in outlets" :key="o.id" :value="o.id">{{ o.name }}</option>
            </select>
          </div>
          <div class="md:col-span-2">
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Bulan</label>
            <input
              v-model="month"
              type="month"
              class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm shadow-sm outline-none transition focus:border-amber-500 focus:ring-2 focus:ring-amber-100"
            />
          </div>
          <div class="md:col-span-2 flex flex-wrap gap-2">
            <button
              type="button"
              class="inline-flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-5 py-2.5 text-sm font-semibold text-emerald-700 shadow-sm transition hover:bg-emerald-100"
              @click="exportExcel"
            >
              <i class="fa-solid fa-file-excel" aria-hidden="true"></i>
              Export Excel
            </button>
            <button
              type="button"
              :disabled="isLoading"
              class="inline-flex items-center gap-2 rounded-lg px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition"
              :class="isLoading ? 'cursor-not-allowed bg-amber-400' : 'bg-amber-600 hover:bg-amber-700'"
              @click="loadReport"
            >
              <i v-if="isLoading" class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i>
              {{ isLoading ? 'Memuat...' : 'Tampilkan' }}
            </button>
          </div>
        </div>

        <p v-if="monthLabel" class="mt-4 text-sm text-slate-600">
          Periode: <strong>{{ monthLabel }}</strong>
        </p>

        <div
          v-if="warningMessage"
          class="mt-4 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-950"
        >
          {{ warningMessage }}
        </div>
      </div>

      <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="max-h-[72vh] overflow-auto">
          <table class="min-w-full border-collapse text-sm">
            <thead>
              <tr class="border-b border-amber-800 bg-[#ffeb9c] text-center text-xs font-bold uppercase tracking-wide text-slate-900">
                <th class="sticky top-0 z-10 border border-amber-300 px-3 py-3">Tanggal</th>
                <th class="sticky top-0 z-10 border border-amber-300 px-3 py-3 text-right">Total</th>
                <th class="sticky top-0 z-10 border border-amber-300 px-3 py-3 text-right">Disc</th>
                <th class="sticky top-0 z-10 border border-amber-300 px-3 py-3 text-right">DPP</th>
                <th class="sticky top-0 z-10 border border-amber-300 px-3 py-3 text-right">PB1</th>
                <th class="sticky top-0 z-10 border border-amber-300 px-3 py-3 text-right">Service</th>
                <th class="sticky top-0 z-10 border border-amber-300 px-3 py-3 text-right">Grand Total</th>
                <th class="sticky top-0 z-10 border border-amber-300 px-3 py-3 text-right">Pax</th>
                <th class="sticky top-0 z-10 border border-amber-300 px-3 py-3 text-right">Commfee</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(row, idx) in rows"
                :key="row.date + '-' + idx"
                class="border-b border-slate-100 odd:bg-white even:bg-slate-50/80"
              >
                <td class="border border-slate-200 px-3 py-2 whitespace-nowrap">{{ row.date_display }}</td>
                <td class="border border-slate-200 px-3 py-2 text-right tabular-nums">{{ formatRp(row.total) }}</td>
                <td class="border border-slate-200 px-3 py-2 text-right tabular-nums">{{ formatRp(row.disc) }}</td>
                <td class="border border-slate-200 px-3 py-2 text-right tabular-nums">{{ formatRp(row.dpp) }}</td>
                <td class="border border-slate-200 px-3 py-2 text-right tabular-nums">{{ formatRp(row.pb1) }}</td>
                <td class="border border-slate-200 px-3 py-2 text-right tabular-nums">{{ formatRp(row.service) }}</td>
                <td class="border border-slate-200 px-3 py-2 text-right tabular-nums font-medium">{{ formatRp(row.grand_total) }}</td>
                <td class="border border-slate-200 px-3 py-2 text-right tabular-nums">{{ formatInt(row.pax) }}</td>
                <td class="border border-slate-200 px-3 py-2 text-right tabular-nums">{{ formatRp(row.commfee) }}</td>
              </tr>
              <tr class="border-t-2 border-amber-400 bg-[#fff2cc] font-bold">
                <td class="border border-slate-200 px-3 py-3">Total</td>
                <td class="border border-slate-200 px-3 py-3 text-right tabular-nums">{{ formatRp(totals.total) }}</td>
                <td class="border border-slate-200 px-3 py-3 text-right tabular-nums">{{ formatRp(totals.disc) }}</td>
                <td class="border border-slate-200 px-3 py-3 text-right tabular-nums">{{ formatRp(totals.dpp) }}</td>
                <td class="border border-slate-200 px-3 py-3 text-right tabular-nums">{{ formatRp(totals.pb1) }}</td>
                <td class="border border-slate-200 px-3 py-3 text-right tabular-nums">{{ formatRp(totals.service) }}</td>
                <td class="border border-slate-200 px-3 py-3 text-right tabular-nums">{{ formatRp(totals.grand_total) }}</td>
                <td class="border border-slate-200 px-3 py-3 text-right tabular-nums">{{ formatInt(totals.pax) }}</td>
                <td class="border border-slate-200 px-3 py-3 text-right tabular-nums">{{ formatRp(totals.commfee) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
