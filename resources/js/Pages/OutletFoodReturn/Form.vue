<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-undo text-orange-500"></i> Buat Return Outlet Food
        </h1>
        <button @click="goBack" class="bg-gray-500 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
          <i class="fa fa-arrow-left mr-2"></i> Kembali
        </button>
      </div>

      <form @submit.prevent="submitForm" class="space-y-6">
        <!-- Basic Information -->
        <div class="bg-white rounded-xl shadow-lg p-6">
          <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fa-solid fa-info-circle text-orange-500"></i> Informasi Return
          </h3>
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Outlet Selection -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Pilih Outlet *</label>
              <select 
                v-model="form.outlet_id" 
                @change="loadWarehouseOutlets"
                :disabled="!canSelectOutlet"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                :class="{ 'bg-gray-100': !canSelectOutlet }"
                required
              >
                <option value="">-- Pilih Outlet --</option>
                <option 
                  v-for="outlet in props.outlets" 
                  :key="outlet.id_outlet" 
                  :value="outlet.id_outlet"
                >
                  {{ outlet.nama_outlet }}
                </option>
              </select>
              <p v-if="!canSelectOutlet" class="text-xs text-gray-500 mt-1">
                Outlet sudah ditentukan berdasarkan user login
              </p>
            </div>

            <!-- Warehouse Outlet Selection -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Pilih Warehouse Outlet *</label>
              <select 
                v-model="form.warehouse_outlet_id" 
                @change="loadGoodReceives"
                :disabled="!form.outlet_id || loadingWarehouseOutlets"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                :class="{ 'bg-gray-100': !form.outlet_id || loadingWarehouseOutlets }"
                required
              >
                <option value="">-- Pilih Warehouse Outlet --</option>
                <option 
                  v-for="warehouse in warehouseOutlets" 
                  :key="warehouse.id" 
                  :value="warehouse.id"
                >
                  {{ warehouse.name }}
                </option>
              </select>
              <p v-if="loadingWarehouseOutlets" class="text-xs text-blue-600 mt-1">
                <i class="fa fa-spinner fa-spin mr-1"></i> Memuat warehouse outlet...
              </p>
              <p v-else-if="!form.outlet_id" class="text-xs text-gray-500 mt-1">
                Pilih outlet terlebih dahulu
              </p>
            </div>

            <!-- Good Receive Selection -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Pilih Good Receive *</label>
              <select 
                v-model="form.outlet_food_good_receive_id" 
                @change="loadGoodReceiveItems"
                :disabled="!form.warehouse_outlet_id || loadingGoodReceives"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                :class="{ 'bg-gray-100': !form.warehouse_outlet_id || loadingGoodReceives }"
                required
              >
                <option value="">-- Pilih Good Receive --</option>
                <option 
                  v-for="gr in goodReceives" 
                  :key="gr.id" 
                  :value="gr.id"
                >
                  {{ gr.number }} - {{ formatDate(gr.receive_date) }}
                </option>
              </select>
              <p v-if="loadingGoodReceives" class="text-xs text-blue-600 mt-1">
                <i class="fa fa-spinner fa-spin mr-1"></i> Memuat Good Receive...
              </p>
              <p v-else-if="!form.warehouse_outlet_id" class="text-xs text-gray-500 mt-1">
                Pilih warehouse outlet terlebih dahulu
              </p>
              <p v-else-if="form.warehouse_outlet_id && goodReceives.length === 0" class="text-xs text-orange-600 mt-1">
                Tidak ada Good Receive dalam 24 jam terakhir
              </p>
            </div>

            <!-- Return Date -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Return *</label>
              <input 
                type="date" 
                v-model="form.return_date" 
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                required
              />
            </div>

            <!-- Notes -->
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-1">Catatan</label>
              <textarea 
                v-model="form.notes" 
                rows="3"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                placeholder="Catatan untuk return ini..."
              ></textarea>
            </div>
          </div>
        </div>

        <!-- Items Section -->
        <div v-if="selectedGoodReceive" class="bg-white rounded-xl shadow-lg p-6">
          <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fa-solid fa-boxes text-orange-500"></i> Item yang Dapat Di-Return
          </h3>
          
          <div v-if="loadingItems" class="text-center py-8">
            <i class="fa fa-spinner fa-spin text-2xl text-orange-500 mb-2"></i>
            <p class="text-gray-600">Memuat item...</p>
          </div>
          
          <div v-else-if="availableItems.length === 0" class="text-center py-8 text-gray-500">
            <i class="fa fa-box-open text-4xl mb-2"></i>
            <p>Tidak ada item yang dapat di-return dari Good Receive ini.</p>
          </div>
          
          <div v-else class="space-y-4">
            <div 
              v-for="item in availableItems" 
              :key="item.gr_item_id"
              class="border border-gray-200 rounded-lg p-4"
            >
              <div class="flex items-center justify-between mb-3">
                <div class="flex-1">
                  <h4 class="font-semibold text-gray-800">{{ item.item_name }}</h4>
                  <p class="text-sm text-gray-600">SKU: {{ item.sku }}</p>
                  <p class="text-sm text-gray-600">
                    Qty yang dapat di-return: <span class="font-medium text-orange-600">{{ item.received_qty }} {{ item.unit_name }}</span>
                  </p>
                  <p class="text-sm text-gray-600">
                    Stok saat ini: <span class="font-medium text-blue-600">{{ formatNumber(item.current_stock || 0) }} {{ item.small_unit_name || item.unit_name }}</span>
                  </p>
                </div>
                <div class="flex items-center gap-2">
                  <input 
                    type="checkbox" 
                    :id="`item_${item.gr_item_id}`"
                    v-model="selectedItems"
                    :value="item.gr_item_id"
                    class="h-4 w-4 text-orange-600 focus:ring-orange-500 border-gray-300 rounded"
                  />
                  <label :for="`item_${item.gr_item_id}`" class="text-sm font-medium text-gray-700">
                    Pilih untuk return
                  </label>
                </div>
              </div>
              
              <!-- Return Quantity Input -->
              <div v-if="selectedItems.includes(item.gr_item_id)" class="mt-3 p-3 bg-orange-50 rounded-lg">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Qty Return *</label>
                    <input 
                      type="number" 
                      step="0.01"
                      min="0.01"
                      :max="item.received_qty"
                      v-model="returnQuantities[item.gr_item_id]"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                      :placeholder="`Maksimal ${item.received_qty} ${item.unit_name}`"
                      required
                    />
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Unit</label>
                    <input 
                      type="text" 
                      :value="item.unit_name"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-100"
                      readonly
                    />
                  </div>
                </div>
                <p class="text-xs text-gray-500 mt-1">
                  Maksimal: {{ item.received_qty }} {{ item.unit_name }}
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- Submit Button -->
        <div class="flex justify-end gap-4">
          <button 
            type="button" 
            @click="goBack"
            class="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors"
          >
            Batal
          </button>
          <button 
            type="submit" 
            :disabled="loading || selectedItems.length === 0"
            class="px-6 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <span v-if="loading">
              <i class="fa fa-spinner fa-spin mr-2"></i> Menyimpan...
            </span>
            <span v-else>
              <i class="fa fa-save mr-2"></i> Simpan Return
            </span>
          </button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import { ref, reactive, watch, computed, onMounted } from 'vue'
import axios from 'axios'
import Swal from 'sweetalert2'

const props = defineProps({
  user: Object,
  outlets: Array,
  warehouseOutlets: Array,
  goodReceives: Array
})

const loading = ref(false)
const loadingItems = ref(false)
const loadingWarehouseOutlets = ref(false)
const loadingGoodReceives = ref(false)
const availableItems = ref([])
const selectedItems = ref([])
const returnQuantities = reactive({})
const selectedGoodReceive = ref(null)
const warehouseOutlets = ref([])
const goodReceives = ref([])

// Check if user can select outlet (only admin with id_outlet = 1)
const canSelectOutlet = computed(() => {
  return props.user?.id_outlet === 1
})

const form = reactive({
  outlet_food_good_receive_id: '',
  outlet_id: props.user?.id_outlet === 1 ? '' : props.user?.id_outlet || '',
  warehouse_outlet_id: '',
  return_date: new Date().toISOString().split('T')[0],
  notes: ''
})

// Initialize data on mount
onMounted(() => {
  // Initialize warehouse outlets from props
  warehouseOutlets.value = props.warehouseOutlets || []
  goodReceives.value = props.goodReceives || []
  
  // If user is not admin, set outlet and load warehouse outlets
  if (!canSelectOutlet.value && form.outlet_id) {
    console.log('Loading warehouse outlets for outlet_id:', form.outlet_id)
    loadWarehouseOutlets()
  }
})

// Watch for outlet selection changes
watch(() => form.outlet_id, (newValue) => {
  if (newValue) {
    loadWarehouseOutlets()
  } else {
    warehouseOutlets.value = []
    goodReceives.value = []
    form.warehouse_outlet_id = ''
    form.outlet_food_good_receive_id = ''
    availableItems.value = []
    selectedItems.value = []
    Object.keys(returnQuantities).forEach(key => delete returnQuantities[key])
  }
})

// Watch for warehouse outlet selection changes
watch(() => form.warehouse_outlet_id, (newValue) => {
  if (newValue) {
    loadGoodReceives()
  } else {
    goodReceives.value = []
    form.outlet_food_good_receive_id = ''
    availableItems.value = []
    selectedItems.value = []
    Object.keys(returnQuantities).forEach(key => delete returnQuantities[key])
  }
})

// Watch for good receive selection changes
watch(() => form.outlet_food_good_receive_id, (newValue) => {
  if (newValue) {
    const gr = goodReceives.value.find(g => g.id == newValue)
    if (gr) {
      selectedGoodReceive.value = gr
      loadGoodReceiveItems()
    }
  } else {
    selectedGoodReceive.value = null
    availableItems.value = []
    selectedItems.value = []
    Object.keys(returnQuantities).forEach(key => delete returnQuantities[key])
  }
})

// Watch for selected items changes
watch(selectedItems, (newItems) => {
  // Clear quantities for unselected items
  Object.keys(returnQuantities).forEach(itemId => {
    if (!newItems.includes(parseInt(itemId))) {
      delete returnQuantities[itemId]
    }
  })
  
  // Set default quantity for newly selected items
  newItems.forEach(itemId => {
    if (!returnQuantities[itemId]) {
      const item = availableItems.value.find(i => i.gr_item_id == itemId)
      if (item) {
        returnQuantities[itemId] = item.received_qty
      }
    }
  })
})

async function loadWarehouseOutlets() {
  if (!form.outlet_id) {
    console.log('No outlet_id selected, skipping warehouse outlets load')
    return
  }
  
  console.log('Loading warehouse outlets for outlet_id:', form.outlet_id)
  loadingWarehouseOutlets.value = true
  try {
    const response = await axios.get('/api/warehouse-outlets', {
      params: {
        outlet_id: form.outlet_id,
        status: 'active'
      }
    })
    
    console.log('Warehouse outlets response:', response.data)
    warehouseOutlets.value = response.data
    
    // Reset dependent fields
    form.warehouse_outlet_id = ''
    form.outlet_food_good_receive_id = ''
    goodReceives.value = []
    availableItems.value = []
    selectedItems.value = []
    Object.keys(returnQuantities).forEach(key => delete returnQuantities[key])
    
  } catch (error) {
    console.error('Error loading warehouse outlets:', error)
    console.error('Error response:', error.response?.data)
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: 'Gagal memuat warehouse outlet: ' + (error.response?.data?.error || error.message)
    })
  } finally {
    loadingWarehouseOutlets.value = false
  }
}

async function loadGoodReceives() {
  if (!form.outlet_id || !form.warehouse_outlet_id) return
  
  loadingGoodReceives.value = true
  try {
    const response = await axios.get('/api/outlet-food-return/get-good-receives', {
      params: {
        outlet_id: form.outlet_id,
        warehouse_outlet_id: form.warehouse_outlet_id
      }
    })
    
    goodReceives.value = response.data
    
    // Reset dependent fields
    form.outlet_food_good_receive_id = ''
    availableItems.value = []
    selectedItems.value = []
    Object.keys(returnQuantities).forEach(key => delete returnQuantities[key])
    
  } catch (error) {
    console.error('Error loading good receives:', error)
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: 'Gagal memuat Good Receive'
    })
  } finally {
    loadingGoodReceives.value = false
  }
}

async function loadGoodReceiveItems() {
  if (!form.outlet_food_good_receive_id) return
  
  loadingItems.value = true
  try {
    const response = await axios.get('/api/outlet-food-return/get-good-receive-items', {
      params: {
        good_receive_id: form.outlet_food_good_receive_id,
        outlet_id: form.outlet_id,
        warehouse_outlet_id: form.warehouse_outlet_id
      }
    })
    
    availableItems.value = response.data
    selectedItems.value = []
    Object.keys(returnQuantities).forEach(key => delete returnQuantities[key])
  } catch (error) {
    console.error('Error loading items:', error)
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: 'Gagal memuat item dari Good Receive'
    })
  } finally {
    loadingItems.value = false
  }
}

async function submitForm() {
  if (selectedItems.value.length === 0) {
    Swal.fire({
      icon: 'warning',
      title: 'Peringatan',
      text: 'Pilih minimal satu item untuk di-return'
    })
    return
  }
  
  // Validate quantities
  for (const itemId of selectedItems.value) {
    const quantity = returnQuantities[itemId]
    const item = availableItems.value.find(i => i.gr_item_id == itemId)
    
    if (!quantity || quantity <= 0) {
      Swal.fire({
        icon: 'error',
        title: 'Error',
        text: `Qty return untuk ${item?.item_name} harus lebih dari 0`
      })
      return
    }
    
    if (quantity > item.received_qty) {
      Swal.fire({
        icon: 'error',
        title: 'Error',
        text: `Qty return untuk ${item.item_name} tidak boleh melebihi qty yang diterima (${item.received_qty})`
      })
      return
    }
  }
  
  loading.value = true
  
  try {
    const items = selectedItems.value.map(itemId => {
      const item = availableItems.value.find(i => i.gr_item_id == itemId)
      return {
        gr_item_id: itemId,
        item_id: item.item_id,
        return_qty: parseFloat(returnQuantities[itemId]),
        unit_id: item.unit_id
      }
    })
    
    const response = await axios.post('/outlet-food-return', {
      ...form,
      items: items
    })
    
    if (response.data.success) {
      Swal.fire({
        icon: 'success',
        title: 'Berhasil',
        text: response.data.message,
        timer: 2000,
        showConfirmButton: false
      }).then(() => {
        router.visit('/outlet-food-return')
      })
    }
  } catch (error) {
    console.error('Error submitting form:', error)
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: error.response?.data?.message || 'Gagal menyimpan return'
    })
  } finally {
    loading.value = false
  }
}

function goBack() {
  router.visit('/outlet-food-return')
}

function formatNumber(number) {
  if (!number && number !== 0) return '0.00'
  return parseFloat(number).toLocaleString('id-ID', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  })
}

function formatDate(date) {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('id-ID')
}
</script>
