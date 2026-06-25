<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto py-8 px-4">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-arrow-trend-up text-blue-600"></i>
            Upselling Sales Achievement
          </h1>
          <p class="text-sm text-gray-500 mt-1">Kelola target upselling per outlet per bulan</p>
        </div>
        <Link
          :href="route('upselling-sales-achievement.create')"
          class="inline-flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-lg shadow hover:bg-blue-700 transition"
        >
          <i class="fa-solid fa-plus"></i>
          Tambah Baru
        </Link>
      </div>

      <div class="bg-white rounded-xl shadow p-4 mb-6">
        <form @submit.prevent="applyFilters" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
          <div class="md:col-span-2">
            <label class="block text-xs font-semibold text-gray-600 mb-1">Cari Outlet</label>
            <input
              v-model="filterForm.search"
              type="text"
              placeholder="Nama outlet..."
              class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
            />
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Outlet</label>
            <select v-model="filterForm.outlet_id" class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">
              <option value="">Semua</option>
              <option v-for="outlet in outlets" :key="outlet.id_outlet" :value="outlet.id_outlet">
                {{ outlet.nama_outlet }}
              </option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Bulan</label>
            <select v-model="filterForm.month" class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">
              <option value="">Semua</option>
              <option v-for="m in monthOptions" :key="m.value" :value="m.value">{{ m.label }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Tahun</label>
            <input
              v-model="filterForm.year"
              type="number"
              min="2000"
              max="2100"
              placeholder="Tahun"
              class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
            />
          </div>
          <div class="md:col-span-5 flex gap-2 justify-end">
            <button type="button" @click="resetFilters" class="px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700">
              Reset
            </button>
            <button type="submit" class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700">
              Filter
            </button>
          </div>
        </form>
      </div>

      <div class="bg-white rounded-xl shadow overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 border-b">
            <tr>
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Outlet</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Bulan</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Tahun</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Created At</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Created By</th>
              <th class="px-4 py-3 text-center font-semibold text-gray-700">Achievement %</th>
              <th class="px-4 py-3 text-center font-semibold text-gray-700">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="records.data.length === 0">
              <td colspan="7" class="px-4 py-8 text-center text-gray-500">Belum ada data.</td>
            </tr>
            <tr v-for="row in records.data" :key="row.id" class="border-b hover:bg-gray-50">
              <td class="px-4 py-3">{{ row.outlet?.nama_outlet || '-' }}</td>
              <td class="px-4 py-3">{{ monthLabel(row.month) }}</td>
              <td class="px-4 py-3">{{ row.year }}</td>
              <td class="px-4 py-3 whitespace-nowrap">{{ formatDateTime(row.created_at) }}</td>
              <td class="px-4 py-3">{{ row.creator?.nama_lengkap || row.creator?.name || '-' }}</td>
              <td class="px-4 py-3 text-center">
                <span :class="achievementClass(row.achievement_percent)" class="px-2 py-1 rounded font-semibold text-xs whitespace-nowrap">
                  {{ formatPercent(row.achievement_percent) }}
                </span>
              </td>
              <td class="px-4 py-3">
                <div class="flex justify-center gap-2">
                  <Link
                    :href="route('upselling-sales-achievement.show', row.id)"
                    class="px-3 py-1.5 rounded bg-indigo-100 text-indigo-700 hover:bg-indigo-200"
                    title="Show Detail"
                  >
                    <i class="fa-solid fa-eye"></i>
                  </Link>
                  <Link
                    :href="route('upselling-sales-achievement.edit', row.id)"
                    class="px-3 py-1.5 rounded bg-amber-100 text-amber-700 hover:bg-amber-200"
                    title="Edit"
                  >
                    <i class="fa-solid fa-pen"></i>
                  </Link>
                  <button
                    type="button"
                    @click="confirmDelete(row)"
                    class="px-3 py-1.5 rounded bg-red-100 text-red-700 hover:bg-red-200"
                    title="Delete"
                  >
                    <i class="fa-solid fa-trash"></i>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="records.links?.length > 3" class="mt-4 flex flex-wrap gap-1">
        <Link
          v-for="link in records.links"
          :key="link.label"
          :href="link.url || '#'"
          class="px-3 py-1 rounded border text-sm"
          :class="link.active ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
          v-html="link.label"
        />
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { reactive } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
  records: { type: Object, required: true },
  outlets: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
  monthOptions: { type: Array, default: () => [] },
});

const filterForm = reactive({
  search: props.filters.search || '',
  outlet_id: props.filters.outlet_id || '',
  month: props.filters.month || '',
  year: props.filters.year || '',
});

const monthMap = Object.fromEntries(props.monthOptions.map((m) => [m.value, m.label]));

function monthLabel(month) {
  return monthMap[month] || month;
}

function formatDateTime(value) {
  if (!value) return '-';
  return new Date(value).toLocaleString('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function formatPercent(val) {
  const n = Number(val) || 0;
  return `${n.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}%`;
}

function achievementClass(pct) {
  const n = Number(pct) || 0;
  if (n >= 100) return 'bg-green-100 text-green-800';
  if (n >= 75) return 'bg-yellow-100 text-yellow-800';
  return 'bg-red-100 text-red-800';
}

function applyFilters() {
  router.get(route('upselling-sales-achievement.index'), { ...filterForm }, { preserveState: true, replace: true });
}

function resetFilters() {
  filterForm.search = '';
  filterForm.outlet_id = '';
  filterForm.month = '';
  filterForm.year = '';
  applyFilters();
}

function confirmDelete(row) {
  Swal.fire({
    title: 'Hapus data?',
    text: `Hapus target upselling ${row.outlet?.nama_outlet || ''} - ${monthLabel(row.month)} ${row.year}?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#dc2626',
    confirmButtonText: 'Hapus',
    cancelButtonText: 'Batal',
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(route('upselling-sales-achievement.destroy', row.id));
    }
  });
}

const page = usePage();
if (page.props.flash?.success) {
  Swal.fire({ icon: 'success', title: 'Berhasil', text: page.props.flash.success, timer: 2000, showConfirmButton: false });
}
</script>
