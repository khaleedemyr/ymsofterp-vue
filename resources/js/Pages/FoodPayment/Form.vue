<template>
  <AppLayout>
    <div class="max-w-5xl w-full mx-auto py-8 px-2">
      <div class="flex items-center gap-2 mb-6">
        <button @click="goBack" class="text-blue-500 hover:underline"><i class="fa fa-arrow-left"></i> Kembali</button>
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2 ml-4">
          <i class="fa-solid fa-money-bill-transfer text-blue-500"></i> {{ isEditMode ? 'Edit' : 'Buat' }} Food Payment
        </h1>
      </div>
      <form @submit.prevent="onSubmit" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700">Tanggal</label>
            <input type="date" v-model="form.date" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" required />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Payment Type</label>
            <select v-model="form.payment_type" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" required>
              <option value="">Pilih Payment Type</option>
              <option value="Transfer">Transfer</option>
              <option value="Giro">Giro</option>
              <option value="Cash">Cash</option>
            </select>
          </div>
        </div>
        <!-- Bank Selection (hanya muncul jika Transfer atau Giro) -->
        <div v-if="form.payment_type === 'Transfer' || form.payment_type === 'Giro'">
          <label class="block text-sm font-medium text-gray-700">
            Pilih Bank <span class="text-red-500">*</span>
          </label>
          <multiselect
            v-model="selectedBank"
            :options="banks"
            :searchable="true"
            :close-on-select="true"
            :show-labels="false"
            placeholder="Cari dan pilih bank..."
            label="display_name"
            track-by="id"
            @select="onBankSelect"
            @remove="onBankRemove"
            class="mt-1"
            required
          >
            <template #noOptions>
              <span>Tidak ada bank ditemukan</span>
            </template>
            <template #noResult>
              <span>Tidak ada bank ditemukan</span>
            </template>
          </multiselect>
          <p class="mt-1 text-xs text-gray-500">Cari dan pilih bank dari master data bank untuk {{ form.payment_type }}</p>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Supplier</label>
          <multiselect
            v-model="selectedSupplier"
            :options="suppliers"
            :searchable="true"
            :close-on-select="true"
            :show-labels="false"
            placeholder="Pilih Supplier"
            label="name"
            track-by="id"
            @select="onSupplierChange"
            @remove="onSupplierRemove"
            class="mt-1"
            required
          >
            <template #noOptions>
              <span>Tidak ada supplier ditemukan</span>
            </template>
            <template #noResult>
              <span>Tidak ada supplier ditemukan</span>
            </template>
          </multiselect>
        </div>
        <!-- Card Info Contra Bon -->
        <div class="bg-blue-50 rounded-lg p-4 shadow mb-4">
          <div class="flex items-center justify-between mb-3">
            <h3 class="font-bold">Pilih Contra Bon yang akan dibayar</h3>
            <div class="text-sm text-gray-600">
              <span v-if="selectedSupplier">{{ filteredContraBons.length }} dari {{ contraBons.length }} contra bon</span>
            </div>
          </div>
          
          <!-- Search Input -->
          <div class="mb-3">
            <div class="relative">
              <input
                type="text"
                v-model="contraBonSearch"
                placeholder="Cari contra bon (nomor, invoice, supplier, total, tanggal, notes, PO, outlet...)"
                class="w-full px-4 py-2 pl-10 rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
              />
              <i class="fa fa-search absolute left-3 top-3 text-gray-400"></i>
              <button
                v-if="contraBonSearch"
                @click="contraBonSearch = ''"
                type="button"
                class="absolute right-3 top-2 text-gray-400 hover:text-gray-600"
              >
                <i class="fa fa-times"></i>
              </button>
            </div>
          </div>
          
          <!-- Select All / Deselect All -->
          <div v-if="filteredContraBons.length > 0" class="mb-2 flex items-center justify-between">
            <div class="flex items-center gap-2">
              <button
                type="button"
                @click="selectAllContraBons"
                class="text-xs px-2 py-1 bg-blue-100 text-blue-700 rounded hover:bg-blue-200"
              >
                <i class="fa fa-check-square mr-1"></i>Pilih Semua
              </button>
              <button
                type="button"
                @click="deselectAllContraBons"
                class="text-xs px-2 py-1 bg-gray-100 text-gray-700 rounded hover:bg-gray-200"
              >
                <i class="fa fa-square mr-1"></i>Batal Semua
              </button>
            </div>
            <div class="text-xs text-gray-600">
              {{ form.selected_contra_bon_ids.length }} dipilih
            </div>
          </div>
          
          <div class="border rounded p-2 max-h-96 overflow-y-auto bg-white">
            <div v-if="filteredContraBons.length === 0" class="text-gray-400 text-sm p-4 text-center">
              <i class="fa fa-search text-2xl mb-2"></i>
              <div v-if="contraBonSearch">Tidak ada contra bon ditemukan untuk "{{ contraBonSearch }}"</div>
              <div v-else>Tidak ada contra bon yang belum dibayar untuk supplier ini.</div>
            </div>
            <div v-for="cb in filteredContraBons" :key="cb.id" class="flex items-center mb-2 p-3 hover:bg-blue-50 rounded border border-gray-200 transition-colors">
              <input 
                type="checkbox" 
                :value="cb.id" 
                v-model="form.selected_contra_bon_ids" 
                class="mr-3 w-4 h-4 text-blue-600 rounded focus:ring-blue-500" 
              />
              <div class="flex-1">
                <div class="flex items-center gap-2 mb-1 flex-wrap">
                  <span class="font-medium text-gray-800">{{ cb.number }}</span>
                  <span v-if="cb.source_type_display === 'PR Foods'" class="bg-blue-100 text-blue-700 px-2 py-1 rounded-full text-xs font-semibold">
                    🔵 PR Foods
                  </span>
                  <span v-else-if="cb.source_type_display === 'RO Supplier'" class="bg-green-100 text-green-700 px-2 py-1 rounded-full text-xs font-semibold">
                    🟢 RO Supplier
                  </span>
                  <span v-else-if="cb.source_type_display === 'Retail Food'" class="bg-purple-100 text-purple-700 px-2 py-1 rounded-full text-xs font-semibold">
                    🟣 Retail Food
                  </span>
                  <span v-else class="bg-gray-100 text-gray-700 px-2 py-1 rounded-full text-xs font-semibold">
                    ⚪ Unknown
                  </span>
                </div>
                <div class="text-sm text-gray-600 space-y-1">
                  <div class="flex items-center gap-4 flex-wrap">
                    <span><strong>Total:</strong> {{ formatCurrency(cb.total_amount) }}</span>
                    <span v-if="cb.date" class="text-xs">
                      <i class="fa fa-calendar mr-1"></i>{{ formatDate(cb.date) }}
                    </span>
                  </div>
                  <div v-if="cb.supplier_invoice_number" class="text-xs text-gray-500">
                    <i class="fa fa-file-invoice mr-1"></i><strong>Invoice:</strong> {{ cb.supplier_invoice_number }}
                  </div>
                  <div v-if="cb.supplier?.name" class="text-xs text-gray-500">
                    <i class="fa fa-truck mr-1"></i><strong>Supplier:</strong> {{ cb.supplier.name }}
                  </div>
                  <div v-if="cb.purchaseOrder?.number" class="text-xs text-blue-600">
                    <i class="fa fa-shopping-cart mr-1"></i><strong>PO:</strong> {{ cb.purchaseOrder.number }}
                  </div>
                  <div v-if="cb.retailFood?.number" class="text-xs text-purple-600">
                    <i class="fa fa-store mr-1"></i><strong>Retail:</strong> {{ cb.retailFood.number }}
                  </div>
                  <div v-if="cb.outlet_names && cb.outlet_names.length > 0" class="text-xs text-orange-600 mt-1">
                    <i class="fa fa-map-marker-alt mr-1"></i>
                    <strong>Outlet:</strong> {{ cb.outlet_names.join(', ') }}
                  </div>
                  <div v-if="cb.notes" class="text-xs text-gray-500 italic mt-1">
                    <i class="fa fa-sticky-note mr-1"></i>{{ cb.notes }}
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="mt-4 flex items-center justify-between">
            <div class="text-sm text-gray-600">
              <span v-if="form.selected_contra_bon_ids.length > 0">
                {{ form.selected_contra_bon_ids.length }} contra bon dipilih
              </span>
            </div>
            <div class="text-right font-bold text-lg text-blue-700">
              Total Bayar: {{ formatCurrency(totalBayar) }}
            </div>
          </div>
        </div>
        <!-- Upload Bukti Transfer -->
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700">Upload Bukti Transfer (image/pdf)</label>
          <input type="file" accept="image/*,application/pdf" @change="onFileChange" class="mt-1 block" />
          <div v-if="existingBuktiPath && !filePreview" class="mt-2">
            <div v-if="isImageFile(existingBuktiPath)" class="mt-2">
              <img :src="`/storage/${existingBuktiPath}`" alt="Current Bukti" class="max-w-xs rounded shadow" />
            </div>
            <div v-else class="mt-2">
              <a :href="`/storage/${existingBuktiPath}`" target="_blank" class="text-blue-500 hover:underline">
                <i class="fas fa-file-pdf mr-1"></i> Lihat Bukti Transfer Saat Ini
              </a>
            </div>
          </div>
          <div v-if="filePreview && isImage" class="mt-2">
            <img :src="filePreview" alt="Preview" class="max-w-xs rounded shadow" />
          </div>
          <div v-if="filePreview && isPdf" class="mt-2">
            <a :href="filePreview" target="_blank" class="text-blue-500 hover:underline">
              <i class="fas fa-file-pdf mr-1"></i> Preview PDF
            </a>
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Notes</label>
          <textarea v-model="form.notes" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"></textarea>
        </div>
        <div class="flex justify-end gap-2">
          <button type="button" @click="goBack" class="px-4 py-2 rounded bg-gray-200 text-gray-700 font-semibold hover:bg-gray-300">Batal</button>
          <button type="submit" class="px-4 py-2 rounded bg-blue-600 text-white font-semibold hover:bg-blue-700">Simpan</button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Swal from 'sweetalert2';
import axios from 'axios';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.min.css';

const props = defineProps({
  payment: {
    type: Object,
    default: null
  },
  banks: {
    type: Array,
    default: () => []
  }
});

const isEditMode = computed(() => !!props.payment);

const suppliers = ref([]);
const selectedSupplier = ref(null);
const contraBons = ref([]);
const contraBonSearch = ref('');
const selectedBank = ref(null);
const form = ref({
  date: '',
  payment_type: '',
  bank_id: null,
  selected_contra_bon_ids: [],
  notes: '',
});

// Transform banks untuk multiselect dengan display name yang include outlet
// Format sama seperti di BankAccount/Index: menggunakan outlet.nama_outlet
const banks = computed(() => {
  if (!props.banks || !Array.isArray(props.banks)) return [];
  return props.banks.map(bank => {
    // Gunakan outlet.nama_outlet jika ada, atau 'Head Office' jika null
    const outletName = bank.outlet?.nama_outlet || bank.outlet_name || 'Head Office';
    return {
      ...bank,
      display_name: `${bank.bank_name} - ${bank.account_number} (${bank.account_name}) - ${outletName}`
    };
  });
});

const file = ref(null);
const filePreview = ref(null);
const isImage = ref(false);
const isPdf = ref(false);
const existingBuktiPath = ref(null);

const totalBayar = computed(() => {
  return contraBons.value
    .filter(cb => form.value.selected_contra_bon_ids.includes(cb.id))
    .reduce((sum, cb) => {
      const amount = parseFloat(cb.total_amount) || 0;
      return sum + amount;
    }, 0);
});

// Filter contra bons based on search
const filteredContraBons = computed(() => {
  if (!contraBonSearch.value) {
    return contraBons.value;
  }
  
  const search = contraBonSearch.value.toLowerCase();
  return contraBons.value.filter(cb => {
    // Search in multiple fields
    const number = (cb.number || '').toLowerCase();
    const invoiceNumber = (cb.supplier_invoice_number || '').toLowerCase();
    const supplierName = (cb.supplier?.name || '').toLowerCase();
    const totalAmount = (cb.total_amount || '').toString().toLowerCase();
    const date = cb.date ? new Date(cb.date).toLocaleDateString('id-ID').toLowerCase() : '';
    const notes = (cb.notes || '').toLowerCase();
    const poNumber = (cb.purchaseOrder?.number || '').toLowerCase();
    const retailNumber = (cb.retailFood?.number || '').toLowerCase();
    const outletNames = (cb.outlet_names || []).join(' ').toLowerCase();
    const sourceType = (cb.source_type_display || '').toLowerCase();
    
    return number.includes(search) ||
           invoiceNumber.includes(search) ||
           supplierName.includes(search) ||
           totalAmount.includes(search) ||
           date.includes(search) ||
           notes.includes(search) ||
           poNumber.includes(search) ||
           retailNumber.includes(search) ||
           outletNames.includes(search) ||
           sourceType.includes(search);
  });
});

function formatDate(dateString) {
  if (!dateString) return '-';
  try {
    const date = new Date(dateString);
    return date.toLocaleDateString('id-ID', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric'
    });
  } catch (e) {
    return dateString;
  }
}

function selectAllContraBons() {
  form.value.selected_contra_bon_ids = filteredContraBons.value.map(cb => cb.id);
}

function deselectAllContraBons() {
  form.value.selected_contra_bon_ids = [];
}

function goBack() { 
  router.visit('/food-payments'); 
}

function onFileChange(e) {
  const f = e.target.files[0];
  file.value = f;
  if (!f) { 
    filePreview.value = null; 
    isImage.value = false; 
    isPdf.value = false; 
    return; 
  }
  if (f.type.startsWith('image/')) {
    isImage.value = true; 
    isPdf.value = false;
    const reader = new FileReader();
    reader.onload = ev => { 
      filePreview.value = ev.target.result; 
    };
    reader.readAsDataURL(f);
  } else if (f.type === 'application/pdf') {
    isImage.value = false; 
    isPdf.value = true;
    filePreview.value = URL.createObjectURL(f);
  } else {
    filePreview.value = null; 
    isImage.value = false; 
    isPdf.value = false;
  }
}

function formatCurrency(value) {
  if (value == null) return '-';
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(value);
}

async function onSupplierChange(supplier) {
  if (!isEditMode.value) {
    form.value.selected_contra_bon_ids = [];
  }
  contraBons.value = [];
  contraBonSearch.value = '';
  if (!supplier || !supplier.id) return;
  try {
    const res = await axios.get('/api/food-payments/contra-bon-unpaid', {
      params: {
        supplier_id: supplier.id
      }
    });
    // Pastikan total_amount adalah number
    let availableContraBons = res.data.map(cb => ({
      ...cb,
      total_amount: parseFloat(cb.total_amount) || 0
    }));
    
    // Jika edit mode, tambahkan contra bon yang sudah dipilih meskipun sudah paid
    if (isEditMode.value && props.payment?.contra_bons) {
      const selectedIds = props.payment.contra_bons.map(cb => cb.id);
      const selectedContraBons = props.payment.contra_bons.map(cb => ({
        ...cb,
        total_amount: parseFloat(cb.total_amount || cb.pivot?.total_amount || 0)
      }));
      // Gabungkan dengan yang available, pastikan tidak duplikat
      const existingIds = availableContraBons.map(cb => cb.id);
      selectedContraBons.forEach(cb => {
        if (!existingIds.includes(cb.id)) {
          availableContraBons.push(cb);
        }
      });
    }
    
    contraBons.value = availableContraBons;
  } catch (e) {
    contraBons.value = [];
    Swal.fire('Error', 'Gagal mengambil data Contra Bon', 'error');
  }
}

function onSupplierRemove() {
  form.value.selected_contra_bon_ids = [];
  contraBons.value = [];
  contraBonSearch.value = '';
  selectedSupplier.value = null;
}

function onBankSelect(bank) {
  if (bank && bank.id) {
    form.value.bank_id = bank.id;
  }
}

function onBankRemove() {
  form.value.bank_id = null;
  selectedBank.value = null;
}

function isImageFile(path) {
  return path && (path.endsWith('.jpg') || path.endsWith('.jpeg') || path.endsWith('.png'));
}

async function onSubmit() {
  if (!selectedSupplier.value || !selectedSupplier.value.id) {
    Swal.fire('Error', 'Pilih supplier terlebih dahulu', 'error');
    return;
  }
  try {
    Swal.fire({
      title: isEditMode.value ? 'Memperbarui Data...' : 'Menyimpan Data...',
      allowOutsideClick: false,
      showConfirmButton: false,
      didOpen: () => Swal.showLoading(),
    });

    const formData = new FormData();
    formData.append('date', form.value.date);
    formData.append('payment_type', form.value.payment_type);
    if (form.value.bank_id) {
      formData.append('bank_id', form.value.bank_id);
    }
    formData.append('notes', form.value.notes);
    formData.append('supplier_id', selectedSupplier.value.id);
    form.value.selected_contra_bon_ids.forEach(id => {
      formData.append('contra_bon_ids[]', id);
    });
    if (file.value) {
      formData.append('bukti_transfer', file.value);
    }

    const url = isEditMode.value 
      ? `/food-payments/${props.payment.id}`
      : '/food-payments';
    const method = isEditMode.value ? 'put' : 'post';

    const response = await axios[method](url, formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    });

    Swal.fire('Berhasil', isEditMode.value ? 'Data berhasil diperbarui' : 'Data berhasil disimpan', 'success').then(() => {
      router.visit('/food-payments');
    });
  } catch (error) {
    Swal.close();
    if (error.response?.data?.errors) {
      // Handle validation errors
      const errors = error.response.data.errors;
      Object.keys(errors).forEach(key => {
        Swal.fire('Error', errors[key][0], 'error');
      });
    } else {
      Swal.fire('Error', `Terjadi kesalahan saat ${isEditMode.value ? 'memperbarui' : 'menyimpan'} data`, 'error');
    }
  }
}

onMounted(async () => {
  try {
    const res = await axios.get('/api/suppliers');
    suppliers.value = res.data || [];
  } catch (e) {
    suppliers.value = [];
    Swal.fire('Error', 'Gagal mengambil data supplier', 'error');
  }

  if (isEditMode.value && props.payment) {
    // Load data payment untuk edit
    form.value.date = props.payment.date || '';
    form.value.payment_type = props.payment.payment_type || '';
    form.value.bank_id = props.payment.bank_id || null;
    form.value.notes = props.payment.notes || '';
    
    // Set selected bank object
    if (props.payment.bank_id && banks.value.length > 0) {
      const bank = banks.value.find(b => b.id == props.payment.bank_id);
      if (bank) {
        selectedBank.value = bank;
      }
    }
    
    // Set selected supplier object - pastikan suppliers sudah ter-load
    if (props.payment.supplier_id && suppliers.value.length > 0) {
      const supplier = suppliers.value.find(s => s.id == props.payment.supplier_id);
      if (supplier) {
        selectedSupplier.value = supplier;
        // Trigger load contra bon untuk supplier ini
        if (props.payment.contra_bons && props.payment.contra_bons.length > 0) {
          form.value.selected_contra_bon_ids = props.payment.contra_bons.map(cb => cb.id);
          await onSupplierChange(supplier);
        }
      }
    }
    
    existingBuktiPath.value = props.payment.bukti_transfer_path || null;
  } else {
    // Set default date ke hari ini untuk create mode
    const today = new Date();
    const yyyy = today.getFullYear();
    const mm = String(today.getMonth() + 1).padStart(2, '0');
    const dd = String(today.getDate()).padStart(2, '0');
    form.value.date = `${yyyy}-${mm}-${dd}`;
  }
});
</script>

<script>
export default { 
  components: {
    Multiselect
  },
  filters: { 
    currency(val) { 
      return 'Rp ' + Number(val).toLocaleString('id-ID'); 
    } 
  } 
}
</script>

<style scoped>
/* Custom multiselect styling */
.multiselect {
  min-height: 42px;
}

.multiselect :deep(.multiselect__tags) {
  border: 1px solid #d1d5db;
  border-radius: 0.5rem;
  box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
  min-height: 42px;
  padding: 8px 12px;
}

.multiselect :deep(.multiselect__placeholder) {
  color: #6b7280;
  padding-top: 8px;
  padding-bottom: 8px;
}

.multiselect :deep(.multiselect__single) {
  color: #111827;
  padding-top: 8px;
  padding-bottom: 8px;
}

.multiselect :deep(.multiselect__input) {
  border: none;
  padding: 0;
  margin: 0;
  min-height: auto;
}

.multiselect :deep(.multiselect__input:focus) {
  outline: none;
}

.multiselect :deep(.multiselect__content-wrapper) {
  border: 1px solid #d1d5db;
  border-radius: 0.5rem;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.multiselect :deep(.multiselect__option--highlight) {
  background: #3b82f6;
  color: white;
}

.multiselect :deep(.multiselect__option--selected) {
  background: #eff6ff;
  color: #1e40af;
  font-weight: 600;
}

.multiselect :deep(.multiselect__option--selected.multiselect__option--highlight) {
  background: #3b82f6;
  color: white;
}
</style> 