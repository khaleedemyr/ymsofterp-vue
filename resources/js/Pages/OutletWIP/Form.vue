<template>
  <div>
    <div class="flex justify-between items-center mb-4">
      <h2 class="text-lg font-bold flex items-center gap-2">
        <i class="fa-solid fa-industry text-blue-500"></i> Buat Produksi WIP Baru
      </h2>
      <div v-if="lastSaved" class="text-xs text-gray-500">
        <i class="fa fa-check-circle text-green-500 mr-1"></i>
        Terakhir disimpan: {{ formatTime(lastSaved) }}
      </div>
    </div>
    
    <form @submit.prevent="submit" class="space-y-4">
      <!-- Header Fields (Shared for all productions) -->
      <div class="bg-gray-50 rounded-lg p-4 space-y-4">
        <h3 class="font-semibold text-gray-700 mb-2">Informasi Umum</h3>
        
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
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Catatan</label>
          <textarea v-model="form.notes" class="input input-bordered w-full" rows="2" placeholder="Catatan produksi (opsional)"></textarea>
        </div>
      </div>

      <!-- Multiple Production Items -->
      <div class="space-y-4">
        <div class="flex justify-between items-center">
          <h3 class="font-semibold text-gray-700">Item Produksi</h3>
          <button 
            type="button" 
            @click="addProductionItem" 
            class="btn btn-sm btn-primary"
            :disabled="!form.warehouse_outlet_id"
          >
            <i class="fa fa-plus mr-1"></i> Tambah Item
          </button>
        </div>

        <div v-if="form.productions.length === 0" class="text-center py-8 text-gray-400 border-2 border-dashed rounded-lg">
          <i class="fa fa-box-open text-4xl mb-2"></i>
          <p>Belum ada item produksi. Klik "Tambah Item" untuk menambahkan.</p>
        </div>

        <div v-for="(production, index) in form.productions" :key="production.id" class="bg-white border rounded-lg p-4 space-y-4">
          <div class="flex justify-between items-start">
            <div class="flex-1">
              <h4 class="font-medium text-gray-800 mb-2">Item Produksi #{{ index + 1 }}</h4>
            </div>
            <button 
              type="button" 
              @click="removeProductionItem(index)" 
              class="btn btn-sm btn-error text-white"
              :disabled="form.productions.length === 1"
            >
              <i class="fa fa-trash"></i>
            </button>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Item Selection -->
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-1">Item Hasil Produksi</label>
              <multiselect
                v-model="production.selectedItem"
                :options="items"
                :searchable="true"
                :close-on-select="true"
                :show-labels="false"
                placeholder="Cari dan pilih item..."
                label="name"
                track-by="id"
                :disabled="!form.warehouse_outlet_id"
                @select="(item) => onItemSelect(item, index)"
                @remove="() => onItemRemove(index)"
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
              <input 
                type="number" 
                min="0" 
                step="0.01" 
                v-model.number="production.qty" 
                class="input input-bordered w-full" 
                required 
                @input="() => onQtyChange(index)"
                :disabled="!form.warehouse_outlet_id || !production.item_id" 
              />
            </div>

            <div class="flex gap-2">
              <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700 mb-1">Qty Jadi</label>
                <input 
                  type="number" 
                  min="0" 
                  step="0.01" 
                  v-model.number="production.qty_jadi" 
                  class="input input-bordered w-full" 
                  required 
                  :disabled="!form.warehouse_outlet_id || !production.item_id" 
                />
              </div>
              <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700 mb-1">Unit</label>
                <input 
                  type="text" 
                  :value="production.itemData?.small_unit_name || '-'" 
                  class="input input-bordered w-full bg-gray-50" 
                  readonly
                  :disabled="!production.item_id"
                />
              </div>
            </div>
          </div>

          <!-- Expandable BoM/Stock Info -->
          <div v-if="production.item_id && production.qty > 0" class="mt-4">
            <button
              type="button"
              @click="production.showBom = !production.showBom"
              class="w-full flex items-center justify-between p-3 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors"
            >
              <div class="flex items-center gap-2">
                <i class="fa fa-chevron-down transition-transform" :class="production.showBom ? 'rotate-180' : ''"></i>
                <span class="font-medium text-gray-700">Lihat BoM & Stock</span>
                <span 
                  v-if="production.bom && production.bom.length > 0"
                  class="px-2 py-1 rounded text-xs font-semibold"
                  :class="production.canProduce ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
                >
                  {{ production.canProduce ? '✓ Bisa Diproduksi' : '✗ Tidak Bisa Diproduksi' }}
                </span>
              </div>
              <span v-if="production.loadingBom" class="text-sm text-gray-500">
                <i class="fa fa-spinner fa-spin"></i> Memuat...
              </span>
            </button>

            <!-- BoM Table (Expandable) -->
            <div v-if="production.showBom && production.bom && production.bom.length > 0" class="mt-3 bg-gray-50 rounded-lg p-4">
              <h4 class="font-semibold text-gray-700 mb-3">Bill of Materials (BOM)</h4>
              <div class="overflow-x-auto">
                <table class="w-full text-sm">
                  <thead>
                    <tr class="border-b">
                      <th class="text-left py-2 px-2">Material</th>
                      <th class="text-left py-2 px-2">Qty Dibutuhkan</th>
                      <th class="text-left py-2 px-2">Stok Tersedia</th>
                      <th class="text-left py-2 px-2">Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="bomItem in production.bom" :key="bomItem.material_item_id" class="border-b">
                      <td class="py-2 px-2">{{ bomItem.material_name }}</td>
                      <td class="py-2 px-2">{{ formatNumber(bomItem.qty_needed) }} {{ bomItem.material_unit_name }}</td>
                      <td class="py-2 px-2">{{ formatNumber(bomItem.stock) }} {{ bomItem.material_unit_name }}</td>
                      <td class="py-2 px-2">
                        <span 
                          :class="bomItem.sufficient ? 'text-green-600' : 'text-red-600'" 
                          class="font-semibold"
                        >
                          {{ bomItem.sufficient ? '✓ Cukup' : '✗ Kurang' }}
                        </span>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- No BOM Message -->
            <div v-else-if="production.showBom && production.bom && production.bom.length === 0" class="mt-3 bg-yellow-50 border border-yellow-200 rounded-lg p-4">
              <div class="flex items-center gap-2 text-yellow-800">
                <i class="fa fa-exclamation-triangle"></i>
                <span class="font-medium">Item ini tidak memiliki BOM</span>
              </div>
              <p class="text-sm text-yellow-700 mt-2">
                Untuk melakukan produksi, item harus memiliki komposisi bahan yang telah didefinisikan.
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Submit Buttons -->
      <div class="flex justify-end gap-3 pt-6 border-t">
        <button 
          type="button" 
          @click="saveDraft" 
          :disabled="isSaving || !canSaveDraft"
          class="btn btn-outline"
        >
          <span v-if="isSaving" class="loading loading-spinner loading-sm"></span>
          {{ isSaving ? 'Menyimpan...' : 'Simpan Draft' }}
        </button>
        <button type="button" @click="$emit('cancel')" class="btn btn-outline">
          Batal
        </button>
        <button 
          type="submit" 
          :disabled="isSubmitting || !canSubmit" 
          class="btn btn-primary"
        >
          <span v-if="isSubmitting" class="loading loading-spinner loading-sm"></span>
          {{ isSubmitting ? 'Menyimpan...' : 'Submit Produksi' }}
        </button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, nextTick } from 'vue'
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
  headerData: Object, // For edit mode
  detailData: Array,  // For edit mode
})

const emit = defineEmits(['submitted', 'cancel'])

// Initialize form
const form = useForm({
  header_id: props.headerData?.id || null,
  outlet_id: props.user_outlet_id !== 1 ? props.user_outlet_id : (props.headerData?.outlet_id || ''),
  warehouse_outlet_id: props.headerData?.warehouse_outlet_id || '',
  production_date: props.headerData?.production_date || new Date().toISOString().split('T')[0],
  batch_number: props.headerData?.batch_number || '',
  notes: props.headerData?.notes || '',
  productions: props.detailData && props.detailData.length > 0
    ? props.detailData.map(detail => {
        const selectedItem = props.items.find(item => item.id == detail.item_id) || null
        // Automatically use small_unit_id if available, otherwise use detail.unit_id
        const unitId = selectedItem?.small_unit_id || detail.unit_id
        return {
          id: detail.id || null,
          item_id: detail.item_id,
          selectedItem: selectedItem,
          itemData: selectedItem, // Set itemData from selectedItem
          qty: detail.qty,
          qty_jadi: detail.qty_jadi,
          unit_id: unitId,
          bom: [],
          showBom: false,
          loadingBom: false,
          canProduce: false,
        }
      })
    : [],
})

const isSubmitting = ref(false)
const isSaving = ref(false)
const lastSaved = ref(null)
const autosaveTimeout = ref(null)
const autosaveInProgress = ref(false)

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

const canSaveDraft = computed(() => {
  return form.outlet_id && 
         form.warehouse_outlet_id && 
         form.production_date &&
         form.productions.length > 0 &&
         form.productions.every(p => p.item_id && p.qty > 0 && p.qty_jadi >= 0 && p.unit_id)
})

const canSubmit = computed(() => {
  return canSaveDraft.value &&
         form.productions.every(p => {
           if (!p.bom || p.bom.length === 0) return false
           return p.canProduce && p.bom.every(bomItem => bomItem.sufficient)
         })
})

function newProductionItem() {
  return {
    id: null,
    item_id: '',
    selectedItem: null,
    itemData: null, // Add itemData property
    qty: 1,
    qty_jadi: 0,
    unit_id: '',
    bom: [],
    showBom: false,
    loadingBom: false,
    canProduce: false,
  }
}

function addProductionItem() {
  form.productions.push(newProductionItem())
}

function removeProductionItem(index) {
  if (form.productions.length > 1) {
    form.productions.splice(index, 1)
  }
}

function onOutletChange() {
  form.warehouse_outlet_id = ''
  form.productions.forEach(prod => {
    prod.item_id = ''
    prod.selectedItem = null
    prod.itemData = null
    prod.unit_id = ''
    prod.bom = []
    prod.showBom = false
  })
}

function onWarehouseChange() {
  form.productions.forEach(prod => {
    prod.item_id = ''
    prod.selectedItem = null
    prod.itemData = null
    prod.unit_id = ''
    prod.bom = []
    prod.showBom = false
  })
}

function onItemSelect(item, index) {
  const production = form.productions[index]
  production.item_id = item.id
  production.selectedItem = item
  // Set itemData from props.items
  production.itemData = props.items.find(i => i.id == item.id) || null
  // Automatically set unit_id to small_unit_id
  if (production.itemData?.small_unit_id) {
    production.unit_id = production.itemData.small_unit_id
  } else {
    production.unit_id = ''
  }
  production.bom = []
  production.showBom = false
  production.canProduce = false
  
  if (production.item_id && production.qty > 0 && form.outlet_id && form.warehouse_outlet_id) {
    fetchBom(index)
  }
}

function onItemRemove(index) {
  const production = form.productions[index]
  production.item_id = ''
  production.selectedItem = null
  production.itemData = null // Clear itemData
  production.unit_id = ''
  production.bom = []
  production.showBom = false
  production.canProduce = false
}

function onQtyChange(index) {
  const production = form.productions[index]
  if (production.item_id && production.qty > 0 && form.outlet_id && form.warehouse_outlet_id) {
    fetchBom(index)
  } else {
    production.bom = []
    production.canProduce = false
  }
}

async function fetchBom(index) {
  const production = form.productions[index]
  if (!production.item_id || !production.qty || !form.outlet_id || !form.warehouse_outlet_id) {
    production.bom = []
    production.canProduce = false
    return
  }

  production.loadingBom = true
  try {
    const response = await axios.post('/outlet-wip/bom', {
      item_id: production.item_id,
      qty: production.qty,
      outlet_id: form.outlet_id,
      warehouse_outlet_id: form.warehouse_outlet_id
    })
    
    production.bom = response.data || []
    production.canProduce = production.bom.length > 0 && production.bom.every(item => item.sufficient)
    
    if (production.bom.length === 0) {
      const itemName = production.selectedItem?.name || 'Item yang dipilih'
      await Swal.fire({
        icon: 'warning',
        title: 'Item Tidak Memiliki BOM',
        html: `
          <div class="text-left">
            <p class="mb-3"><strong>${itemName}</strong> tidak memiliki Bill of Materials (BOM).</p>
            <p class="text-sm text-gray-600 mb-3">Untuk melakukan produksi, item harus memiliki komposisi bahan yang telah didefinisikan.</p>
          </div>
        `,
        confirmButtonText: 'OK',
        confirmButtonColor: '#3b82f6',
      })
    }
  } catch (error) {
    console.error('Error fetching BOM:', error)
    production.bom = []
    production.canProduce = false
    await Swal.fire({
      icon: 'error',
      title: 'Error',
      text: 'Terjadi kesalahan saat mengambil data BOM. Silakan coba lagi.',
      confirmButtonText: 'OK',
      confirmButtonColor: '#ef4444'
    })
  } finally {
    production.loadingBom = false
  }
}

function formatNumber(value) {
  if (value === null || value === undefined) return '0.00'
  return new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  }).format(value)
}

function formatTime(date) {
  if (!date) return '-'
  return new Date(date).toLocaleTimeString('id-ID', {
    hour: '2-digit',
    minute: '2-digit'
  })
}

// Autosave function
async function autosave() {
  if (autosaveInProgress.value) {
    return
  }
  
  if (!form.outlet_id || !form.warehouse_outlet_id || !form.production_date || form.productions.length === 0) {
    return
  }
  
  // Filter valid productions
  const validProductions = form.productions.filter(p => p.item_id && p.qty > 0 && p.qty_jadi >= 0 && p.unit_id)
  
  if (validProductions.length === 0) {
    return
  }
  
  autosaveInProgress.value = true
  
  try {
    const formData = {
      outlet_id: form.outlet_id,
      warehouse_outlet_id: form.warehouse_outlet_id,
      production_date: form.production_date,
      batch_number: form.batch_number,
      notes: form.notes,
      productions: validProductions.map(p => ({
        item_id: p.item_id,
        qty: p.qty,
        qty_jadi: p.qty_jadi,
        unit_id: p.unit_id,
      })),
      autosave: true
    }
    
    const response = await axios.post(route('outlet-wip.store'), formData, {
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      }
    })
    
    if (response.data.success && response.data.header_id) {
      form.header_id = response.data.header_id
      lastSaved.value = new Date()
      console.log('✅ Autosave: Draft saved with header_id:', response.data.header_id)
    }
  } catch (error) {
    console.error('❌ Autosave failed:', error)
  } finally {
    autosaveInProgress.value = false
  }
}

// Watch form changes for autosave
watch(() => [
  form.outlet_id,
  form.warehouse_outlet_id,
  form.production_date,
  form.batch_number,
  form.notes,
  form.productions
], () => {
  if (autosaveInProgress.value) {
    return
  }
  
  if (autosaveTimeout.value) {
    clearTimeout(autosaveTimeout.value)
  }
  
  autosaveTimeout.value = setTimeout(() => {
    if (!autosaveInProgress.value) {
      autosave()
    }
  }, 3000)
}, { deep: true })

// Save draft function
async function saveDraft() {
  if (isSaving.value) return
  
  isSaving.value = true
  
  if (autosaveTimeout.value) {
    clearTimeout(autosaveTimeout.value)
    autosaveTimeout.value = null
  }
  
  while (autosaveInProgress.value) {
    await new Promise(resolve => setTimeout(resolve, 100))
  }
  
  try {
    const formData = {
      outlet_id: form.outlet_id,
      warehouse_outlet_id: form.warehouse_outlet_id,
      production_date: form.production_date,
      batch_number: form.batch_number,
      notes: form.notes,
      productions: form.productions
        .filter(p => p.item_id && p.qty > 0 && p.qty_jadi >= 0 && p.unit_id)
        .map(p => ({
          item_id: p.item_id,
          qty: p.qty,
          qty_jadi: p.qty_jadi,
          unit_id: p.unit_id,
        })),
      autosave: false
    }
    
    const response = await axios.post(route('outlet-wip.store'), formData, {
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      }
    })
    
    if (response.data.success) {
      if (response.data.header_id) {
        form.header_id = response.data.header_id
      }
      lastSaved.value = new Date()
      
      Swal.fire({
        icon: 'success',
        title: 'Berhasil',
        text: 'Draft berhasil disimpan',
        timer: 1500,
        showConfirmButton: false
      })
    }
  } catch (error) {
    console.error('Save draft failed:', error)
    Swal.fire({
      icon: 'error',
      title: 'Gagal',
      text: error.response?.data?.message || 'Gagal menyimpan draft',
      confirmButtonText: 'OK'
    })
  } finally {
    isSaving.value = false
  }
}

function submit() {
  if (!canSubmit.value) {
    Swal.fire({
      icon: 'warning',
      title: 'Validasi Gagal',
      text: 'Pastikan semua item memiliki BOM yang valid dan stock cukup',
      confirmButtonText: 'OK'
    })
    return
  }

  isSubmitting.value = true
  
  // Prepare submit data
  const submitData = {
    outlet_id: form.outlet_id,
    warehouse_outlet_id: form.warehouse_outlet_id,
    production_date: form.production_date,
    batch_number: form.batch_number,
    notes: form.notes,
    productions: form.productions
      .filter(p => p.item_id && p.qty > 0 && p.qty_jadi >= 0 && p.unit_id)
      .map(p => ({
        item_id: p.item_id,
        qty: p.qty,
        qty_jadi: p.qty_jadi,
        unit_id: p.unit_id,
      }))
  }
  
  // If header_id exists, submit existing draft
  // Otherwise, create and submit directly
  const submitUrl = form.header_id 
    ? route('outlet-wip.submit', form.header_id)
    : route('outlet-wip.store-and-submit')
  
  axios.post(submitUrl, submitData, {
    headers: {
      'Accept': 'application/json',
      'Content-Type': 'application/json'
    }
  })
  .then(response => {
    if (response.data.success) {
      Swal.fire({
        icon: 'success',
        title: 'Berhasil',
        text: response.data.message || 'Produksi WIP berhasil disubmit',
        timer: 1500,
        showConfirmButton: false
      }).then(() => {
        emit('submitted')
      })
    }
  })
  .catch(error => {
    console.error('Submit failed:', error)
    Swal.fire({
      icon: 'error',
      title: 'Gagal Submit',
      text: error.response?.data?.message || 'Gagal submit produksi',
      confirmButtonText: 'OK'
    })
  })
  .finally(() => {
    isSubmitting.value = false
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

// Auto-fetch BoM when form is loaded in edit mode
onMounted(async () => {
  if (props.headerData && props.detailData && props.detailData.length > 0) {
    // Wait for Vue to finish rendering
    await nextTick()
    // Wait a bit more for form to be fully initialized
    setTimeout(() => {
      form.productions.forEach((production, index) => {
        if (production.item_id && production.qty > 0 && form.outlet_id && form.warehouse_outlet_id) {
          console.log('Auto-fetching BoM for production', index, production.item_id, {
            item_id: production.item_id,
            qty: production.qty,
            outlet_id: form.outlet_id,
            warehouse_outlet_id: form.warehouse_outlet_id
          })
          fetchBom(index)
        } else {
          console.log('Skipping BoM fetch for production', index, {
            item_id: production.item_id,
            qty: production.qty,
            outlet_id: form.outlet_id,
            warehouse_outlet_id: form.warehouse_outlet_id
          })
        }
      })
    }, 800)
  }
})

// Watch warehouse_outlet_id to auto-fetch BoM when it's set (for edit mode)
watch(() => form.warehouse_outlet_id, (newValue, oldValue) => {
  // Only trigger if warehouse was just set (not on initial load)
  if (newValue && !oldValue && props.headerData && form.productions.length > 0) {
    setTimeout(() => {
      form.productions.forEach((production, index) => {
        if (production.item_id && production.qty > 0 && form.outlet_id && form.warehouse_outlet_id) {
          console.log('Auto-fetching BoM after warehouse change', index, production.item_id)
          fetchBom(index)
        }
      })
    }, 300)
  }
})

// Watch productions to auto-fetch BoM when data is loaded (for edit mode)
watch(() => form.productions, (newProductions) => {
  if (props.headerData && newProductions && newProductions.length > 0 && form.outlet_id && form.warehouse_outlet_id) {
    setTimeout(() => {
      newProductions.forEach((production, index) => {
        // Only fetch if item_id and qty exist, and bom hasn't been loaded yet
        if (production.item_id && production.qty > 0 && (!production.bom || production.bom.length === 0)) {
          console.log('Auto-fetching BoM for loaded production', index, production.item_id)
          fetchBom(index)
        }
      })
    }, 500)
  }
}, { deep: false, immediate: false })
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

.btn-sm {
  @apply px-2 py-1 text-sm;
}

.btn-error {
  @apply bg-red-600 text-white hover:bg-red-700;
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
