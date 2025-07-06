<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Swal from 'sweetalert2';

const props = defineProps({
  shifts: Array,
});

const search = ref('');
const showInactive = ref(false);

const filteredShifts = computed(() => {
  let data = props.shifts;
  if (!showInactive.value) {
    data = data.filter(s => s.status === 'Active' || !s.status || s.status === 1);
  }
  if (search.value) {
    const q = search.value.toLowerCase();
    data = data.filter(s =>
      (s.division?.nama_divisi || '').toLowerCase().includes(q) ||
      (s.shift_name || '').toLowerCase().includes(q)
    );
  }
  return data;
});

function openCreate() {
  router.visit('/shifts/create');
}

function openEdit(shift) {
  router.visit(`/shifts/${shift.id}/edit`);
}

function hapus(shift) {
  Swal.fire({
    title: 'Hapus Shift?',
    text: `Yakin ingin menghapus shift "${shift.shift_name}"?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Ya, Hapus!',
    cancelButtonText: 'Batal',
  }).then(result => {
    if (result.isConfirmed) {
      router.delete(`/shifts/${shift.id}`);
    }
  });
}
</script>

<template>
  <AppLayout title="Master Jam Kerja (Shift)">
    <div class="max-w-6xl mx-auto py-8">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-blue-800 flex items-center gap-2">
          <i class="fa-solid fa-clock text-blue-500"></i> Master Jam Kerja (Shift)
        </h1>
        <button @click="openCreate" class="bg-blue-600 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
          + Tambah Shift
        </button>
      </div>
      <div class="flex items-center gap-4 mb-4">
        <label class="flex items-center gap-2">
          <input type="checkbox" v-model="showInactive" class="form-checkbox rounded" />
          <span class="text-gray-600">Tampilkan Inactive</span>
        </label>
        <input
          v-model="search"
          type="text"
          placeholder="Cari divisi, nama shift..."
          class="flex-1 px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
        />
      </div>
      <div class="bg-white rounded-2xl shadow-lg overflow-x-auto">
        <table class="min-w-full divide-y divide-blue-200">
          <thead class="bg-blue-600 text-white">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Divisi</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Nama Shift</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Jam Mulai</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Jam Selesai</th>
              <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Status</th>
              <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(shift, idx) in filteredShifts" :key="shift.id" :class="idx % 2 === 1 ? 'bg-blue-50' : ''">
              <td class="px-4 py-2 whitespace-nowrap">{{ shift.division?.nama_divisi || '-' }}</td>
              <td class="px-4 py-2 whitespace-nowrap font-semibold">{{ shift.shift_name }}</td>
              <td class="px-4 py-2 whitespace-nowrap font-mono">{{ shift.time_start }}</td>
              <td class="px-4 py-2 whitespace-nowrap font-mono">{{ shift.time_end }}</td>
              <td class="px-4 py-2 whitespace-nowrap text-center">
                <span :class="shift.status === 'Inactive' || shift.status === 0 ? 'bg-gray-200 text-gray-600' : 'bg-green-100 text-green-700'" class="px-3 py-1 rounded-full text-xs font-bold">
                  {{ shift.status === 'Inactive' || shift.status === 0 ? 'Inactive' : 'Active' }}
                </span>
              </td>
              <td class="px-4 py-2 whitespace-nowrap text-center flex gap-2 justify-center">
                <button @click="openEdit(shift)" class="px-2 py-1 rounded bg-yellow-200 text-yellow-900 hover:bg-yellow-300 transition" title="Edit">
                  <i class="fa-solid fa-pen-to-square"></i>
                </button>
                <button @click="hapus(shift)" class="px-2 py-1 rounded bg-red-500 text-white hover:bg-red-600 transition" title="Hapus">
                  <i class="fa-solid fa-trash"></i>
                </button>
              </td>
            </tr>
            <tr v-if="filteredShifts.length === 0">
              <td colspan="6" class="text-center py-8 text-gray-400">Tidak ada data shift</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template> 