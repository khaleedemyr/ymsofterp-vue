<template>
  <div>
    <h2 class="text-lg font-bold mb-4 flex items-center gap-2">
      <i class="fa-solid fa-industry text-blue-500"></i> Buat Produksi WIP Baru
    </h2>
    
    <form @submit.prevent="submit" class="space-y-4">
      <!-- Outlet Selection (for superuser) -->
      <div v-if="user_outlet_id == 1">
        <label class="block text-sm font-medium text-gray-700 mb-1">Outlet</label>
        <select v-model="form.outlet_id" @change="onOutletChange" class="input input-bordered w-full" required>
          <option value="" disabled>Pilih Outlet</option>
          <option v-for="outlet in outlets" :key="outlet.id" :value="outlet.id">{{ outlet.name }}</option>
        </select>
      </div>
      
      <!-- Warehouse Outlet Selection -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Warehouse Outlet</label>
        <select v-model="form.warehouse_outlet_id" @change="onWarehouseChange" class="input input-bordered w-full" required>
          <option value="" disabled>Pilih Warehouse Outlet</option>
          <option v-for="warehouse in filteredWarehouseOutlets" :key="warehouse.id" :value="warehouse.id">{{ warehouse.name }}</option>
        </select>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Produksi</label>
          <input type="date" v-model="form.production_date" class="input input-bordered w-full" required :disabled="!form.warehouse_outlet_id" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Batch Number</label>
          <input type="text" v-model="form.batch_number" class="input input-bordered w-full" placeholder="Batch/No Lot" :disabled="!form.warehouse_outlet_id" />
        </div>
        <div class="md:col-span-2">
          <label class="block text-sm font-medium text-gray-700 mb-1">Item Hasil Produksi</label>
          <multiselect
            v-model="selectedItem"
            :options="items"
            :searchable="true"
            :close-on-select="true"
            :show-labels="false"
            placeholder="Cari dan pilih item..."
            label="name"
            track-by="id"
            :disabled="!form.warehouse_outlet_id"
            @select="onItemSelect"
            @remove="onItemRemove"
            class="multiselect-custom"
          >
            <template #option="{ option }">
              <div class="flex items-center justify-between">
                <div>
                  <div class="font-medium text-gray-900">{{ option.name }}</div>
                  <div class="text-sm text-gray-500">
                    <span v-if="option.small_unit_name">Small: {{ option.small_unit_name }}</span>
                    <span v-if="option.medium_unit_name"> | Medium: {{ option.medium_unit_name }}</span>
                    <span v-if="option.large_unit_name"> | Large: {{ option.large_unit_name }}</span>
                  </div>
                </div>
              </div>
            </template>
            <template #noResult>
              <div class="text-center py-2 text-gray-500">
                <i class="fa-solid fa-search mr-2"></i>
                Tidak ada item yang ditemukan
              </div>
            </template>
            <template #noOptions>
              <div class="text-center py-2 text-gray-500">
                <i class="fa-solid fa-box mr-2"></i>
                Tidak ada item tersedia
              </div>
            </template>
          </multiselect>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Qty Produksi</label>
          <input type="number" min="1" v-model.number="form.qty" class="input input-bordered w-full" required @input="onQtyChange" :disabled="!form.warehouse_outlet_id || !form.item_id" />
        </div>
        <div class="md:col-span-2 flex gap-2 items-end">
          <div class="flex-1">
            <label class="block text-sm font-medium text-gray-700 mb-1">Qty Jadi</label>
            <input type="number" min="0" step="0.01" v-model.number="form.qty_jadi" class="input input-bordered w-full" required :disabled="!form.warehouse_outlet_id || !form.item_id" />
          </div>
          <div class="flex-1">
            <label class="block text-sm font-medium text-gray-700 mb-1">Unit</label>
            <select v-model="form.unit_id" class="input input-bordered w-full" required :disabled="!form.warehouse_outlet_id || !form.item_id">
              <option value="" disabled>Pilih Unit</option>
              <option v-if="selectedItemData?.small_unit_id" :value="selectedItemData.small_unit_id">{{ selectedItemData.small_unit_name }}</option>
              <option v-if="selectedItemData?.medium_unit_id" :value="selectedItemData.medium_unit_id">{{ selectedItemData.medium_unit_name }}</option>
              <option v-if="selectedItemData?.large_unit_id" :value="selectedItemData.large_unit_id">{{ selectedItemData.large_unit_name }}</option>
            </select>
          </div>
        </div>
        <div class="md:col-span-2">
          <label class="block text-sm font-medium text-gray-700 mb-1">Catatan</label>
          <textarea v-model="form.notes" class="input input-bordered w-full" rows="3" placeholder="Catatan produksi (opsional)"></textarea>
        </div>
      </div>

      <!-- BOM Preview -->
      <div v-if="bom.length > 0" class="mt-6">
        <h3 class="text-md font-semibold text-gray-700 mb-3">Bill of Materials (BOM)</h3>
        <div class="bg-gray-50 rounded-lg p-4">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b">
                <th class="text-left py-2">Material</th>
                <th class="text-left py-2">Qty Dibutuhkan</th>
                <th class="text-left py-2">Stok Tersedia</th>
                <th class="text-left py-2">Status</th>
              </tr>
            </thead>
            <tbody>
                             <tr v-for="item in bom" :key="item.material_item_id" class="border-b">
                 <td class="py-2">{{ item.material_name }}</td>
                 <td class="py-2">{{ formatNumber(item.qty_needed) }} {{ item.material_unit_name }}</td>
                 <td class="py-2">{{ formatNumber(item.stock) }} {{ item.material_unit_name }}</td>
                 <td class="py-2">
                   <span :class="item.sufficient ? 'text-green-600' : 'text-red-600'" class="font-semibold">
                     {{ item.sufficient ? '✓ Cukup' : '✗ Kurang' }}
                   </span>
                 </td>
               </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Submit Buttons -->
      <div class="flex justify-end gap-3 pt-6 border-t">
        <button type="button" @click="$emit('cancel')" class="btn btn-outline">
          Batal
        </button>
        <button type="submit" :disabled="isSubmitting || !canSubmit" class="btn btn-primary">
          <span v-if="isSubmitting" class="loading loading-spinner loading-sm"></span>
          {{ isSubmitting ? 'Menyimpan...' : 'Simpan Produksi' }}
        </button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import axios from 'axios'
import Multiselect from 'vue-multiselect'
import 'vue-multiselect/dist/vue-multiselect.min.css'
import Swal from 'sweetalert2'

const props = defineProps({
  items: Array,
  warehouse_outlets: Array,
  outlets: Array,
  user_outlet_id: Number,
})

const emit = defineEmits(['submitted', 'cancel'])

const form = useForm({
  outlet_id: props.user_outlet_id !== 1 ? props.user_outlet_id : '',
  warehouse_outlet_id: '',
  production_date: new Date().toISOString().split('T')[0],
  batch_number: '',
  item_id: '',
  qty: 1,
  qty_jadi: 0,
  unit_id: '',
  notes: '',
})

const bom = ref([])
const isSubmitting = ref(false)
const selectedItem = ref(null)
const selectedItemData = computed(() => props.items.find(item => item.id == form.item_id))

// Filter warehouse outlets based on selected outlet
const filteredWarehouseOutlets = computed(() => {
  if (props.user_outlet_id !== 1) {
    return props.warehouse_outlets.filter(w => w.outlet_id == props.user_outlet_id)
  }
  if (form.outlet_id) {
    return props.warehouse_outlets.filter(w => w.outlet_id == form.outlet_id)
  }
  return props.warehouse_outlets
})

const canSubmit = computed(() => {
  return form.outlet_id && 
         form.warehouse_outlet_id && 
         form.production_date && 
         form.item_id && 
         form.qty > 0 && 
         form.qty_jadi > 0 && 
         form.unit_id &&
         bom.value.length > 0 &&
         bom.value.every(item => item.sufficient)
})

function onOutletChange() {
  form.warehouse_outlet_id = ''
  form.item_id = ''
  form.unit_id = ''
  bom.value = []
}

function onWarehouseChange() {
  form.item_id = ''
  form.unit_id = ''
  bom.value = []
}

function onItemSelect(item) {
  form.item_id = item.id
  form.unit_id = ''
  bom.value = []
  if (form.item_id && form.qty > 0) {
    fetchBom()
  }
}

function onItemRemove() {
  form.item_id = ''
  form.unit_id = ''
  bom.value = []
  selectedItem.value = null
}

function onItemChange() {
  form.unit_id = ''
  bom.value = []
  if (form.item_id && form.qty > 0) {
    fetchBom()
  }
}

function onQtyChange() {
  if (form.item_id && form.qty > 0) {
    fetchBom()
  } else {
    bom.value = []
  }
}

async function fetchBom() {
  if (!form.item_id || !form.qty || !form.outlet_id || !form.warehouse_outlet_id) {
    bom.value = []
    return
  }

  try {
    const response = await axios.post('/outlet-wip/bom', {
      item_id: form.item_id,
      qty: form.qty,
      outlet_id: form.outlet_id,
      warehouse_outlet_id: form.warehouse_outlet_id
    })
    
    // Cek apakah item memiliki BOM
    if (response.data.length === 0) {
      const selectedItemName = selectedItemData.value?.name || 'Item yang dipilih'
      
      await Swal.fire({
        icon: 'warning',
        title: 'Item Tidak Memiliki BOM',
        html: `
          <div class="text-left">
            <p class="mb-3"><strong>${selectedItemName}</strong> tidak memiliki Bill of Materials (BOM).</p>
            <p class="text-sm text-gray-600 mb-3">Untuk melakukan produksi, item harus memiliki komposisi bahan yang telah didefinisikan.</p>
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
              <p class="text-sm text-blue-800 font-medium">
                <i class="fas fa-info-circle mr-2"></i>
                Segera hubungi Cost Control untuk konfirmasi Bill of Material
              </p>
            </div>
          </div>
        `,
        confirmButtonText: 'OK',
        confirmButtonColor: '#3b82f6',
        customClass: {
          popup: 'text-sm'
        }
      })
      
      // Reset form item selection
      form.item_id = ''
      form.unit_id = ''
      selectedItem.value = null
    }
    
    bom.value = response.data
  } catch (error) {
    console.error('Error fetching BOM:', error)
    bom.value = []
    
    // Tampilkan error jika ada masalah dengan request
    await Swal.fire({
      icon: 'error',
      title: 'Error',
      text: 'Terjadi kesalahan saat mengambil data BOM. Silakan coba lagi.',
      confirmButtonText: 'OK',
      confirmButtonColor: '#ef4444'
    })
  }
}

function formatNumber(value) {
  if (value === null || value === undefined) return '0.00'
  return new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  }).format(value)
}

function submit() {
  if (!canSubmit.value) return

  isSubmitting.value = true
  form.post(route('outlet-wip.store'), {
    onSuccess: () => {
      emit('submitted')
    },
    onError: () => {
      isSubmitting.value = false
    },
    onFinish: () => {
      isSubmitting.value = false
    }
  })
}

// Watch for changes in outlet_id to update warehouse_outlet_id
watch(() => form.outlet_id, (newValue) => {
  if (newValue && form.warehouse_outlet_id) {
    const warehouse = filteredWarehouseOutlets.value.find(w => w.id == form.warehouse_outlet_id)
    if (!warehouse) {
      form.warehouse_outlet_id = ''
    }
  }
})

// Watch for changes in form.item_id to update selectedItem
watch(() => form.item_id, (newValue) => {
  if (newValue) {
    selectedItem.value = props.items.find(item => item.id == newValue) || null
  } else {
    selectedItem.value = null
  }
})

// Watch for changes in selectedItem to update form.item_id
watch(selectedItem, (newValue) => {
  if (newValue) {
    form.item_id = newValue.id
  } else {
    form.item_id = ''
  }
})
</script>

<style scoped>
.input {
  @apply w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500;
}

.btn {
  @apply px-4 py-2 rounded-md font-medium transition-colors;
}

.btn-primary {
  @apply bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed;
}

.btn-outline {
  @apply border border-gray-300 text-gray-700 hover:bg-gray-50;
}

.loading {
  @apply animate-spin;
}

/* Custom multiselect styling */
.multiselect-custom {
  min-height: 42px;
}

.multiselect-custom .multiselect__tags {
  @apply border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500;
  min-height: 42px;
  padding: 8px 12px;
}

.multiselect-custom .multiselect__placeholder {
  @apply text-gray-500;
  padding-top: 8px;
  padding-bottom: 8px;
}

.multiselect-custom .multiselect__single {
  @apply text-gray-900;
  padding-top: 8px;
  padding-bottom: 8px;
}

.multiselect-custom .multiselect__input {
  @apply text-gray-900;
  padding-top: 8px;
  padding-bottom: 8px;
}

.multiselect-custom .multiselect__input::placeholder {
  @apply text-gray-500;
}

.multiselect-custom .multiselect__content-wrapper {
  @apply border border-gray-300 rounded-md shadow-lg;
  border-top: none;
  border-top-left-radius: 0;
  border-top-right-radius: 0;
}

.multiselect-custom .multiselect__option {
  @apply text-gray-900;
  padding: 12px 16px;
}

.multiselect-custom .multiselect__option--highlight {
  @apply bg-blue-50 text-blue-900;
}

.multiselect-custom .multiselect__option--selected {
  @apply bg-blue-100 text-blue-900;
}

.multiselect-custom .multiselect__clear {
  @apply text-gray-400;
}

.multiselect-custom .multiselect__clear:hover {
  @apply text-gray-600;
}

.multiselect-custom.multiselect--disabled .multiselect__tags {
  @apply bg-gray-100 border-gray-200;
}

.multiselect-custom.multiselect--disabled .multiselect__placeholder {
  @apply text-gray-400;
}
</style>
