<template>
  <AppLayout>
    <div class="w-full max-w-none py-6 px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-list-check text-indigo-600"></i>
            Onboarding Template
          </h1>
          <p class="text-sm text-gray-500 mt-1">Kelola template checklist onboarding karyawan</p>
        </div>
        <Link :href="route('onboarding-templates.create')" class="inline-flex items-center gap-2 bg-indigo-600 text-white px-4 py-2 rounded-lg shadow hover:bg-indigo-700 transition">
          <i class="fa-solid fa-plus"></i>
          Buat Template
        </Link>
      </div>

      <div class="bg-white rounded-xl shadow p-4 mb-6">
        <form @submit.prevent="applyFilters" class="flex flex-col md:flex-row gap-3">
          <input v-model="filterForm.search" type="text" placeholder="Cari nama atau code..." class="flex-1 rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" />
          <button type="submit" class="px-4 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">Cari</button>
        </form>
      </div>

      <div class="bg-white rounded-xl shadow overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 border-b">
            <tr>
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Code</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Nama</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Minggu</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Status</th>
              <th class="px-4 py-3 text-right font-semibold text-gray-700">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="templates.data.length === 0">
              <td colspan="5" class="px-4 py-8 text-center text-gray-500">Belum ada template.</td>
            </tr>
            <tr v-for="row in templates.data" :key="row.id" class="border-b hover:bg-indigo-50/40">
              <td class="px-4 py-3 font-medium">{{ row.code }}</td>
              <td class="px-4 py-3">{{ row.name }}</td>
              <td class="px-4 py-3">{{ row.total_weeks }}</td>
              <td class="px-4 py-3">
                <span :class="row.is_active ? 'text-green-700 bg-green-100' : 'text-gray-600 bg-gray-100'" class="px-2 py-1 rounded-full text-xs font-semibold">
                  {{ row.is_active ? 'Aktif' : 'Nonaktif' }}
                </span>
              </td>
              <td class="px-4 py-3 text-right">
                <Link :href="route('onboarding-templates.edit', row.id)" class="text-indigo-600 hover:text-indigo-800 mr-3"><i class="fa-solid fa-pen"></i></Link>
                <button type="button" @click="destroyRow(row.id)" class="text-red-600 hover:text-red-800"><i class="fa-solid fa-trash"></i></button>
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
import { Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
  templates: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
});

const filterForm = reactive({ search: props.filters.search || '' });

function applyFilters() {
  router.get(route('onboarding-templates.index'), { ...filterForm }, { preserveState: true, replace: true });
}

function destroyRow(id) {
  Swal.fire({
    title: 'Hapus template?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    confirmButtonText: 'Hapus',
    cancelButtonText: 'Batal',
  }).then((result) => {
    if (result.isConfirmed) router.delete(route('onboarding-templates.destroy', id));
  });
}
</script>
