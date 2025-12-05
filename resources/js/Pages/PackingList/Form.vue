<script setup>
import { ref, computed, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';
import Swal from 'sweetalert2';

const props = defineProps({
  floorOrders: Array,
  warehouseDivisions: Array,
});

const selectedFO = ref('');
const selectedDivision = ref('');
const items = ref([]);
const loadingItems = ref(false);
const error = ref('');
const isSubmitting = ref(false);

// New RO selection variables
const searchRO = ref('');
const statusFilter = ref('');
const selectedRO = ref(null);
const viewMode = ref('cards');
const arrivalDateFilter = ref(''); // Filter tanggal kedatangan

const foDetail = computed(() => props.floorOrders.find(f => f.id == selectedFO.value) || {});

const selectedDivisionName = computed(() => {
  const div = props.warehouseDivisions.find(d => d.id == selectedDivision.value);
  return div ? div.name : '-';
});

const itemsByCategory = computed(() => {
  const map = {};
  items.value.forEach(item => {
    const cat = item.item?.category?.name || 'Tanpa Kategori';
    if (!map[cat]) map[cat] = [];
    map[cat].push(item);
  });
  return map;
});

// New RO selection computed properties
const filteredROs = computed(() => {
  let filtered = props.floorOrders || [];

  if (searchRO.value) {
    const search = searchRO.value.toLowerCase();
    filtered = filtered.filter(ro => 
      ro.outlet?.nama_outlet?.toLowerCase().includes(search) ||
      ro.order_number?.toLowerCase().includes(search) ||
      ro.user?.nama_lengkap?.toLowerCase().includes(search) ||
      formatDate(ro.tanggal).toLowerCase().includes(search)
    );
  }

  if (statusFilter.value) {
    filtered = filtered.filter(ro => ro.status === statusFilter.value);
  }

  // Filter berdasarkan tanggal kedatangan
  if (arrivalDateFilter.value) {
    filtered = filtered.filter(ro => {
      if (!ro.arrival_date) return false;
      const roArrivalDate = new Date(ro.arrival_date).toISOString().split('T')[0];
      return roArrivalDate === arrivalDateFilter.value;
    });
  }

  return filtered;
});

const selectedROData = computed(() => {
  return props.floorOrders?.find(ro => ro.id === selectedRO.value);
});

// Methods
const formatDate = (date) => {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('id-ID', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric'
  });
};

const getStatusBadgeClass = (status) => {
  switch (status) {
    case 'approved':
      return 'bg-green-100 text-green-800';
    case 'packing':
      return 'bg-yellow-100 text-yellow-800';
    default:
      return 'bg-gray-100 text-gray-800';
  }
};

const selectRO = (roId) => {
  selectedRO.value = roId;
  selectedFO.value = roId;
};

const clearSelection = () => {
  selectedRO.value = null;
  selectedFO.value = '';
};

const clearSearch = () => {
  searchRO.value = '';
};

const filterByArrivalDate = () => {
  // Filter akan otomatis terupdate melalui computed property
};

const clearAllFilters = () => {
  searchRO.value = '';
  statusFilter.value = '';
  arrivalDateFilter.value = '';
};

// Function untuk mengisi quantity satu item
const fillItemQuantity = (item) => {
  item.input_qty = item.qty ?? item.qty_order;
};

// Computed untuk summary data
const summaryData = computed(() => {
  const selectedItems = items.value.filter(i => i.checked);
  const totalItems = selectedItems.length;
  const totalQty = selectedItems.reduce((sum, item) => sum + (item.input_qty || 0), 0);
  
  return {
    selectedItems,
    totalItems,
    totalQty,
    warehouseDivision: props.warehouseDivisions.find(d => d.id == selectedDivision.value),
    floorOrder: props.floorOrders.find(f => f.id == selectedFO.value)
  };
});

// Function untuk menampilkan modal summary
const showSummaryModal = async () => {
  if (!selectedFO.value || !selectedDivision.value || items.value.length === 0) return;
  
  const selectedItems = items.value.filter(i => i.checked);
  if (selectedItems.length === 0) {
    await Swal.fire({
      icon: 'warning',
      title: 'Peringatan',
      text: 'Pilih minimal satu item untuk di-packing!'
    });
    return;
  }

  // Validasi input quantity
  const invalidItems = selectedItems.filter(item => !item.input_qty || item.input_qty <= 0);
  if (invalidItems.length > 0) {
    await Swal.fire({
      icon: 'warning',
      title: 'Peringatan',
      text: 'Semua item yang dipilih harus memiliki quantity yang valid!'
    });
    return;
  }

  // Buat HTML untuk summary
  const summaryHtml = `
    <div class="text-left">
      <div class="mb-4">
        <h3 class="font-bold text-lg mb-2 text-blue-600">Summary Packing List</h3>
        
        <!-- RO Info -->
        <div class="bg-blue-50 p-3 rounded-lg mb-3">
          <h4 class="font-semibold text-blue-800 mb-2">Detail Request Order</h4>
          <div class="grid grid-cols-2 gap-2 text-sm">
            <div><span class="font-medium">Outlet:</span> ${summaryData.value.floorOrder?.outlet?.nama_outlet || '-'}</div>
            <div><span class="font-medium">Nomor RO:</span> <span class="font-mono">${summaryData.value.floorOrder?.order_number || '-'}</span></div>
            <div><span class="font-medium">Tanggal:</span> ${formatDate(summaryData.value.floorOrder?.tanggal)}</div>
            <div><span class="font-medium">Warehouse Division:</span> ${summaryData.value.warehouseDivision?.name || '-'}</div>
          </div>
        </div>

        <!-- Items Summary -->
        <div class="bg-gray-50 p-3 rounded-lg mb-3">
          <h4 class="font-semibold text-gray-800 mb-2">Items yang akan di-packing</h4>
          <div class="text-sm mb-2">
            <span class="font-medium">Total Items:</span> ${summaryData.value.totalItems} item(s)
          </div>
          <div class="text-sm mb-3">
            <span class="font-medium">Total Quantity:</span> ${summaryData.value.totalQty}
          </div>
          
          <div class="max-h-40 overflow-y-auto">
            <table class="w-full text-xs">
              <thead class="bg-gray-100">
                <tr>
                  <th class="py-1 px-2 text-left">No</th>
                  <th class="py-1 px-2 text-left">Item</th>
                  <th class="py-1 px-2 text-left">Qty Order</th>
                  <th class="py-1 px-2 text-left">Qty Packing</th>
                  <th class="py-1 px-2 text-left">Unit</th>
                </tr>
              </thead>
              <tbody>
                ${selectedItems.map((item, idx) => `
                  <tr class="border-b border-gray-200">
                    <td class="py-1 px-2">${idx + 1}</td>
                    <td class="py-1 px-2">${item.item?.name || item.item_name}</td>
                    <td class="py-1 px-2 text-right">${item.qty ?? item.qty_order}</td>
                    <td class="py-1 px-2 text-right font-medium text-blue-600">${item.input_qty}</td>
                    <td class="py-1 px-2">${item.unit}</td>
                  </tr>
                `).join('')}
              </tbody>
            </table>
          </div>
        </div>

        <div class="text-sm text-gray-600">
          <i class="fas fa-info-circle mr-1"></i>
          Pastikan semua data sudah benar sebelum melanjutkan.
        </div>
      </div>
    </div>
  `;

  const result = await Swal.fire({
    title: 'Konfirmasi Packing List',
    html: summaryHtml,
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Ya, Buat Packing List',
    cancelButtonText: 'Batal',
    confirmButtonColor: '#3B82F6',
    cancelButtonColor: '#6B7280',
    width: '600px',
    customClass: {
      container: 'summary-modal-container'
    }
  });

  if (result.isConfirmed) {
    await submitPackingList();
  }
};

// Function untuk submit packing list
async function submitPackingList() {
  if (!selectedFO.value || !selectedDivision.value || items.value.length === 0) return;
  
  const data = {
    food_floor_order_id: selectedFO.value,
    warehouse_division_id: selectedDivision.value,
    items: items.value
      .filter(i => i.checked)
      .map(i => ({
        food_floor_order_item_id: i.id,
        qty: i.input_qty ?? 0,
        unit: i.unit,
        source: i.source,
        reason: i.reason || null
      }))
  };
  
  isSubmitting.value = true;
  try {
    const res = await axios.post('/packing-list', data);
    isSubmitting.value = false;
    await Swal.fire({
      icon: 'success',
      title: 'Berhasil',
      text: 'Packing List berhasil dibuat!'
    });
    window.location.href = '/packing-list';
  } catch (e) {
    isSubmitting.value = false;
    await Swal.fire({
      icon: 'error',
      title: 'Gagal',
      text: e?.response?.data?.message || 'Gagal membuat Packing List.'
    });
  }
}

// Original methods
watch([selectedFO, selectedDivision], async ([fo, div]) => {
  if (fo && div) {
    loadingItems.value = true;
    error.value = '';
    try {
      const res = await axios.get('/api/packing-list/available-items', {
        params: { fo_id: fo, division_id: div }
      });
      items.value = (res.data.items || []).map(item => ({
        ...item,
        source: 'warehouse',
        checked: true,
        input_qty: null
      }));
    } catch (e) {
      error.value = 'Gagal mengambil data item.';
      items.value = [];
    } finally {
      loadingItems.value = false;
    }
  } else {
    items.value = [];
  }
});



// Watch for RO selection changes
watch(selectedRO, (newValue) => {
  if (newValue) {
    selectedFO.value = newValue;
  }
});

// Function untuk print Packing List
const printPackingList = () => {
  if (!selectedFO.value || !selectedDivision.value || items.value.length === 0) {
    Swal.fire({
      icon: 'warning',
      title: 'Peringatan',
      text: 'Pilih RO dan Warehouse Division terlebih dahulu!'
    });
    return;
  }

  const selectedItems = items.value.filter(i => i.checked);
  if (selectedItems.length === 0) {
    Swal.fire({
      icon: 'warning',
      title: 'Peringatan',
      text: 'Pilih minimal satu item untuk di-print!'
    });
    return;
  }

  // Data untuk print
  const roData = props.floorOrders.find(f => f.id == selectedFO.value);
  const divisionData = props.warehouseDivisions.find(d => d.id == selectedDivision.value);
  
  const printData = {
    orderNumber: roData?.order_number || '-',
    date: formatDate(roData?.tanggal),
    outlet: roData?.outlet?.nama_outlet || '-',
    items: selectedItems.map(item => ({
      name: item.item?.name || item.item_name || '-',
      qty: item.input_qty || item.qty || item.qty_order || 0,
      unit: item.unit || '-'
    })),
    divisionName: divisionData?.name || '-',
    roNumber: roData?.order_number || '-',
    roDate: formatDate(roData?.tanggal),
    roCreatorName: roData?.user?.nama_lengkap || '-',
    arrivalDate: roData?.arrival_date ? formatDate(roData?.arrival_date) : '-'
  };

  // Buka window print
  const printWindow = window.open('', '_blank', 'width=600,height=600');
  printWindow.document.write(`
    <!DOCTYPE html>
    <html>
    <head>
      <title>Packing List - ${printData.orderNumber}</title>
      <style>
        @media print {
          @page {
            size: 58mm auto;
            margin: 0;
          }
          body {
            width: 58mm;
            margin: 0;
            padding: 2mm;
            font-family: 'Courier New', monospace;
            font-size: 10px;
            line-height: 1.2;
          }
        }
        body {
          font-family: 'Courier New', monospace;
          font-size: 10px;
          line-height: 1.2;
          width: 58mm;
          margin: 0;
          padding: 2mm;
        }
        .header {
          text-align: center;
          font-weight: bold;
          margin-bottom: 4mm;
        }
        .title {
          font-size: 12px;
          margin-bottom: 2mm;
        }
        .company {
          font-size: 10px;
          margin-bottom: 2mm;
        }
        .info {
          margin-bottom: 4mm;
        }
        .info div {
          margin-bottom: 1mm;
        }
        .separator {
          border-top: 1px solid #000;
          margin: 2mm 0;
        }
        .items {
          margin-bottom: 4mm;
        }
        .item {
          margin-bottom: 2mm;
        }
                 .item-name {
           font-weight: bold;
         }
        .summary {
          margin-top: 4mm;
        }
        .footer {
          text-align: center;
          margin-top: 4mm;
          font-size: 9px;
        }
        @media screen {
          body {
            border: 1px solid #ccc;
            margin: 10px auto;
          }
        }
      </style>
    </head>
    <body>
      <div class="header">
        <div class="title">PACKING LIST</div>
        <div class="company">JUSTUS GROUP</div>
        <div class="company">${printData.divisionName}</div>
      </div>
      
      <div class="info">
        <div>No: ${printData.orderNumber}</div>
        <div>Tanggal: ${printData.date}</div>
        <div>Outlet: ${printData.outlet}</div>
        <div>RO: ${printData.roNumber}</div>
        <div>Tgl RO: ${printData.roDate}</div>
        <div>Kedatangan: ${printData.arrivalDate}</div>
        <div>Pembuat RO: ${printData.roCreatorName}</div>
      </div>
      
      <div class="separator"></div>
      
               <div class="items">
           <div style="font-weight: bold; margin-bottom: 2mm;">ITEMS:</div>
                       ${printData.items.map((item, index) => `
              <div class="item">
                <div class="item-name">${item.qty} ${item.unit} ${item.name}</div>
              </div>
            `).join('')}
         </div>
      
      <div class="separator"></div>
      
             <div class="summary">
         <div style="font-weight: bold; margin-bottom: 2mm;">SUMMARY:</div>
         <div>Total Items: ${printData.items.length}</div>
       </div>
      
      <div class="footer">
        <div>Generated: ${new Date().toLocaleString('id-ID')}</div>
        <div style="margin-top: 2mm;">Terima kasih</div>
      </div>
    </body>
    </html>
  `);
  printWindow.document.close();
  printWindow.focus();
  
  // Auto print setelah window terbuka
  setTimeout(() => {
    printWindow.print();
  }, 500);
};
</script>

<template>
  <AppLayout>
    <div class="max-w-4xl mx-auto py-4 px-3">
      <h1 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
        <i class="fa-solid fa-box text-blue-500"></i> Buat Packing List
      </h1>

      <!-- Step 1: RO Selection -->
      <div class="mb-8">
        <h2 class="text-lg font-semibold text-gray-700 mb-4">1. Pilih Request Order (RO)</h2>
        
        <!-- Search and Filter Section -->
        <div class="bg-white p-3 rounded-lg border border-gray-200 mb-3">
          <div class="space-y-3">
            <!-- Search Box -->
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">Cari RO</label>
              <div class="relative">
                <input 
                  type="text" 
                  v-model="searchRO" 
                  placeholder="Outlet, nomor RO, atau tanggal..." 
                  class="w-full px-3 py-2 pl-8 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                />
                <div class="absolute left-2 top-2">
                  <i class="fas fa-search text-gray-400 text-xs"></i>
                </div>
                <div v-if="searchRO" class="absolute right-2 top-2">
                  <button @click="clearSearch" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xs"></i>
                  </button>
                </div>
              </div>
            </div>
            
            <!-- Filter by Status -->
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">Status</label>
              <select v-model="statusFilter" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500">
                <option value="">Semua Status</option>
                <option value="approved">Approved</option>
                <option value="packing">Packing</option>
              </select>
            </div>

            <!-- Filter by Arrival Date -->
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">Tanggal Kedatangan</label>
              <input 
                type="date" 
                v-model="arrivalDateFilter" 
                @change="filterByArrivalDate"
                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
              />
            </div>

            <!-- Clear Filters Button -->
            <div>
              <button 
                @click="clearAllFilters" 
                class="w-full px-3 py-2 text-sm bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200 transition-colors"
              >
                <i class="fas fa-times mr-2"></i>Clear All Filters
              </button>
            </div>
          </div>
        </div>

        <!-- RO Selection Cards -->
        <div v-if="filteredROs.length > 0" class="space-y-3">
          <div class="flex justify-between items-center">
            <h3 class="text-sm font-semibold text-gray-900">
              RO Tersedia ({{ filteredROs.length }})
            </h3>
            <div class="flex gap-1">
              <button 
                @click="viewMode = 'cards'" 
                :class="[
                  'px-2 py-1 rounded text-xs font-medium',
                  viewMode === 'cards' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                ]"
              >
                <i class="fas fa-th-large mr-1"></i> Cards
              </button>
              <button 
                @click="viewMode = 'list'" 
                :class="[
                  'px-2 py-1 rounded text-xs font-medium',
                  viewMode === 'list' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                ]"
              >
                <i class="fas fa-list mr-1"></i> List
              </button>
            </div>
          </div>

          <!-- Cards View -->
          <div v-if="viewMode === 'cards'" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
            <div 
              v-for="ro in filteredROs" 
              :key="ro.id"
              @click="selectRO(ro.id)"
              :class="[
                'p-2 border rounded-md cursor-pointer transition-all hover:shadow-sm',
                selectedRO === ro.id ? 'border-blue-500 bg-blue-50 ring-1 ring-blue-200' : 'border-gray-200 hover:border-blue-300'
              ]"
            >
              <div class="flex justify-between items-start mb-1">
                <div class="font-medium text-gray-900 text-xs truncate flex-1 mr-1">{{ ro.outlet?.nama_outlet || 'Unknown Outlet' }}</div>
                <span :class="getStatusBadgeClass(ro.status)" class="text-xs px-1 py-0.5 rounded flex-shrink-0">
                  {{ ro.status }}
                </span>
              </div>
              <div class="text-xs font-mono text-blue-600 mb-1">{{ ro.order_number }}</div>
                             <div class="text-xs text-gray-500 mb-1">
                 <div class="flex items-center mb-0.5">
                   <i class="fas fa-calendar mr-1 w-3"></i>
                   <span class="truncate">{{ formatDate(ro.tanggal) }}</span>
                 </div>
                 <div class="flex items-center">
                   <i class="fas fa-truck mr-1 w-3"></i>
                   <span class="truncate">Kedatangan: {{ ro.arrival_date ? formatDate(ro.arrival_date) : '-' }}</span>
                 </div>
               </div>
              <div class="text-xs text-gray-600 mb-1">
                <i class="fas fa-user mr-1 w-3"></i>
                <span class="truncate">{{ ro.user?.nama_lengkap || 'Unknown User' }}</span>
              </div>
              <div class="text-xs text-gray-500">
                <i class="fas fa-boxes mr-1 w-3"></i>
                {{ ro.items?.length || 0 }} items
              </div>
            </div>
          </div>

          <!-- List View -->
          <div v-else class="bg-white border border-gray-200 rounded-md overflow-hidden">
            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Outlet</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">RO</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tanggal</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Kedatangan</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Aksi</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <tr 
                    v-for="ro in filteredROs" 
                    :key="ro.id"
                    :class="[
                      'hover:bg-gray-50 cursor-pointer',
                      selectedRO === ro.id ? 'bg-blue-50' : ''
                    ]"
                    @click="selectRO(ro.id)"
                  >
                    <td class="px-3 py-2 whitespace-nowrap text-xs font-medium text-gray-900">
                      {{ ro.outlet?.nama_outlet || 'Unknown Outlet' }}
                    </td>
                    <td class="px-3 py-2 whitespace-nowrap text-xs font-mono text-blue-600">
                      {{ ro.order_number }}
                    </td>
                    <td class="px-3 py-2 whitespace-nowrap text-xs text-gray-500">
                      {{ formatDate(ro.tanggal) }}
                    </td>
                    <td class="px-3 py-2 whitespace-nowrap text-xs text-gray-500">
                      {{ ro.arrival_date ? formatDate(ro.arrival_date) : '-' }}
                    </td>
                    <td class="px-3 py-2 whitespace-nowrap">
                      <span :class="getStatusBadgeClass(ro.status)" class="text-xs px-1.5 py-0.5 rounded">
                        {{ ro.status }}
                      </span>
                    </td>
                    <td class="px-3 py-2 whitespace-nowrap text-xs text-gray-500">
                      <button 
                        @click.stop="selectRO(ro.id)"
                        class="text-blue-600 hover:text-blue-900 font-medium"
                      >
                        Pilih
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- No Results -->
        <div v-else class="text-center py-8">
          <i class="fas fa-search text-gray-400 text-2xl mb-2"></i>
          <h3 class="text-sm font-medium text-gray-900 mb-1">Tidak ada RO ditemukan</h3>
          <p class="text-xs text-gray-500">Coba ubah kata kunci pencarian atau filter status</p>
        </div>

        <!-- Selected RO Details -->
        <div v-if="selectedRO" class="bg-blue-50 border border-blue-200 rounded-md p-3">
          <h3 class="text-sm font-semibold text-blue-900 mb-2">RO Terpilih</h3>
          <div class="grid grid-cols-2 gap-3 text-xs">
            <div>
              <label class="block text-xs font-medium text-blue-700">Outlet</label>
              <p class="text-xs text-blue-900">{{ selectedROData?.outlet?.nama_outlet }}</p>
            </div>
            <div>
              <label class="block text-xs font-medium text-blue-700">Nomor RO</label>
              <p class="text-xs font-mono text-blue-900">{{ selectedROData?.order_number }}</p>
            </div>
            <div>
              <label class="block text-xs font-medium text-blue-700">Tanggal</label>
              <p class="text-xs text-blue-900">{{ formatDate(selectedROData?.tanggal) }}</p>
            </div>
            <div>
              <label class="block text-xs font-medium text-blue-700">Kedatangan</label>
              <p class="text-xs text-blue-900">{{ selectedROData?.arrival_date ? formatDate(selectedROData?.arrival_date) : '-' }}</p>
            </div>
            <div>
              <label class="block text-xs font-medium text-blue-700">Status</label>
              <p class="text-xs text-blue-900">{{ selectedROData?.status }}</p>
            </div>
            <div>
              <label class="block text-xs font-medium text-blue-700">Items</label>
              <p class="text-xs text-blue-900">{{ selectedROData?.items?.length || 0 }} items</p>
            </div>
          </div>
          <div class="mt-3 flex gap-2">
            <button 
              @click="clearSelection"
              class="px-3 py-1 text-xs font-medium text-blue-700 bg-white border border-blue-300 rounded hover:bg-blue-50"
            >
              Pilih RO Lain
            </button>
          </div>
        </div>
      </div>

      <!-- Step 2: Warehouse Division Selection -->
      <div v-if="selectedFO" id="warehouse-selection" class="mb-6">
        <h2 class="text-sm font-semibold text-gray-700 mb-3">2. Pilih Warehouse Division</h2>
        
        <!-- Detail Request Order -->
        <div class="bg-blue-50 border-l-4 border-blue-400 p-3 rounded mb-3">
          <div class="font-bold text-blue-800 text-sm mb-1">Detail RO</div>
          <div v-if="foDetail" class="text-xs space-y-1">
            <div><b>Outlet:</b> {{ foDetail.outlet?.nama_outlet }}</div>
            <div v-if="foDetail.warehouse_outlet && foDetail.warehouse_outlet.name"><b>Warehouse Outlet:</b> {{ foDetail.warehouse_outlet.name }}</div>
            <div><b>Tanggal:</b> {{ formatDate(foDetail.tanggal) }}</div>
            <div v-if="foDetail.arrival_date"><b>Kedatangan:</b> {{ formatDate(foDetail.arrival_date) }}</div>
            <div><b>RO Mode:</b> {{ foDetail.fo_mode }}</div>
            <div><b>Nomor:</b> {{ foDetail.order_number }}</div>
            <div><b>Creator:</b> {{ foDetail.user?.nama_lengkap || '-' }}</div>
          </div>
        </div>

        <!-- Warehouse Division Selection -->
        <div class="mb-3">
          <label class="block text-xs font-medium text-gray-700 mb-1">Warehouse Division</label>
          <select v-model="selectedDivision" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500">
            <option value="">Pilih Warehouse Division</option>
            <option v-for="div in props.warehouseDivisions" :key="div.id" :value="div.id">
              {{ div.name }}
            </option>
          </select>
        </div>
      </div>

      <!-- Step 3: Items Selection -->
      <div v-if="selectedFO && selectedDivision">
        <h2 class="text-sm font-semibold text-gray-700 mb-3">3. Pilih Items untuk Packing</h2>
        
        <div v-if="!loadingItems && items.length === 0" class="text-center text-gray-500 my-6">
          <p class="text-sm">Semua item di warehouse division ini sudah di-packing.</p>
        </div>
        
        <div v-if="loadingItems" class="text-center py-6">
          <i class="fas fa-spinner fa-spin text-blue-500 text-xl"></i>
          <p class="mt-2 text-sm text-gray-600">Memuat data item...</p>
        </div>
        
        <div v-if="error" class="text-red-600 mb-3 text-sm">{{ error }}</div>
        
        <div v-if="items.length">
          <!-- Info Total Items -->
          <div class="mb-3">
            <div class="text-sm text-gray-600">
              Total items: {{ items.filter(item => item.checked).length }} dari {{ items.length }}
            </div>
          </div>
          
          <div v-for="(catItems, catName) in itemsByCategory" :key="catName" class="mb-4">
            <div class="font-bold text-blue-700 text-sm mb-2">{{ catName }}</div>
            <div class="overflow-x-auto">
              <table class="w-full text-xs">
                <thead>
                  <tr class="bg-blue-50">
                    <th class="py-1 px-2 text-left">No</th>
                    <th class="py-1 px-2 text-left">Pilih</th>
                    <th class="py-1 px-2 text-left">Nama Item</th>
                    <th class="py-1 px-2 text-left">Qty Order</th>
                    <th class="py-1 px-2 text-left">Input Qty</th>
                    <th class="py-1 px-2 text-left">Unit</th>
                    <th class="py-1 px-2 text-left">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(item, idx) in catItems" :key="item.id" class="border-b">
                    <td class="py-1 px-2">{{ idx + 1 }}</td>
                    <td class="py-1 px-2"><input type="checkbox" v-model="item.checked" class="w-3 h-3" /></td>
                    <td class="py-1 px-2">{{ item.item?.name || item.item_name }}</td>
                    <td class="py-1 px-2">{{ item.qty ?? item.qty_order }}</td>
                    <td class="py-1 px-2">
                      <input type="number" v-model.number="item.input_qty" min="0" step="0.01" class="w-16 px-1 py-0.5 text-xs border border-gray-300 rounded text-right" :placeholder="'Qty'" />
                    </td>
                    <td class="py-1 px-2">{{ item.unit }}</td>
                    <td class="py-1 px-2">
                      <button 
                        @click="fillItemQuantity(item)"
                        class="text-blue-600 hover:text-blue-800 text-xs px-1 py-0.5 rounded hover:bg-blue-50"
                        title="Isi otomatis quantity packing sesuai quantity request"
                      >
                        <i class="fas fa-equals"></i>
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
        
        <div class="flex gap-2">
          <button @click="printPackingList" :disabled="!selectedFO || !selectedDivision || !items.length" class="flex-1 px-4 py-2 rounded bg-green-600 text-white font-semibold text-sm hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed">
            <i class="fas fa-print mr-2"></i> Print Packing List
          </button>
          <button @click="showSummaryModal" :disabled="!selectedFO || !selectedDivision || !items.length || isSubmitting" class="flex-1 px-4 py-2 rounded bg-blue-600 text-white font-semibold text-sm hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed">
            <i class="fas fa-save mr-2"></i> Submit Packing List
            <span v-if="isSubmitting" class="ml-2"><i class="fas fa-spinner fa-spin"></i></span>
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
/* Custom styles for summary modal */
:deep(.summary-modal-container) {
  z-index: 9999;
}

:deep(.summary-modal-container .swal2-popup) {
  font-size: 14px;
}

:deep(.summary-modal-container .swal2-title) {
  font-size: 18px;
  font-weight: 600;
}

:deep(.summary-modal-container .swal2-html-container) {
  margin: 0;
  padding: 0;
}

:deep(.summary-modal-container table) {
  border-collapse: collapse;
}

:deep(.summary-modal-container th),
:deep(.summary-modal-container td) {
  padding: 4px 8px;
  border: 1px solid #e5e7eb;
}

:deep(.summary-modal-container th) {
  background-color: #f3f4f6;
  font-weight: 600;
  font-size: 11px;
}

:deep(.summary-modal-container td) {
  font-size: 11px;
}

:deep(.summary-modal-container .max-h-40) {
  max-height: 160px;
}

:deep(.summary-modal-container .overflow-y-auto) {
  overflow-y: auto;
}

:deep(.summary-modal-container .overflow-y-auto::-webkit-scrollbar) {
  width: 6px;
}

:deep(.summary-modal-container .overflow-y-auto::-webkit-scrollbar-track) {
  background: #f1f1f1;
  border-radius: 3px;
}

:deep(.summary-modal-container .overflow-y-auto::-webkit-scrollbar-thumb) {
  background: #c1c1c1;
  border-radius: 3px;
}

:deep(.summary-modal-container .overflow-y-auto::-webkit-scrollbar-thumb:hover) {
  background: #a8a8a8;
}
</style>
