<template>
  <AppLayout>
    <div class="w-full py-8 px-0">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-truck"></i> Good Receive
        </h1>
        <div class="flex gap-2">
          <a 
            :href="route('food-good-receive.report')" 
            class="bg-gradient-to-r from-green-500 to-green-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold"
          >
            <i class="fa-solid fa-chart-bar mr-2"></i> Report
          </a>
          <button @click="showForm = true" class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
            + Buat Good Receive
          </button>
        </div>
      </div>
      <div class="flex flex-wrap gap-3 mb-4 items-center">
        <input
          v-model="search"
          @input="onSearchInput"
          type="text"
          placeholder="Cari Nomor GR, PO, Supplier, Petugas, atau Keterangan..."
          class="w-64 px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
        />
        <input type="date" v-model="from" @change="onDateChange" class="px-2 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition" placeholder="Dari tanggal" />
        <span>-</span>
        <input type="date" v-model="to" @change="onDateChange" class="px-2 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition" placeholder="Sampai tanggal" />
      </div>
      <div class="bg-white rounded-2xl shadow-2xl overflow-x-auto transition-all">
        <table class="w-full min-w-full divide-y divide-gray-200">
          <thead class="bg-gradient-to-r from-blue-50 to-blue-100">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tl-2xl">Nomor GR</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Tanggal</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">No. PO</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Supplier</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Petugas</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Keterangan</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tr-2xl">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!goodReceives.data || !goodReceives.data.length">
              <td colspan="7" class="text-center py-10 text-gray-400">Belum ada data Good Receive.</td>
            </tr>
            <tr v-for="gr in goodReceives.data" :key="gr.id" class="hover:bg-blue-50 transition shadow-sm">
              <td class="px-6 py-3 font-semibold">{{ gr.gr_number }}</td>
              <td class="px-6 py-3">{{ gr.receive_date }}</td>
              <td class="px-6 py-3">{{ gr.po_number }}</td>
              <td class="px-6 py-3">{{ gr.supplier_name }}</td>
              <td class="px-6 py-3">{{ gr.received_by_name }}</td>
              <td class="px-6 py-3">
                <div v-if="gr.notes" class="max-w-xs">
                  <div class="text-sm text-gray-700 truncate" :title="gr.notes">
                    {{ gr.notes }}
                  </div>
                </div>
                <div v-else class="text-gray-400 text-sm italic">
                  -
                </div>
              </td>
              <td class="px-6 py-3">
                <div class="flex gap-2">
                  <button @click="openDetail(gr.id)" class="inline-flex items-center btn btn-xs bg-blue-100 text-blue-800 hover:bg-blue-200 rounded px-2 py-1 font-semibold transition">
                    <i class="fa-solid fa-eye mr-1"></i> Detail
                  </button>
                  <button @click="handleReprint(gr.id)" :disabled="loadingReprintId === gr.id" class="inline-flex items-center btn btn-xs bg-green-100 text-green-800 hover:bg-green-200 rounded px-2 py-1 font-semibold transition disabled:opacity-50">
                    <i v-if="loadingReprintId === gr.id" class="fa fa-spinner fa-spin mr-1"></i>
                    <i v-else class="fa fa-print mr-1"></i> Reprint
                  </button>
                  <button @click="openEdit(gr.id)" class="inline-flex items-center btn btn-xs bg-yellow-100 text-yellow-800 hover:bg-yellow-200 rounded px-2 py-1 font-semibold transition">
                    <i class="fa-solid fa-pen-to-square mr-1"></i> Edit
                  </button>
                  <button 
                    v-if="props.canDelete"
                    @click="hapus(gr.id)" 
                    class="inline-flex items-center btn btn-xs bg-red-100 text-red-700 hover:bg-red-200 rounded px-2 py-1 font-semibold transition">
                    <i class="fa-solid fa-trash mr-1"></i> Hapus
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <!-- Pagination -->
      <div class="flex justify-end mt-4 gap-2">
        <button
          v-for="link in goodReceives.links"
          :key="link.label"
          :disabled="!link.url"
          @click="goToPage(link.url)"
          v-html="link.label"
          class="px-3 py-1 rounded-lg border text-sm font-semibold"
          :class="[
            link.active ? 'bg-blue-600 text-white shadow-lg' : 'bg-white text-blue-700 hover:bg-blue-50',
            !link.url ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'
          ]"
        />
      </div>
      <FormGoodReceive v-if="showForm" @close="showForm = false" />
      <ModalDetailGoodReceive :show="showDetailModal" :gr="detailGR" @close="closeDetailModal" />
      <ModalEditGoodReceive
        :show="showEditModal"
        :gr="editGR"
        @close="closeEditModal"
        @success="handleEditSuccess"
      />
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted, watch, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import axios from 'axios';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';
import FormGoodReceive from './Form.vue';
import ModalDetailGoodReceive from './ModalDetailGoodReceive.vue';
import ModalEditGoodReceive from './ModalEditGoodReceive.vue';
import { generateStrukPDF } from './generateStrukPDF';

const props = defineProps({
  goodReceives: Object,
  filters: Object,
  canDelete: {
    type: Boolean,
    default: false
  }
});

const page = usePage();
const user = computed(() => page.props.auth?.user || page.props.user);

const showForm = ref(false);
const from = ref(props.filters?.from || '');
const to = ref(props.filters?.to || '');
const search = ref(props.filters?.search || '');
const showDetailModal = ref(false);
const showEditModal = ref(false);
const detailGR = ref(null);
const editGR = ref(null);
const loadingReprintId = ref(null);

// Filter persistence functions
const saveFilterState = () => {
  const filterState = {
    search: search.value,
    from: from.value,
    to: to.value
  };
  sessionStorage.setItem('foodGoodReceiveFilters', JSON.stringify(filterState));
};

const restoreFilterState = () => {
  try {
    const savedFilters = sessionStorage.getItem('foodGoodReceiveFilters');
    if (savedFilters) {
      const filterState = JSON.parse(savedFilters);
      search.value = filterState.search || '';
      from.value = filterState.from || '';
      to.value = filterState.to || '';
    }
  } catch (error) {
    console.error('Error restoring filter state:', error);
  }
};

const debouncedSearch = debounce(() => {
  saveFilterState();
  router.get('/food-good-receive', { search: search.value, from: from.value, to: to.value }, { preserveState: true, replace: true });
}, 400);

// Watch for filter changes to auto-save
watch([search, from, to], () => {
  saveFilterState();
}, { deep: true });

function onDateChange() {
  debouncedSearch();
}
function onSearchInput() {
  debouncedSearch();
}
async function openDetail(id) {
  saveFilterState();
  try {
    const res = await fetch(`/food-good-receive/${id}`);
    if (!res.ok) throw new Error('Gagal fetch detail');
    detailGR.value = await res.json();
    showDetailModal.value = true;
  } catch (e) {
    alert('Gagal mengambil detail Good Receive');
  }
}
async function openEdit(id) {
  saveFilterState();
  try {
    const res = await fetch(`/food-good-receive/${id}`);
    if (!res.ok) throw new Error('Gagal fetch detail');
    editGR.value = await res.json();
    showEditModal.value = true;
  } catch (e) {
    alert('Gagal mengambil detail Good Receive');
  }
}
async function hapus(id) {
  const result = await Swal.fire({
    title: 'Yakin hapus data ini?',
    text: 'Data Good Receive dan semua data inventory terkait akan dihapus permanen!',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, hapus',
    cancelButtonText: 'Batal',
    reverseButtons: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6'
  });

  if (result.isConfirmed) {
    try {
      const response = await axios.delete(`/food-good-receive/${id}`);
      
      if (response.data.success) {
        await Swal.fire({
          icon: 'success',
          title: 'Berhasil!',
          text: 'Good Receive berhasil dihapus',
          timer: 2000,
          showConfirmButton: false
        });
        
        // Reload halaman dengan filter yang sama
        router.get('/food-good-receive', { 
          search: search.value, 
          from: from.value, 
          to: to.value 
        }, { 
          preserveState: true, 
          replace: true 
        });
      } else {
        throw new Error(response.data.message || 'Gagal menghapus data');
      }
    } catch (error) {
      console.error('Error deleting good receive:', error);
      await Swal.fire({
        icon: 'error',
        title: 'Gagal!',
        text: error.response?.data?.message || error.message || 'Terjadi kesalahan saat menghapus data',
        confirmButtonColor: '#3085d6'
      });
    }
  }
}
function closeDetailModal() {
  showDetailModal.value = false;
  detailGR.value = null;
}
function closeEditModal() {
  showEditModal.value = false;
  editGR.value = null;
}
function handleEditSuccess() {
  showEditModal.value = false;
  editGR.value = null;
  // Reload data dengan filter yang sama
  router.get('/food-good-receive', { search: search.value, from: from.value, to: to.value }, { preserveState: true, replace: true });
}
function goToPage(url) {
  if (url) {
    saveFilterState();
    router.get(url, { search: search.value, from: from.value, to: to.value }, { preserveState: true, replace: true });
  }
}

async function handleReprint(grId) {
  loadingReprintId.value = grId;
  try {
    const { data } = await axios.get(`/api/food-good-receive/${grId}/struk`);
    await generateStrukPDF({
      ...data,
      showReprintLabel: true
    });
  } catch (e) {
    await Swal.fire({
      icon: 'error',
      title: 'Gagal',
      text: 'Gagal mengambil data struk. Coba lagi.'
    });
  } finally {
    loadingReprintId.value = null;
  }
}

// Initialize filter state on mount
onMounted(() => {
  restoreFilterState();
  saveFilterState(); // Save initial state
});
</script> 