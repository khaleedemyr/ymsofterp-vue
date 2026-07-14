<script setup>
import { ref, computed, onMounted, watch, nextTick } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Swal from 'sweetalert2';
import CustomerModal from './CustomerModal.vue';

const props = defineProps({
  warehouses: Array,
  warehouseDivisions: Array,
  customers: Array,
});

const form = ref({
  customer_id: '',
  sale_date: new Date().toISOString().split('T')[0], // Default to today
  warehouse_id: '',
  warehouse_division_id: '',
  notes: '',
  items: [],
  total_amount: 0
});

const barcodeInput = ref('');
const itemSearchInput = ref('');
const showCustomerModal = ref(false);
const isSubmitting = ref(false);
const scannedItems = ref([]);
const searchResults = ref([]);
const showSearchResults = ref(false);
const selectedSearchIndex = ref(-1);

const serialMode = ref(false);
const serialInput = ref('');
const serialInputRef = ref(null);
const serialScanning = ref(false);
const serialFeedback = ref('');
const serialFeedbackSuccess = ref(false);
const scannedSerials = ref([]);

const filteredDivisions = computed(() => {
  if (!form.value.warehouse_id) return [];
  return props.warehouseDivisions.filter(div => div.warehouse_id == form.value.warehouse_id);
});

const totalAmount = computed(() => {
  const itemsTotal = form.value.items.reduce((total, item) => total + (item.subtotal || 0), 0);
  const serialTotal = scannedSerials.value.reduce((total, s) => total + (Number(s.subtotal) || 0), 0);
  return itemsTotal + serialTotal;
});

// Debounce function
let searchTimeout = null;

onMounted(() => {
  // Focus barcode input
  setTimeout(() => {
    document.getElementById('barcode-input')?.focus();
  }, 100);
});

// Watch for search input changes with debounce
watch(itemSearchInput, (newValue) => {
  if (searchTimeout) {
    clearTimeout(searchTimeout);
  }
  
  if (newValue && newValue.length >= 2) {
    searchTimeout = setTimeout(() => {
      searchItemsByName();
    }, 300); // 300ms debounce
  } else {
    searchResults.value = [];
    showSearchResults.value = false;
  }
});

async function scanBarcode() {
  if (!barcodeInput.value.trim()) return;
  
  if (!form.value.warehouse_id) {
    Swal.fire('Error', 'Pilih warehouse terlebih dahulu!', 'error');
    return;
  }

  try {
    const response = await fetch('/retail-warehouse-sale/search-items', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
      },
      body: JSON.stringify({
        barcode: barcodeInput.value,
        warehouse_id: form.value.warehouse_id
      })
    });

    const result = await response.json();
    
    if (!result.success) {
      Swal.fire('Error', result.message, 'error');
      barcodeInput.value = '';
      return;
    }

    const item = result.item;
    
    // Check if item already exists
    const existingItem = form.value.items.find(i => i.item_id === item.item_id);
    if (existingItem) {
      existingItem.qty += 1;
      existingItem.subtotal = existingItem.qty * existingItem.price;
    } else {
      // Add new item — default medium; harga dari backend sudah dikonversi ke medium
      const newItem = buildSaleItem(item, barcodeInput.value);
      form.value.items.push(newItem);
    }

    barcodeInput.value = '';
    document.getElementById('barcode-input')?.focus();

  } catch (error) {
    console.error('Error scanning barcode:', error);
    Swal.fire('Error', 'Terjadi kesalahan saat scan barcode', 'error');
  }
}

async function searchItemsByName() {
  if (!itemSearchInput.value.trim() || itemSearchInput.value.length < 2) {
    searchResults.value = [];
    showSearchResults.value = false;
    return;
  }
  
  if (!form.value.warehouse_id) {
    Swal.fire('Error', 'Pilih warehouse terlebih dahulu!', 'error');
    return;
  }

  try {
    const response = await fetch('/retail-warehouse-sale/search-items-by-name', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
      },
      body: JSON.stringify({
        search: itemSearchInput.value,
        warehouse_id: form.value.warehouse_id
      })
    });

    const result = await response.json();
    
    if (result.success) {
      searchResults.value = result.items;
      showSearchResults.value = true;
      selectedSearchIndex.value = -1;
    } else {
      searchResults.value = [];
      showSearchResults.value = false;
    }

  } catch (error) {
    console.error('Error searching items:', error);
    searchResults.value = [];
    showSearchResults.value = false;
  }
}

function selectSearchItem(item) {
  // Check if item already exists
  const existingItem = form.value.items.find(i => i.item_id === item.item_id);
  if (existingItem) {
    existingItem.qty += 1;
    existingItem.subtotal = existingItem.qty * existingItem.price;
  } else {
    form.value.items.push(buildSaleItem(item));
  }

  // Clear search
  itemSearchInput.value = '';
  searchResults.value = [];
  showSearchResults.value = false;
  selectedSearchIndex.value = -1;
  
  // Focus back to search input
  setTimeout(() => {
    document.getElementById('item-search-input')?.focus();
  }, 100);
}

function handleSearchKeydown(event) {
  if (!showSearchResults.value || searchResults.value.length === 0) return;

  switch (event.key) {
    case 'ArrowDown':
      event.preventDefault();
      selectedSearchIndex.value = Math.min(selectedSearchIndex.value + 1, searchResults.value.length - 1);
      break;
    case 'ArrowUp':
      event.preventDefault();
      selectedSearchIndex.value = Math.max(selectedSearchIndex.value - 1, -1);
      break;
    case 'Enter':
      event.preventDefault();
      if (selectedSearchIndex.value >= 0 && selectedSearchIndex.value < searchResults.value.length) {
        selectSearchItem(searchResults.value[selectedSearchIndex.value]);
      }
      break;
    case 'Escape':
      showSearchResults.value = false;
      selectedSearchIndex.value = -1;
      break;
  }
}

function hideSearchResults() {
  // Delay to allow click events to fire
  setTimeout(() => {
    showSearchResults.value = false;
    selectedSearchIndex.value = -1;
  }, 200);
}

function updateItemSubtotal(index) {
  const item = form.value.items[index];
  item.subtotal = item.qty * item.price;
}

function buildSaleItem(item, barcode = '') {
  return {
    item_id: item.item_id,
    item_name: item.item_name,
    barcode: barcode || '',
    qty: 1,
    unit_size: 'medium',
    unit: item.unit_medium,
    price: item.price || 0,
    subtotal: item.price || 0,
    stock: {
      small: item.qty_small || 0,
      medium: item.qty_medium || 0,
      large: item.qty_large || 0
    },
    units: {
      small: item.unit_small,
      medium: item.unit_medium,
      large: item.unit_large
    },
    unit_ids: {
      small: item.small_unit_id,
      medium: item.medium_unit_id,
      large: item.large_unit_id
    }
  };
}

async function onUnitChange(index) {
  const item = form.value.items[index];
  const size = item.unit_size || 'medium';
  item.unit = item.units?.[size] || item.unit;
  const unitId = item.unit_ids?.[size] || null;
  item.price = await fetchItemPrice(item.item_id, unitId, size);
  updateItemSubtotal(index);
}

function removeItem(index) {
  form.value.items.splice(index, 1);
}

function searchCustomers() {
  showCustomerModal.value = true;
}

function onCustomerSelected(customer) {
  form.value.customer_id = customer.id;
  showCustomerModal.value = false;
}

function onCustomerCreated(customer) {
  form.value.customer_id = customer.id;
  showCustomerModal.value = false;
}

function playBeep(success) {
  try {
    const ctx = new (window.AudioContext || window.webkitAudioContext)();
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    osc.connect(gain);
    gain.connect(ctx.destination);
    osc.frequency.value = success ? 880 : 300;
    osc.type = success ? 'sine' : 'square';
    gain.gain.value = 0.3;
    osc.start();
    osc.stop(ctx.currentTime + (success ? 0.12 : 0.25));
  } catch (e) {}
}

async function onSerialScan() {
  const input = serialInput.value.trim();
  if (!input) return;

  if (!form.value.warehouse_id) {
    serialFeedback.value = 'Pilih warehouse dulu';
    serialFeedbackSuccess.value = false;
    playBeep(false);
    return;
  }

  if (scannedSerials.value.some(s => s.serial_number === input)) {
    serialFeedback.value = `Serial "${input}" sudah discan`;
    serialFeedbackSuccess.value = false;
    playBeep(false);
    serialInput.value = '';
    return;
  }

  serialScanning.value = true;
  try {
    const response = await fetch('/retail-warehouse-sale/validate-serial', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
      },
      body: JSON.stringify({
        serial_number: input,
        warehouse_id: form.value.warehouse_id,
      })
    });
    const res = await response.json();

    if (res.valid) {
      const serial = res.serial;
      scannedSerials.value.push({
        serial_id: serial.id,
        serial_number: serial.serial_number,
        item_id: serial.item_id,
        item_name: serial.item_name,
        unit_id: serial.unit_id,
        unit_name: serial.unit_name,
        qty: serial.qty,
        qty_small: serial.qty_small,
        price: serial.price,
        subtotal: serial.subtotal,
      });
      serialFeedback.value = `Serial "${input}" valid`;
      serialFeedbackSuccess.value = true;
      playBeep(true);
    } else {
      serialFeedback.value = res.message || 'Serial tidak valid';
      serialFeedbackSuccess.value = false;
      playBeep(false);
    }
  } catch (e) {
    serialFeedback.value = 'Gagal validasi serial';
    serialFeedbackSuccess.value = false;
    playBeep(false);
  } finally {
    serialScanning.value = false;
    serialInput.value = '';
    nextTick(() => serialInputRef.value?.focus());
  }
}

function removeSerial(idx) {
  scannedSerials.value.splice(idx, 1);
}

function updateSerialSubtotal(idx) {
  const s = scannedSerials.value[idx];
  s.subtotal = (Number(s.qty) || 0) * (Number(s.price) || 0);
}

function handleSerialKeyPress(event) {
  if (event.key === 'Enter') {
    event.preventDefault();
    onSerialScan();
  }
}

async function submitForm() {
  if (!form.value.customer_id) {
    Swal.fire('Error', 'Pilih customer terlebih dahulu!', 'error');
    return;
  }

  if (!form.value.warehouse_id) {
    Swal.fire('Error', 'Pilih warehouse terlebih dahulu!', 'error');
    return;
  }

  const hasItems = form.value.items.length > 0;
  const hasSerials = scannedSerials.value.length > 0;

  if (!hasItems && !hasSerials) {
    Swal.fire('Error', 'Minimal harus ada 1 item (qty) atau 1 nomor seri!', 'error');
    return;
  }

  if (hasItems) {
    const invalidItems = form.value.items.filter(item => !item.price || item.price <= 0);
    if (invalidItems.length > 0) {
      Swal.fire('Error', 'Semua item harus memiliki harga!', 'error');
      return;
    }
  }

  if (hasSerials) {
    const invalidSerials = scannedSerials.value.filter(s => !s.price || s.price <= 0);
    if (invalidSerials.length > 0) {
      Swal.fire('Error', 'Semua serial harus memiliki harga!', 'error');
      return;
    }
  }

  isSubmitting.value = true;

  try {
    const payload = {
      ...form.value,
      items: hasItems ? form.value.items : [],
      serial_items: hasSerials ? scannedSerials.value.map(s => ({
        serial_id: s.serial_id,
        serial_number: s.serial_number,
        item_id: s.item_id,
        unit_id: s.unit_id,
        unit_name: s.unit_name,
        qty: s.qty,
        qty_small: s.qty_small,
        price: s.price,
        subtotal: s.subtotal,
      })) : [],
      total_amount: totalAmount.value,
    };

    const response = await fetch('/retail-warehouse-sale', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
      },
      body: JSON.stringify(payload)
    });

    const result = await response.json();
    
    if (result.success) {
      Swal.fire({
        title: 'Berhasil!',
        text: result.message,
        icon: 'success',
        confirmButtonText: 'OK'
      }).then(() => {
        router.visit(route('retail-warehouse-sale.index'));
      });
    } else {
      Swal.fire('Error', result.message, 'error');
    }
  } catch (error) {
    console.error('Error submitting form:', error);
    Swal.fire('Error', 'Terjadi kesalahan saat menyimpan data', 'error');
  } finally {
    isSubmitting.value = false;
  }
}

function handleKeyPress(event) {
  if (event.key === 'Enter') {
    scanBarcode();
  }
}

async function fetchItemPrice(itemId, unitId = null, unitSize = null) {
  try {
    const params = new URLSearchParams({ item_id: itemId });
    if (unitId) params.set('unit_id', unitId);
    if (unitSize) params.set('unit_size', unitSize);

    const response = await fetch(`/api/retail-warehouse-sale/item-price?${params.toString()}`, {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
      }
    });

    const result = await response.json();
    return result.price || 0;
  } catch (error) {
    console.error('Error fetching price:', error);
    return 0;
  }
}

async function refreshItemPrice(index) {
  const item = form.value.items[index];
  const size = item.unit_size || 'medium';
  const unitId = item.unit_ids?.[size] || null;
  const price = await fetchItemPrice(item.item_id, unitId, size);
  item.price = price;
  updateItemSubtotal(index);
}
</script>

<template>
  <AppLayout>
    <div class="max-w-7xl w-full mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-store text-blue-500"></i>
          Buat Penjualan Warehouse Retail
        </h1>
        <button @click="router.visit(route('retail-warehouse-sale.index'))" class="bg-gray-500 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-xl transition-all font-semibold">
          Kembali
        </button>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Form Section -->
        <div class="lg:col-span-1">
          <div class="bg-white rounded-2xl shadow-2xl p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Informasi Penjualan</h2>
            
            <!-- Customer Selection -->
            <div class="mb-4">
              <label class="block text-sm font-medium text-gray-700 mb-2">Customer</label>
              <select v-model="form.customer_id" class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                <option value="">Pilih Customer</option>
                <option v-for="customer in customers" :key="customer.id" :value="customer.id">
                  {{ customer.name }} ({{ customer.code }})
                </option>
              </select>
              <button @click="searchCustomers" type="button" class="mt-2 w-full px-3 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors">
                <i class="fa-solid fa-plus mr-2"></i>
                Tambah Customer Baru
              </button>
            </div>

            <!-- Sale Date -->
            <div class="mb-4">
              <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Penjualan</label>
              <input 
                v-model="form.sale_date" 
                type="date" 
                class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
              />
            </div>

            <!-- Warehouse Selection -->
            <div class="mb-4">
              <label class="block text-sm font-medium text-gray-700 mb-2">Warehouse</label>
              <select v-model="form.warehouse_id" class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                <option value="">Pilih Warehouse</option>
                <option v-for="warehouse in warehouses" :key="warehouse.id" :value="warehouse.id">
                  {{ warehouse.name }}
                </option>
              </select>
            </div>

            <!-- Division Selection -->
            <div class="mb-4">
              <label class="block text-sm font-medium text-gray-700 mb-2">Division (Opsional)</label>
              <select v-model="form.warehouse_division_id" class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                <option value="">Pilih Division</option>
                <option v-for="division in filteredDivisions" :key="division.id" :value="division.id">
                  {{ division.name }}
                </option>
              </select>
            </div>

            <!-- Notes -->
            <div class="mb-4">
              <label class="block text-sm font-medium text-gray-700 mb-2">Catatan</label>
              <textarea v-model="form.notes" rows="3" class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"></textarea>
            </div>

            <!-- Mode Serial -->
            <div class="mb-4 border rounded-lg p-4" :class="serialMode ? 'border-indigo-300 bg-indigo-50/30' : 'border-gray-200'">
              <label class="flex items-center justify-between cursor-pointer">
                <span class="text-sm font-medium text-gray-700">
                  <i class="fa-solid fa-qrcode mr-1 text-indigo-500"></i>
                  Mode Nomor Seri
                </span>
                <input type="checkbox" v-model="serialMode" class="sr-only peer">
                <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:bg-indigo-500 relative after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full"></div>
              </label>
              <div v-if="serialMode" class="mt-3 space-y-2">
                <input
                  ref="serialInputRef"
                  v-model="serialInput"
                  @keypress="handleSerialKeyPress"
                  type="text"
                  placeholder="Scan nomor seri..."
                  class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                  :disabled="serialScanning"
                />
                <p v-if="serialFeedback" class="text-sm" :class="serialFeedbackSuccess ? 'text-green-600' : 'text-red-600'">
                  {{ serialFeedback }}
                </p>
              </div>
            </div>

            <!-- Barcode Scanner -->
            <div class="mb-4">
              <label class="block text-sm font-medium text-gray-700 mb-2">Scan Barcode</label>
              <div class="flex gap-2">
                <input
                  id="barcode-input"
                  v-model="barcodeInput"
                  @keypress="handleKeyPress"
                  type="text"
                  placeholder="Scan barcode item..."
                  class="flex-1 rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                />
                <button @click="scanBarcode" type="button" class="px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600">
                  <i class="fa-solid fa-barcode"></i>
                </button>
              </div>
            </div>

            <!-- Search by Name -->
            <div class="mb-4 relative">
              <label class="block text-sm font-medium text-gray-700 mb-2">Cari Nama Barang</label>
              <div class="relative">
                <input
                  id="item-search-input"
                  v-model="itemSearchInput"
                  @keydown="handleSearchKeydown"
                  @blur="hideSearchResults"
                  type="text"
                  placeholder="Ketik nama barang..."
                  class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                />
                <div class="absolute inset-y-0 right-0 flex items-center pr-3">
                  <i class="fa-solid fa-search text-gray-400"></i>
                </div>
              </div>
              
              <!-- Autocomplete Results -->
              <div 
                v-if="showSearchResults && searchResults.length > 0" 
                class="absolute z-50 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg max-h-60 overflow-y-auto"
              >
                <div
                  v-for="(item, index) in searchResults"
                  :key="item.item_id"
                  @click="selectSearchItem(item)"
                  :class="[
                    'px-4 py-3 cursor-pointer border-b border-gray-100 hover:bg-blue-50 transition-colors',
                    selectedSearchIndex === index ? 'bg-blue-100' : ''
                  ]"
                >
                  <div class="flex justify-between items-start">
                    <div class="flex-1">
                      <h4 class="font-semibold text-gray-800">{{ item.item_name }}</h4>
                      <div class="text-sm text-gray-500 mt-1">
                        <span class="inline-block mr-4">Stok: {{ item.qty_medium || 0 }} {{ item.unit_medium }}</span>
                        <span class="inline-block">Harga: {{ new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(item.price) }}</span>
                      </div>
                    </div>
                    <div class="text-right">
                      <span class="text-xs text-gray-400">Enter untuk pilih</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Submit Button -->
            <button 
              @click="submitForm" 
              :disabled="isSubmitting"
              class="w-full bg-gradient-to-r from-blue-500 to-blue-700 text-white py-3 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold disabled:opacity-60"
            >
              <span v-if="isSubmitting">Menyimpan...</span>
              <span v-else>Simpan Penjualan</span>
            </button>
          </div>
        </div>

        <!-- Items Section -->
        <div class="lg:col-span-2">
          <div class="bg-white rounded-2xl shadow-2xl p-6">
            <div class="flex justify-between items-center mb-4">
              <h2 class="text-xl font-bold text-gray-800">Item Penjualan</h2>
              <div class="text-lg font-bold text-blue-600">
                Total: {{ new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(totalAmount) }}
              </div>
            </div>

            <div v-if="scannedSerials.length > 0" class="mb-6">
              <h3 class="text-sm font-semibold text-indigo-700 mb-2">Nomor Seri ({{ scannedSerials.length }})</h3>
              <div class="space-y-3">
                <div v-for="(s, sIdx) in scannedSerials" :key="s.serial_number" class="border border-indigo-200 rounded-lg p-4 bg-indigo-50/20">
                  <div class="flex justify-between items-start mb-2">
                    <div>
                      <p class="font-mono font-semibold text-indigo-800">{{ s.serial_number }}</p>
                      <p class="text-sm text-gray-600">{{ s.item_name }}</p>
                      <p class="text-xs text-gray-500">{{ s.qty }} {{ s.unit_name }}</p>
                    </div>
                    <button @click="removeSerial(sIdx)" type="button" class="text-red-500 hover:text-red-700">
                      <i class="fa-solid fa-trash"></i>
                    </button>
                  </div>
                  <div class="grid grid-cols-2 gap-3">
                    <div>
                      <label class="block text-xs text-gray-500 mb-1">Harga</label>
                      <input v-model.number="s.price" @input="updateSerialSubtotal(sIdx)" type="number" min="0" step="100" class="w-full rounded border-gray-300 text-sm" />
                    </div>
                    <div class="text-right flex items-end justify-end">
                      <span class="font-semibold text-indigo-600 text-sm">
                        {{ new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(s.subtotal) }}
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div v-if="form.items.length === 0 && scannedSerials.length === 0" class="text-center py-10 text-gray-400">
              <i class="fa-solid fa-barcode text-4xl mb-4"></i>
              <p>Scan barcode atau nomor seri untuk menambahkan item</p>
            </div>

            <div v-if="form.items.length > 0" class="space-y-4">
              <div v-for="(item, index) in form.items" :key="index" class="border border-gray-200 rounded-lg p-4">
                <div class="flex justify-between items-start mb-3">
                  <div class="flex-1">
                    <h3 class="font-semibold text-gray-800">{{ item.item_name }}</h3>
                    <p class="text-sm text-gray-500">Barcode: {{ item.barcode }}</p>
                    <p class="text-sm text-gray-500">Stok: {{ item.stock.medium }} {{ item.units.medium }}</p>
                  </div>
                  <button @click="removeItem(index)" class="text-red-500 hover:text-red-700">
                    <i class="fa-solid fa-trash"></i>
                  </button>
                </div>

                <div class="grid grid-cols-3 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Qty</label>
                    <input 
                      v-model.number="item.qty" 
                      @input="updateItemSubtotal(index)"
                      type="number" 
                      min="1"
                      class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                    />
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Unit</label>
                    <select 
                      v-model="item.unit_size" 
                      @change="onUnitChange(index)"
                      class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                    >
                      <option value="small">{{ item.units.small }}</option>
                      <option value="medium">{{ item.units.medium }}</option>
                      <option value="large">{{ item.units.large }}</option>
                    </select>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Harga</label>
                    <div class="flex gap-2">
                      <input 
                        v-model.number="item.price" 
                        @input="updateItemSubtotal(index)"
                        type="number" 
                        min="0"
                        step="100"
                        class="flex-1 rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                      />
                      <button 
                        @click="refreshItemPrice(index)" 
                        type="button"
                        class="px-3 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600"
                        title="Refresh harga dari sistem"
                      >
                        <i class="fa-solid fa-sync-alt"></i>
                      </button>
                    </div>
                  </div>
                </div>

                <div class="mt-3 text-right">
                  <span class="font-semibold text-blue-600">
                    Subtotal: {{ new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(item.subtotal) }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Customer Modal -->
      <CustomerModal
        :show="showCustomerModal"
        @close="showCustomerModal = false"
        @customer-selected="onCustomerSelected"
        @customer-created="onCustomerCreated"
      />
    </div>
  </AppLayout>
</template> 