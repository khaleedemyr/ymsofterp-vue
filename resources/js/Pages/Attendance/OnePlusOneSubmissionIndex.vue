<template>
  <AppLayout>
    <div class="w-full max-w-none py-6 px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-minus-circle text-rose-600"></i>
            One Plus One
          </h1>
          <p class="text-sm text-gray-500 mt-1">Input pengurangan jam lembur per karyawan per tanggal</p>
        </div>
        <Link :href="route('one-plus-one-submissions.create')" class="inline-flex items-center gap-2 bg-rose-600 text-white px-4 py-2 rounded-lg shadow hover:bg-rose-700 transition">
          <i class="fa-solid fa-plus"></i>
          Buat One Plus One
        </Link>
      </div>

      <div class="bg-white rounded-xl shadow p-4 mb-6">
        <form @submit.prevent="applyFilters" class="flex flex-col md:flex-row gap-3">
          <input v-model="filterForm.search" type="text" placeholder="Cari nomor / pembuat..." class="flex-1 rounded-lg border-gray-300 focus:border-rose-500 focus:ring-rose-500" />
          <button type="submit" class="px-4 py-2 rounded-lg bg-rose-600 text-white hover:bg-rose-700">Cari</button>
        </form>
      </div>

      <div class="bg-white rounded-xl shadow overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 border-b">
            <tr>
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Nomor</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Tanggal</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Pembuat</th>
              <th class="px-4 py-3 text-right font-semibold text-gray-700">Jumlah Karyawan</th>
              <th class="px-4 py-3 text-right font-semibold text-gray-700">Jumlah Item</th>
              <th class="px-4 py-3 text-right font-semibold text-gray-700">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="records.data.length === 0">
              <td colspan="6" class="px-4 py-8 text-center text-gray-500">Belum ada data One Plus One.</td>
            </tr>
            <tr v-for="row in records.data" :key="row.id" class="border-b hover:bg-rose-50/40">
              <td class="px-4 py-3 font-medium">{{ row.number }}</td>
              <td class="px-4 py-3">{{ formatDate(row.submission_date) }}</td>
              <td class="px-4 py-3">{{ row.creator?.nama_lengkap || '-' }}</td>
              <td class="px-4 py-3 text-right">{{ row.employee_count || 0 }}</td>
              <td class="px-4 py-3 text-right">{{ row.items_count || 0 }}</td>
              <td class="px-4 py-3 text-right">
                <button
                  v-if="canDelete"
                  type="button"
                  @click="confirmDelete(row)"
                  class="text-red-600 hover:text-red-800"
                  title="Hapus"
                >
                  <i class="fa-solid fa-trash"></i>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
  records: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
});

const page = usePage();
const canDelete = computed(() => String(page.props.auth?.user?.id_role || '') === '5af56935b011a');

const filterForm = reactive({ search: props.filters.search || '' });

function applyFilters() {
  router.get(route('one-plus-one-submissions.index'), { ...filterForm }, { preserveState: true, replace: true });
}

function formatDate(value) {
  if (!value) return '-';
  return new Date(value).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

function confirmDelete(row) {
  Swal.fire({
    title: 'Hapus One Plus One?',
    text: `Hapus ${row.number}?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#dc2626',
    confirmButtonText: 'Hapus',
    cancelButtonText: 'Batal',
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(route('one-plus-one-submissions.destroy', row.id));
    }
  });
}
</script>
