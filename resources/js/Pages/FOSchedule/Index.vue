<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';
import Pagination from '@/Components/Pagination.vue';

const props = defineProps({
  schedules: Object,
  filters: Object
});

const search = ref(props.filters?.search || '');

function goToPage(url) {
  if (url) router.visit(url, { preserveState: true, replace: true, only: ['schedules'] });
}

watch(search, () => {
  router.get('/fo-schedules', { search: search.value }, {
    preserveState: true,
    replace: true,
    only: ['schedules'],
  });
});

function openEdit(schedule) {
  router.visit(`/fo-schedules/${schedule.id}/edit`);
}

async function hapus(schedule) {
  const result = await Swal.fire({
    title: 'Hapus Jadwal?',
    text: `Yakin ingin menghapus jadwal FO "${schedule.fo_mode}"?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Ya, Hapus!',
    cancelButtonText: 'Batal'
  });
  if (!result.isConfirmed) return;
  router.delete(`/fo-schedules/${schedule.id}`);
}

function openCreate() {
  router.visit('/fo-schedules/create');
}
</script>

<template>
  <AppLayout>
    <div class="max-w-7xl w-full mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-calendar-days text-blue-500"></i> Floor Order Schedule
        </h1>
        <button @click="openCreate" class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
          + Buat Jadwal FO
        </button>
      </div>
      
      <div class="mb-4 flex gap-2">
        <input v-model="search" type="text" placeholder="Cari jadwal FO..." class="input w-full max-w-xs" />
      </div>
      
      <div class="bg-white rounded-2xl shadow-2xl overflow-x-auto">
        <table class="w-full min-w-full divide-y divide-gray-200">
          <thead class="bg-gradient-to-r from-blue-50 to-blue-100">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Mode FO</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Warehouse Division</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Regions</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Outlets</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Hari</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Jam Operasional</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <tr v-if="!schedules.data.length">
              <td colspan="7" class="text-center py-10 text-gray-400">Tidak ada data jadwal FO.</td>
            </tr>
            <tr v-for="schedule in schedules.data" :key="schedule.id" class="hover:bg-blue-50 transition">
              <td class="px-6 py-4">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                  :class="{
                    'bg-green-100 text-green-800': schedule.fo_mode === 'FO Utama',
                    'bg-blue-100 text-blue-800': schedule.fo_mode === 'FO Tambahan',
                    'bg-purple-100 text-purple-800': schedule.fo_mode === 'FO Pengambilan'
                  }">
                  {{ schedule.fo_mode }}
                </span>
              </td>
              <td class="px-6 py-4">
                <div class="flex flex-wrap gap-1">
                  <span v-for="division in schedule.warehouse_divisions" :key="division.id"
                    class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">
                    {{ division.name }}
                  </span>
                </div>
              </td>
              <td class="px-6 py-4">
                <div class="flex flex-wrap gap-1">
                  <span v-for="region in schedule.regions" :key="region.id"
                    class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">
                    {{ region.name }}
                  </span>
                </div>
              </td>
              <td class="px-6 py-4">
                <div class="flex flex-wrap gap-1">
                  <span v-for="outlet in schedule.outlets" :key="outlet.id_outlet"
                    class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">
                    {{ outlet.nama_outlet }}
                  </span>
                </div>
              </td>
              <td class="px-6 py-4">{{ schedule.day }}</td>
              <td class="px-6 py-4">{{ schedule.open_time }} - {{ schedule.close_time }}</td>
              <td class="px-6 py-4">
                <div class="flex gap-2">
                  <button @click="openEdit(schedule)" 
                    class="inline-flex items-center px-2 py-1 bg-yellow-100 text-yellow-700 rounded hover:bg-yellow-200 transition">
                    <i class="fa fa-edit mr-1"></i> Edit
                  </button>
                  <button @click="hapus(schedule)"
                    class="inline-flex items-center px-2 py-1 bg-red-100 text-red-700 rounded hover:bg-red-200 transition">
                    <i class="fa fa-trash mr-1"></i> Hapus
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="mt-4 flex justify-center">
        <Pagination :links="schedules.links" @navigate="goToPage" />
      </div>
    </div>
  </AppLayout>
</template> 