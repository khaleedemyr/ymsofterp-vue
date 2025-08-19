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
          <select v-model="form.item_id" @change="onItemChange" class="input input-bordered w-full" required :disabled="!form.warehouse_outlet_id">
            <option value="" disabled>Pilih Item</option>
            <option v-for="item in items" :key="item.id" :value="item.id">{{ item.name }}</option>
          </select>
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
              <option v-if="selectedItem?.small_unit_id" :value="selectedItem.small_unit_id">{{ selectedItem.small_unit_name }}</option>
              <option v-if="selectedItem?.medium_unit_id" :value="selectedItem.medium_unit_id">{{ selectedItem.medium_unit_name }}</option>
              <option v-if="selectedItem?.large_unit_id" :value="selectedItem.large_unit_id">{{ selectedItem.large_unit_name }}</option>
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
const selectedItem = computed(() => props.items.find(item => item.id == form.item_id))

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
    bom.value = response.data
  } catch (error) {
    console.error('Error fetching BOM:', error)
    bom.value = []
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
</style>
