<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-blue-800 flex items-center gap-2">
          <i class="fa-solid fa-clipboard-check text-blue-500"></i> Marketing Visit Checklist
        </h1>
        <button @click="goToCreate" class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
          + Tambah Checklist
        </button>
      </div>
      <!-- Filter -->
      <form @submit.prevent="fetchData" class="flex flex-wrap gap-2 mb-4 items-center">
        <select v-model="filter.outlet_id" class="border rounded px-3 py-2 focus:ring-2 focus:ring-blue-200 min-w-[200px]">
          <option value="">Semua Outlet</option>
          <option v-for="outlet in outlets" :key="outlet.id" :value="outlet.id">{{ outlet.name }}</option>
        </select>
        <input type="date" v-model="filter.visit_date" class="border rounded px-3 py-2 focus:ring-2 focus:ring-blue-200" />
        <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded font-semibold hover:bg-blue-600 transition">Filter</button>
        <button v-if="filter.outlet_id || filter.visit_date" type="button" @click="resetFilter" class="ml-2 bg-gray-200 text-gray-700 px-3 py-2 rounded hover:bg-gray-300 transition">Reset</button>
      </form>
      <div class="bg-white rounded-2xl shadow-2xl overflow-x-auto transition-all">
        <table class="w-full min-w-full divide-y divide-gray-200">
          <thead class="bg-gradient-to-r from-blue-50 to-blue-100">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tl-2xl">No</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Outlet</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Tanggal</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">User</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tr-2xl">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!checklists.length">
              <td colspan="5" class="text-center py-10 text-blue-300">Tidak ada data Checklist.</td>
            </tr>
            <tr v-for="(item, i) in checklists" :key="item.id" class="hover:bg-blue-50 transition shadow-sm">
              <td class="px-6 py-3">{{ i+1 }}</td>
              <td class="px-6 py-3">{{ item.outlet?.nama_outlet || '-' }}</td>
              <td class="px-6 py-3">{{ item.visit_date }}</td>
              <td class="px-6 py-3">{{ item.user?.nama_lengkap || item.user?.name || '-' }}</td>
              <td class="px-6 py-3">
                <button @click="goToShow(item.id)" class="inline-flex items-center btn btn-xs bg-blue-100 text-blue-800 hover:bg-blue-200 rounded px-2 py-1 font-semibold transition">
                  <i class="fa fa-eye mr-1"></i> Detail
                </button>
                <button @click="goToEdit(item.id)" class="ml-2 inline-flex items-center btn btn-xs bg-yellow-100 text-yellow-800 hover:bg-yellow-200 rounded px-2 py-1 font-semibold transition">
                  <i class="fa fa-edit mr-1"></i> Edit
                </button>
                <button @click="deleteChecklist(item.id)" class="ml-2 inline-flex items-center btn btn-xs bg-red-100 text-red-800 hover:bg-red-200 rounded px-2 py-1 font-semibold transition">
                  <i class="fa fa-trash mr-1"></i> Hapus
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
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const checklists = ref(page.props.checklists || []);
const outlets = ref(page.props.outlets || []);
const filter = ref({
  outlet_id: page.props.outlet_id || '',
  visit_date: page.props.visit_date || ''
});

function fetchData() {
  router.get(route('marketing-visit-checklist.index'), filter.value, { preserveState: true });
}
function resetFilter() {
  filter.value.outlet_id = '';
  filter.value.visit_date = '';
  fetchData();
}
function goToCreate() {
  router.get(route('marketing-visit-checklist.create'));
}
function goToEdit(id) {
  router.get(route('marketing-visit-checklist.edit', id));
}
function goToShow(id) {
  router.get(route('marketing-visit-checklist.show', id));
}
function deleteChecklist(id) {
  if (confirm('Yakin hapus checklist ini?')) {
    router.delete(route('marketing-visit-checklist.destroy', id));
  }
}
function exportExcel(id) {
  window.open(route('marketing-visit-checklist.export', id), '_blank');
}
</script> 