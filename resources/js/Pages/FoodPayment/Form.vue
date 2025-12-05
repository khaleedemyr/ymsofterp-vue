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
          <h3 class="font-bold mb-2">Pilih Contra Bon yang akan dibayar</h3>
          <div class="border rounded p-2 max-h-60 overflow-y-auto bg-white">
            <div v-if="contraBons.length === 0" class="text-gray-400 text-sm p-2">Tidak ada contra bon yang belum dibayar untuk supplier ini.</div>
            <div v-for="cb in contraBons" :key="cb.id" class="flex items-center mb-2 p-3 hover:bg-blue-50 rounded border border-gray-200">
              <input type="checkbox" :value="cb.id" v-model="form.selected_contra_bon_ids" class="mr-3" />
              <div class="flex-1">
                <div class="flex items-center gap-2 mb-1">
                  <span class="font-medium">{{ cb.number }}</span>
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
                <div class="text-sm text-gray-600">
                  <div>Total: {{ formatCurrency(cb.total_amount) }}</div>
                  <div v-if="cb.supplier_invoice_number" class="text-xs text-gray-500">
                    No. Invoice: {{ cb.supplier_invoice_number }}
                  </div>
                  <div v-if="cb.outlet_names && cb.outlet_names.length > 0" class="text-xs text-orange-600 mt-1">
                    <i class="fa fa-map-marker-alt mr-1"></i>
                    Outlet: {{ cb.outlet_names.join(', ') }}
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="mt-4 text-right font-bold text-lg text-blue-700">
            Total Bayar: {{ formatCurrency(totalBayar) }}
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
  }
});

const isEditMode = computed(() => !!props.payment);

const suppliers = ref([]);
const selectedSupplier = ref(null);
const contraBons = ref([]);
const form = ref({
  date: '',
  payment_type: '',
  selected_contra_bon_ids: [],
  notes: '',
});

const file = ref(null);
const filePreview = ref(null);
const isImage = ref(false);
const isPdf = ref(false);
const existingBuktiPath = ref(null);

const totalBayar = computed(() => {
  return contraBons.value
    .filter(cb => form.value.selected_contra_bon_ids.includes(cb.id))
    .reduce((sum, cb) => sum + (cb.total_amount || 0), 0);
});

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
  if (!supplier || !supplier.id) return;
  try {
    const res = await axios.get('/api/food-payments/contra-bon-unpaid');
    // Filter hanya yang supplier_id sesuai
    let availableContraBons = res.data.filter(cb => cb.supplier_id == supplier.id);
    
    // Jika edit mode, tambahkan contra bon yang sudah dipilih meskipun sudah paid
    if (isEditMode.value && props.payment?.contra_bons) {
      const selectedIds = props.payment.contra_bons.map(cb => cb.id);
      const selectedContraBons = props.payment.contra_bons.map(cb => ({
        ...cb,
        total_amount: cb.total_amount || cb.pivot?.total_amount || 0
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
  selectedSupplier.value = null;
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
    form.value.notes = props.payment.notes || '';
    
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