<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';
import ImportModal from './ImportModal.vue';

const props = defineProps({
  roulettes: Object, // { data, links, meta }
  filters: Object,
});

const search = ref(props.filters?.search || '');
const showImportModal = ref(false);

const debouncedSearch = debounce(() => {
  router.get('/roulette', {
    search: search.value,
  }, { preserveState: true, replace: true });
}, 400);

function onSearchInput() {
  debouncedSearch();
}

function goToPage(url) {
  if (url) router.visit(url, { preserveState: true, replace: true });
}

function openCreate() {
  router.visit('/roulette/create');
}

function openEdit(roulette) {
  router.visit(`/roulette/${roulette.id}/edit`);
}

function openShow(roulette) {
  router.visit(`/roulette/${roulette.id}`);
}

async function hapus(roulette) {
  const result = await Swal.fire({
    title: 'Hapus Data Roulette?',
    text: `Yakin ingin menghapus data "${roulette.nama}"?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Ya, Hapus!',
    cancelButtonText: 'Batal',
  });
  if (!result.isConfirmed) return;
  
  // Tampilkan loading animation
  Swal.fire({
    title: 'Menghapus Data...',
    text: 'Mohon tunggu sebentar, sedang menghapus data...',
    icon: 'info',
    allowOutsideClick: false,
    allowEscapeKey: false,
    showConfirmButton: false,
    didOpen: () => {
      Swal.showLoading();
    }
  });
  
  router.delete(route('roulette.destroy', roulette.id), {
    onSuccess: () => {
      Swal.fire({
        title: 'Berhasil!',
        text: 'Data roulette berhasil dihapus!',
        icon: 'success',
        confirmButtonText: 'OK'
      });
    },
    onError: (errors) => {
      Swal.fire({
        title: 'Error!',
        text: 'Gagal menghapus data: ' + (errors.message || 'Terjadi kesalahan'),
        icon: 'error',
        confirmButtonText: 'OK'
      });
    }
  });
}

function reload() {
  router.reload({ preserveState: true, replace: true });
}

function openImportModal() {
  showImportModal.value = true;
}

function closeImportModal() {
  showImportModal.value = false;
}

function openRouletteGame() {
  window.open(route('roulette.game'), '_blank');
}

function openGridGame() {
  window.open(route('roulette.grid'), '_blank');
}

function openSlotMachine() {
  window.open(route('roulette.slot'), '_blank');
}

function openLotteryMachine() {
  window.open(route('roulette.lottery'), '_blank');
}

async function hapusSemua() {
  const result = await Swal.fire({
    title: 'Hapus Semua Peserta?',
    text: `Yakin ingin menghapus seluruh ${props.roulettes.data.length} data peserta roulette? Tindakan ini tidak dapat dibatalkan!`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Ya, Hapus Semua!',
    cancelButtonText: 'Batal',
    input: 'text',
    inputLabel: 'Ketik "HAPUS" untuk konfirmasi',
    inputPlaceholder: 'HAPUS',
    inputValidator: (value) => {
      if (value !== 'HAPUS') {
        return 'Anda harus mengetik "HAPUS" untuk melanjutkan';
      }
    }
  });
  
  if (!result.isConfirmed) return;
  
  // Tampilkan loading animation
  Swal.fire({
    title: 'Menghapus Data...',
    text: 'Mohon tunggu sebentar, sedang menghapus seluruh data peserta...',
    icon: 'info',
    allowOutsideClick: false,
    allowEscapeKey: false,
    showConfirmButton: false,
    didOpen: () => {
      Swal.showLoading();
    }
  });
  
  router.delete(route('roulette.destroy-all'), {
    onSuccess: () => {
      Swal.fire({
        title: 'Berhasil!',
        text: 'Semua data peserta roulette berhasil dihapus!',
        icon: 'success',
        confirmButtonText: 'OK'
      });
    },
    onError: (errors) => {
      Swal.fire({
        title: 'Error!',
        text: 'Gagal menghapus data: ' + (errors.message || 'Terjadi kesalahan'),
        icon: 'error',
        confirmButtonText: 'OK'
      });
    }
  });
}
</script>

<template>
  <AppLayout title="Data Roulette">
    <div class="max-w-7xl w-full mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-dice text-purple-500"></i> Data Roulette
          </h1>
          <p class="text-sm text-gray-600 mt-1">Total: {{ roulettes.data.length }} peserta</p>
        </div>
        <div class="flex gap-2">
          <button @click="openRouletteGame" class="bg-gradient-to-r from-green-500 to-green-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold flex items-center gap-2">
            <i class="fa-solid fa-play"></i>
            Main Roulette
          </button>
          <button @click="openGridGame" class="bg-gradient-to-r from-purple-500 to-purple-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold flex items-center gap-2">
            <i class="fa-solid fa-th"></i>
            Grid Game
          </button>
          <button @click="openSlotMachine" class="bg-gradient-to-r from-yellow-500 to-orange-600 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold flex items-center gap-2">
            <i class="fa-solid fa-slot-machine"></i>
            Slot Machine
          </button>
          <button @click="openLotteryMachine" class="bg-gradient-to-r from-red-500 to-pink-600 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold flex items-center gap-2">
            <i class="fa-solid fa-dice"></i>
            Lottery Machine
          </button>
          <button @click="openImportModal" class="bg-green-500 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold flex items-center gap-2">
            <i class="fas fa-file-excel"></i>
            Import Excel
          </button>
          <button @click="openCreate" class="bg-gradient-to-r from-purple-500 to-purple-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
            + Tambah Data Roulette
          </button>
          <button 
            v-if="roulettes.data.length > 0"
            @click="hapusSemua" 
            class="bg-gradient-to-r from-red-500 to-red-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold flex items-center gap-2"
          >
            <i class="fa-solid fa-trash-can"></i>
            Hapus Semua
          </button>
        </div>
      </div>
      <div class="mb-4">
        <input
          v-model="search"
          @input="onSearchInput"
          type="text"
          placeholder="Cari nama, email, no hp..."
          class="w-full px-4 py-2 rounded-xl border border-purple-200 shadow focus:ring-2 focus:ring-purple-400 focus:border-purple-400 transition"
        />
      </div>
      <div class="bg-white rounded-2xl shadow-lg overflow-x-auto">
        <table class="min-w-full divide-y divide-purple-200">
          <thead class="bg-purple-600 text-white">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">No</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Nama</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Email</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">No HP</th>
              <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(roulette, index) in roulettes.data" :key="roulette.id" class="hover:bg-purple-50 transition">
              <td class="px-4 py-2 whitespace-nowrap">{{ index + 1 }}</td>
              <td class="px-4 py-2 whitespace-nowrap font-semibold">{{ roulette.nama }}</td>
              <td class="px-4 py-2 whitespace-nowrap">{{ roulette.email }}</td>
              <td class="px-4 py-2 whitespace-nowrap">{{ roulette.no_hp }}</td>
              <td class="px-4 py-2 whitespace-nowrap text-center flex gap-2 justify-center">
                <button @click="openShow(roulette)" class="px-2 py-1 rounded bg-blue-100 text-blue-700 hover:bg-blue-200 transition" title="Detail">
                  <i class="fa-solid fa-eye"></i>
                </button>
                <button @click="openEdit(roulette)" class="px-2 py-1 rounded bg-yellow-200 text-yellow-900 hover:bg-yellow-300 transition" title="Edit">
                  <i class="fa-solid fa-pen-to-square"></i>
                </button>
                <button @click="hapus(roulette)" class="px-2 py-1 rounded bg-red-500 text-white hover:bg-red-600 transition" title="Hapus">
                  <i class="fa-solid fa-trash"></i>
                </button>
              </td>
            </tr>
            <tr v-if="roulettes.data.length === 0">
              <td colspan="5" class="text-center py-8 text-gray-400">Tidak ada data roulette</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="mt-4 flex justify-end">
        <nav v-if="roulettes.links && roulettes.links.length > 3" class="inline-flex -space-x-px">
          <template v-for="(link, i) in roulettes.links" :key="i">
            <button v-if="link.url" @click="goToPage(link.url)" :class="['px-3 py-1 border border-gray-300', link.active ? 'bg-purple-600 text-white' : 'bg-white text-purple-700 hover:bg-purple-50']" v-html="link.label"></button>
            <span v-else class="px-3 py-1 border border-gray-200 text-gray-400" v-html="link.label"></span>
          </template>
        </nav>
      </div>
    </div>
    <ImportModal :show="showImportModal" @close="closeImportModal" />
  </AppLayout>
</template> 