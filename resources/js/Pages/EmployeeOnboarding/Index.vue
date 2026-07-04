<template>
  <AppLayout>
    <div class="w-full max-w-none py-6 px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-user-plus text-indigo-600"></i>
            Employee Onboarding
          </h1>
          <p class="text-sm text-gray-500 mt-1">Tracking onboarding karyawan baru per minggu</p>
        </div>
        <Link :href="route('employee-onboarding.create')" class="inline-flex items-center gap-2 bg-indigo-600 text-white px-4 py-2 rounded-lg shadow hover:bg-indigo-700 transition">
          <i class="fa-solid fa-plus"></i>
          Buat Onboarding
        </Link>
      </div>

      <div class="bg-white rounded-xl shadow p-4 mb-6">
        <form @submit.prevent="applyFilters" class="flex flex-col md:flex-row gap-3">
          <input v-model="filterForm.search" type="text" placeholder="Cari nomor, karyawan, template..." class="flex-1 rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" />
          <button type="submit" class="px-4 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">Cari</button>
        </form>
      </div>

      <div class="bg-white rounded-xl shadow overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 border-b">
            <tr>
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Nomor</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Karyawan</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Template</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Minggu</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Status</th>
              <th class="px-4 py-3 text-right font-semibold text-gray-700">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="records.data.length === 0">
              <td colspan="6" class="px-4 py-8 text-center text-gray-500">Belum ada onboarding.</td>
            </tr>
            <tr v-for="row in records.data" :key="row.id" class="border-b hover:bg-indigo-50/40">
              <td class="px-4 py-3 font-medium">{{ row.number }}</td>
              <td class="px-4 py-3">{{ row.employee?.nama_lengkap || '-' }}</td>
              <td class="px-4 py-3">{{ row.template_name }}</td>
              <td class="px-4 py-3">{{ row.unlocked_week }} / {{ row.total_weeks }}</td>
              <td class="px-4 py-3"><span class="px-2 py-1 rounded-full text-xs font-semibold bg-gray-100">{{ statusLabel(row.status) }}</span></td>
              <td class="px-4 py-3 text-right">
                <Link :href="route('employee-onboarding.show', row.id)" class="text-indigo-600 hover:text-indigo-800"><i class="fa-solid fa-eye"></i></Link>
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

const props = defineProps({
  records: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
});

const filterForm = reactive({ search: props.filters.search || '' });

function applyFilters() {
  router.get(route('employee-onboarding.index'), { ...filterForm }, { preserveState: true, replace: true });
}

function statusLabel(status) {
  const map = { draft: 'Draft', in_progress: 'In Progress', completed: 'Completed', cancelled: 'Cancelled' };
  return map[status] || status;
}
</script>
