<template>
  <div>
    <h2 class="text-lg font-bold mb-4 flex items-center gap-2">
      <i class="fa-solid fa-industry text-blue-500"></i> Buat Produksi Baru
    </h2>
    <div class="mb-4">
      <label class="block text-sm font-medium text-gray-700 mb-1">Warehouse</label>
      <select v-model="form.warehouse_id" class="input input-bordered w-full" required>
        <option value="" disabled>Pilih Warehouse</option>
        <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
      </select>
    </div>
    <form @submit.prevent="submit" class="space-y-4">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Produksi</label>
          <input type="date" v-model="form.production_date" class="input input-bordered w-full" required :disabled="!form.warehouse_id" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Batch Number</label>
          <input type="text" v-model="form.batch_number" class="input input-bordered w-full" placeholder="Batch/No Lot" :disabled="!form.warehouse_id" />
        </div>
        <div class="md:col-span-2">
          <label class="block text-sm font-medium text-gray-700 mb-1">Item Hasil Produksi</label>
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
          <div v-if="itemsWithBom && itemsWithBom.length" class="text-xs text-gray-500 mt-1">
            💡 <strong>{{ itemsWithBom.length }}</strong> item tersedia dengan BOM. 
            <span v-if="form.item_id && !isItemHasBom(form.item_id)" class="text-orange-600">
              Item yang dipilih tidak memiliki BOM.
            </span>
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Qty Produksi</label>
          <input type="number" min="1" v-model.number="form.qty" class="input input-bordered w-full" required @input="onQtyChange" :disabled="!form.warehouse_id || !form.item_id" />
        </div>
        <div class="md:col-span-2 flex gap-2 items-end">
          <div class="flex-1">
            <label class="block text-sm font-medium text-gray-700 mb-1">Qty Jadi</label>
            <input type="number" min="0" v-model.number="form.qty_jadi" class="input input-bordered w-full" :disabled="!form.item_id" />
          </div>
          <div style="min-width:120px">
            <label class="block text-sm font-medium text-gray-700 mb-1">Unit</label>
            <select v-model="form.unit_jadi" class="input input-bordered w-full" :disabled="!form.item_id">
              <option value="" disabled>Pilih Unit</option>
              <option v-for="u in unitOptions" :key="u.id" :value="u.id">{{ u.name }}</option>
            </select>
          </div>
        </div>
        <div class="md:col-span-2">
          <label class="block text-sm font-medium text-gray-700 mb-1">Catatan</label>
          <textarea v-model="form.notes" class="input input-bordered w-full" rows="2" placeholder="Catatan tambahan" :disabled="!form.warehouse_id"></textarea>
        </div>
      </div>
      <div v-if="bom.length" class="mt-6">
        <h3 class="font-semibold mb-2 text-blue-700">Bahan Baku (BOM)</h3>
        <table class="min-w-full bg-white rounded shadow text-sm">
          <thead class="bg-blue-100">
            <tr>
              <th class="px-2 py-1 text-left">Bahan</th>
              <th class="px-2 py-1 text-right">Qty/1</th>
              <th class="px-2 py-1 text-right">Total</th>
              <th class="px-2 py-1 text-right">Unit</th>
              <th class="px-2 py-1 text-right">Stok</th>
              <th class="px-2 py-1 text-right">Sisa</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="b in bom" :key="b.material_item_id" :class="b.sisa < 0 ? 'bg-red-50' : ''">
              <td class="px-2 py-1">{{ b.material_name }}</td>
              <td class="px-2 py-1 text-right">{{ formatNumber(b.qty_per_1) }}</td>
              <td class="px-2 py-1 text-right">{{ formatNumber(b.qty_total) }}</td>
              <td class="px-2 py-1 text-right">{{ b.unit_name }}</td>
              <td class="px-2 py-1 text-right">{{ formatNumber(b.stok) }}</td>
              <td class="px-2 py-1 text-right font-bold" :class="b.sisa < 0 ? 'text-red-600' : 'text-green-700'">{{ formatNumber(b.sisa) }}</td>
            </tr>
          </tbody>
        </table>
        <div v-if="bom.some(b => b.sisa < 0)" class="text-red-600 mt-2">Stok bahan tidak cukup untuk produksi!</div>
      </div>
      <div class="flex justify-end gap-2 mt-6">
        <button type="button" class="btn btn-ghost" @click="$emit('cancel')">Batal</button>
        <button type="submit" class="btn btn-primary bg-blue-600 hover:bg-blue-700 text-white" :disabled="loading || bom.some(b => b.sisa < 0) || !form.item_id || !form.qty">
          <span v-if="loading" class="animate-spin mr-2"><i class="fa fa-spinner"></i></span>
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
  qty: 1,
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