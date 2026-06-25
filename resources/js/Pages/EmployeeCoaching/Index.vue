<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto py-8 px-4">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-user-graduate text-blue-600"></i>
            Employee Coaching
          </h1>
          <p class="text-sm text-gray-500 mt-1">Kelola dokumen coaching karyawan</p>
        </div>
        <Link
          :href="route('employee-coaching.create')"
          class="inline-flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-lg shadow hover:bg-blue-700 transition"
        >
          <i class="fa-solid fa-plus"></i>
          Tambah Baru
        </Link>
      </div>

      <div class="bg-white rounded-xl shadow p-4 mb-6">
        <form @submit.prevent="applyFilters" class="flex flex-col sm:flex-row gap-4 items-end">
          <div class="flex-1 w-full">
            <label class="block text-xs font-semibold text-gray-600 mb-1">Cari Karyawan / Outlet / Jabatan</label>
            <input
              v-model="filterForm.search"
              type="text"
              placeholder="Nama karyawan, outlet, jabatan..."
              class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
            />
          </div>
          <div class="flex gap-2">
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
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Karyawan</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Outlet</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Jabatan</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Review Plan Date</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Created At</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Created By</th>
              <th class="px-4 py-3 text-center font-semibold text-gray-700">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="records.data.length === 0">
              <td colspan="7" class="px-4 py-8 text-center text-gray-500">Belum ada data.</td>
            </tr>
            <tr v-for="row in records.data" :key="row.id" class="border-b hover:bg-gray-50">
              <td class="px-4 py-3 font-medium">{{ row.employee_name || '-' }}</td>
              <td class="px-4 py-3">{{ row.outlet_name || '-' }}</td>
              <td class="px-4 py-3">{{ row.jabatan_name || '-' }}</td>
              <td class="px-4 py-3 whitespace-nowrap">{{ formatDate(row.performance_review_plan_date) }}</td>
              <td class="px-4 py-3 whitespace-nowrap">{{ formatDateTime(row.created_at) }}</td>
              <td class="px-4 py-3">{{ row.creator?.nama_lengkap || row.creator?.name || '-' }}</td>
              <td class="px-4 py-3">
                <div class="flex justify-center gap-2">
                  <Link
                    :href="route('employee-coaching.show', row.id)"
                    class="px-3 py-1.5 rounded bg-indigo-100 text-indigo-700 hover:bg-indigo-200"
                    title="View"
                  >
                    <i class="fa-solid fa-eye"></i>
                  </Link>
                  <Link
                    :href="route('employee-coaching.edit', row.id)"
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
  filters: { type: Object, default: () => ({}) },
});

const filterForm = reactive({
  search: props.filters.search || '',
});

function formatDate(value) {
  if (!value) return '-';
  return new Date(value).toLocaleDateString('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  });
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

function applyFilters() {
  router.get(route('employee-coaching.index'), { ...filterForm }, { preserveState: true, replace: true });
}

function resetFilters() {
  filterForm.search = '';
  applyFilters();
}

function confirmDelete(row) {
  Swal.fire({
    title: 'Hapus data?',
    text: `Hapus Employee Coaching untuk ${row.employee_name}?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#dc2626',
    confirmButtonText: 'Hapus',
    cancelButtonText: 'Batal',
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(route('employee-coaching.destroy', row.id));
    }
  });
}

const page = usePage();
if (page.props.flash?.success) {
  Swal.fire({ icon: 'success', title: 'Berhasil', text: page.props.flash.success, timer: 2000, showConfirmButton: false });
}
</script>
