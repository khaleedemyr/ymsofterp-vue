<template>
  <div>
    <div class="flex items-center gap-2 mb-6">
      <div class="p-2 bg-blue-100 rounded-lg">
        <i class="fa-solid fa-industry text-blue-600"></i>
      </div>
      <h2 class="text-xl font-bold text-gray-800">Form Produksi Baru</h2>
    </div>

    <!-- Warehouse Selection -->
    <div class="mb-6">
      <label class="block text-sm font-semibold text-gray-700 mb-2">
        <i class="fa-solid fa-warehouse text-blue-500 mr-2"></i> Warehouse
      </label>
      <select 
        v-model="form.warehouse_id" 
        class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all bg-white" 
        required
      >
        <option value="" disabled>Pilih Warehouse</option>
        <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
      </select>
    </div>

    <form @submit.prevent="submit" class="space-y-6">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">
            <i class="fa-solid fa-calendar-day text-blue-500 mr-2"></i> Tanggal Produksi
          </label>
          <input 
            type="date" 
            v-model="form.production_date" 
            class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all" 
            required 
            :disabled="!form.warehouse_id" 
          />
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">
            <i class="fa-solid fa-barcode text-blue-500 mr-2"></i> Batch Number
          </label>
          <input 
            type="text" 
            v-model="form.batch_number" 
            class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all" 
            placeholder="Batch/No Lot" 
            :disabled="!form.warehouse_id" 
          />
        </div>
        <div class="md:col-span-2">
          <label class="block text-sm font-semibold text-gray-700 mb-2">
            <i class="fa-solid fa-box text-blue-500 mr-2"></i> Item Hasil Produksi
          </label>
          <Multiselect
            v-model="form.item_id"
            :options="items"
            :searchable="true"
            :close-on-select="true"
            :clear-on-select="false"
            :preserve-search="true"
            placeholder="Pilih atau cari item..."
            track-by="id"
            label="name"
            :preselect-first="false"
            class="w-full"
            @select="onItemChange"
          />
          <div v-if="itemsWithBom && itemsWithBom.length" class="text-xs text-gray-600 mt-2 bg-blue-50 px-3 py-2 rounded-lg border border-blue-200">
            <i class="fa-solid fa-lightbulb text-blue-500 mr-1"></i>
            <strong>{{ itemsWithBom.length }}</strong> item tersedia dengan BOM. 
            <span v-if="form.item_id && !isItemHasBom(form.item_id)" class="text-orange-600 font-semibold">
              Item yang dipilih tidak memiliki BOM.
            </span>
          </div>
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">
            <i class="fa-solid fa-calculator text-blue-500 mr-2"></i> Qty Produksi
          </label>
          <input 
            type="number" 
            min="0" 
            step="0.01" 
            v-model.number="form.qty" 
            class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all" 
            required 
            @input="onQtyChange" 
            :disabled="!form.warehouse_id || !form.item_id"
            placeholder="0.00"
          />
          <p class="text-xs text-gray-500 mt-1">Bisa menggunakan desimal (contoh: 0.5, 1.25)</p>
        </div>
        <div class="md:col-span-2 flex gap-4 items-end">
          <div class="flex-1">
            <label class="block text-sm font-semibold text-gray-700 mb-2">
              <i class="fa-solid fa-check-circle text-blue-500 mr-2"></i> Qty Jadi
            </label>
            <input 
              type="number" 
              min="0" 
              step="0.01" 
              v-model.number="form.qty_jadi" 
              class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all" 
              :disabled="!form.item_id"
              placeholder="0.00"
            />
          </div>
          <div class="w-40">
            <label class="block text-sm font-semibold text-gray-700 mb-2">
              <i class="fa-solid fa-ruler text-blue-500 mr-2"></i> Unit
            </label>
            <select 
              v-model="form.unit_jadi" 
              class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all bg-white" 
              :disabled="!form.item_id"
            >
              <option value="" disabled>Pilih Unit</option>
              <option v-for="u in unitOptions" :key="u.id" :value="u.id">{{ u.name }}</option>
            </select>
          </div>
        </div>
        <div class="md:col-span-2">
          <label class="block text-sm font-semibold text-gray-700 mb-2">
            <i class="fa-solid fa-note-sticky text-blue-500 mr-2"></i> Catatan
          </label>
          <textarea 
            v-model="form.notes" 
            class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all resize-none" 
            rows="3" 
            placeholder="Catatan tambahan (opsional)" 
            :disabled="!form.warehouse_id"
          ></textarea>
        </div>
      </div>
      <!-- BOM Table -->
      <div v-if="bom.length" class="mt-6 bg-gray-50 rounded-xl p-4 border border-gray-200">
        <div class="flex items-center gap-2 mb-4">
          <div class="p-2 bg-amber-100 rounded-lg">
            <i class="fa-solid fa-list-check text-amber-600"></i>
          </div>
          <h3 class="font-bold text-gray-800">Bahan Baku (BOM)</h3>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full bg-white rounded-lg shadow-sm">
            <thead class="bg-gradient-to-r from-amber-600 to-amber-700">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-bold text-white uppercase tracking-wider">
                  <div class="flex items-center gap-2">
                    <i class="fa-solid fa-box text-amber-200"></i>
                    <span>Bahan</span>
                  </div>
                </th>
                <th class="px-4 py-3 text-right text-xs font-bold text-white uppercase tracking-wider">
                  <div class="flex items-center justify-end gap-2">
                    <i class="fa-solid fa-calculator text-amber-200"></i>
                    <span>Qty/1</span>
                  </div>
                </th>
                <th class="px-4 py-3 text-right text-xs font-bold text-white uppercase tracking-wider">
                  <div class="flex items-center justify-end gap-2">
                    <i class="fa-solid fa-sigma text-amber-200"></i>
                    <span>Total</span>
                  </div>
                </th>
                <th class="px-4 py-3 text-right text-xs font-bold text-white uppercase tracking-wider">
                  <div class="flex items-center justify-end gap-2">
                    <i class="fa-solid fa-ruler text-amber-200"></i>
                    <span>Unit</span>
                  </div>
                </th>
                <th class="px-4 py-3 text-right text-xs font-bold text-white uppercase tracking-wider">
                  <div class="flex items-center justify-end gap-2">
                    <i class="fa-solid fa-warehouse text-amber-200"></i>
                    <span>Stok</span>
                  </div>
                </th>
                <th class="px-4 py-3 text-right text-xs font-bold text-white uppercase tracking-wider">
                  <div class="flex items-center justify-end gap-2">
                    <i class="fa-solid fa-balance-scale text-amber-200"></i>
                    <span>Sisa</span>
                  </div>
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
              <tr 
                v-for="b in bom" 
                :key="b.material_item_id" 
                :class="b.sisa < 0 ? 'bg-red-50 hover:bg-red-100' : 'hover:bg-amber-50'"
                class="transition-colors"
              >
                <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ b.material_name }}</td>
                <td class="px-4 py-3 text-sm text-right text-gray-700">{{ formatNumber(b.qty_per_1) }}</td>
                <td class="px-4 py-3 text-sm text-right text-gray-700 font-semibold">{{ formatNumber(b.qty_total) }}</td>
                <td class="px-4 py-3 text-sm text-right">
                  <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-gray-100 text-gray-800">
                    {{ b.unit_name }}
                  </span>
                </td>
                <td class="px-4 py-3 text-sm text-right text-gray-700">{{ formatNumber(b.stok) }}</td>
                <td class="px-4 py-3 text-sm text-right font-bold" :class="b.sisa < 0 ? 'text-red-600' : 'text-green-700'">
                  <span :class="b.sisa < 0 ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'" class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold">
                    {{ formatNumber(b.sisa) }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-if="bom.some(b => b.sisa < 0)" class="mt-4 p-3 bg-red-50 border border-red-200 rounded-lg">
          <div class="flex items-center gap-2 text-red-700 font-semibold">
            <i class="fa-solid fa-exclamation-triangle"></i>
            <span>Stok bahan tidak cukup untuk produksi!</span>
          </div>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="flex justify-end gap-3 mt-8 pt-6 border-t border-gray-200">
        <button 
          type="button" 
          class="px-6 py-2.5 text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-xl transition-all font-semibold border-2 border-gray-200 hover:border-gray-300"
          @click="$emit('cancel')"
        >
          <i class="fa-solid fa-times mr-2"></i> Batal
        </button>
        <button 
          type="submit" 
          class="px-6 py-2.5 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white rounded-xl shadow-lg hover:shadow-xl transition-all font-semibold transform hover:-translate-y-0.5"
          :disabled="loading || bom.some(b => b.sisa < 0) || !form.item_id || !form.qty || form.qty <= 0"
        >
          <span v-if="loading" class="animate-spin mr-2 inline-block">
            <i class="fa fa-spinner"></i>
          </span>
          <span v-else>
            <i class="fa-solid fa-save mr-2"></i>
          </span>
          Simpan Produksi
        </button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, watch, computed } from 'vue'
import axios from 'axios'
import { usePage } from '@inertiajs/vue3'
import Swal from 'sweetalert2'
import Multiselect from 'vue-multiselect'
import 'vue-multiselect/dist/vue-multiselect.min.css'

const props = defineProps({
  items: Array,
  warehouses: Array,
  loading: Boolean,
  itemsWithBom: Array,
})

const emit = defineEmits(['submitted', 'cancel'])

const form = ref({
  warehouse_id: '',
  production_date: new Date().toISOString().slice(0, 10),
  batch_number: '',
  item_id: null,
  qty: 0,
  notes: '',
  unit_id: '',
  qty_jadi: '',
  unit_jadi: '',
})
const bom = ref([])
const unitName = ref('')
const loading = ref(false)
const unitOptions = computed(() => {
  // Debug form.item_id
  
  // Extract item ID dengan cara yang lebih aman
  let itemId = null
  if (form.value.item_id) {
    if (typeof form.value.item_id === 'object' && form.value.item_id.id) {
      itemId = form.value.item_id.id
    } else if (typeof form.value.item_id === 'number' || typeof form.value.item_id === 'string') {
      itemId = parseInt(form.value.item_id)
    }
  }
  
  if (!itemId) {
    return []
  }
  
  // Cari item berdasarkan ID yang sudah diextract
  const item = props.items.find(i => i.id === itemId)
  
  if (!item) {
    return []
  }
  
  const opts = []
  
  // Tambahkan unit berdasarkan urutan: Small -> Medium -> Large
  // Pastikan unit_id ada dan unit_name tidak null/empty
  if (item.small_unit_id && item.small_unit_name) {
    opts.push({ 
      id: item.small_unit_id, 
      name: item.small_unit_name 
    })
  }
  if (item.medium_unit_id && item.medium_unit_name && item.medium_unit_id !== item.small_unit_id) {
    opts.push({ 
      id: item.medium_unit_id, 
      name: item.medium_unit_name 
    })
  }
  if (item.large_unit_id && item.large_unit_name && 
      item.large_unit_id !== item.small_unit_id && 
      item.large_unit_id !== item.medium_unit_id) {
    opts.push({ 
      id: item.large_unit_id, 
      name: item.large_unit_name 
    })
  }
  
  return opts
})

const onItemChange = (selectedItem) => {
  
  if (!selectedItem) {
    form.value.item_id = null
    form.value.unit_id = ''
    form.value.unit_jadi = ''
    unitName.value = ''
    return
  }
  
  // Set form.item_id ke objek item yang dipilih
  form.value.item_id = selectedItem
  
  // Set unit_id ke small_unit_id sebagai default
  form.value.unit_id = selectedItem.small_unit_id || ''
  form.value.unit_jadi = selectedItem.small_unit_id || ''
  unitName.value = selectedItem.small_unit_name || ''
  
  
  fetchBom()
}

function onQtyChange() {
  fetchBom()
}

function fetchBom() {
  const itemId = form.value.item_id?.id || form.value.item_id
  if (!itemId || !form.value.qty || !form.value.warehouse_id) {
    bom.value = []
    return
  }
  
  const requestData = { 
    item_id: itemId, 
    qty: form.value.qty, 
    warehouse_id: form.value.warehouse_id 
  }
  
  
  loading.value = true
  axios.post('/mk-production/bom', requestData, {
    headers: {
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
    }
  })
    .then(res => {
      
      // Cek apakah ada error message
      if (res.data.error) {
        Swal.fire({
          icon: 'warning',
          title: 'Item Tidak Memiliki BOM',
          text: res.data.error,
          confirmButtonText: 'OK'
        })
        bom.value = []
        return
      }
      
      bom.value = res.data
    })
    .catch(error => {
      bom.value = []
    })
    .finally(() => loading.value = false)
}

function submit() {
  if (bom.value.some(b => b.sisa < 0)) return
  loading.value = true
  const itemId = form.value.item_id?.id || form.value.item_id
  const payload = {
    ...form.value,
    item_id: itemId,
    qty: form.value.qty,
    unit_id: form.value.unit_jadi || form.value.unit_id,
  }
  axios.post('/mk-production', payload, {
    headers: {
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
    }
  })
    .then(() => {
      Swal.fire({
        icon: 'success',
        title: 'Berhasil',
        text: 'Produksi berhasil disimpan!',
        timer: 1500,
        showConfirmButton: false
      }).then(() => {
        emit('submitted')
      })
    })
    .catch(e => {
      Swal.fire({
        icon: 'error',
        title: 'Gagal',
        text: e.response?.data?.message || 'Gagal menyimpan produksi',
      })
    })
    .finally(() => loading.value = false)
}

function formatNumber(val) {
  return Number(val).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function isItemHasBom(itemId) {
  const item = typeof itemId === 'object' ? itemId : { id: itemId }
  return props.itemsWithBom?.some(bomItem => bomItem.id == item.id) || false
}

watch(() => form.value.item_id, () => {
  form.value.unit_jadi = ''
})

// Watch warehouse change to refetch BOM
watch(() => form.value.warehouse_id, () => {
  if (form.value.item_id) {
    fetchBom()
  }
})
</script>

<style scoped>
.input {
  @apply border rounded px-2 py-1 focus:outline-none focus:ring-2 focus:ring-blue-300;
}
.btn {
  @apply px-4 py-2 rounded font-semibold shadow transition;
}
.btn-primary {
  @apply bg-blue-600 text-white hover:bg-blue-700;
}
.btn-ghost {
  @apply bg-gray-100 hover:bg-gray-200;
}

/* Custom styling for vue-multiselect */
:deep(.multiselect) {
  min-height: 38px;
  border: 1px solid #d1d5db;
  border-radius: 0.5rem;
}

:deep(.multiselect:focus-within) {
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

:deep(.multiselect__placeholder) {
  color: #6b7280;
  font-size: 0.875rem;
  padding: 8px 12px;
}

:deep(.multiselect__single) {
  padding: 8px 12px;
  font-size: 0.875rem;
  color: #374151;
}

:deep(.multiselect__input) {
  padding: 8px 12px;
  font-size: 0.875rem;
}

:deep(.multiselect__content-wrapper) {
  border: 1px solid #d1d5db;
  border-radius: 0.5rem;
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
}

:deep(.multiselect__option) {
  padding: 8px 12px;
  font-size: 0.875rem;
}

:deep(.multiselect__option--highlight) {
  background: #3b82f6;
  color: white;
}

:deep(.multiselect__option--selected) {
  background: #dbeafe;
  color: #1e40af;
}
</style> 