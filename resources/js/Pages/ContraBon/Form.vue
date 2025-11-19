<script setup>
import { ref, computed, onMounted } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Swal from 'sweetalert2';
import axios from 'axios';

const props = defineProps({
  contraBon: Object, // for edit, can be null for create
});

const isEdit = computed(() => !!props.contraBon);

const poWithGRList = ref([]);
const selectedPOGR = ref(null);
const loadingPOGR = ref(false);
const selectedPOGRKey = ref('');
const supplierDetail = ref(null);
const fileImage = ref(null);
const fileImagePreview = ref(null);

// Retail Food variables
const retailFoodList = ref([]);
const selectedRetailFood = ref(null);
const selectedRetailFoodKey = ref('');

// Warehouse Retail Food variables
const warehouseRetailFoodList = ref([]);
const selectedWarehouseRetailFood = ref(null);
const selectedWarehouseRetailFoodKey = ref('');

// Loading state untuk initial load
const initialLoading = ref(true);

const sourceType = ref('purchase_order'); // 'purchase_order', 'retail_food', or 'warehouse_retail_food'

// Modal search functionality
const showPOListModal = ref(false);
const showRetailFoodModal = ref(false);
const showWarehouseRetailFoodModal = ref(false);
const poSearchQuery = ref('');
const retailFoodSearchQuery = ref('');
const warehouseRetailFoodSearchQuery = ref('');
const filteredPOList = ref([]);
const filteredRetailFoodList = ref([]);
const filteredWarehouseRetailFoodList = ref([]);

const form = useForm({
  date: props.contraBon?.date ? props.contraBon.date.substring(0, 10) : '',
  po_id: props.contraBon?.po_id || '',
  gr_id: props.contraBon?.gr_id || '',
  notes: props.contraBon?.notes || '',
  supplier_invoice_number: props.contraBon?.supplier_invoice_number || '',
  source_type: props.contraBon?.source_type || 'purchase_order',
  source_id: props.contraBon?.source_id || '',
  items: props.contraBon?.items?.map(i => ({
    gr_item_id: i.gr_item_id,
    item_id: i.item_id,
    quantity: i.quantity,
    unit_id: i.unit_id,
    price: i.price,
    discount_percent: i.discount_percent || 0,
    discount_amount: i.discount_amount || 0,
    po_item_total: i.po_item_total || null,
    notes: i.notes || '',
    item: i.item,
    unit: i.unit,
    selected: true, // Default selected untuk edit mode
    _rowKey: Date.now() + '-' + Math.random(),
  })) || [],
  selectedItems: [], // Array untuk menyimpan item yang dicentang
});

onMounted(async () => {
  if (!isEdit.value) {
    // Set default date ke hari ini
    const today = new Date();
    const yyyy = today.getFullYear();
    const mm = String(today.getMonth() + 1).padStart(2, '0');
    const dd = String(today.getDate()).padStart(2, '0');
    form.date = `${yyyy}-${mm}-${dd}`;
    
    // Load semua data secara parallel untuk mempercepat loading
    initialLoading.value = true;
    
    try {
      // Load semua data secara bersamaan (parallel)
      const [poRes, retailFoodRes, warehouseRetailFoodRes] = await Promise.all([
        axios.get('/api/contra-bon/po-with-approved-gr').catch(e => {
          console.error('Error loading PO/GR:', e);
          return { data: [] };
        }),
        axios.get('/api/contra-bon/retail-food-contra-bon').catch(e => {
          console.error('Error loading retail food:', e);
          return { data: [] };
        }),
        axios.get('/api/contra-bon/warehouse-retail-food-contra-bon').catch(e => {
          console.error('Error loading warehouse retail food:', e);
          return { data: [] };
        })
      ]);
      
      poWithGRList.value = poRes.data || [];
      retailFoodList.value = retailFoodRes.data || [];
      warehouseRetailFoodList.value = warehouseRetailFoodRes.data || [];
      
      // Initialize filtered lists
      filteredPOList.value = poWithGRList.value;
      filteredRetailFoodList.value = retailFoodList.value;
      filteredWarehouseRetailFoodList.value = warehouseRetailFoodList.value;
      
    } catch (e) {
      console.error('Error loading data:', e);
      Swal.fire('Error', 'Gagal mengambil data', 'error');
    } finally {
      initialLoading.value = false;
    }
  } else {
    // Mode edit: set preview image jika ada
    if (props.contraBon?.image_path) {
      fileImagePreview.value = `/storage/${props.contraBon.image_path}`;
    }
    initialLoading.value = false;
  }
});

// Filter functions for modal search
function filterPOList() {
  if (!poSearchQuery.value.trim()) {
    filteredPOList.value = poWithGRList.value;
    return;
  }
  
  const query = poSearchQuery.value.toLowerCase();
  filteredPOList.value = poWithGRList.value.filter(p => 
    p.po_number.toLowerCase().includes(query) ||
    p.gr_number.toLowerCase().includes(query) ||
    p.supplier_name.toLowerCase().includes(query) ||
    (p.outlet_names && p.outlet_names.some(outlet => outlet.toLowerCase().includes(query)))
  );
}

function filterRetailFoodList() {
  if (!retailFoodSearchQuery.value.trim()) {
    filteredRetailFoodList.value = retailFoodList.value;
    return;
  }
  
  const query = retailFoodSearchQuery.value.toLowerCase();
  filteredRetailFoodList.value = retailFoodList.value.filter(rf => 
    rf.retail_number.toLowerCase().includes(query) ||
    rf.supplier_name.toLowerCase().includes(query) ||
    (rf.outlet_name && rf.outlet_name.toLowerCase().includes(query)) ||
    (rf.warehouse_outlet_name && rf.warehouse_outlet_name.toLowerCase().includes(query))
  );
}

function filterWarehouseRetailFoodList() {
  if (!warehouseRetailFoodSearchQuery.value.trim()) {
    filteredWarehouseRetailFoodList.value = warehouseRetailFoodList.value;
    return;
  }
  
  const query = warehouseRetailFoodSearchQuery.value.toLowerCase();
  filteredWarehouseRetailFoodList.value = warehouseRetailFoodList.value.filter(rwf => 
    rwf.retail_number.toLowerCase().includes(query) ||
    rwf.supplier_name.toLowerCase().includes(query) ||
    (rwf.warehouse_name && rwf.warehouse_name.toLowerCase().includes(query)) ||
    (rwf.warehouse_division_name && rwf.warehouse_division_name.toLowerCase().includes(query))
  );
}

// Modal functions
function openPOListModal() {
  showPOListModal.value = true;
  poSearchQuery.value = '';
  filteredPOList.value = poWithGRList.value;
}

function openRetailFoodModal() {
  showRetailFoodModal.value = true;
  retailFoodSearchQuery.value = '';
  filteredRetailFoodList.value = retailFoodList.value;
}

function openWarehouseRetailFoodModal() {
  showWarehouseRetailFoodModal.value = true;
  warehouseRetailFoodSearchQuery.value = '';
  filteredWarehouseRetailFoodList.value = warehouseRetailFoodList.value;
}

function closePOListModal() {
  showPOListModal.value = false;
  poSearchQuery.value = '';
}

function closeRetailFoodModal() {
  showRetailFoodModal.value = false;
  retailFoodSearchQuery.value = '';
}

function closeWarehouseRetailFoodModal() {
  showWarehouseRetailFoodModal.value = false;
  warehouseRetailFoodSearchQuery.value = '';
}

function selectPOFromModal(po) {
  selectedPOGR.value = po;
  selectedPOGRKey.value = `${po.po_id}-${po.gr_id}`;
  form.po_id = po.po_id;
  form.gr_id = po.gr_id;
  
  if (po.items) {
    form.items = po.items.map(item => ({
      gr_item_id: item.id,
      item_id: item.item_id,
      po_item_id: item.po_item_id,
      unit_id: item.unit_id,
      quantity: item.qty_received,
      price: item.po_price,
      discount_percent: item.discount_percent || 0,
      discount_amount: item.discount_amount || 0,
      po_item_total: item.po_item_total || (item.po_price * item.qty_received),
      notes: '',
      selected: false, // Default tidak dicentang
      _rowKey: Date.now() + '-' + Math.random(),
    }));
  } else {
    form.items = [];
  }
  
  // Fetch supplier detail
  if (po.supplier_id) {
    axios.get(`/api/suppliers/${po.supplier_id}`)
      .then(res => supplierDetail.value = res.data)
      .catch(() => supplierDetail.value = null);
  } else {
    supplierDetail.value = null;
  }
  
  closePOListModal();
}

function selectRetailFoodFromModal(rf) {
  selectedRetailFood.value = rf;
  selectedRetailFoodKey.value = rf.retail_food_id;
  
  if (rf.items) {
    form.items = rf.items.map(item => ({
      gr_item_id: null,
      item_id: null,
      po_item_id: null,
      unit_id: null,
      quantity: item.qty,
      price: item.price,
      notes: '',
      item_name: item.item_name,
      unit_name: item.unit_name,
      retail_food_item_id: item.id, // Simpan ID item dari retail food
      selected: false, // Default tidak dicentang
      _rowKey: Date.now() + '-' + Math.random(),
    }));
  } else {
    form.items = [];
  }
  
  // Fetch supplier detail
  if (rf.supplier_id) {
    axios.get(`/api/suppliers/${rf.supplier_id}`)
      .then(res => supplierDetail.value = res.data)
      .catch(() => supplierDetail.value = null);
  } else {
    supplierDetail.value = null;
  }
  
  closeRetailFoodModal();
}

function selectWarehouseRetailFoodFromModal(rwf) {
  selectedWarehouseRetailFood.value = rwf;
  selectedWarehouseRetailFoodKey.value = rwf.retail_warehouse_food_id;
  
  if (rwf.items) {
    form.items = rwf.items.map(item => ({
      gr_item_id: null,
      item_id: null,
      po_item_id: null,
      unit_id: null,
      quantity: item.qty,
      price: item.price,
      notes: '',
      item_name: item.item_name,
      unit_name: item.unit_name,
      warehouse_retail_food_item_id: item.id, // Simpan ID item dari warehouse retail food
      selected: false, // Default tidak dicentang
      _rowKey: Date.now() + '-' + Math.random(),
    }));
  } else {
    form.items = [];
  }
  
  // Fetch supplier detail
  if (rwf.supplier_id) {
    axios.get(`/api/suppliers/${rwf.supplier_id}`)
      .then(res => supplierDetail.value = res.data)
      .catch(() => supplierDetail.value = null);
  } else {
    supplierDetail.value = null;
  }
  
  closeWarehouseRetailFoodModal();
}

async function onPOGRChange() {
  if (!selectedPOGRKey.value) {
    selectedPOGR.value = null;
    form.po_id = '';
    form.gr_id = '';
    form.items = [];
    supplierDetail.value = null;
    fileImage.value = null;
    fileImagePreview.value = null;
    poSearchQuery.value = '';
    return;
  }
  const sepIdx = selectedPOGRKey.value.lastIndexOf('-');
  const poId = selectedPOGRKey.value.substring(0, sepIdx);
  const grId = selectedPOGRKey.value.substring(sepIdx + 1);
  form.po_id = poId;
  form.gr_id = grId;
  const pogr = poWithGRList.value.find(p => String(p.po_id) === poId && String(p.gr_id) === grId);
  selectedPOGR.value = pogr;
  if (pogr) {
    poSearchQuery.value = `${pogr.po_number} - ${pogr.gr_number} - ${pogr.supplier_name}`;
    form.items = pogr.items.map(item => ({
      gr_item_id: item.id,
      item_id: item.item_id,
      po_item_id: item.po_item_id,
      unit_id: item.unit_id,
      quantity: item.qty_received,
      price: item.po_price,
      notes: '',
      selected: false, // Default tidak dicentang
      _rowKey: Date.now() + '-' + Math.random(),
    }));
    // Fetch supplier detail
    if (pogr.supplier_id) {
      try {
        const res = await axios.get(`/api/suppliers/${pogr.supplier_id}`);
        supplierDetail.value = res.data;
      } catch (e) {
        supplierDetail.value = null;
      }
    } else {
      supplierDetail.value = null;
    }
  } else {
    form.items = [];
    supplierDetail.value = null;
  }
}


async function onRetailFoodChange() {
  if (!selectedRetailFoodKey.value) {
    selectedRetailFood.value = null;
    form.po_id = '';
    form.gr_id = '';
    form.items = [];
    supplierDetail.value = null;
    fileImage.value = null;
    fileImagePreview.value = null;
    return;
  }
  
  // Data sudah dimuat di onMounted, tidak perlu loading spinner
  try {
    const retailFood = retailFoodList.value.find(rf => 
      rf.retail_food_id == selectedRetailFoodKey.value
    );
    
    selectedRetailFood.value = retailFood;
    
    if (retailFood) {
      form.items = retailFood.items.map(item => ({
        gr_item_id: null,
        item_id: null, // Tidak ada item_id karena menggunakan item_name
        po_item_id: null,
        unit_id: null, // Tidak ada unit_id karena menggunakan unit string
        quantity: item.qty,
        price: item.price,
        notes: '',
        item_name: item.item_name,
        unit_name: item.unit_name,
        retail_food_item_id: item.id, // Simpan ID item dari retail food
        selected: false, // Default tidak dicentang
        _rowKey: Date.now() + '-' + Math.random(),
      }));
    
      // Fetch supplier detail
      if (retailFood.supplier_id) {
        try {
          const res = await axios.get(`/api/suppliers/${retailFood.supplier_id}`);
          supplierDetail.value = res.data;
        } catch (e) {
          supplierDetail.value = null;
        }
      } else {
        supplierDetail.value = null;
      }
    } else {
      form.items = [];
      supplierDetail.value = null;
    }
  } catch (error) {
    console.error('Error in onRetailFoodChange:', error);
    Swal.fire('Error', 'Gagal memuat data retail food', 'error');
  }
}

function onSourceTypeChange() {
  // Reset selections when switching source type
  selectedPOGRKey.value = '';
  selectedRetailFoodKey.value = '';
  selectedWarehouseRetailFoodKey.value = '';
  selectedPOGR.value = null;
  selectedRetailFood.value = null;
  selectedWarehouseRetailFood.value = null;
  form.po_id = '';
  form.gr_id = '';
  form.items = [];
  supplierDetail.value = null;
  fileImage.value = null;
  fileImagePreview.value = null;
}

function onFileChange(e) {
  const file = e.target.files[0];
  fileImage.value = file;
  if (file) {
    const reader = new FileReader();
    reader.onload = (ev) => {
      fileImagePreview.value = ev.target.result;
    };
    reader.readAsDataURL(file);
  } else {
    fileImagePreview.value = null;
  }
}

function formatCurrency(value) {
  if (value == null) return '-';
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(value);
}

// Computed untuk menghitung total dari item yang dicentang
const totalAmount = computed(() => {
  return form.items
    .filter(item => item.selected)
    .reduce((sum, item) => sum + (item.quantity * (item.price || 0)), 0);
});

// Function to update item total when price changes
function updateItemTotal(item) {
  // Ensure price is a valid number
  if (item.price === null || item.price === undefined || item.price === '') {
    item.price = 0;
  } else {
    // Convert to number if it's a string
    const numPrice = parseFloat(item.price);
    // Ensure price is not negative
    item.price = numPrice >= 0 ? numPrice : 0;
  }
}

async function onSubmit() {
  // Validasi: minimal harus ada 1 item yang dicentang
  const selectedItems = form.items.filter(item => item.selected);
  if (selectedItems.length === 0) {
    Swal.fire('Peringatan', 'Pilih minimal 1 item untuk dibuat contra bon', 'warning');
    return;
  }

  const loadingSwal = Swal.fire({
    title: isEdit.value ? 'Menyimpan Perubahan...' : 'Menyimpan Data...',
    allowOutsideClick: false,
    showConfirmButton: false,
    didOpen: () => Swal.showLoading(),
  });
  // Kirim pakai FormData jika ada file
  if (fileImage.value) {
    const fd = new FormData();
    fd.append('date', form.date);
    fd.append('po_id', form.po_id);
    fd.append('gr_id', form.gr_id);
    fd.append('notes', form.notes);
    fd.append('supplier_invoice_number', form.supplier_invoice_number);
      fd.append('source_type', sourceType.value);
      if (sourceType.value === 'retail_food') {
        fd.append('source_id', selectedRetailFoodKey.value);
      } else if (sourceType.value === 'warehouse_retail_food') {
        fd.append('source_id', selectedWarehouseRetailFoodKey.value);
      } else {
        fd.append('source_id', '');
      }
      fd.append('image', fileImage.value);
    // Hanya kirim item yang dicentang
    selectedItems.forEach((item, idx) => {
      Object.keys(item).forEach(key => {
        // Jangan kirim field selected dan _rowKey
        if (key !== 'selected' && key !== '_rowKey') {
          fd.append(`items[${idx}][${key}]`, item[key]);
        }
      });
    });
    try {
      const url = isEdit.value ? `/contra-bons/${props.contraBon.id}` : '/contra-bons';
      const method = isEdit.value ? 'post' : 'post';
      const config = { 
        headers: { 
          'Content-Type': 'multipart/form-data',
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        } 
      };
      let res;
      if (isEdit.value) {
        fd.append('_method', 'PUT');
        res = await axios.post(url, fd, config);
      } else {
        res = await axios.post(url, fd, config);
      }
      
      // Check if response indicates success
      if (res.status === 200 || res.status === 201) {
        if (res.data && res.data.success === false) {
          throw new Error(res.data.message || 'Gagal menyimpan data');
        }
        // Close loading Swal first
        await Swal.close();
        
        // Success - check if we got JSON response or redirect
        if (res.data && res.data.success === true) {
          await Swal.fire('Berhasil', res.data.message || 'Data berhasil disimpan', 'success');
          router.visit('/contra-bons');
        } else {
          // If no JSON response, assume success (redirect response)
          await Swal.fire('Berhasil', 'Data berhasil disimpan', 'success');
          router.visit('/contra-bons');
        }
      } else {
        throw new Error('Unexpected response status: ' + res.status);
      }
    } catch (e) {
      console.error('Error saving contra bon:', e);
      // Close loading Swal first
      await Swal.close();
      
      let errorMessage = 'Terjadi kesalahan saat menyimpan data';
      
      if (e.response) {
        // Server responded with error
        if (e.response.data) {
          if (e.response.data.message) {
            errorMessage = e.response.data.message;
          } else if (e.response.data.errors) {
            // Validation errors
            const errors = e.response.data.errors;
            const errorMessages = Object.keys(errors).map(key => {
              return Array.isArray(errors[key]) ? errors[key].join(', ') : errors[key];
            });
            errorMessage = errorMessages.join('\n');
            form.setError(errors);
          } else if (e.response.data.error) {
            errorMessage = e.response.data.error;
          } else if (typeof e.response.data === 'string') {
            errorMessage = e.response.data;
          }
        }
        if (e.response.status === 422) {
          errorMessage = 'Validasi gagal: ' + errorMessage;
        } else if (e.response.status === 500) {
          errorMessage = 'Server error: ' + errorMessage;
        }
      } else if (e.request) {
        errorMessage = 'Tidak ada response dari server. Pastikan koneksi internet Anda stabil.';
      } else {
        errorMessage = e.message || errorMessage;
      }
      
      await Swal.fire({
        icon: 'error',
        title: 'Gagal Menyimpan',
        text: errorMessage,
        confirmButtonText: 'OK'
      });
    }
    return;
  }
  // Tanpa file, pakai inertia
  // Set source_type dan source_id ke form
  form.source_type = sourceType.value;
  if (sourceType.value === 'retail_food') {
    form.source_id = selectedRetailFoodKey.value;
  } else if (sourceType.value === 'warehouse_retail_food') {
    form.source_id = selectedWarehouseRetailFoodKey.value;
  } else {
    form.source_id = '';
  }
  
  // Hanya kirim item yang dicentang (hapus field selected dan _rowKey)
  const itemsToSubmit = selectedItems.map(item => {
    const { selected, _rowKey, ...itemData } = item;
    return itemData;
  });
  form.items = itemsToSubmit;
  
  if (isEdit.value) {
    form.put(`/contra-bons/${props.contraBon.id}`, {
      onSuccess: async () => {
        await Swal.close();
        await Swal.fire('Berhasil', 'Data berhasil disimpan', 'success');
        router.visit('/contra-bons');
      },
      onError: async (errors) => {
        console.error('Error saving contra bon:', errors);
        // Close loading Swal first
        await Swal.close();
        
        let errorMessage = 'Terjadi kesalahan saat menyimpan data';
        
        if (errors) {
          // Inertia validation errors
          const errorMessages = Object.keys(errors).map(key => {
            const errorValue = errors[key];
            return Array.isArray(errorValue) ? errorValue.join(', ') : errorValue;
          });
          errorMessage = errorMessages.join('\n');
        }
        
        await Swal.fire({
          icon: 'error',
          title: 'Gagal Menyimpan',
          text: errorMessage,
          confirmButtonText: 'OK'
        });
      },
    });
  } else {
    form.post('/contra-bons', {
      onSuccess: async () => {
        await Swal.close();
        await Swal.fire('Berhasil', 'Data berhasil disimpan', 'success');
        router.visit('/contra-bons');
      },
      onError: async (errors) => {
        console.error('Error saving contra bon:', errors);
        // Close loading Swal first
        await Swal.close();
        
        let errorMessage = 'Terjadi kesalahan saat menyimpan data';
        
        if (errors) {
          // Inertia validation errors
          const errorMessages = Object.keys(errors).map(key => {
            const errorValue = errors[key];
            return Array.isArray(errorValue) ? errorValue.join(', ') : errorValue;
          });
          errorMessage = errorMessages.join('\n');
        }
        
        await Swal.fire({
          icon: 'error',
          title: 'Gagal Menyimpan',
          text: errorMessage,
          confirmButtonText: 'OK'
        });
      },
    });
  }
}

function goBack() {
  router.visit('/contra-bons');
}

function toggleAllItems(event) {
  const checked = event.target.checked;
  form.items.forEach(item => {
    item.selected = checked;
  });
}
</script>
<template>
  <AppLayout>
    <div class="max-w-5xl w-full mx-auto py-8 px-2">
      <div class="flex items-center gap-2 mb-6">
        <button @click="goBack" class="text-blue-500 hover:underline"><i class="fa fa-arrow-left"></i> Kembali</button>
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2 ml-4">
          <i class="fa-solid fa-file-circle-xmark text-blue-500"></i> {{ isEdit ? 'Edit' : 'Tambah' }} Contra Bon
        </h1>
      </div>
      <form @submit.prevent="onSubmit" class="space-y-6">
        <!-- Source Type Selector -->
        <div class="mb-6">
          <label class="block text-sm font-medium text-gray-700 mb-2">Sumber Data</label>
          <div class="flex gap-4">
            <label class="flex items-center">
              <input type="radio" v-model="sourceType" value="purchase_order" @change="onSourceTypeChange" class="mr-2">
              <span>Purchase Order / Good Receive</span>
            </label>
            <label class="flex items-center">
              <input type="radio" v-model="sourceType" value="retail_food" @change="onSourceTypeChange" class="mr-2">
              <span>Retail Food Outlet (Contra Bon)</span>
            </label>
            <label class="flex items-center">
              <input type="radio" v-model="sourceType" value="warehouse_retail_food" @change="onSourceTypeChange" class="mr-2">
              <span>Warehouse Retail Food (Contra Bon)</span>
            </label>
          </div>
        </div>

        <!-- Purchase Order Selection -->
        <div v-if="sourceType === 'purchase_order'" class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700">Purchase Order</label>
            <div class="flex gap-2">
              <input 
                :value="selectedPOGR ? `${selectedPOGR.po_number} - ${selectedPOGR.gr_number} - ${selectedPOGR.supplier_name}` : ''"
                readonly
                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm bg-gray-50"
                placeholder="Pilih PO - GR - Supplier"
              />
              <button 
                type="button" 
                @click="openPOListModal"
                class="mt-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:ring-2 focus:ring-blue-500"
              >
                <i class="fa fa-search"></i> Cari
              </button>
            </div>
            <input type="hidden" v-model="form.po_id" />
            <input type="hidden" v-model="form.gr_id" />
            <div v-if="form.errors.po_id" class="text-xs text-red-500 mt-1">{{ form.errors.po_id }}</div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Tanggal</label>
            <input type="date" v-model="form.date" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" />
            <div v-if="form.errors.date" class="text-xs text-red-500 mt-1">{{ form.errors.date }}</div>
          </div>
        </div>

        <!-- Retail Food Selection -->
        <div v-if="sourceType === 'retail_food'" class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700">Retail Food</label>
            <div class="flex gap-2">
              <input 
                :value="selectedRetailFood ? `${selectedRetailFood.retail_number} - ${selectedRetailFood.supplier_name}` : ''"
                readonly
                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm bg-gray-50"
                placeholder="Pilih Retail Food - Supplier"
              />
              <button 
                type="button" 
                @click="openRetailFoodModal"
                class="mt-1 px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 focus:ring-2 focus:ring-purple-500"
              >
                <i class="fa fa-search"></i> Cari
              </button>
            </div>
            <input type="hidden" v-model="form.po_id" />
            <input type="hidden" v-model="form.gr_id" />
            <div v-if="form.errors.po_id" class="text-xs text-red-500 mt-1">{{ form.errors.po_id }}</div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Tanggal</label>
            <input type="date" v-model="form.date" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" />
            <div v-if="form.errors.date" class="text-xs text-red-500 mt-1">{{ form.errors.date }}</div>
          </div>
        </div>

        <!-- Warehouse Retail Food Selection -->
        <div v-if="sourceType === 'warehouse_retail_food'" class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700">Warehouse Retail Food</label>
            <div class="flex gap-2">
              <input 
                :value="selectedWarehouseRetailFood ? `${selectedWarehouseRetailFood.retail_number} - ${selectedWarehouseRetailFood.supplier_name}` : ''"
                readonly
                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm bg-gray-50"
                placeholder="Pilih Warehouse Retail Food - Supplier"
              />
              <button 
                type="button" 
                @click="openWarehouseRetailFoodModal"
                class="mt-1 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500"
              >
                <i class="fa fa-search"></i> Cari
              </button>
            </div>
            <input type="hidden" v-model="form.po_id" />
            <input type="hidden" v-model="form.gr_id" />
            <div v-if="form.errors.po_id" class="text-xs text-red-500 mt-1">{{ form.errors.po_id }}</div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Tanggal</label>
            <input type="date" v-model="form.date" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" />
            <div v-if="form.errors.date" class="text-xs text-red-500 mt-1">{{ form.errors.date }}</div>
          </div>
        </div>

        <!-- Card Info PO & GR (for create mode) -->
        <div v-if="!isEdit && selectedPOGR" class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
          <div class="bg-blue-50 rounded-lg p-4 shadow">
            <h3 class="font-bold mb-2">Info PO</h3>
            <div>No. PO: {{ selectedPOGR.po_number }}</div>
            <div>Tanggal PO: {{ selectedPOGR.po_date }}</div>
            <div>Supplier: <b>{{ selectedPOGR.supplier_name }}</b></div>
            <div>Dibuat oleh: {{ selectedPOGR.po_creator_name }}</div>
            <div v-if="selectedPOGR.source_type_display">
              Source Type: 
              <span :class="{
                'bg-blue-100 text-blue-700': selectedPOGR.source_type_display === 'PR Foods',
                'bg-green-100 text-green-700': selectedPOGR.source_type_display === 'RO Supplier'
              }" class="px-2 py-1 rounded-full text-xs font-semibold">
                {{ selectedPOGR.source_type_display }}
              </span>
            </div>
            <div v-if="selectedPOGR.outlet_names && selectedPOGR.outlet_names.length > 0">
              Outlet: 
              <span v-for="outlet in selectedPOGR.outlet_names" :key="outlet" 
                    class="bg-orange-100 text-orange-800 text-xs px-2 py-1 rounded-full ml-1">
                {{ outlet }}
              </span>
            </div>
            <!-- Discount Info -->
            <div v-if="selectedPOGR.po_discount_info && (selectedPOGR.po_discount_info.discount_total_percent > 0 || selectedPOGR.po_discount_info.discount_total_amount > 0)" class="mt-3 pt-3 border-t border-blue-200">
              <div class="text-sm font-semibold text-blue-800 mb-1">Informasi Diskon PO:</div>
              <div v-if="selectedPOGR.po_discount_info.discount_total_percent > 0" class="text-xs">
                Diskon Total: <span class="font-semibold text-red-600">{{ selectedPOGR.po_discount_info.discount_total_percent }}%</span>
              </div>
              <div v-if="selectedPOGR.po_discount_info.discount_total_amount > 0" class="text-xs">
                Diskon Total: <span class="font-semibold text-red-600">{{ formatCurrency(selectedPOGR.po_discount_info.discount_total_amount) }}</span>
              </div>
              <div class="text-xs mt-1">
                Subtotal PO: <span class="font-semibold">{{ formatCurrency(selectedPOGR.po_discount_info.subtotal) }}</span>
              </div>
              <div class="text-xs">
                Grand Total PO: <span class="font-semibold text-green-600">{{ formatCurrency(selectedPOGR.po_discount_info.grand_total) }}</span>
              </div>
            </div>
          </div>
          <div class="bg-green-50 rounded-lg p-4 shadow">
            <h3 class="font-bold mb-2">Info Good Receive</h3>
            <div>No. GR: {{ selectedPOGR.gr_number }}</div>
            <div>Tanggal GR: {{ selectedPOGR.gr_date }}</div>
            <div>Diterima oleh: {{ selectedPOGR.gr_receiver_name }}</div>
            <div>Supplier: <b>{{ selectedPOGR.supplier_name }}</b></div>
          </div>
        </div>

        <!-- Card Info PO & GR (for edit mode) -->
        <div v-if="isEdit && contraBon && contraBon.purchase_order && contraBon.source_type === 'purchase_order'" class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
          <div class="bg-blue-50 rounded-lg p-4 shadow">
            <h3 class="font-bold mb-2">Info PO</h3>
            <div>No. PO: {{ contraBon.purchase_order?.number || '-' }}</div>
            <div>Tanggal PO: {{ contraBon.purchase_order?.date ? new Date(contraBon.purchase_order.date).toLocaleDateString('id-ID') : '-' }}</div>
            <div>Supplier: <b>{{ contraBon.supplier?.name || '-' }}</b></div>
            <div v-if="contraBon.source_type_display">
              Source Type: 
              <span :class="{
                'bg-blue-100 text-blue-700': contraBon.source_type_display === 'PR Foods',
                'bg-green-100 text-green-700': contraBon.source_type_display === 'RO Supplier'
              }" class="px-2 py-1 rounded-full text-xs font-semibold">
                {{ contraBon.source_type_display }}
              </span>
            </div>
            <div v-if="contraBon.po_discount_info && (contraBon.po_discount_info.discount_total_percent > 0 || contraBon.po_discount_info.discount_total_amount > 0)" class="mt-3 pt-3 border-t border-blue-200">
              <div class="text-sm font-semibold text-blue-800 mb-1">Informasi Diskon PO:</div>
              <div v-if="contraBon.po_discount_info.discount_total_percent > 0" class="text-xs">
                Diskon Total: <span class="font-semibold text-red-600">{{ contraBon.po_discount_info.discount_total_percent }}%</span>
              </div>
              <div v-if="contraBon.po_discount_info.discount_total_amount > 0" class="text-xs">
                Diskon Total: <span class="font-semibold text-red-600">{{ formatCurrency(contraBon.po_discount_info.discount_total_amount) }}</span>
              </div>
              <div class="text-xs mt-1">
                Subtotal PO: <span class="font-semibold">{{ formatCurrency(contraBon.po_discount_info.subtotal) }}</span>
              </div>
              <div class="text-xs">
                Grand Total PO: <span class="font-semibold text-green-600">{{ formatCurrency(contraBon.po_discount_info.grand_total) }}</span>
              </div>
            </div>
          </div>
          <div class="bg-green-50 rounded-lg p-4 shadow">
            <h3 class="font-bold mb-2">Info Good Receive</h3>
            <div>No. GR: {{ contraBon.gr_number || '-' }}</div>
            <div>Tanggal GR: {{ contraBon.gr_date ? new Date(contraBon.gr_date).toLocaleDateString('id-ID') : '-' }}</div>
          </div>
        </div>

                 <!-- Card Info Retail Food -->
         <div v-if="selectedRetailFood" class="bg-purple-50 rounded-lg p-4 shadow mb-4">
           <h3 class="font-bold mb-2">Info Retail Food</h3>
           <div>No. Retail Food: {{ selectedRetailFood.retail_number }}</div>
           <div>Tanggal Transaksi: {{ selectedRetailFood.transaction_date }}</div>
           <div>Outlet: <b>{{ selectedRetailFood.outlet_name || '-' }}</b></div>
           <div>Warehouse Outlet: <b>{{ selectedRetailFood.warehouse_outlet_name || '-' }}</b></div>
           <div>Supplier: <b>{{ selectedRetailFood.supplier_name }}</b></div>
           <div>Dibuat oleh: {{ selectedRetailFood.creator_name }}</div>
           <div>Total Amount: <b>{{ formatCurrency(selectedRetailFood.total_amount) }}</b></div>
           <div v-if="selectedRetailFood.notes">Notes: {{ selectedRetailFood.notes }}</div>
         </div>

        <!-- Card Info Warehouse Retail Food -->
        <div v-if="selectedWarehouseRetailFood" class="bg-indigo-50 rounded-lg p-4 shadow mb-4">
          <h3 class="font-bold mb-2">Info Warehouse Retail Food</h3>
          <div>No. Warehouse Retail Food: {{ selectedWarehouseRetailFood.retail_number }}</div>
          <div>Tanggal Transaksi: {{ selectedWarehouseRetailFood.transaction_date }}</div>
          <div>Warehouse: <b>{{ selectedWarehouseRetailFood.warehouse_name || '-' }}</b></div>
          <div>Warehouse Division: <b>{{ selectedWarehouseRetailFood.warehouse_division_name || '-' }}</b></div>
          <div>Supplier: <b>{{ selectedWarehouseRetailFood.supplier_name }}</b></div>
          <div>Dibuat oleh: {{ selectedWarehouseRetailFood.creator_name }}</div>
          <div>Total Amount: <b>{{ formatCurrency(selectedWarehouseRetailFood.total_amount) }}</b></div>
          <div v-if="selectedWarehouseRetailFood.notes">Notes: {{ selectedWarehouseRetailFood.notes }}</div>
        </div>

        <!-- Card Info Supplier -->
        <div v-if="supplierDetail" class="bg-yellow-50 rounded-lg p-4 shadow mb-4">
          <h3 class="font-bold mb-2">Info Supplier</h3>
          <div><b>Nama:</b> {{ supplierDetail.name }}</div>
          <div><b>PIC:</b> {{ supplierDetail.contact_person }}</div>
          <div><b>No. Telp:</b> {{ supplierDetail.phone }}</div>
          <div><b>Email:</b> {{ supplierDetail.email }}</div>
          <div><b>Alamat:</b> {{ supplierDetail.address }}</div>
          <div><b>Kota:</b> {{ supplierDetail.city }}</div>
          <div><b>Provinsi:</b> {{ supplierDetail.province }}</div>
          <div><b>Kode Pos:</b> {{ supplierDetail.postal_code }}</div>
          <div><b>NPWP:</b> {{ supplierDetail.npwp }}</div>
        </div>

        <!-- Upload File Contra Bon Fisik -->
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700">Upload Scan/Foto Contra Bon Fisik</label>
          <input type="file" accept="image/*" @change="onFileChange" class="mt-1 block" />
          <div v-if="fileImagePreview" class="mt-2">
            <img :src="fileImagePreview" alt="Preview" class="max-w-xs rounded shadow" />
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700">No Invoice Supplier</label>
          <input type="text" v-model="form.supplier_invoice_number" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" placeholder="No Invoice dari Supplier" />
          <div v-if="form.errors.supplier_invoice_number" class="text-xs text-red-500 mt-1">{{ form.errors.supplier_invoice_number }}</div>
        </div>

                 <div>
           <label class="block text-sm font-medium text-gray-700 mb-2">Detail Item</label>
           <div class="overflow-x-auto">
             <!-- Loading spinner hanya saat initial load -->
             <div v-if="initialLoading && !isEdit" class="flex justify-center items-center py-8">
               <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
               <span class="ml-2 text-gray-600">Memuat data...</span>
             </div>
             
             <!-- Items table -->
             <table v-else class="w-full min-w-full divide-y divide-gray-200">
               <thead class="bg-gradient-to-r from-blue-50 to-blue-100">
                 <tr>
                   <th class="px-3 py-2 text-center text-xs font-bold text-blue-700 uppercase tracking-wider w-12">
                     <input 
                       type="checkbox" 
                       :checked="form.items.length > 0 && form.items.every(item => item.selected)"
                       @change="toggleAllItems"
                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                     />
                   </th>
                   <th class="px-3 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Item</th>
                   <th class="px-3 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Qty</th>
                   <th class="px-3 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Unit</th>
                   <th class="px-3 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Price</th>
                   <th v-if="sourceType === 'purchase_order'" class="px-3 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Diskon</th>
                   <th class="px-3 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Total</th>
                   <th class="px-3 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Notes</th>
                 </tr>
               </thead>
               <tbody>
                 <tr v-if="form.items.length === 0" class="text-center text-gray-500">
                   <td :colspan="sourceType === 'purchase_order' ? 8 : 7" class="px-3 py-4">Tidak ada data item</td>
                 </tr>
                 <tr v-for="(item, idx) in form.items" :key="item._rowKey || idx" :class="{ 'bg-gray-50': !item.selected }">
                   <td class="px-3 py-2 text-center">
                     <input 
                       type="checkbox" 
                       v-model="item.selected"
                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                     />
                   </td>
                   <td class="px-3 py-2 min-w-[200px]">
                     {{ isEdit ? (item.item?.name || '-') : (sourceType === 'retail_food' || sourceType === 'warehouse_retail_food' ? (item.item_name || '-') : (selectedPOGR?.items.find(i => i.item_id === item.item_id)?.item_name || '-')) }}
                   </td>
                   <td class="px-3 py-2 min-w-[100px]">
                     {{ item.quantity }}
                   </td>
                   <td class="px-3 py-2 min-w-[100px]">
                     {{ (sourceType === 'retail_food' || sourceType === 'warehouse_retail_food') ? (item.unit_name || '-') : (selectedPOGR?.items.find(i => i.item_id === item.item_id)?.unit_name || item.unit?.name || '-') }}
                   </td>
                   <td class="px-3 py-2 min-w-[120px]">
                     <input 
                       type="number" 
                       v-model.number="item.price" 
                       @input="updateItemTotal(item)"
                       @blur="updateItemTotal(item)"
                       step="0.01"
                       min="0"
                       class="w-full px-2 py-1 rounded border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500"
                       placeholder="0.00"
                     />
                   </td>
                   <td v-if="sourceType === 'purchase_order'" class="px-3 py-2 min-w-[100px] text-xs">
                     <div v-if="item.discount_percent > 0 || item.discount_amount > 0" class="text-red-600">
                       <div v-if="item.discount_percent > 0">{{ item.discount_percent }}%</div>
                       <div v-if="item.discount_amount > 0">{{ formatCurrency(item.discount_amount) }}</div>
                     </div>
                     <span v-else class="text-gray-400">-</span>
                   </td>
                   <td class="px-3 py-2 min-w-[100px]">
                     <span v-if="sourceType === 'purchase_order' && item.po_item_total">
                       {{ formatCurrency(item.po_item_total) }}
                     </span>
                     <span v-else>
                     {{ formatCurrency(item.quantity * (item.price || 0)) }}
                     </span>
                   </td>
                   <td class="px-3 py-2 min-w-[120px]">
                     <input type="text" v-model="item.notes" class="w-full rounded border-gray-300" />
                   </td>
                 </tr>
               </tbody>
               <tfoot class="bg-gray-100">
                 <tr>
                   <td colspan="1" class="px-3 py-3"></td>
                   <td :colspan="sourceType === 'purchase_order' ? 4 : 3" class="px-3 py-3 text-right font-bold text-gray-700">
                     Total:
                   </td>
                   <td class="px-3 py-3 font-bold text-blue-700">
                     {{ formatCurrency(totalAmount) }}
                   </td>
                   <td v-if="sourceType === 'purchase_order'" class="px-3 py-3"></td>
                   <td class="px-3 py-3"></td>
                 </tr>
               </tfoot>
             </table>
           </div>
         </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Notes</label>
          <textarea v-model="form.notes" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"></textarea>
          <div v-if="form.errors.notes" class="text-xs text-red-500 mt-1">{{ form.errors.notes }}</div>
        </div>
        <div class="flex justify-end gap-2">
          <button type="button" @click="goBack" class="px-4 py-2 rounded bg-gray-200 text-gray-700 font-semibold hover:bg-gray-300">Batal</button>
          <button type="submit" :disabled="form.processing" class="px-4 py-2 rounded bg-blue-600 text-white font-semibold hover:bg-blue-700 disabled:opacity-50">{{ isEdit ? 'Simpan Perubahan' : 'Simpan' }}</button>
        </div>
      </form>
    </div>

    <!-- Modal PO List -->
    <div v-if="showPOListModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl max-h-[80vh] flex flex-col">
        <div class="p-6 border-b">
          <div class="flex justify-between items-center">
            <h3 class="text-lg font-semibold">Pilih Purchase Order</h3>
            <button @click="closePOListModal" class="text-gray-500 hover:text-gray-700">
              <i class="fa fa-times"></i>
            </button>
          </div>
          <div class="mt-4">
            <input 
              v-model="poSearchQuery" 
              @input="filterPOList"
              type="text" 
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
              placeholder="Cari PO, GR, Supplier, atau Outlet..."
            />
          </div>
        </div>
        <div class="flex-1 overflow-y-auto">
          <div v-if="filteredPOList.length === 0" class="p-8 text-center text-gray-500">
            Tidak ada data yang ditemukan
          </div>
          <div v-else class="divide-y divide-gray-200">
            <div 
              v-for="p in filteredPOList" 
              :key="p.po_id + '-' + p.gr_id" 
              @click="selectPOFromModal(p)"
              class="p-4 cursor-pointer hover:bg-blue-50 transition-colors"
            >
              <div class="flex items-center gap-3">
                <span v-if="p.source_type_display === 'PR Foods'" class="text-blue-500 text-xl">🔵</span>
                <span v-else-if="p.source_type_display === 'RO Supplier'" class="text-green-500 text-xl">🟢</span>
                <span v-else class="text-gray-500 text-xl">⚪</span>
                <div class="flex-1">
                  <div class="font-semibold text-lg">
                    {{ p.po_number }} - {{ p.gr_number }}
                  </div>
                  <div class="text-gray-600">{{ p.supplier_name }}</div>
                  <div v-if="p.outlet_names && p.outlet_names.length > 0" class="text-sm text-orange-600 mt-1">
                    <i class="fa fa-map-marker-alt"></i> {{ p.outlet_names.join(', ') }}
                  </div>
                </div>
                <div class="text-right">
                  <div class="text-sm text-gray-500">{{ p.source_type_display }}</div>
                  <div class="text-sm text-gray-500">{{ p.po_date }}</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Retail Food List -->
    <div v-if="showRetailFoodModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl max-h-[80vh] flex flex-col">
        <div class="p-6 border-b">
          <div class="flex justify-between items-center">
            <h3 class="text-lg font-semibold">Pilih Retail Food</h3>
            <button @click="closeRetailFoodModal" class="text-gray-500 hover:text-gray-700">
              <i class="fa fa-times"></i>
            </button>
          </div>
          <div class="mt-4">
            <input 
              v-model="retailFoodSearchQuery" 
              @input="filterRetailFoodList"
              type="text" 
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500" 
              placeholder="Cari Retail Food, Supplier, atau Outlet..."
            />
          </div>
        </div>
        <div class="flex-1 overflow-y-auto">
          <div v-if="filteredRetailFoodList.length === 0" class="p-8 text-center text-gray-500">
            Tidak ada data yang ditemukan
          </div>
          <div v-else class="divide-y divide-gray-200">
            <div 
              v-for="rf in filteredRetailFoodList" 
              :key="rf.retail_food_id" 
              @click="selectRetailFoodFromModal(rf)"
              class="p-4 cursor-pointer hover:bg-purple-50 transition-colors"
            >
              <div class="flex items-center gap-3">
                <span class="text-purple-500 text-xl">🟣</span>
                <div class="flex-1">
                  <div class="font-semibold text-lg">{{ rf.retail_number }}</div>
                  <div class="text-gray-600">{{ rf.supplier_name }}</div>
                  <div v-if="rf.outlet_name || rf.warehouse_outlet_name" class="text-sm text-purple-600 mt-1">
                    <i class="fa fa-map-marker-alt"></i> {{ rf.outlet_name || rf.warehouse_outlet_name }}
                  </div>
                </div>
                <div class="text-right">
                  <div class="text-sm text-gray-500">Retail Food</div>
                  <div class="text-sm text-gray-500">{{ rf.transaction_date }}</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Warehouse Retail Food List -->
    <div v-if="showWarehouseRetailFoodModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl max-h-[80vh] flex flex-col">
        <div class="p-6 border-b">
          <div class="flex justify-between items-center">
            <h3 class="text-lg font-semibold">Pilih Warehouse Retail Food</h3>
            <button @click="closeWarehouseRetailFoodModal" class="text-gray-500 hover:text-gray-700">
              <i class="fa fa-times"></i>
            </button>
          </div>
          <div class="mt-4">
            <input 
              v-model="warehouseRetailFoodSearchQuery" 
              @input="filterWarehouseRetailFoodList"
              type="text" 
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" 
              placeholder="Cari Warehouse Retail Food, Supplier, atau Warehouse..."
            />
          </div>
        </div>
        <div class="flex-1 overflow-y-auto">
          <div v-if="filteredWarehouseRetailFoodList.length === 0" class="p-8 text-center text-gray-500">
            Tidak ada data yang ditemukan
          </div>
          <div v-else class="divide-y divide-gray-200">
            <div 
              v-for="rwf in filteredWarehouseRetailFoodList" 
              :key="rwf.retail_warehouse_food_id" 
              @click="selectWarehouseRetailFoodFromModal(rwf)"
              class="p-4 cursor-pointer hover:bg-indigo-50 transition-colors"
            >
              <div class="flex items-center gap-3">
                <span class="text-indigo-500 text-xl">🟦</span>
                <div class="flex-1">
                  <div class="font-semibold text-lg">{{ rwf.retail_number }}</div>
                  <div class="text-gray-600">{{ rwf.supplier_name }}</div>
                  <div v-if="rwf.warehouse_name || rwf.warehouse_division_name" class="text-sm text-indigo-600 mt-1">
                    <i class="fa fa-map-marker-alt"></i> {{ rwf.warehouse_name || '' }}{{ rwf.warehouse_name && rwf.warehouse_division_name ? ' - ' : '' }}{{ rwf.warehouse_division_name || '' }}
                  </div>
                </div>
                <div class="text-right">
                  <div class="text-sm text-gray-500">Warehouse Retail Food</div>
                  <div class="text-sm text-gray-500">{{ rwf.transaction_date }}</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template> 