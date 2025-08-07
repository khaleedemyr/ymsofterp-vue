<script setup>
import { ref, computed, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import axios from 'axios';
import dayjs from 'dayjs';

const props = defineProps({
  user: Object,
  packingLists: Object,
  filters: Object,
});

const search = ref(props.filters?.search || '');
const selectedStatus = ref(props.filters?.status || '');
const from = ref(props.filters?.from || '');
const to = ref(props.filters?.to || '');

const showSummaryModal = ref(false);
const summaryDate = ref(dayjs().format('YYYY-MM-DD'));
const summaryLoading = ref(false);
const summaryItems = ref([]);
const summaryError = ref('');

const showUnpickedModal = ref(false);
const unpickedDate = ref(dayjs().format('YYYY-MM-DD'));
const unpickedLoading = ref(false);
const unpickedData = ref([]);
const unpickedError = ref('');
const expandedOutlets = ref(new Set());
const expandedWarehouseOutlets = ref(new Set());
const expandedWarehouseDivisions = ref(new Set());
const expandedSummaryDivisions = ref(new Set());
const exportLoading = ref(false);
const summaryExportLoading = ref(false);

watch(
  () => props.filters,
  (filters) => {
    search.value = filters?.search || '';
    selectedStatus.value = filters?.status || '';
    from.value = filters?.from || '';
    to.value = filters?.to || '';
  },
  { immediate: true }
);

function debouncedSearch() {
  router.get('/packing-list', { 
    search: search.value, 
    status: selectedStatus.value, 
    from: from.value, 
    to: to.value 
  }, { preserveState: true, replace: true });
}

function onSearchInput() {
  debouncedSearch();
}

function onStatusChange() {
  debouncedSearch();
}

function onDateChange() {
  debouncedSearch();
}
function goToPage(url) {
  if (url) router.visit(url, { preserveState: true, replace: true });
}
function openCreate() {
  window.location.href = '/packing-list/create';
}
function openEdit(id) {
  router.visit(`/packing-list/edit/${id}`);
}
function openDetail(id) {
  router.visit(`/packing-list/${id}`);
}
async function hapus(list) {
  const result = await Swal.fire({
    title: 'Hapus Packing List?',
    text: `Yakin ingin menghapus Packing List ${list.packing_number}?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Ya, Hapus!',
    cancelButtonText: 'Batal'
  });
  if (!result.isConfirmed) return;
  try {
    await axios.delete(`/packing-list/${list.id}`);
    Swal.fire({
      icon: 'success',
      title: 'Berhasil',
      text: 'Packing List berhasil dihapus.',
      timer: 1500,
      showConfirmButton: false
    });
    window.location.reload();
  } catch (err) {
    Swal.fire({
      icon: 'error',
      title: 'Gagal',
      text: err?.response?.data?.error || 'Tidak bisa hapus Packing List ini.'
    });
  }
}

function formatRupiah(val) {
  if (!val) return 'Rp 0';
  return 'Rp ' + Number(val).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function getSubtotal(list) {
  if (!list.items) return 0;
  return list.items.reduce((sum, item) => sum + (Number(item.subtotal) || 0), 0);
}
const grandTotal = computed(() =>
  props.packingLists.data.reduce((sum, list) => sum + getSubtotal(list), 0)
);

async function openSummaryModal() {
  showSummaryModal.value = true;
  await fetchSummary();
}
async function fetchSummary() {
  summaryLoading.value = true;
  summaryError.value = '';
  summaryItems.value = [];
  expandedSummaryDivisions.value.clear();
  try {
    const res = await axios.get('/api/packing-list/summary', { params: { tanggal: summaryDate.value } });
    summaryItems.value = res.data.divisions || [];
  } catch (e) {
    summaryError.value = 'Gagal mengambil data rangkuman.';
  } finally {
    summaryLoading.value = false;
  }
}

async function openUnpickedModal() {
  showUnpickedModal.value = true;
  await fetchUnpickedData();
}

async function fetchUnpickedData() {
  unpickedLoading.value = true;
  unpickedError.value = '';
  unpickedData.value = [];
  expandedOutlets.value.clear();
  expandedWarehouseOutlets.value.clear();
  expandedWarehouseDivisions.value.clear();
  try {
    const res = await axios.get('/api/packing-list/unpicked-floor-orders', { params: { tanggal: unpickedDate.value } });
    unpickedData.value = res.data.outlets || [];
  } catch (e) {
    unpickedError.value = 'Gagal mengambil data FO yang belum di-packing.';
  } finally {
    unpickedLoading.value = false;
  }
}

async function exportUnpickedData() {
  exportLoading.value = true;
  try {
    const response = await axios.get('/api/packing-list/export-unpicked-floor-orders', {
      params: { tanggal: unpickedDate.value },
      responseType: 'blob'
    });
    
    // Create download link
    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', `RO_Belum_di_Packing_${unpickedDate.value}.xlsx`);
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
    
    Swal.fire({
      icon: 'success',
      title: 'Berhasil',
      text: 'File Excel berhasil diunduh.',
      timer: 1500,
      showConfirmButton: false
    });
  } catch (e) {
    console.error('Export error:', e);
    let errorMessage = 'Gagal mengunduh file Excel.';
    
    if (e.response?.data?.error) {
      errorMessage = e.response.data.error;
    } else if (e.response?.status === 404) {
      errorMessage = 'Tidak ada data untuk di-export pada tanggal ini.';
    }
    
    Swal.fire({
      icon: 'error',
      title: 'Gagal',
      text: errorMessage
    });
  } finally {
    exportLoading.value = false;
  }
}

function toggleOutlet(outletName) {
  if (expandedOutlets.value.has(outletName)) {
    expandedOutlets.value.delete(outletName);
  } else {
    expandedOutlets.value.add(outletName);
  }
}

function toggleWarehouseOutlet(key) {
  if (expandedWarehouseOutlets.value.has(key)) {
    expandedWarehouseOutlets.value.delete(key);
  } else {
    expandedWarehouseOutlets.value.add(key);
  }
}

function toggleWarehouseDivision(key) {
  if (expandedWarehouseDivisions.value.has(key)) {
    expandedWarehouseDivisions.value.delete(key);
  } else {
    expandedWarehouseDivisions.value.add(key);
  }
}

function toggleSummaryDivision(divisionName) {
  if (expandedSummaryDivisions.value.has(divisionName)) {
    expandedSummaryDivisions.value.delete(divisionName);
  } else {
    expandedSummaryDivisions.value.add(divisionName);
  }
}

async function exportSummaryData() {
  summaryExportLoading.value = true;
  try {
    const response = await axios.get('/api/packing-list/export-summary', {
      params: { tanggal: summaryDate.value },
      responseType: 'blob'
    });
    
    // Create download link
    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', `Rangkuman_Packing_List_${summaryDate.value}.csv`);
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
    
    Swal.fire({
      icon: 'success',
      title: 'Berhasil',
      text: 'File CSV berhasil diunduh.',
      timer: 1500,
      showConfirmButton: false
    });
  } catch (e) {
    console.error('Export error:', e);
    let errorMessage = 'Gagal mengunduh file CSV.';
    
    if (e.response?.data?.error) {
      errorMessage = e.response.data.error;
    } else if (e.response?.status === 404) {
      errorMessage = 'Tidak ada data untuk di-export pada tanggal ini.';
    }
    
    Swal.fire({
      icon: 'error',
      title: 'Gagal',
      text: errorMessage
    });
  } finally {
    summaryExportLoading.value = false;
  }
}
</script>
<template>
  <AppLayout>
    <div class="w-full py-8 px-0">
      <div v-if="props.user?.outlet" class="mb-4">
        <div class="inline-flex items-center gap-2 bg-blue-50 text-blue-800 px-4 py-2 rounded-xl font-semibold">
          <i class="fa fa-store"></i>
          Outlet Anda: <span class="font-bold">{{ props.user.outlet.nama_outlet }}</span>
        </div>
      </div>
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-box text-blue-500"></i> Packing List
        </h1>
        <div class="flex gap-2">
          <button @click="openSummaryModal" class="bg-gradient-to-r from-green-500 to-green-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
            <i class="fa fa-list mr-1"></i> Rangkuman Packing List
          </button>
          <button 
            @click="openUnpickedModal"
            class="bg-gradient-to-r from-orange-500 to-orange-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold cursor-pointer"
            type="button"
          >
            <i class="fa fa-file-text mr-1"></i> RO Belum di-Packing
          </button>
          <button @click="openCreate" class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
            + Buat Packing List
          </button>
        </div>
      </div>
      <div class="flex flex-wrap gap-3 mb-4 items-center">
        <input
          v-model="search"
          @input="onSearchInput"
          type="text"
          placeholder="Cari nomor Packing List..."
          class="w-64 px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
        />
        <select v-model="selectedStatus" @change="onStatusChange" class="px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition">
          <option value="">Semua Status</option>
          <option value="draft">Draft</option>
          <option value="approved">Approved</option>
        </select>
        <input type="date" v-model="from" @change="onDateChange" class="px-2 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition" placeholder="Dari tanggal" />
        <span>-</span>
        <input type="date" v-model="to" @change="onDateChange" class="px-2 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition" placeholder="Sampai tanggal" />
      </div>
      <div class="bg-white rounded-2xl shadow-2xl overflow-x-auto transition-all">
        <table class="w-full min-w-full divide-y divide-gray-200">
          <thead class="bg-gradient-to-r from-blue-50 to-blue-100">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tl-2xl">No. Packing List</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Tanggal</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Divisi Gudang Asal</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Outlet Tujuan</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Pembuat</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Pemohon FO</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Status</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tr-2xl">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="props.packingLists.data.length === 0">
              <td colspan="7" class="text-center py-10 text-gray-400">Tidak ada data Packing List.</td>
            </tr>
            <tr v-for="list in props.packingLists.data" :key="list.id" class="hover:bg-blue-50 transition shadow-sm">
              <td class="px-6 py-3 font-mono font-semibold text-blue-700">{{ list.packing_number }}</td>
              <td class="px-6 py-3">{{ new Date(list.created_at).toLocaleDateString('id-ID') }}</td>
              <td class="px-6 py-3">{{ list.warehouse_division?.name ?? '-' }}</td>
              <td class="px-6 py-3">{{ list.floor_order?.outlet?.nama_outlet ?? '-' }}</td>
              <td class="px-6 py-3">{{ list.creator?.nama_lengkap ?? '-' }}</td>
              <td class="px-6 py-3">{{ list.floor_order?.requester?.nama_lengkap ?? '-' }}</td>
              <td class="px-6 py-3">
                <span :class="{
                  'bg-gray-100 text-gray-700': list.status === 'draft',
                  'bg-green-100 text-green-700': list.status === 'packing',
                }" class="px-2 py-1 rounded-full text-xs font-semibold shadow">
                  {{ list.status }}
                </span>
              </td>
              <td class="px-6 py-3">
                <div class="flex gap-2">
                  <button @click="openDetail(list.id)" class="inline-flex items-center btn btn-xs bg-blue-100 text-blue-800 hover:bg-blue-200 rounded px-2 py-1 font-semibold transition">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    Detail
                  </button>
                  <button @click="openEdit(list.id)" :disabled="list.status !== 'packing'" class="inline-flex items-center btn btn-xs bg-yellow-100 text-yellow-800 hover:bg-yellow-200 rounded px-2 py-1 font-semibold transition disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M15.232 5.232l3.536 3.536M9 13l6-6m2 2l-6 6m-2 2h6a2 2 0 002-2v-6a2 2 0 00-2-2H7a2 2 0 00-2 2v6a2 2 0 002 2z"/></svg>
                    Edit
                  </button>
                  <button @click="hapus(list)" :disabled="list.status !== 'packing'" class="inline-flex items-center btn btn-xs bg-red-100 text-red-700 hover:bg-red-200 rounded px-2 py-1 font-semibold transition disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                    Hapus
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
          v-for="link in props.packingLists.links"
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
      <!-- Modal Rangkuman Packing List -->
      <div v-if="showSummaryModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40">
         <div class="bg-white rounded-xl shadow-lg w-full max-w-3xl relative flex flex-col" style="max-height:80vh;">
           <!-- Fixed Header -->
           <div class="p-6 border-b border-gray-200 flex-shrink-0">
          <button @click="showSummaryModal = false" class="absolute top-3 right-3 text-gray-400 hover:text-gray-600">
            <i class="fas fa-times text-lg"></i>
          </button>
          <h2 class="text-xl font-bold mb-4">Rangkuman Packing List (Belum di-Packing)</h2>
             <div class="flex items-center justify-between">
               <div class="flex items-center gap-2">
            <label class="font-semibold">Tanggal:</label>
            <input type="date" v-model="summaryDate" @change="fetchSummary" class="rounded border-gray-300 px-2 py-1" />
          </div>
                               <button 
                  @click="exportSummaryData"
                  :disabled="summaryItems.length === 0 || summaryExportLoading"
                  class="bg-gradient-to-r from-green-500 to-green-700 text-white px-4 py-2 rounded-lg shadow-lg hover:shadow-xl transition-all font-semibold disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  <i v-if="summaryExportLoading" class="fas fa-spinner fa-spin mr-2"></i>
                  <i v-else class="fas fa-file-csv mr-2"></i> 
                  {{ summaryExportLoading ? 'Mengunduh...' : 'Export CSV' }}
                </button>
             </div>
           </div>
           
           <!-- Scrollable Content -->
           <div class="p-6 overflow-y-auto flex-1">
          <div v-if="summaryLoading" class="text-center py-8">
            <i class="fas fa-spinner fa-spin text-blue-500 text-2xl"></i>
            <p class="mt-2 text-gray-600">Memuat data rangkuman...</p>
          </div>
          <div v-else-if="summaryError" class="text-red-600 mb-4">{{ summaryError }}</div>
          <div v-else>
               <div v-if="summaryItems.length === 0" class="text-center text-gray-400 py-6">
                 Tidak ada data yang belum di-packing pada tanggal ini.
               </div>
               
               <div v-else class="space-y-3">
                 <!-- Warehouse Division Level -->
                 <div v-for="division in summaryItems" :key="division.warehouse_division_name" class="border border-gray-200 rounded-lg">
                   <!-- Warehouse Division Header -->
                   <div 
                     @click="toggleSummaryDivision(division.warehouse_division_name)"
                     class="bg-blue-50 p-3 cursor-pointer hover:bg-blue-100 transition-colors flex items-center justify-between"
                   >
                     <div class="flex items-center gap-3">
                       <i 
                         :class="expandedSummaryDivisions.has(division.warehouse_division_name) ? 'fas fa-chevron-down' : 'fas fa-chevron-right'"
                         class="text-blue-600"
                       ></i>
                       <div>
                         <h3 class="font-bold text-blue-800">{{ division.warehouse_division_name }}</h3>
                       </div>
                     </div>
                     <span class="bg-blue-200 text-blue-800 px-2 py-1 rounded-full text-xs font-semibold">
                       {{ division.items.length }} Item
                     </span>
                   </div>
                   
                   <!-- Items Level -->
                   <div v-if="expandedSummaryDivisions.has(division.warehouse_division_name)" class="border-t border-gray-100">
                     <div class="p-3">
                       <table class="w-full text-sm">
              <thead>
                           <tr class="bg-gray-50">
                             <th class="text-left py-2 px-2">Nama Item</th>
                             <th class="text-left py-2 px-2">Total Qty</th>
                             <th class="text-left py-2 px-2">Unit</th>
                </tr>
              </thead>
              <tbody>
                           <tr v-for="item in division.items" :key="`${division.warehouse_division_name}-${item.item_id}-${item.unit}`" class="border-b border-gray-50 last:border-b-0">
                             <td class="py-2 px-2">{{ item.item_name }}</td>
                             <td class="py-2 px-2 font-medium">{{ item.total_qty }}</td>
                             <td class="py-2 px-2">{{ item.unit }}</td>
                           </tr>
                         </tbody>
                       </table>
                     </div>
                   </div>
                 </div>
               </div>
             </div>
           </div>
         </div>
       </div>
      
      <!-- Modal FO Belum di-Packing -->
      <div v-if="showUnpickedModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40">
        <div class="bg-white rounded-xl shadow-lg w-full max-w-6xl relative flex flex-col" style="max-height:90vh;">
          <!-- Fixed Header -->
          <div class="p-6 border-b border-gray-200 flex-shrink-0">
            <button @click="showUnpickedModal = false" class="absolute top-3 right-3 text-gray-400 hover:text-gray-600">
              <i class="fas fa-times text-lg"></i>
            </button>
            <h2 class="text-xl font-bold mb-4">Request Order belum di packing</h2>
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <label class="font-semibold">Tanggal:</label>
                <input type="date" v-model="unpickedDate" @change="fetchUnpickedData" class="rounded border-gray-300 px-2 py-1" />
              </div>
              <button 
                @click="exportUnpickedData"
                :disabled="unpickedData.length === 0 || exportLoading"
                class="bg-gradient-to-r from-green-500 to-green-700 text-white px-4 py-2 rounded-lg shadow-lg hover:shadow-xl transition-all font-semibold disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <i v-if="exportLoading" class="fas fa-spinner fa-spin mr-2"></i>
                <i v-else class="fas fa-file-excel mr-2"></i> 
                {{ exportLoading ? 'Mengunduh...' : 'Export Excel' }}
              </button>
            </div>
          </div>
          
          <!-- Scrollable Content -->
          <div class="p-6 overflow-y-auto flex-1">
          
          <div v-if="unpickedLoading" class="text-center py-8">
            <i class="fas fa-spinner fa-spin text-blue-500 text-2xl"></i>
            <p class="mt-2 text-gray-600">Memuat data FO...</p>
          </div>
          
          <div v-else-if="unpickedError" class="text-red-600 mb-4">{{ unpickedError }}</div>
          
          <div v-else>
            <div v-if="unpickedData.length === 0" class="text-center text-gray-400 py-6">
              Tidak ada FO yang belum di-packing pada tanggal ini.
            </div>
            
            <div v-else class="space-y-4">
              <!-- Outlet Level -->
              <div v-for="outlet in unpickedData" :key="outlet.outlet_name" class="border border-gray-200 rounded-lg">
                <!-- Outlet Header -->
                <div 
                  @click="toggleOutlet(outlet.outlet_name)"
                  class="bg-blue-50 p-4 cursor-pointer hover:bg-blue-100 transition-colors flex items-center justify-between"
                >
                  <div class="flex items-center gap-3">
                    <i 
                      :class="expandedOutlets.has(outlet.outlet_name) ? 'fas fa-chevron-down' : 'fas fa-chevron-right'"
                      class="text-blue-600"
                    ></i>
                    <div>
                      <h3 class="font-bold text-blue-800">{{ outlet.outlet_name }}</h3>
                      <p class="text-sm text-blue-600">{{ new Date(outlet.tanggal).toLocaleDateString('id-ID') }}</p>
                    </div>
                  </div>
                  <span class="bg-blue-200 text-blue-800 px-2 py-1 rounded-full text-xs font-semibold">
                    {{ outlet.warehouse_outlets.length }} Warehouse Outlet
                  </span>
                </div>
                
                <!-- Warehouse Outlet Level -->
                <div v-if="expandedOutlets.has(outlet.outlet_name)" class="border-t border-gray-200">
                  <div v-for="(warehouseOutlet, woIndex) in outlet.warehouse_outlets" :key="`${outlet.outlet_name}-${woIndex}`" class="border-b border-gray-100 last:border-b-0">
                    <!-- Warehouse Outlet Header -->
                    <div 
                      @click="toggleWarehouseOutlet(`${outlet.outlet_name}-${woIndex}`)"
                      class="bg-green-50 p-3 cursor-pointer hover:bg-green-100 transition-colors flex items-center justify-between"
                    >
                      <div class="flex items-center gap-3 ml-6">
                        <i 
                          :class="expandedWarehouseOutlets.has(`${outlet.outlet_name}-${woIndex}`) ? 'fas fa-chevron-down' : 'fas fa-chevron-right'"
                          class="text-green-600"
                        ></i>
                        <div>
                          <h4 class="font-semibold text-green-800">{{ warehouseOutlet.warehouse_outlet_name }}</h4>
                        </div>
                      </div>
                                             <span class="bg-green-200 text-green-800 px-2 py-1 rounded-full text-xs font-semibold">
                         {{ warehouseOutlet.warehouse_divisions.length }} Floor Order
                       </span>
                    </div>
                    
                                         <!-- Floor Order Level -->
                     <div v-if="expandedWarehouseOutlets.has(`${outlet.outlet_name}-${woIndex}`)" class="border-t border-gray-100">
                       <div v-for="(floorOrder, foIndex) in warehouseOutlet.warehouse_divisions" :key="`${outlet.outlet_name}-${woIndex}-${foIndex}`" class="border-b border-gray-50 last:border-b-0">
                         <!-- Floor Order Header -->
                         <div class="bg-yellow-50 p-3 flex items-center justify-between">
                           <div class="flex items-center gap-3 ml-12">
                             <div>
                               <h5 class="font-medium text-yellow-800">{{ floorOrder.fo_number }}</h5>
                               <p class="text-sm text-yellow-600">Pemohon: {{ floorOrder.requester }}</p>
                             </div>
                           </div>
                           <span class="bg-yellow-200 text-yellow-800 px-2 py-1 rounded-full text-xs font-semibold">
                             {{ floorOrder.unpicked_items_by_division.length }} Warehouse Division
                           </span>
                         </div>
                         
                         <!-- Warehouse Division Level -->
                         <div class="bg-white p-3">
                           <div class="ml-16 space-y-3">
                             <div v-for="(division, divIndex) in floorOrder.unpicked_items_by_division" :key="`${outlet.outlet_name}-${woIndex}-${foIndex}-${divIndex}`" class="border border-gray-200 rounded-lg">
                               <!-- Warehouse Division Header -->
                               <div 
                                 @click="toggleWarehouseDivision(`${outlet.outlet_name}-${woIndex}-${foIndex}-${divIndex}`)"
                                 class="bg-purple-50 p-3 cursor-pointer hover:bg-purple-100 transition-colors flex items-center justify-between"
                               >
                                 <div class="flex items-center gap-3">
                                   <i 
                                     :class="expandedWarehouseDivisions.has(`${outlet.outlet_name}-${woIndex}-${foIndex}-${divIndex}`) ? 'fas fa-chevron-down' : 'fas fa-chevron-right'"
                                     class="text-purple-600"
                                   ></i>
                                   <div>
                                     <h6 class="font-semibold text-purple-800">{{ division.warehouse_division_name }}</h6>
                                   </div>
                                 </div>
                                 <span class="bg-purple-200 text-purple-800 px-2 py-1 rounded-full text-xs font-semibold">
                                   {{ division.items.length }} Item
                                 </span>
                               </div>
                               
                               <!-- Items Level -->
                               <div v-if="expandedWarehouseDivisions.has(`${outlet.outlet_name}-${woIndex}-${foIndex}-${divIndex}`)" class="border-t border-gray-100">
                                 <div class="p-3">
                                   <table class="w-full text-sm">
                                     <thead>
                                       <tr class="bg-gray-50">
                                         <th class="text-left py-2 px-2">Item</th>
                                         <th class="text-left py-2 px-2">Qty</th>
                                         <th class="text-left py-2 px-2">Unit</th>
                </tr>
                                     </thead>
                                     <tbody>
                                       <tr v-for="item in division.items" :key="item.item_name" class="border-b border-gray-50 last:border-b-0">
                                         <td class="py-2 px-2">{{ item.item_name }}</td>
                                         <td class="py-2 px-2 font-medium">{{ item.qty }}</td>
                                         <td class="py-2 px-2">{{ item.unit }}</td>
                </tr>
              </tbody>
            </table>
                                 </div>
                               </div>
                             </div>
                           </div>
                         </div>
                       </div>
                     </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template> 