<script setup>
import { ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Swal from 'sweetalert2';
import FormModal from './FormModal.vue';

const props = defineProps({
  libur: Array,
  years: Array,
  filter: Object,
});

const search = ref(props.filter?.search || '');
const tahun = ref(props.filter?.year || new Date().getFullYear());
const showForm = ref(false);
const editData = ref(null);

const filteredLibur = computed(() => {
  // Data sudah dipaging dari backend, search/filter trigger reload
  return props.libur.data || [];
});

function openCreate() {
  editData.value = null;
  showForm.value = true;
}
function openEdit(item) {
  editData.value = { ...item };
  showForm.value = true;
}
function hapus(item) {
  Swal.fire({
    title: 'Hapus Libur Nasional?',
    text: `Yakin ingin menghapus libur "${item.keterangan}" (${item.tgl_libur})?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Ya, Hapus!',
    cancelButtonText: 'Batal',
  }).then(result => {
    if (result.isConfirmed) {
      router.delete(`/kalender-perusahaan/${item.id}`);
    }
  });
}
function applyFilter() {
  router.get('/kalender-perusahaan', { year: tahun.value, search: search.value }, { preserveState: true, replace: true });
}
</script>

<template>
  <AppLayout title="Libur Nasional">
    <div class="max-w-4xl mx-auto py-8">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-blue-800 flex items-center gap-2">
          <i class="fa-solid fa-calendar-day text-blue-500"></i> Libur Nasional
        </h1>
        <button @click="openCreate" class="bg-blue-600 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
          + Tambah Libur Nasional
        </button>
      </div>
      <div class="flex items-center gap-4 mb-4">
        <select v-model="tahun" @change="applyFilter" class="px-3 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition">
          <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
        </select>
        <input
          v-model="search"
          @keyup.enter="applyFilter"
          type="text"
          placeholder="Cari tanggal, keterangan..."
          class="flex-1 px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
        />
        <button @click="applyFilter" class="bg-blue-500 text-white px-4 py-2 rounded-xl shadow hover:bg-blue-600 transition">
          <i class="fa fa-search"></i>
        </button>
      </div>
      <div class="bg-white rounded-2xl shadow-lg overflow-x-auto">
        <table class="min-w-full divide-y divide-blue-200">
          <thead class="bg-blue-600 text-white">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Tanggal Libur</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Keterangan</th>
              <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(item, idx) in filteredLibur" :key="item.id" :class="idx % 2 === 1 ? 'bg-blue-50' : ''">
              <td class="px-4 py-2 whitespace-nowrap font-mono">{{ item.tgl_libur }}</td>
              <td class="px-4 py-2 whitespace-nowrap">{{ item.keterangan }}</td>
              <td class="px-4 py-2 whitespace-nowrap text-center flex gap-2 justify-center">
                <button @click="openEdit(item)" class="px-2 py-1 rounded bg-yellow-200 text-yellow-900 hover:bg-yellow-300 transition" title="Edit">
                  <i class="fa-solid fa-pen-to-square"></i>
                </button>
                <button @click="hapus(item)" class="px-2 py-1 rounded bg-red-500 text-white hover:bg-red-600 transition" title="Hapus">
                  <i class="fa-solid fa-trash"></i>
                </button>
              </td>
            </tr>
            <tr v-if="filteredLibur.length === 0">
              <td colspan="3" class="text-center py-8 text-gray-400">Tidak ada data libur nasional</td>
            </tr>
          </tbody>
        </table>
        <div v-if="props.libur && props.libur.links && props.libur.links.length > 1" class="flex justify-center my-4 gap-1 flex-wrap">
          <button
            v-for="(link, i) in props.libur.links"
            :key="i"
            v-html="link.label"
            :disabled="!link.url"
            @click="link.url && router.get(link.url, {}, { preserveState: true, replace: true })"
            class="px-3 py-1 rounded-lg border text-sm font-semibold"
            :class="[
              link.active ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-blue-700 border-blue-200 hover:bg-blue-50',
              !link.url ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'
            ]"
          />
        </div>
      </div>
      <FormModal v-if="showForm" :show="showForm" :editData="editData" @close="showForm = false" />
    </div>
  </AppLayout>
</template> 