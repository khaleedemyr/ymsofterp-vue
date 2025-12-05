<script setup>
import { ref, watch, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

const props = defineProps({
  jurnals: Object,
  coas: Array,
  statistics: {
    type: Object,
    default: () => ({
      total: 0,
      draft: 0,
      posted: 0,
      cancelled: 0
    })
  },
  filters: Object,
});

const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || 'all');
const dateFrom = ref(props.filters?.date_from || '');
const dateTo = ref(props.filters?.date_to || '');
const perPage = ref(props.filters?.per_page || 15);

const debouncedSearch = debounce(() => {
  router.get('/jurnal', {
    search: search.value,
    status: status.value,
    date_from: dateFrom.value,
    date_to: dateTo.value,
    per_page: perPage.value,
  }, { preserveState: true, replace: true });
}, 400);

function onSearchInput() {
  debouncedSearch();
}

watch([status, dateFrom, dateTo, perPage], () => {
  router.get('/jurnal', {
    search: search.value,
    status: status.value,
    date_from: dateFrom.value,
    date_to: dateTo.value,
    per_page: perPage.value,
  }, { preserveState: true, replace: true });
});

function goToPage(url) {
  if (url) {
    const urlObj = new URL(url, window.location.origin);
    urlObj.searchParams.set('search', search.value);
    urlObj.searchParams.set('status', status.value);
    urlObj.searchParams.set('date_from', dateFrom.value);
    urlObj.searchParams.set('date_to', dateTo.value);
    urlObj.searchParams.set('per_page', perPage.value);
    router.visit(urlObj.toString(), { preserveState: true, replace: true });
  }
}

function openCreate() {
  router.visit('/jurnal/create');
}

function openEdit(jurnal) {
  router.visit(`/jurnal/${jurnal.id}/edit`);
}

async function showDetail(jurnal) {
  try {
    const response = await axios.get(route('jurnal.show', jurnal.id));
    const data = response.data;
    const jurnalData = data.jurnal;
    const relatedJurnals = data.related_jurnals || [];
    
    let html = `
      <div class="text-left space-y-3">
        <div class="grid grid-cols-2 gap-2">
          <div><strong>No Jurnal:</strong></div>
          <div>${jurnalData.no_jurnal}</div>
          
          <div><strong>Tanggal:</strong></div>
          <div>${formatDate(jurnalData.tanggal)}</div>
          
          <div><strong>Outlet:</strong></div>
          <div>${jurnalData.outlet?.nama_outlet || '-'}</div>
          
          <div><strong>Status:</strong></div>
          <div>
            <span class="px-2 py-1 rounded-full text-xs font-semibold ${
              jurnalData.status === 'posted' ? 'bg-green-100 text-green-800' : 
              jurnalData.status === 'cancelled' ? 'bg-red-100 text-red-800' : 
              'bg-yellow-100 text-yellow-800'
            }">
              ${jurnalData.status === 'posted' ? 'Posted' : jurnalData.status === 'cancelled' ? 'Cancelled' : 'Draft'}
            </span>
          </div>
        </div>
        
        <div class="border-t pt-3">
          <strong>Keterangan:</strong>
          <div class="mt-1 text-gray-600">${jurnalData.keterangan || '-'}</div>
        </div>
        
        <div class="border-t pt-3">
          <strong>CoA Debit:</strong>
          <div class="mt-1">
            <span class="font-semibold">${jurnalData.coa_debit?.code || '-'}</span>
            <span class="text-gray-600 ml-2">${jurnalData.coa_debit?.name || ''}</span>
          </div>
        </div>
        
        <div class="border-t pt-3">
          <strong>CoA Kredit:</strong>
          <div class="mt-1">
            <span class="font-semibold">${jurnalData.coa_kredit?.code || '-'}</span>
            <span class="text-gray-600 ml-2">${jurnalData.coa_kredit?.name || ''}</span>
          </div>
        </div>
        
        <div class="grid grid-cols-2 gap-2 border-t pt-3">
          <div>
            <strong>Jumlah Debit:</strong>
            <div class="text-green-700 font-semibold">${formatCurrency(jurnalData.jumlah_debit)}</div>
          </div>
          <div>
            <strong>Jumlah Kredit:</strong>
            <div class="text-red-700 font-semibold">${formatCurrency(jurnalData.jumlah_kredit)}</div>
          </div>
        </div>
        
        ${jurnalData.creator ? `
          <div class="border-t pt-3">
            <strong>Dibuat oleh:</strong>
            <div class="mt-1 text-gray-600">${jurnalData.creator?.name || '-'}</div>
          </div>
        ` : ''}
    `;
    
    if (relatedJurnals.length > 0) {
      html += `
        <div class="border-t pt-3">
          <strong>Related Entries (${relatedJurnals.length}):</strong>
          <div class="mt-2 space-y-2 max-h-40 overflow-y-auto">
      `;
      relatedJurnals.forEach((related, idx) => {
        html += `
          <div class="bg-gray-50 p-2 rounded text-sm">
            <div class="font-semibold">${related.no_jurnal}</div>
            <div class="text-xs text-gray-600">
              ${related.coa_debit?.code} → ${related.coa_kredit?.code} | 
              ${formatCurrency(related.jumlah_debit)}
            </div>
          </div>
        `;
      });
      html += `
          </div>
        </div>
      `;
    }
    
    html += `</div>`;
    
    Swal.fire({
      title: 'Detail Jurnal',
      html: html,
      width: '600px',
      confirmButtonText: 'Tutup',
      customClass: {
        popup: 'text-left'
      }
    });
  } catch (error) {
    Swal.fire('Error', 'Gagal memuat detail jurnal', 'error');
  }
}

async function hapus(jurnal) {
  const result = await Swal.fire({
    title: 'Hapus Jurnal?',
    text: `Yakin ingin menghapus jurnal "${jurnal.no_jurnal}"?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Ya, Hapus!',
    cancelButtonText: 'Batal',
  });
  if (!result.isConfirmed) return;
  router.delete(route('jurnal.destroy', jurnal.id), {
    onSuccess: () => {
      Swal.fire('Berhasil', 'Jurnal berhasil dihapus!', 'success').then(() => {
        reload();
      });
    },
    onError: (errors) => {
      Swal.fire('Error', errors.error || 'Gagal menghapus jurnal', 'error');
    }
  });
}

async function postJurnal(jurnal) {
  const result = await Swal.fire({
    title: 'Post Jurnal?',
    text: `Yakin ingin mem-post jurnal "${jurnal.no_jurnal}"?`,
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#6b7280',
    confirmButtonText: 'Ya, Post!',
    cancelButtonText: 'Batal',
  });
  if (!result.isConfirmed) return;
  
  try {
    const response = await axios.post(route('jurnal.post', jurnal.id));
    if (response.data) {
      Swal.fire('Berhasil', 'Jurnal berhasil di-post!', 'success');
      reload();
    }
  } catch (error) {
    Swal.fire('Error', error.response?.data?.message || 'Gagal mem-post jurnal', 'error');
  }
}

async function cancelJurnal(jurnal) {
  const result = await Swal.fire({
    title: 'Batalkan Jurnal?',
    text: `Yakin ingin membatalkan jurnal "${jurnal.no_jurnal}"?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#f59e0b',
    cancelButtonColor: '#6b7280',
    confirmButtonText: 'Ya, Batalkan!',
    cancelButtonText: 'Batal',
  });
  if (!result.isConfirmed) return;
  
  try {
    const response = await axios.post(route('jurnal.cancel', jurnal.id));
    if (response.data) {
      Swal.fire('Berhasil', 'Jurnal berhasil dibatalkan!', 'success');
      reload();
    }
  } catch (error) {
    Swal.fire('Error', error.response?.data?.message || 'Gagal membatalkan jurnal', 'error');
  }
}

function reload() {
  router.reload({ preserveState: true, replace: true });
}

function filterByStatus(newStatus) {
  status.value = newStatus;
}

function formatCurrency(amount) {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(amount);
}

function formatDate(date) {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('id-ID', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  });
}

// Handle success/error messages from backend
onMounted(() => {
  if (window.flash?.success) {
    Swal.fire('Berhasil', window.flash.success, 'success');
  }
  if (window.flash?.error) {
    Swal.fire('Error', window.flash.error, 'error');
  }
});
</script>

<template>
  <AppLayout title="Jurnal">
    <div class="w-full py-8 px-4">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-book text-blue-500"></i> Jurnal
        </h1>
        <button @click="openCreate" class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
          + Tambah Jurnal Baru
        </button>
      </div>

      <!-- Statistics Cards -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <!-- Total Jurnal -->
        <div :class="[
          'rounded-xl shadow-lg p-6 border-l-4 cursor-pointer transition-all relative',
          status === 'all' ? 'bg-blue-50 border-blue-500 shadow-xl' : 'bg-white border-blue-500 hover:shadow-xl'
        ]" @click="filterByStatus('all')" title="Klik untuk melihat semua jurnal">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">Total Jurnal</p>
              <p class="text-2xl font-bold text-gray-900">{{ statistics.total }}</p>
            </div>
            <div class="bg-blue-100 p-3 rounded-full">
              <i class="fa-solid fa-book text-blue-600 text-xl"></i>
            </div>
          </div>
        </div>

        <!-- Draft -->
        <div :class="[
          'rounded-xl shadow-lg p-6 border-l-4 cursor-pointer transition-all relative',
          status === 'draft' ? 'bg-yellow-50 border-yellow-500 shadow-xl' : 'bg-white border-yellow-500 hover:shadow-xl'
        ]" @click="filterByStatus('draft')" title="Klik untuk melihat jurnal draft">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">Draft</p>
              <p class="text-2xl font-bold text-gray-900">{{ statistics.draft }}</p>
            </div>
            <div class="bg-yellow-100 p-3 rounded-full">
              <i class="fa-solid fa-file-lines text-yellow-600 text-xl"></i>
            </div>
          </div>
        </div>

        <!-- Posted -->
        <div :class="[
          'rounded-xl shadow-lg p-6 border-l-4 cursor-pointer transition-all relative',
          status === 'posted' ? 'bg-green-50 border-green-500 shadow-xl' : 'bg-white border-green-500 hover:shadow-xl'
        ]" @click="filterByStatus('posted')" title="Klik untuk melihat jurnal posted">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">Posted</p>
              <p class="text-2xl font-bold text-gray-900">{{ statistics.posted }}</p>
            </div>
            <div class="bg-green-100 p-3 rounded-full">
              <i class="fa-solid fa-check-circle text-green-600 text-xl"></i>
            </div>
          </div>
        </div>

        <!-- Cancelled -->
        <div :class="[
          'rounded-xl shadow-lg p-6 border-l-4 cursor-pointer transition-all relative',
          status === 'cancelled' ? 'bg-red-50 border-red-500 shadow-xl' : 'bg-white border-red-500 hover:shadow-xl'
        ]" @click="filterByStatus('cancelled')" title="Klik untuk melihat jurnal cancelled">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">Cancelled</p>
              <p class="text-2xl font-bold text-gray-900">{{ statistics.cancelled }}</p>
            </div>
            <div class="bg-red-100 p-3 rounded-full">
              <i class="fa-solid fa-times-circle text-red-600 text-xl"></i>
            </div>
          </div>
        </div>
      </div>

      <!-- Filters -->
      <div class="mb-4 flex gap-4 flex-wrap">
        <select v-model="status" class="form-input rounded-xl">
          <option value="all">Semua Status</option>
          <option value="draft">Draft</option>
          <option value="posted">Posted</option>
          <option value="cancelled">Cancelled</option>
        </select>
        <input
          v-model="dateFrom"
          type="date"
          placeholder="Dari Tanggal"
          class="form-input rounded-xl"
        />
        <input
          v-model="dateTo"
          type="date"
          placeholder="Sampai Tanggal"
          class="form-input rounded-xl"
        />
        <select v-model="perPage" class="form-input rounded-xl">
          <option value="10">10 per halaman</option>
          <option value="15">15 per halaman</option>
          <option value="25">25 per halaman</option>
          <option value="50">50 per halaman</option>
        </select>
        <input
          v-model="search"
          @input="onSearchInput"
          type="text"
          placeholder="Cari No Jurnal, Keterangan, CoA..."
          class="flex-1 px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition min-w-64"
        />
      </div>

      <!-- Table -->
      <div class="bg-white rounded-2xl shadow-lg overflow-x-auto">
        <table class="min-w-full divide-y divide-blue-200">
          <thead class="bg-blue-600 text-white">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">No Jurnal</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Tanggal</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Outlet</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Keterangan</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">CoA Debit</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">CoA Kredit</th>
              <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider">Jumlah Debit</th>
              <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider">Jumlah Kredit</th>
              <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Status</th>
              <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Aksi</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-for="jurnal in jurnals.data" :key="jurnal.id" class="hover:bg-blue-50 transition">
              <td class="px-4 py-2 whitespace-nowrap font-semibold">{{ jurnal.no_jurnal }}</td>
              <td class="px-4 py-2 whitespace-nowrap">{{ formatDate(jurnal.tanggal) }}</td>
              <td class="px-4 py-2 whitespace-nowrap">{{ jurnal.outlet?.nama_outlet || '-' }}</td>
              <td class="px-4 py-2">{{ jurnal.keterangan || '-' }}</td>
              <td class="px-4 py-2 whitespace-nowrap">
                <div class="text-sm">
                  <div class="font-semibold">{{ jurnal.coa_debit?.code }}</div>
                  <div class="text-gray-500 text-xs">{{ jurnal.coa_debit?.name }}</div>
                </div>
              </td>
              <td class="px-4 py-2 whitespace-nowrap">
                <div class="text-sm">
                  <div class="font-semibold">{{ jurnal.coa_kredit?.code }}</div>
                  <div class="text-gray-500 text-xs">{{ jurnal.coa_kredit?.name }}</div>
                </div>
              </td>
              <td class="px-4 py-2 whitespace-nowrap text-right font-semibold">{{ formatCurrency(jurnal.jumlah_debit) }}</td>
              <td class="px-4 py-2 whitespace-nowrap text-right font-semibold">{{ formatCurrency(jurnal.jumlah_kredit) }}</td>
              <td class="px-4 py-2 whitespace-nowrap text-center">
                <span :class="[
                  'px-2 py-1 rounded-full text-xs font-semibold',
                  jurnal.status === 'posted' ? 'bg-green-100 text-green-800' : 
                  jurnal.status === 'cancelled' ? 'bg-red-100 text-red-800' : 
                  'bg-yellow-100 text-yellow-800'
                ]">
                  {{ jurnal.status === 'posted' ? 'Posted' : jurnal.status === 'cancelled' ? 'Cancelled' : 'Draft' }}
                </span>
              </td>
              <td class="px-4 py-2 whitespace-nowrap text-center">
                <div class="flex gap-2 justify-center">
                  <button 
                    @click="showDetail(jurnal)" 
                    class="px-2 py-1 rounded bg-blue-500 text-white hover:bg-blue-600 transition" 
                    title="Detail">
                    <i class="fa-solid fa-eye"></i>
                  </button>
                  <button 
                    v-if="jurnal.status === 'draft'"
                    @click="openEdit(jurnal)" 
                    class="px-2 py-1 rounded bg-yellow-200 text-yellow-900 hover:bg-yellow-300 transition" 
                    title="Edit">
                    <i class="fa-solid fa-pen-to-square"></i>
                  </button>
                  <button 
                    v-if="jurnal.status === 'draft'"
                    @click="postJurnal(jurnal)" 
                    class="px-2 py-1 rounded bg-green-500 text-white hover:bg-green-600 transition" 
                    title="Post">
                    <i class="fa-solid fa-check"></i>
                  </button>
                  <button 
                    v-if="jurnal.status === 'draft'"
                    @click="cancelJurnal(jurnal)" 
                    class="px-2 py-1 rounded bg-orange-500 text-white hover:bg-orange-600 transition" 
                    title="Cancel">
                    <i class="fa-solid fa-times"></i>
                  </button>
                  <button 
                    v-if="jurnal.status === 'draft'"
                    @click="hapus(jurnal)" 
                    class="px-2 py-1 rounded bg-red-200 text-red-900 hover:bg-red-300 transition" 
                    title="Hapus">
                    <i class="fa-solid fa-trash"></i>
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="jurnals.data.length === 0">
              <td colspan="10" class="text-center py-8 text-gray-400">Tidak ada data jurnal</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="jurnals.links && jurnals.links.length > 3" class="mt-4 flex justify-center">
        <nav class="flex gap-2">
          <button
            v-for="(link, index) in jurnals.links"
            :key="index"
            @click="goToPage(link.url)"
            :disabled="!link.url"
            :class="[
              'px-4 py-2 rounded-lg transition',
              link.active 
                ? 'bg-blue-600 text-white font-semibold' 
                : link.url 
                  ? 'bg-white text-gray-700 hover:bg-blue-50 border border-gray-300' 
                  : 'bg-gray-100 text-gray-400 cursor-not-allowed'
            ]"
            v-html="link.label"
          ></button>
        </nav>
      </div>
    </div>
  </AppLayout>
</template>

