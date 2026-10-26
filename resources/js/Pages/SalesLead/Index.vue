<template>
  <AppLayout>
    <div class="w-full max-w-none py-6 px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-handshake text-emerald-600"></i>
            Sales Leads
          </h1>
          <p class="text-sm text-gray-500 mt-1">Tracking sales leads — list & detail lengkap.</p>
        </div>
        <Link
          :href="route('sales-leads.create')"
          class="inline-flex items-center gap-2 bg-emerald-600 text-white px-4 py-2 rounded-lg shadow hover:bg-emerald-700 transition"
        >
          <i class="fa-solid fa-plus"></i>
          Buat Lead
        </Link>
      </div>

      <div class="bg-white rounded-xl shadow p-4 mb-6">
        <form @submit.prevent="applyFilters" class="grid grid-cols-1 md:grid-cols-5 gap-3">
          <input
            v-model="filterForm.search"
            type="text"
            placeholder="Cari nomor / company / sales..."
            class="md:col-span-2 rounded-lg border-gray-300 focus:border-emerald-500 focus:ring-emerald-500"
          />
          <select v-model="filterForm.segment_1" class="rounded-lg border-gray-300">
            <option value="">Semua Segment 1</option>
            <option v-for="s in segment1Options" :key="s" :value="s">{{ s }}</option>
          </select>
          <select v-model="filterForm.status" class="rounded-lg border-gray-300">
            <option value="">Semua Status</option>
            <option v-for="(label, key) in statuses" :key="key" :value="key">{{ label }}</option>
          </select>
          <div class="flex gap-2">
            <select v-model="filterForm.outlet_id" class="flex-1 rounded-lg border-gray-300">
              <option value="">Semua Outlet</option>
              <option v-for="o in outlets" :key="o.id_outlet" :value="o.id_outlet">{{ o.nama_outlet }}</option>
            </select>
            <button type="submit" class="px-4 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">Cari</button>
          </div>
        </form>
      </div>

      <div class="bg-white rounded-xl shadow overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 border-b">
            <tr>
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Nomor</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Company</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Segment</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Event</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Date</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Outlet</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Status</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Sales</th>
              <th class="px-4 py-3 text-right font-semibold text-gray-700">Revenue</th>
              <th class="px-4 py-3 text-right font-semibold text-gray-700">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!records.data?.length">
              <td colspan="10" class="px-4 py-8 text-center text-gray-500">Belum ada Sales Lead.</td>
            </tr>
            <tr v-for="row in records.data" :key="row.id" class="border-b hover:bg-emerald-50/40 align-top">
              <td class="px-4 py-3 font-medium">{{ row.number }}</td>
              <td class="px-4 py-3">{{ row.company_name }}</td>
              <td class="px-4 py-3">
                <div>{{ row.segment_1 || '-' }}</div>
                <div class="text-xs text-gray-500">{{ row.segment_2 || '-' }}</div>
              </td>
              <td class="px-4 py-3">{{ row.type_of_event || '-' }}</td>
              <td class="px-4 py-3">{{ formatDate(row.event_date) }}</td>
              <td class="px-4 py-3">{{ row.outlet?.nama_outlet || '-' }}</td>
              <td class="px-4 py-3">
                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold" :class="statusClass(row.status)">
                  {{ statuses[row.status] || row.status }}
                </span>
              </td>
              <td class="px-4 py-3">{{ row.sales?.nama_lengkap || '-' }}</td>
              <td class="px-4 py-3 text-right font-medium">{{ formatMoney(row.revenue) }}</td>
              <td class="px-4 py-3 text-right whitespace-nowrap">
                <Link :href="route('sales-leads.show', row.id)" class="text-emerald-600 hover:text-emerald-800 mr-3" title="Detail">
                  <i class="fa-solid fa-eye"></i>
                </Link>
                <Link :href="route('sales-leads.edit', row.id)" class="text-indigo-600 hover:text-indigo-800 mr-3" title="Edit">
                  <i class="fa-solid fa-pen"></i>
                </Link>
                <button type="button" class="text-red-600 hover:text-red-800" title="Hapus" @click="confirmDelete(row)">
                  <i class="fa-solid fa-trash"></i>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="records.links?.length > 3" class="mt-4 flex justify-center gap-1 flex-wrap">
        <Link
          v-for="(link, i) in records.links"
          :key="i"
          :href="link.url || '#'"
          class="px-3 py-1 rounded text-sm"
          :class="link.active ? 'bg-emerald-600 text-white' : 'bg-white text-gray-700 border'"
          v-html="link.label"
        />
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
  records: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
  segment1Options: { type: Array, default: () => [] },
  statuses: { type: Object, default: () => ({}) },
  outlets: { type: Array, default: () => [] },
});

const filterForm = reactive({
  search: props.filters.search || '',
  segment_1: props.filters.segment_1 || '',
  status: props.filters.status || '',
  outlet_id: props.filters.outlet_id || '',
});

const applyFilters = () => {
  router.get(route('sales-leads.index'), { ...filterForm }, { preserveState: true, replace: true });
};

const formatDate = (d) => (d ? String(d).substring(0, 10) : '-');

const formatMoney = (v) => {
  if (v === null || v === undefined || v === '') return '-';
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(v));
};

const statusClass = (s) => {
  const map = {
    new_leads: 'bg-blue-100 text-blue-800',
    connected: 'bg-sky-100 text-sky-800',
    quotation_send: 'bg-amber-100 text-amber-800',
    confirmed: 'bg-emerald-100 text-emerald-800',
    no_respond: 'bg-gray-100 text-gray-700',
    cancelled: 'bg-red-100 text-red-800',
  };
  return map[s] || 'bg-gray-100 text-gray-700';
};

const confirmDelete = async (row) => {
  const result = await Swal.fire({
    title: 'Hapus Sales Lead?',
    text: `${row.number} — ${row.company_name}`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, hapus',
    cancelButtonText: 'Batal',
    confirmButtonColor: '#dc2626',
  });
  if (!result.isConfirmed) return;
  router.delete(route('sales-leads.destroy', row.id));
};
</script>
