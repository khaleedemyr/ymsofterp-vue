<script setup>
import { ref, onMounted, watch } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import { debounce } from 'lodash';

const prList = ref([]);
const selectedPRs = ref([]);
const items = ref([]);
const suppliers = ref([]);
const loading = ref(false);
const poList = ref([]);

const props = defineProps({
  purchaseOrders: Object,
  filters: Object,
  user: Object,
});

const search = ref(props.filters?.search || '');
const selectedStatus = ref(props.filters?.status || '');
const from = ref(props.filters?.from || '');
const to = ref(props.filters?.to || '');
const perPage = ref(props.filters?.perPage || 10);

// Modal state untuk pending GM Finance
const showPendingGMFinanceModal = ref(false);
const pendingGMFINANCEPOs = ref([]);
const loadingPendingPOs = ref(false);
const expandedPOsInModal = ref(new Set());

// Filter state untuk modal
const modalSearch = ref('');
const modalStatusFilter = ref('');
const modalDateFrom = ref('');
const modalDateTo = ref('');

const dataLoaded = ref(false);
const loadingData = ref(false);

const loadData = async () => {
  if (loadingData.value) return;
  
  loadingData.value = true;
  dataLoaded.value = true;
  
  try {
    const filterState = {
      search: search.value,
      status: selectedStatus.value,
      from: from.value,
      to: to.value,
      perPage: perPage.value,
      load: 'true'
    };
    
    sessionStorage.setItem('po-foods-filters', JSON.stringify(filterState));
    
    router.get('/po-foods', filterState, { preserveState: true, replace: true });
  } catch (error) {
    console.error('Error loading data:', error);
    Swal.fire('Error', 'Gagal memuat data', 'error');
  } finally {
    loadingData.value = false;
  }
};

const debouncedSearch = debounce(() => {
  if (!dataLoaded.value) return; // Jangan auto-search jika belum load data
  
  // Simpan filter state ke sessionStorage
  const filterState = {
    search: search.value,
    status: selectedStatus.value,
    from: from.value,
    to: to.value,
    perPage: perPage.value,
    load: 'true'
  };
  sessionStorage.setItem('po-foods-filters', JSON.stringify(filterState));
  
  router.get('/po-foods', filterState, { preserveState: true, replace: true });
}, 400);

function onSearchInput() {
  debouncedSearch();
}
function onStatusChange() {
  debouncedSearch();
}
function onDateChange() {
  debouncedSearch();
}

function onPerPageChange() {
  debouncedSearch();
}

function clearFilters() {
  search.value = '';
  selectedStatus.value = '';
  from.value = '';
  to.value = '';
  perPage.value = 10;
  dataLoaded.value = false;
}

// Fetch PO yang pending GM Finance approval
async function fetchPendingGMFINANCEPOs() {
  try {
    loadingPendingPOs.value = true;
    const params = {
      search: modalSearch.value,
      status: modalStatusFilter.value,
      from: modalDateFrom.value,
      to: modalDateTo.value
    };
    
    const response = await axios.get('/api/po-foods/pending-gm-finance', { params });
    pendingGMFINANCEPOs.value = response.data;
  } catch (error) {
    console.error('Error fetching pending GM Finance POs:', error);
    Swal.fire('Error', 'Gagal mengambil data PO pending GM Finance', 'error');
  } finally {
    loadingPendingPOs.value = false;
  }
}

// Apply filter di modal
function applyModalFilter() {
  fetchPendingGMFINANCEPOs();
}

// Reset filter di modal
function resetModalFilter() {
  modalSearch.value = '';
  modalStatusFilter.value = '';
  modalDateFrom.value = '';
  modalDateTo.value = '';
  fetchPendingGMFINANCEPOs();
}

// Toggle modal pending GM Finance
async function togglePendingGMFINANCEModal() {
  showPendingGMFinanceModal.value = !showPendingGMFinanceModal.value;
  if (showPendingGMFinanceModal.value) {
    await fetchPendingGMFINANCEPOs();
  }
}

// Toggle expand PO di modal
function toggleExpandPOInModal(poId) {
  if (expandedPOsInModal.value.has(poId)) {
    expandedPOsInModal.value.delete(poId);
  } else {
    expandedPOsInModal.value.add(poId);
    // Fetch stock when expanding
    const po = pendingGMFINANCEPOs.value.find(p => p.id === poId);
    if (po) {
      fetchStockForPO(po);
    }
  }
}

// Fungsi untuk memulihkan filter state dari sessionStorage
function restoreFilterState() {
  try {
    const savedFilters = sessionStorage.getItem('po-foods-filters');
    if (savedFilters) {
      const filters = JSON.parse(savedFilters);
      search.value = filters.search || '';
      selectedStatus.value = filters.status || '';
      from.value = filters.from || '';
      to.value = filters.to || '';
      perPage.value = filters.perPage || 10;
      
      // Trigger search dengan filter yang dipulihkan
      debouncedSearch();
    }
  } catch (error) {
    console.error('Error restoring filter state:', error);
  }
}

function goToPage(url) {
  if (url) {
    // Simpan filter state sebelum navigasi
    const filterState = {
      search: search.value,
      status: selectedStatus.value,
      from: from.value,
      to: to.value,
      perPage: perPage.value
    };
    sessionStorage.setItem('po-foods-filters', JSON.stringify(filterState));
    
    router.visit(url, { preserveState: true, replace: true });
  }
}

function openCreate() {
  // Simpan filter state sebelum navigasi
  const filterState = {
    search: search.value,
    status: selectedStatus.value,
    from: from.value,
    to: to.value,
    perPage: perPage.value
  };
  sessionStorage.setItem('po-foods-filters', JSON.stringify(filterState));
  router.visit('/po-foods/create');
}

function openEdit(id) {
  // Simpan filter state sebelum navigasi
  const filterState = {
    search: search.value,
    status: selectedStatus.value,
    from: from.value,
    to: to.value,
    perPage: perPage.value
  };
  sessionStorage.setItem('po-foods-filters', JSON.stringify(filterState));
  router.visit(`/po-foods/${id}/edit`);
}

function openDetail(id) {
  // Simpan filter state sebelum navigasi
  const filterState = {
    search: search.value,
    status: selectedStatus.value,
    from: from.value,
    to: to.value,
    perPage: perPage.value
  };
  sessionStorage.setItem('po-foods-filters', JSON.stringify(filterState));
  router.visit(`/po-foods/${id}`);
}
async function hapus(po) {
  const result = await Swal.fire({
    title: 'Hapus PO Foods?',
    text: `Yakin ingin menghapus PO ${po.number}?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Ya, Hapus!',
    cancelButtonText: 'Batal'
  });
  if (!result.isConfirmed) return;
  router.delete(route('po-foods.destroy', po.id), {
    onSuccess: () => Swal.fire('Berhasil', 'PO berhasil dihapus!', 'success'),
  });
}

async function approveGMFinance(po) {
  const { value: note } = await Swal.fire({
    title: 'Approve PO?',
    input: 'textarea',
    inputLabel: 'Catatan (opsional)',
    inputValue: '',
    showCancelButton: true,
    confirmButtonText: 'Approve',
    cancelButtonText: 'Batal',
  });
  
  if (note !== undefined) {
    try {
      const response = await axios.post(route('po-foods.approve-gm-finance', po.id), { 
        approved: true, 
        note 
      });
      if (response.data.success) {
        await Swal.fire({
          icon: 'success',
          title: 'Berhasil',
          text: 'PO berhasil diapprove!',
        });
        // Refresh modal data
        await fetchPendingGMFINANCEPOs();
      }
    } catch (e) {
      Swal.fire('Gagal', 'Terjadi kesalahan saat approve', 'error');
    }
  }
}

async function rejectGMFinance(po) {
  const { value: note } = await Swal.fire({
    title: 'Reject PO?',
    input: 'textarea',
    inputLabel: 'Catatan (opsional)',
    inputValue: '',
    showCancelButton: true,
    confirmButtonText: 'Reject',
    cancelButtonText: 'Batal',
  });
  
  if (note !== undefined) {
    try {
      const response = await axios.post(route('po-foods.approve-gm-finance', po.id), { 
        approved: false, 
        note 
      });
      if (response.data.success) {
        await Swal.fire({
          icon: 'success',
          title: 'Berhasil',
          text: 'PO berhasil direject!',
        });
        // Refresh modal data
        await fetchPendingGMFINANCEPOs();
      }
    } catch (e) {
      Swal.fire('Gagal', 'Terjadi kesalahan saat reject', 'error');
    }
  }
}

// Fetch stock untuk item PO
async function fetchStockForPO(po) {
  if (!po.items || po.items.length === 0) return;
  
  try {
    const stockPromises = po.items.map(async (item) => {
      if (!item.item_id || !po.warehouse_outlet_id) return item;
      
      const response = await axios.get('/api/inventory/stock', {
        params: { 
          item_id: item.item_id, 
          warehouse_id: po.warehouse_outlet_id 
        }
      });
      
      return {
        ...item,
        stock: response.data
      };
    });
    
    const itemsWithStock = await Promise.all(stockPromises);
    po.items_with_stock = itemsWithStock;
  } catch (error) {
    console.error('Error fetching stock for PO:', error);
  }
}

// Format stock display seperti di PR Foods
function formatStockDisplay(stock) {
  if (!stock) return 'Stok: 0';
  
  const parts = [];
  if (stock.qty_small !== undefined && stock.qty_small > 0) {
    parts.push(`${Number(stock.qty_small).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${stock.unit_small || ''}`);
  }
  if (stock.qty_medium !== undefined && stock.qty_medium > 0) {
    parts.push(`${Number(stock.qty_medium).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${stock.unit_medium || ''}`);
  }
  if (stock.qty_large !== undefined && stock.qty_large > 0) {
    parts.push(`${Number(stock.qty_large).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${stock.unit_large || ''}`);
  }
  
  return parts.length > 0 ? `Stok: ${parts.join(' | ')}` : 'Stok: 0';
}

// Check if user can approve GM Finance
function canApproveGMFinance() {
  return (props.user?.id_jabatan === 152 && props.user?.status === 'A') || 
         (props.user?.id_role === '5af56935b011a' && props.user?.status === 'A');
}

// Helper functions for formatting and calculations
function formatRupiah(value) {
  if (typeof value !== 'number') value = Number(value) || 0;
  return 'Rp ' + value.toLocaleString('id-ID');
}

function calculateTotal(po) {
  if (!po.items) return 0;
  return po.items.reduce((sum, item) => sum + (Number(item.total) || 0), 0);
}

function calculateGrandTotal(po) {
  const subtotal = calculateTotal(po);
  if (po.ppn_enabled) {
    const ppnAmount = po.ppn_amount || (subtotal * 0.11);
    return subtotal + ppnAmount;
  }
  return subtotal;
}

// Form untuk generate PO
const poForm = useForm({
    items: [],
});

// Fetch PR list yang belum di-PO
const fetchPRList = async () => {
    try {
        const response = await axios.get('/api/pr-foods/available');
        prList.value = response.data;
    } catch (error) {
        console.error('Error fetching PR list:', error);
        Swal.fire('Error', 'Failed to fetch PR list', 'error');
    }
};

// Fetch suppliers
const fetchSuppliers = async () => {
    try {
        const response = await axios.get('/api/suppliers');
        suppliers.value = response.data;
    } catch (error) {
        console.error('Error fetching suppliers:', error);
        Swal.fire('Error', 'Failed to fetch suppliers', 'error');
    }
};

// Ketika PR dipilih, fetch items dari PR tersebut
const handlePRSelection = async () => {
    if (selectedPRs.value.length === 0) {
        items.value = [];
        return;
    }

    try {
        const response = await axios.post('/api/pr-foods/items', {
            pr_ids: selectedPRs.value
        });
        items.value = response.data;
    } catch (error) {
        console.error('Error fetching PR items:', error);
        Swal.fire('Error', 'Failed to fetch PR items', 'error');
    }
};

// Generate PO berdasarkan supplier
const generatePO = async () => {
    // Validasi
    const itemsWithoutSupplier = items.value.filter(item => !item.supplier_id);
    if (itemsWithoutSupplier.length > 0) {
        Swal.fire('Error', 'Please select supplier for all items', 'error');
        return;
    }

    // Kelompokkan items berdasarkan supplier
    const itemsBySupplier = {};
    items.value.forEach(item => {
        if (!itemsBySupplier[item.supplier_id]) {
            itemsBySupplier[item.supplier_id] = [];
        }
        itemsBySupplier[item.supplier_id].push(item);
    });

    try {
        loading.value = true;
        const response = await axios.post('/api/po-foods/generate', {
            items_by_supplier: itemsBySupplier
        });
        
        Swal.fire('Success', 'PO has been generated successfully', 'success');
        // Reset form
        selectedPRs.value = [];
        items.value = [];
        await fetchPRList();
    } catch (error) {
        console.error('Error generating PO:', error);
        Swal.fire('Error', 'Failed to generate PO', 'error');
    } finally {
        loading.value = false;
    }
};

// Fetch PO list
const fetchPOList = async () => {
    try {
        loading.value = true;
        const response = await axios.get('/api/po-foods');
        poList.value = response.data;
    } catch (error) {
        console.error('Error fetching PO list:', error);
        Swal.fire('Error', 'Failed to fetch PO list', 'error');
    } finally {
        loading.value = false;
    }
};

// Watch untuk memantau perubahan props dan memulihkan filter state
watch(() => props.filters, (newFilters) => {
  if (newFilters) {
    // Jika ada filter dari props, gunakan itu
    search.value = newFilters.search || '';
    selectedStatus.value = newFilters.status || '';
    from.value = newFilters.from || '';
    to.value = newFilters.to || '';
    perPage.value = newFilters.perPage || 10;
    
    // Jika ada purchaseOrders di props, berarti data sudah loaded
    if (props.purchaseOrders) {
      dataLoaded.value = true;
    }
  } else {
    // Jika tidak ada filter dari props, coba pulihkan dari sessionStorage
    restoreFilterState();
  }
}, { immediate: true });

// Watch untuk purchaseOrders props
watch(() => props.purchaseOrders, (newData) => {
  if (newData) {
    dataLoaded.value = true;
  }
}, { immediate: true });

// Watch untuk auto-save filter state saat ada perubahan
watch([search, selectedStatus, from, to, perPage], () => {
  const filterState = {
    search: search.value,
    status: selectedStatus.value,
    from: from.value,
    to: to.value,
    perPage: perPage.value
  };
  sessionStorage.setItem('po-foods-filters', JSON.stringify(filterState));
}, { deep: true });

onMounted(() => {
    fetchPRList();
    fetchSuppliers();
    fetchPOList();
    
    // Jika tidak ada filter dari props, coba pulihkan dari sessionStorage
    if (!props.filters || Object.keys(props.filters).length === 0) {
        restoreFilterState();
    }
    
    // Jika ada purchaseOrders di props, berarti data sudah loaded
    if (props.purchaseOrders) {
        dataLoaded.value = true;
    }
    
    // Simpan filter state awal
    const filterState = {
        search: search.value,
        status: selectedStatus.value,
        from: from.value,
        to: to.value,
        perPage: perPage.value
    };
    sessionStorage.setItem('po-foods-filters', JSON.stringify(filterState));
});
</script>

<template>
    <AppLayout>
        <div class="w-full py-8 px-0">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                    <i class="fa-solid fa-file-invoice text-blue-500"></i> Purchase Order Foods
                </h1>
                <button
                    @click="openCreate"
                    class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold"
                >
                    + Buat PO Foods Baru
                </button>
            </div>
            <div class="py-4">
                <div class="flex flex-wrap gap-3 mb-4 items-center">
                    <input
                        v-model="search"
                        @input="onSearchInput"
                        type="text"
                        placeholder="Cari nomor PO atau nama supplier..."
                        class="w-64 px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
                    />
                    <select v-model="selectedStatus" @change="onStatusChange" class="px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition">
                        <option value="">Semua Status</option>
                        <option value="draft">Draft</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                        <option value="receive">Receive</option>
                        <option value="payment">Payment</option>
                    </select>
                    <input type="date" v-model="from" @change="onDateChange" class="px-2 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition" placeholder="Dari tanggal" />
                    <span>-</span>
                    <input type="date" v-model="to" @change="onDateChange" class="px-2 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition" placeholder="Sampai tanggal" />
                    <select v-model="perPage" @change="onPerPageChange" class="px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition">
                        <option value="10">10 data</option>
                        <option value="25">25 data</option>
                        <option value="50">50 data</option>
                        <option value="100">100 data</option>
                    </select>
                    <button @click="clearFilters" class="px-4 py-2 rounded-xl bg-gray-500 text-white hover:bg-gray-600 transition font-semibold">
                        <i class="fas fa-undo mr-2"></i>
                        Clear Filter
                    </button>
                    <!-- Tombol Load Data -->
                    <button 
                        @click="loadData"
                        :disabled="loadingData"
                        class="px-4 py-2 rounded-xl bg-gradient-to-r from-green-500 to-green-700 text-white hover:from-green-600 hover:to-green-800 transition font-semibold shadow-lg disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                    >
                        <i v-if="loadingData" class="fa fa-spinner fa-spin"></i>
                        <i v-else class="fas fa-database mr-1"></i>
                        {{ loadingData ? 'Loading...' : 'Load Data' }}
                    </button>
                    <!-- Tombol Pending GM Finance -->
                    <button 
                        v-if="canApproveGMFinance()"
                        @click="togglePendingGMFINANCEModal" 
                        class="px-4 py-2 rounded-xl bg-purple-100 text-purple-700 hover:bg-purple-200 font-semibold transition"
                    >
                        <i class="fas fa-clock mr-2"></i>
                        Pending Approval GM
                    </button>
                </div>
            </div>
            <div class="py-12">
                <div class="w-full px-0">
                    <div class="bg-white overflow-hidden shadow-xl rounded-none p-0">
                        <!-- PO List -->
                        <div v-if="loadingData" class="flex justify-center items-center py-8">
                            <div class="text-center">
                                <svg class="animate-spin h-8 w-8 text-blue-600 mx-auto mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <p class="text-gray-600">Memuat data...</p>
                            </div>
                        </div>
                        <div v-else-if="!dataLoaded" class="flex justify-center items-center py-12">
                            <div class="text-center">
                                <i class="fas fa-database text-6xl text-gray-300 mb-4"></i>
                                <p class="text-gray-600 text-lg mb-2">Data belum dimuat</p>
                                <p class="text-gray-500 text-sm mb-4">Silakan isi filter dan klik tombol "Load Data" untuk menampilkan data</p>
                                <button 
                                    @click="loadData"
                                    class="px-6 py-2 rounded-xl bg-gradient-to-r from-green-500 to-green-700 text-white hover:from-green-600 hover:to-green-800 transition font-semibold shadow-lg"
                                >
                                    <i class="fas fa-database mr-2"></i>
                                    Load Data
                                </button>
                            </div>
                        </div>
                        <div v-else>
                            <div class="bg-white rounded-2xl shadow-2xl overflow-x-auto transition-all">
                                <table class="w-full min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gradient-to-r from-blue-50 to-blue-100">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tl-2xl">No. PO</th>
                                            <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Tanggal</th>
                                            <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Source</th>
                                            <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Supplier</th>
                                            <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">GR Number</th>
                                            <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Tgl Kedatangan</th>
                                            <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Tanggal Print</th>
                                            <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Created By</th>
                                            <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Purchasing Manager</th>
                                            <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">GM Finance</th>
                                            <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Status</th>
                                            <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tr-2xl">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-if="!purchaseOrders?.data || purchaseOrders.data.length === 0">
                                            <td colspan="12" class="text-center py-10 text-gray-400">Tidak ada data PO Foods.</td>
                                        </tr>
                                        <tr v-for="po in purchaseOrders?.data || []" :key="po.id" class="hover:bg-blue-50 transition shadow-sm">
                                            <td class="px-6 py-3 font-mono font-semibold text-blue-700">{{ po.number }}</td>
                                            <td class="px-6 py-3">{{ new Date(po.date).toLocaleDateString('id-ID') }}</td>
                                            <td class="px-6 py-3">
                                                <div class="flex flex-col gap-1">
                                                    <!-- Source Type Badge -->
                                                    <div class="flex items-center gap-1">
                                                        <span v-if="po.source_type === 'pr_foods' || !po.source_type" 
                                                              class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full font-semibold">
                                                            Purchase Requisition
                                                        </span>
                                                        <span v-else-if="po.source_type === 'ro_supplier'" 
                                                              class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full font-semibold">
                                                            RO Supplier
                                                        </span>
                                                        <span v-else 
                                                              class="bg-gray-100 text-gray-800 text-xs px-2 py-1 rounded-full font-semibold">
                                                            N/A
                                                        </span>
                                                    </div>
                                                    <!-- Source Numbers -->
                                                    <div class="flex flex-wrap gap-1">
                                                        <span v-for="sourceNumber in po.source_numbers" :key="sourceNumber" 
                                                              :class="{
                                                                  'bg-blue-100 text-blue-800': po.source_type === 'pr_foods' || !po.source_type,
                                                                  'bg-green-100 text-green-800': po.source_type === 'ro_supplier'
                                                              }"
                                                              class="text-xs px-2 py-1 rounded-full">
                                                            {{ sourceNumber }}
                                                        </span>
                                                    </div>
                                                    <!-- Outlet Names for RO Supplier -->
                                                    <div v-if="po.source_type === 'ro_supplier' && po.source_outlets && po.source_outlets.length > 0" 
                                                         class="flex flex-wrap gap-1 mt-1">
                                                        <span v-for="outlet in po.source_outlets" :key="outlet" 
                                                              class="bg-orange-100 text-orange-800 text-xs px-2 py-1 rounded-full">
                                                            {{ outlet }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-3">{{ po.supplier?.name }}</td>
                                            <td class="px-6 py-3">
                                                <span v-if="po.gr_number" class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                    <i class="fas fa-check-circle mr-1"></i>
                                                    {{ po.gr_number }}
                                                </span>
                                                <span v-else class="text-gray-400 text-sm">-</span>
                                            </td>
                                            <td class="px-6 py-3">{{ po.arrival_date ? new Date(po.arrival_date).toLocaleDateString('id-ID') : '-' }}</td>
                                            <td class="px-6 py-3">{{ po.printed_at ? new Date(po.printed_at).toLocaleDateString('id-ID') + ' ' + new Date(po.printed_at).toLocaleTimeString('id-ID') : '-' }}</td>
                                            <td class="px-6 py-3">{{ po.creator?.nama_lengkap }}</td>
                                            <td class="px-6 py-3">
                                                <div v-if="po.purchasing_manager_approved_at" class="text-xs">
                                                    <div class="font-semibold text-green-700">{{ po.purchasing_manager?.nama_lengkap }}</div>
                                                    <div class="text-gray-600">{{ new Date(po.purchasing_manager_approved_at).toLocaleDateString('id-ID') }}</div>
                                                    <div class="text-gray-500">{{ new Date(po.purchasing_manager_approved_at).toLocaleTimeString('id-ID') }}</div>
                                                </div>
                                                <div v-else class="text-xs text-gray-400">-</div>
                                            </td>
                                            <td class="px-6 py-3">
                                                <div v-if="po.gm_finance_approved_at" class="text-xs">
                                                    <div class="font-semibold text-green-700">{{ po.gm_finance?.nama_lengkap }}</div>
                                                    <div class="text-gray-600">{{ new Date(po.gm_finance_approved_at).toLocaleDateString('id-ID') }}</div>
                                                    <div class="text-gray-500">{{ new Date(po.gm_finance_approved_at).toLocaleTimeString('id-ID') }}</div>
                                                </div>
                                                <div v-else class="text-xs text-gray-400">-</div>
                                            </td>
                                            <td class="px-6 py-3">
                                                <span :class="{
                                                    'bg-gray-100 text-gray-700': po.status === 'draft',
                                                    'bg-green-100 text-green-700': po.status === 'approved',
                                                    'bg-red-100 text-red-700': po.status === 'rejected',
                                                    'bg-yellow-100 text-yellow-700': po.status === 'receive',
                                                    'bg-purple-100 text-purple-700': po.status === 'payment',
                                                }" class="px-2 py-1 rounded-full text-xs font-semibold shadow">
                                                    {{ po.status }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-3">
                                                <div class="flex gap-2">
                                                    <button @click="openDetail(po.id)" class="inline-flex items-center btn btn-xs bg-blue-100 text-blue-800 hover:bg-blue-200 rounded px-2 py-1 font-semibold transition">
                                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                                        Detail
                                                    </button>
                                                    <button @click="openEdit(po.id)" class="inline-flex items-center btn btn-xs bg-yellow-100 text-yellow-800 hover:bg-yellow-200 rounded px-2 py-1 font-semibold transition">
                                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M15.232 5.232l3.536 3.536M9 13l6-6m2 2l-6 6m-2 2h6a2 2 0 002-2v-6a2 2 0 00-2-2H7a2 2 0 00-2 2v6a2 2 0 002 2z"/></svg>
                                                        Edit
                                                    </button>
                                                    <button @click="hapus(po)" class="inline-flex items-center btn btn-xs bg-red-100 text-red-700 hover:bg-red-200 rounded px-2 py-1 font-semibold transition">
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
                                    v-for="link in purchaseOrders?.links || []"
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
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Modal Pending GM Finance Approval -->
        <div v-if="showPendingGMFinanceModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg shadow-xl w-11/12 max-w-7xl max-h-[90vh] overflow-hidden">
                <!-- Modal Header -->
                <div class="flex justify-between items-center p-6 border-b border-gray-200">
                    <h2 class="text-xl font-semibold text-gray-800">
                        <i class="fas fa-clock text-purple-500 mr-2"></i>
                        PO Pending GM Finance Approval
                    </h2>
                    <button 
                        @click="togglePendingGMFINANCEModal"
                        class="text-gray-400 hover:text-gray-600 text-2xl font-bold"
                    >
                        &times;
                    </button>
                </div>
                
                <!-- Filter Section -->
                <div class="p-4 bg-gray-50 border-b border-gray-200">
                    <div class="flex flex-wrap gap-3 items-center">
                        <input
                            v-model="modalSearch"
                            @keyup.enter="applyModalFilter"
                            type="text"
                            placeholder="Cari nomor PO atau nama supplier..."
                            class="w-48 px-3 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-purple-400 focus:border-purple-400 transition text-sm"
                        />
                        <select 
                            v-model="modalStatusFilter" 
                            @change="applyModalFilter"
                            class="px-3 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-purple-400 focus:border-purple-400 transition text-sm"
                        >
                            <option value="">Semua Status</option>
                            <option value="draft">Draft</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                        </select>
                        <input 
                            type="date" 
                            v-model="modalDateFrom" 
                            @change="applyModalFilter"
                            class="px-3 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-purple-400 focus:border-purple-400 transition text-sm"
                            placeholder="Dari tanggal" 
                        />
                        <span class="text-gray-500">-</span>
                        <input 
                            type="date" 
                            v-model="modalDateTo" 
                            @change="applyModalFilter"
                            class="px-3 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-purple-400 focus:border-purple-400 transition text-sm"
                            placeholder="Sampai tanggal" 
                        />
                        <button 
                            @click="applyModalFilter"
                            class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition text-sm font-semibold"
                        >
                            <i class="fas fa-search mr-1"></i>
                            Filter
                        </button>
                        <button 
                            @click="resetModalFilter"
                            class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition text-sm font-semibold"
                        >
                            <i class="fas fa-undo mr-1"></i>
                            Reset
                        </button>
                    </div>
                </div>
                
                <!-- Modal Body -->
                <div class="p-6 overflow-y-auto max-h-[calc(90vh-120px)]">
                    <div v-if="loadingPendingPOs" class="flex justify-center items-center py-8">
                        <svg class="animate-spin h-8 w-8 text-purple-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </div>
                    
                    <div v-else-if="pendingGMFINANCEPOs.length === 0" class="text-center py-8 text-gray-500">
                        <i class="fas fa-check-circle text-4xl text-green-500 mb-4"></i>
                        <p class="text-lg">Tidak ada PO yang pending GM Finance approval</p>
                    </div>
                    
                    <div v-else>
                        <!-- Summary -->
                        <div class="mb-4 p-3 bg-purple-50 rounded-lg border border-purple-200">
                            <div class="flex justify-between items-center">
                                <div class="text-purple-800">
                                    <span class="font-semibold">{{ pendingGMFINANCEPOs.length }}</span> PO ditemukan yang pending GM Finance approval
                                </div>
                                <div class="text-sm text-purple-600">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    PO yang sudah di-approve Purchasing Manager
                                </div>
                            </div>
                        </div>
                        
                        <div class="space-y-4">
                            <div v-for="po in pendingGMFINANCEPOs" :key="po.id" class="border border-gray-200 rounded-lg overflow-hidden">
                                <!-- PO Header -->
                                <div class="bg-gradient-to-r from-purple-50 to-purple-100 p-4">
                                    <div class="flex justify-between items-center">
                                        <div class="flex items-center gap-4">
                                            <button 
                                                @click="toggleExpandPOInModal(po.id)" 
                                                class="text-purple-600 hover:text-purple-800 transition"
                                                :title="expandedPOsInModal.has(po.id) ? 'Sembunyikan detail' : 'Tampilkan detail'"
                                            >
                                                <i :class="expandedPOsInModal.has(po.id) ? 'fas fa-chevron-down' : 'fas fa-chevron-right'"></i>
                                            </button>
                                            <div>
                                                <h3 class="font-semibold text-purple-800">{{ po.number }}</h3>
                                                <p class="text-sm text-purple-600">{{ po.supplier?.name }}</p>
                                                <div v-if="po.purchasing_manager_approved_at" class="text-xs text-green-600 mt-1">
                                                    <i class="fas fa-check-circle mr-1"></i>
                                                    Approved by {{ po.purchasing_manager?.nama_lengkap }} on {{ new Date(po.purchasing_manager_approved_at).toLocaleDateString('id-ID') }} {{ new Date(po.purchasing_manager_approved_at).toLocaleTimeString('id-ID') }}
                                                </div>
                                            </div>
                                        </div>
                                        <div class="flex gap-2">
                                            <button @click="approveGMFinance(po)" class="px-3 py-1 bg-green-100 text-green-700 rounded text-sm hover:bg-green-200 transition">
                                                <i class="fas fa-check mr-1"></i>
                                                Approve
                                            </button>
                                            <button @click="rejectGMFinance(po)" class="px-3 py-1 bg-red-100 text-red-700 rounded text-sm hover:bg-red-200 transition">
                                                <i class="fas fa-times mr-1"></i>
                                                Reject
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Expanded Detail -->
                                <div v-if="expandedPOsInModal.has(po.id)" class="p-4 bg-gray-50">
                                    <div class="flex justify-between items-center mb-4">
                                        <h4 class="text-lg font-semibold text-gray-800">Detail Item PO</h4>
                                        <button 
                                            @click="fetchStockForPO(po)" 
                                            class="px-3 py-1 bg-blue-100 text-blue-700 rounded text-sm hover:bg-blue-200 transition"
                                        >
                                            <i class="fas fa-sync-alt mr-1"></i>
                                            Refresh Stock
                                        </button>
                                    </div>
                                    <div class="overflow-x-auto">
                                        <table class="w-full text-sm">
                                            <thead class="bg-white">
                                                <tr>
                                                    <th class="px-4 py-2 text-left font-medium text-gray-700">Item</th>
                                                    <th class="px-4 py-2 text-left font-medium text-gray-700">Qty</th>
                                                    <th class="px-4 py-2 text-left font-medium text-gray-700">Unit</th>
                                                    <th class="px-4 py-2 text-left font-medium text-gray-700">Harga</th>
                                                    <th class="px-4 py-2 text-left font-medium text-gray-700">Total</th>
                                                    <th class="px-4 py-2 text-left font-medium text-gray-700">Last Stock</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white">
                                                <tr v-for="item in (po.items_with_stock || po.items)" :key="item.id" class="border-b border-gray-100">
                                                    <td class="px-4 py-3">{{ item.item?.name }}</td>
                                                    <td class="px-4 py-3">{{ item.quantity }}</td>
                                                    <td class="px-4 py-3">{{ item.unit?.name }}</td>
                                                    <td class="px-4 py-3">{{ formatRupiah(item.price) }}</td>
                                                    <td class="px-4 py-3">{{ formatRupiah(item.total) }}</td>
                                                    <td class="px-4 py-3 text-xs text-gray-600">
                                                        {{ formatStockDisplay(item.stock) }}
                                                    </td>
                                                </tr>
                                            </tbody>
                                            <tfoot class="bg-gray-50">
                                                <tr>
                                                    <td colspan="4" class="px-4 py-2 text-right font-medium">Subtotal:</td>
                                                    <td class="px-4 py-2 font-medium">{{ formatRupiah(po.subtotal || calculateTotal(po)) }}</td>
                                                    <td></td>
                                                </tr>
                                                <tr v-if="po.ppn_enabled">
                                                    <td colspan="4" class="px-4 py-2 text-right font-medium text-blue-600">PPN (11%):</td>
                                                    <td class="px-4 py-2 font-medium text-blue-600">{{ formatRupiah(po.ppn_amount || 0) }}</td>
                                                    <td></td>
                                                </tr>
                                                <tr class="bg-gray-100">
                                                    <td colspan="4" class="px-4 py-2 text-right font-bold">Grand Total:</td>
                                                    <td class="px-4 py-2 font-bold text-green-600">{{ formatRupiah(po.grand_total || calculateGrandTotal(po)) }}</td>
                                                    <td></td>
                                                </tr>
                                            </tfoot>
                                        </table>
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