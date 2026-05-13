<template>
  <AppLayout>
    <div class="max-w-[96rem] mx-auto py-8 px-2">
      <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 mb-6">
        <h1 class="text-2xl font-bold text-slate-800 flex items-center gap-2">
          <i class="fa-solid fa-file-circle-xmark text-blue-600"></i>
          Laporan Void dari POS
        </h1>
        <div class="flex flex-wrap items-center gap-2">
          <a
            :href="exportUrl"
            class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 text-white rounded-xl shadow hover:bg-emerald-700 transition font-semibold text-sm"
            :class="{ 'opacity-50 pointer-events-none': !filters.date_from || !filters.date_to }"
          >
            <i class="fa fa-file-excel"></i> Export Excel
          </a>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow-lg p-6 mb-6 border border-slate-100">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-4">
          <div v-if="isAdmin">
            <label class="block text-xs font-semibold text-slate-600 mb-1.5">Outlet (kode POS)</label>
            <select v-model="filters.kode_outlet" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
              <option value="">Semua outlet</option>
              <option v-for="o in props.outlets" :key="o.id_outlet" :value="o.qr_code">{{ o.nama_outlet }} ({{ o.qr_code }})</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1.5">Status</label>
            <select v-model="filters.status" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
              <option value="">Semua</option>
              <option value="pending">Pending</option>
              <option value="approved">Approved</option>
              <option value="rejected">Rejected</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1.5">Dari tanggal</label>
            <input v-model="filters.date_from" type="date" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1.5">Sampai tanggal</label>
            <input v-model="filters.date_to" type="date" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500" />
          </div>
          <div class="flex items-end gap-2">
            <button type="button" class="flex-1 px-4 py-2 bg-slate-500 text-white rounded-lg hover:bg-slate-600 text-sm font-semibold" @click="resetFilters">Reset</button>
            <button type="button" class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-semibold" @click="applyFilters">Filter</button>
          </div>
        </div>
        <p class="text-xs text-slate-500">
          Menampilkan pemohon void (kasir POS), daftar approver yang dipilih di POS, dan user HO yang menyetujui (jika approved).
          Untuk status <strong>rejected</strong>, sistem belum menyimpan nama user HO penolak — hanya catatan penolakan.
        </p>
      </div>

      <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-slate-100">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-gradient-to-r from-blue-600 to-indigo-600">
              <tr>
                <th class="px-3 py-3 text-left text-xs font-bold text-white uppercase">Tanggal</th>
                <th class="px-3 py-3 text-left text-xs font-bold text-white uppercase">Outlet</th>
                <th class="px-3 py-3 text-left text-xs font-bold text-white uppercase">Order</th>
                <th class="px-3 py-3 text-left text-xs font-bold text-white uppercase">Tipe</th>
                <th class="px-3 py-3 text-left text-xs font-bold text-white uppercase">Item / label</th>
                <th class="px-3 py-3 text-left text-xs font-bold text-white uppercase">Pemohon</th>
                <th class="px-3 py-3 text-left text-xs font-bold text-white uppercase">Approver (POS)</th>
                <th class="px-3 py-3 text-left text-xs font-bold text-white uppercase">HO approve</th>
                <th class="px-3 py-3 text-left text-xs font-bold text-white uppercase">Status</th>
                <th class="px-3 py-3 text-left text-xs font-bold text-white uppercase">Alasan</th>
                <th class="px-3 py-3 text-left text-xs font-bold text-white uppercase">Tolak</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
              <tr v-if="!props.data?.data?.length">
                <td colspan="11" class="text-center py-12 text-slate-400">Tidak ada data.</td>
              </tr>
              <tr v-for="row in props.data.data" :key="row.id" class="hover:bg-blue-50/50">
                <td class="px-3 py-2 whitespace-nowrap text-slate-700">{{ formatDt(row.created_at) }}</td>
                <td class="px-3 py-2 text-slate-800">
                  <div class="font-medium">{{ row.outlet_name || row.kode_outlet }}</div>
                  <div class="text-xs text-slate-500">{{ row.kode_outlet }}</div>
                </td>
                <td class="px-3 py-2 font-mono text-xs text-blue-800">{{ row.order_nomor || row.order_id }}</td>
                <td class="px-3 py-2 text-xs">{{ row.void_type_label }}</td>
                <td class="px-3 py-2 max-w-[220px] truncate" :title="row.item_label">{{ row.item_label }}</td>
                <td class="px-3 py-2">{{ row.requester_display }}</td>
                <td class="px-3 py-2 text-xs max-w-[200px]" :title="row.designated_approvers">{{ row.designated_approvers }}</td>
                <td class="px-3 py-2 text-xs">
                  <span v-if="row.status === 'approved'">{{ row.approved_by_name || '-' }}</span>
                  <span v-else class="text-slate-400">—</span>
                  <div v-if="row.approved_at" class="text-[10px] text-slate-500">{{ formatDt(row.approved_at) }}</div>
                </td>
                <td class="px-3 py-2">
                  <span :class="statusClass(row.status)" class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold">{{ row.status }}</span>
                </td>
                <td class="px-3 py-2 max-w-[180px] truncate text-xs" :title="row.reason">{{ row.reason }}</td>
                <td class="px-3 py-2 text-xs max-w-[160px] truncate" :title="row.rejection_note || ''">{{ row.rejection_note || '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="props.data?.links?.length > 3" class="px-4 py-3 border-t border-slate-200 flex flex-wrap justify-between items-center gap-2">
          <span class="text-sm text-slate-600">{{ props.data.from }}–{{ props.data.to }} / {{ props.data.total }}</span>
          <div class="flex flex-wrap gap-1">
            <Link
              v-for="link in props.data.links"
              :key="link.label"
              :href="link.url || '#'"
              class="px-2 py-1 rounded text-sm border"
              :class="link.active ? 'bg-blue-600 text-white border-blue-600' : link.url ? 'bg-white text-slate-700 hover:bg-slate-50' : 'opacity-40 pointer-events-none'"
              v-html="link.label"
            />
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

const page = usePage();
const user = computed(() => page.props.auth?.user || {});
const isAdmin = computed(() => Number(user.value.id_outlet) === 1);

const props = defineProps({
  data: Object,
  outlets: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
});

const filters = reactive({
  kode_outlet: props.filters?.kode_outlet || '',
  status: props.filters?.status || '',
  date_from: props.filters?.date_from || '',
  date_to: props.filters?.date_to || '',
});

function formatDt(val) {
  if (!val) return '—';
  try {
    const d = new Date(val);
    return d.toLocaleString('id-ID', { dateStyle: 'short', timeStyle: 'short' });
  } catch {
    return val;
  }
}

function statusClass(st) {
  if (st === 'approved') return 'bg-emerald-100 text-emerald-800';
  if (st === 'rejected') return 'bg-red-100 text-red-800';
  return 'bg-amber-100 text-amber-900';
}

const exportUrl = computed(() => {
  const p = new URLSearchParams();
  if (filters.kode_outlet) p.set('kode_outlet', filters.kode_outlet);
  if (filters.status) p.set('status', filters.status);
  if (filters.date_from) p.set('date_from', filters.date_from);
  if (filters.date_to) p.set('date_to', filters.date_to);
  return `/pos-void-bill-report/export?${p.toString()}`;
});

function applyFilters() {
  router.get('/pos-void-bill-report', { ...filters }, { preserveState: true, preserveScroll: true });
}

function resetFilters() {
  const df = new Date();
  df.setDate(df.getDate() - 30);
  const pad = (n) => String(n).padStart(2, '0');
  const iso = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
  Object.assign(filters, {
    kode_outlet: '',
    status: '',
    date_from: iso(df),
    date_to: iso(new Date()),
  });
  applyFilters();
}
</script>
