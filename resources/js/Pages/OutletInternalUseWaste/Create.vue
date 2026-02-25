<template>
  <AppLayout>
    <div class="min-h-screen bg-gray-50 py-8 px-4 md:px-8">
      <div class="w-full max-w-5xl mx-auto bg-white rounded-2xl shadow-2xl p-6 md:p-8">
          <h1 class="text-2xl font-bold mb-8 flex items-center gap-2 text-green-700">
          <i class="fa-solid fa-recycle text-green-500"></i> {{ isEdit ? 'Edit' : 'Input' }} Category Cost Outlet
        </h1>
        <form @submit.prevent="submit" class="space-y-5" @submit.prevent.stop>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-bold text-gray-600 mb-1">Tipe</label>
              <select v-model="form.type" class="input input-bordered w-full" required>
                <option value="">Pilih Tipe</option>
                <option value="internal_use">Internal Use</option>
                <option value="spoil">Spoil</option>
                <option value="waste">Waste</option>
                <option value="stock_cut">Stock Cut</option>
                <option value="r_and_d">R & D</option>
                <option value="marketing">Marketing</option>
                <option value="non_commodity">Non Commodity</option>
                <option value="guest_supplies">Guest Supplies</option>
                <option value="wrong_maker">Wrong Maker</option>
                <option value="training">Training</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-bold text-gray-600 mb-1">Tanggal</label>
              <input type="date" v-model="form.date" class="input input-bordered w-full" required />
            </div>
            <div>
              <label class="block text-xs font-bold text-gray-600 mb-1">Outlet</label>
              <select v-model="form.outlet_id" :disabled="outletDisabled" class="input input-bordered w-full" required>
                <option value="">Pilih Outlet</option>
                <option v-for="o in props.outlets" :key="o.id_outlet" :value="o.id_outlet">{{ o.nama_outlet }}</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-bold text-gray-600 mb-1">Warehouse Outlet</label>
              <select v-model="form.warehouse_outlet_id" class="input input-bordered w-full" required>
                <option value="">Pilih Warehouse Outlet</option>
                <option v-for="wo in filteredWarehouseOutlets" :key="wo.id" :value="wo.id">{{ wo.name }}</option>
              </select>
            </div>
          </div>
          <!-- Items Section -->
          <div class="space-y-4">
            <div class="flex justify-between items-center">
              <h3 class="font-semibold text-gray-700">Items</h3>
              <button 
                type="button" 
                @click="addItem" 
                class="btn btn-sm btn-primary"
                :disabled="!form.warehouse_outlet_id || form.type === 'stock_cut'"
              >
                <i class="fa fa-plus mr-1"></i> Tambah Item
              </button>
            </div>

            <div v-if="form.type === 'stock_cut' && loadingStockCutItems" class="text-sm text-blue-600 bg-blue-50 border border-blue-200 rounded-lg px-3 py-2">
              <i class="fa fa-spinner fa-spin mr-2"></i>Memuat item stock cut...
            </div>

            <div v-if="form.items.length === 0" class="text-center py-8 text-gray-400 border-2 border-dashed rounded-lg">
              <i class="fa fa-box-open text-4xl mb-2"></i>
              <p v-if="form.type === 'stock_cut'">Tidak ada item stock cut dengan stok tersedia pada outlet dan warehouse ini.</p>
              <p v-else>Belum ada item. Klik "Tambah Item" untuk menambahkan.</p>
            </div>

            <div v-for="(item, idx) in form.items" :key="item.id || idx" class="bg-white border rounded-lg p-4 space-y-4 relative">
              <div class="flex justify-between items-start mb-2">
                <h4 class="font-medium text-gray-800">Item #{{ idx + 1 }}</h4>
                <button 
                  v-if="form.type !== 'stock_cut'"
                  type="button" 
                  @click="removeItem(idx)" 
                  class="px-3 py-1 bg-red-600 text-white rounded hover:bg-red-700 transition-colors text-sm font-medium"
                  :disabled="form.items.length === 1"
                  :class="form.items.length === 1 ? 'opacity-50 cursor-not-allowed' : ''"
                  title="Hapus Item"
                >
                  <i class="fa fa-trash mr-1"></i> Hapus
                </button>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Item Selection -->
                <div class="md:col-span-2">
                  <label class="block text-sm font-medium text-gray-700 mb-1">Item</label>
                  <multiselect
                    v-model="item.selectedItem"
                    :options="items"
                    :searchable="true"
                    :close-on-select="true"
                    :show-labels="false"
                    placeholder="Cari dan pilih item..."
                    label="name"
                    track-by="id"
                    :disabled="!form.warehouse_outlet_id || form.type === 'stock_cut'"
                    @select="(selectedItem) => onItemSelect(selectedItem, idx)"
                    @remove="() => onItemRemove(idx)"
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
                  <div v-if="!form.warehouse_outlet_id" class="text-xs text-yellow-600 mt-1">
                    Pilih warehouse outlet terlebih dahulu untuk melihat stok.
                  </div>
                  <div v-if="item.stock" class="text-xs mt-1" :class="Number(item.stock.qty_small || 0) <= 0 ? 'text-red-600 font-bold' : 'text-gray-500'">
                    Stok: {{ formatStockDisplay(item) }}
                    <span v-if="Number(item.stock.qty_small || 0) <= 0" class="ml-1">⚠️ Stock tidak tersedia</span>
                  </div>
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Qty</label>
                  <input 
                    type="number" 
                    min="0.01" 
                    step="0.01" 
                    v-model.number="item.qty" 
                    class="input input-bordered w-full" 
                    required 
                    :disabled="!form.warehouse_outlet_id || !item.item_id"
                  />
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Unit</label>
                  <input 
                    type="text" 
                    :value="getItemUnitName(item)" 
                    class="input input-bordered w-full bg-gray-50" 
                    readonly 
                    :disabled="!item.item_id"
                  />
                </div>

                <div class="md:col-span-2">
                  <label class="block text-sm font-medium text-gray-700 mb-1">Note</label>
                  <textarea 
                    v-model="item.note" 
                    class="input input-bordered w-full" 
                    placeholder="Catatan item (opsional)"
                    rows="3"
                    :disabled="form.type === 'stock_cut'"
                  ></textarea>
                </div>
              </div>
            </div>
            
            <!-- Tambah Item Button at Bottom -->
            <div class="flex justify-center pt-2">
              <button 
                type="button" 
                @click="addItem" 
                class="btn btn-sm btn-primary"
                :disabled="!form.warehouse_outlet_id || form.type === 'stock_cut'"
              >
                <i class="fa fa-plus mr-1"></i> Tambah Item
              </button>
            </div>
          </div>
          <div>
            <label class="block text-xs font-bold text-gray-600 mb-1">Catatan Umum</label>
            <textarea v-model="form.notes" class="input input-bordered w-full" rows="2" placeholder="Catatan tambahan"></textarea>
          </div>

          <!-- Approval Flow Section (Only for r_and_d, marketing, wrong_maker, training) -->
          <div v-if="form.type === 'r_and_d' || form.type === 'marketing' || form.type === 'wrong_maker' || form.type === 'training'" class="mb-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Approval Flow</h3>
            <p class="text-sm text-gray-600 mb-4">Tambahkan approver dalam urutan dari level terendah ke tertinggi. Approver pertama akan menjadi level terendah, dan approver terakhir akan menjadi level tertinggi.</p>
            
            <!-- Add Approver Input -->
            <div class="mb-4">
              <div class="relative">
                <input
                  v-model="approverSearch"
                  type="text"
                  placeholder="Cari user berdasarkan nama, email, atau jabatan..."
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  @input="handleApproverSearch"
                  @focus="handleApproverFocus"
                  @blur="handleApproverBlur"
                />
                
                <!-- Dropdown Results -->
                <div v-if="showApproverDropdown && approverResults.length > 0" class="absolute z-50 w-full mt-1 bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-y-auto">
                  <div
                    v-for="user in approverResults"
                    :key="user.id"
                    @mousedown.prevent="addApprover(user)"
                    class="px-3 py-2 hover:bg-gray-100 cursor-pointer border-b border-gray-200 last:border-b-0"
                  >
                    <div class="font-medium">{{ user.name }}</div>
                    <div class="text-sm text-gray-600">{{ user.email }}</div>
                    <div v-if="user.jabatan" class="text-xs text-blue-600 font-medium">{{ user.jabatan }}</div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Approvers List -->
            <div v-if="form.approvers.length > 0" class="space-y-2">
              <h4 class="font-medium text-gray-700">Urutan Approval (Terendah ke Tertinggi):</h4>
              <div
                v-for="(approver, index) in form.approvers"
                :key="approver.id"
                class="flex items-center justify-between p-3 bg-gray-50 border border-gray-200 rounded-md"
              >
                <div class="flex items-center space-x-3">
                  <div class="flex items-center space-x-2">
                    <button
                      v-if="index > 0"
                      @click="reorderApprover(index, index - 1)"
                      class="p-1 text-gray-500 hover:text-gray-700"
                      title="Pindah ke atas"
                    >
                      <i class="fa fa-arrow-up"></i>
                    </button>
                    <button
                      v-if="index < form.approvers.length - 1"
                      @click="reorderApprover(index, index + 1)"
                      class="p-1 text-gray-500 hover:text-gray-700"
                      title="Pindah ke bawah"
                    >
                      <i class="fa fa-arrow-down"></i>
                    </button>
                  </div>
                  <div class="flex items-center space-x-2">
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                      Level {{ index + 1 }}
                    </span>
                    <div>
                      <div class="font-medium">{{ approver.name }}</div>
                      <div class="text-sm text-gray-600">{{ approver.email }}</div>
                      <div v-if="approver.jabatan" class="text-xs text-blue-600 font-medium">{{ approver.jabatan }}</div>
                    </div>
                  </div>
                </div>
                <button
                  @click="removeApprover(index)"
                  class="text-red-600 hover:text-red-800"
                  title="Hapus"
                >
                  <i class="fa fa-trash"></i>
                </button>
              </div>
            </div>
            <div v-else class="text-sm text-amber-600 bg-amber-50 border border-amber-200 rounded-md p-3">
              <i class="fa fa-exclamation-triangle mr-2"></i>
              Wajib menambahkan minimal 1 approver untuk tipe ini.
            </div>
          </div>

          <div class="flex justify-between items-center mt-8">
            <div class="flex items-center gap-2 text-sm text-gray-500">
              <span v-if="isAutosaving">
                <i class="fa fa-spinner fa-spin"></i> Menyimpan otomatis...
              </span>
              <span v-else-if="lastSaved">
                <i class="fa fa-check text-green-500"></i> Terakhir disimpan: {{ new Date(lastSaved).toLocaleTimeString('id-ID') }}
              </span>
            </div>
            <div class="flex justify-end gap-2">
              <button type="button" class="btn btn-ghost px-6 py-2 rounded-lg" @click="goBack">Batal</button>
              <button type="button" @click="saveDraft" class="btn bg-gray-500 text-white px-6 py-2 rounded-lg font-bold shadow hover:shadow-xl transition-all" :disabled="loading || isAutosaving">
                <span v-if="loading && !isAutosaving">
                  <i class="fa fa-spinner fa-spin"></i> Menyimpan...
                </span>
                <span v-else>
                  Simpan Draft
                </span>
              </button>
              <button type="button" @click="submit" class="btn bg-gradient-to-r from-green-500 to-green-700 text-white px-8 py-2 rounded-lg font-bold shadow hover:shadow-xl transition-all" :disabled="loading || isAutosaving">
                <span v-if="loading && !isAutosaving">
                  <i class="fa fa-spinner fa-spin"></i> Submit...
                </span>
                <span v-else>
                  Submit
                </span>
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, watch, computed, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import Swal from 'sweetalert2'
import Multiselect from 'vue-multiselect'
import 'vue-multiselect/dist/vue-multiselect.min.css'

const props = defineProps({
  outlets: Array,
  items: Array,
  warehouse_outlets: Array,
  header: Object,
  details: Array,
  approvalFlows: Array,
  isEdit: Boolean
})

const page = usePage()
const userOutletId = computed(() => page.props.auth?.user?.id_outlet || '')

// Add filtered warehouse outlets computed property
const filteredWarehouseOutlets = ref(props.warehouse_outlets || [])

// Check if edit mode
const isEdit = computed(() => props.isEdit || false)

function newItem() {
  return {
    id: Date.now() + Math.random(), // Unique ID for each item
    item_id: '',
    item_name: '',
    selectedItem: null,
    qty: '',
    unit_id: '',
    unit_name: '',
    note: '',
    stock: null
  }
}

// Initialize form - if edit mode, load from props
const form = ref({
  header_id: props.header?.id || null,
  type: props.header?.type || 'internal_use',
  date: props.header?.date || '',
  outlet_id: props.header?.outlet_id || (userOutletId.value == 1 ? '' : userOutletId.value),
  notes: props.header?.notes || '',
  items: props.details && props.details.length > 0 
    ? props.details.map(detail => {
        const selectedItem = props.items.find(item => item.id == detail.item_id) || null
        return {
          id: Date.now() + Math.random(), // Unique ID for each item
          item_id: detail.item_id,
          item_name: detail.item_name || '',
          selectedItem: selectedItem,
          qty: detail.qty,
          unit_id: detail.unit_id,
          unit_name: detail.unit_name || '',
          note: detail.note || '',
          stock: null
        }
      })
    : [newItem()],
  warehouse_outlet_id: props.header?.warehouse_outlet_id || '',
  approvers: props.approvalFlows && props.approvalFlows.length > 0
    ? props.approvalFlows.map(flow => ({
        id: flow.approver_id,
        name: flow.name,
        email: flow.email,
        jabatan: flow.jabatan
      }))
    : []
})

const autosaveTimeout = ref(null)
const lastSaved = ref(null)
const isAutosaving = ref(false)
const autosaveInProgress = ref(false) // Flag to prevent multiple simultaneous autosaves
const submitInProgress = ref(false) // Flag to prevent multiple simultaneous submits

const approverSearch = ref('')
const approverResults = ref([])
const showApproverDropdown = ref(false)

const outletDisabled = computed(() => userOutletId.value != 1)
const loading = ref(false)
const loadingStockCutItems = ref(false)

// Add watch function to monitor outlet changes
watch(() => form.value.outlet_id, async (newOutletId) => {
  // Don't reset warehouse outlet if in edit mode and it's already set
  if (!isEdit.value || !form.value.warehouse_outlet_id) {
    form.value.warehouse_outlet_id = ''
  }
  
  if (newOutletId && userOutletId.value == 1) {
    // For superuser, fetch warehouse outlets for selected outlet
    try {
      const response = await axios.get(`/api/warehouse-outlets/by-outlet/${newOutletId}`)
      filteredWarehouseOutlets.value = response.data
    } catch (error) {
      console.error('Error fetching warehouse outlets:', error)
      filteredWarehouseOutlets.value = []
    }
  } else if (newOutletId && userOutletId.value != 1) {
    // For regular user, filter from existing warehouse outlets
    filteredWarehouseOutlets.value = props.warehouse_outlets.filter(wo => wo.outlet_id == newOutletId)
  } else {
    // No outlet selected, show empty
    filteredWarehouseOutlets.value = []
  }
}, { immediate: true })

// On mount, if edit mode, fetch stock for existing items
onMounted(async () => {
  if (isEdit.value && form.value.warehouse_outlet_id) {
    // Fetch stock for all items
    for (let idx = 0; idx < form.value.items.length; idx++) {
      if (form.value.items[idx].item_id) {
        await fetchStock(idx)
      }
    }
  }
})

async function loadStockCutItems() {
  if (form.value.type !== 'stock_cut') return
  if (!form.value.outlet_id || !form.value.warehouse_outlet_id) return

  loadingStockCutItems.value = true
  try {
    const res = await axios.get(route('outlet-internal-use-waste.stock-cut-items'), {
      params: {
        outlet_id: form.value.outlet_id,
        warehouse_outlet_id: form.value.warehouse_outlet_id,
      }
    })

    const sourceItems = Array.isArray(res.data?.items) ? res.data.items : []
    const mappedItems = sourceItems.map((row) => {
      const selectedItem = props.items.find((item) => item.id == row.item_id) || {
        id: row.item_id,
        name: row.item_name,
      }

      return {
        id: Date.now() + Math.random(),
        item_id: row.item_id,
        item_name: row.item_name,
        selectedItem,
        qty: '',
        unit_id: row.unit_id || '',
        unit_name: row.unit_name || '',
        note: '',
        stock: row.stock || null,
      }
    })

    form.value.items = mappedItems
  } catch (error) {
    console.error('Error loading stock cut items:', error)
    form.value.items = []
  } finally {
    loadingStockCutItems.value = false
  }
}

watch(
  () => [form.value.type, form.value.outlet_id, form.value.warehouse_outlet_id],
  async ([type, outletId, warehouseOutletId]) => {
    if (type === 'stock_cut' && outletId && warehouseOutletId) {
      await loadStockCutItems()
    }
  },
  { immediate: true }
)

function addItem() {
  form.value.items.push(newItem())
}
function removeItem(idx) {
  if (form.value.items.length === 1) return
  form.value.items.splice(idx, 1)
}

async function fetchItemSuggestions(idx, q) {
  if (!q || q.length < 2) {
    form.value.items[idx].suggestions = [];
    form.value.items[idx].highlightedIndex = -1;
    return;
  }
  form.value.items[idx].loading = true;
  try {
    const res = await axios.get('/items/search-for-outlet-transfer', {
      params: {
        q: q,
        outlet_id: form.value.outlet_id,
        region_id: page.props.auth?.user?.region_id
      }
    });
    form.value.items[idx].suggestions = res.data;
    form.value.items[idx].showDropdown = true;
    form.value.items[idx].highlightedIndex = 0;
  } finally {
    form.value.items[idx].loading = false;
  }
}

function onItemSelect(item, idx) {
  if (!item || !item.id) return
  
  form.value.items[idx].item_id = item.id
  form.value.items[idx].item_name = item.name
  form.value.items[idx].selectedItem = item
  // Set small unit directly
  setSmallUnit(idx, item.id)
  // Fetch stock untuk item yang dipilih
  if (form.value.warehouse_outlet_id && form.value.outlet_id) {
    fetchStock(idx)
  }
}

function onItemRemove(idx) {
  form.value.items[idx].item_id = ''
  form.value.items[idx].item_name = ''
  form.value.items[idx].selectedItem = null
  form.value.items[idx].unit_id = ''
  form.value.items[idx].unit_name = ''
  form.value.items[idx].stock = null
}


async function setSmallUnit(idx, itemId) {
  if (itemId) {
    const res = await axios.get(`/outlet-internal-use-waste/get-item-units/${itemId}`)
    // Automatically select the first unit (small unit)
    if (res.data.units && res.data.units.length > 0) {
      form.value.items[idx].unit_id = res.data.units[0].id
      form.value.items[idx].unit_name = res.data.units[0].name
    } else {
      form.value.items[idx].unit_id = ''
      form.value.items[idx].unit_name = ''
    }
  } else {
    form.value.items[idx].unit_id = ''
    form.value.items[idx].unit_name = ''
  }
}

function getItemUnitName(item) {
  return item.unit_name || '';
}


// Check stock availability before saving
async function validateStockBeforeSave() {
  if (!form.value.warehouse_outlet_id || !form.value.outlet_id) {
    return { valid: false, message: 'Pilih outlet dan warehouse outlet terlebih dahulu' }
  }
  
  for (let idx = 0; idx < form.value.items.length; idx++) {
    const item = form.value.items[idx]
    if (!item.item_id || !item.qty || item.qty <= 0) {
      continue // Skip empty items
    }
    
    // Check if stock exists and is not 0
    if (item.stock) {
      const stockSmall = Number(item.stock.qty_small || 0)
      if (stockSmall <= 0) {
        const itemName = item.item_name || 'Item'
        return { 
          valid: false, 
          message: `Stok item "${itemName}" tidak tersedia (stock: 0). Tidak dapat menyimpan draft.` 
        }
      }
    } else {
      // Try to fetch stock if not available
      try {
        await fetchStock(idx)
        if (form.value.items[idx].stock) {
          const stockSmall = Number(form.value.items[idx].stock.qty_small || 0)
          if (stockSmall <= 0) {
            const itemName = form.value.items[idx].item_name || 'Item'
            return { 
              valid: false, 
              message: `Stok item "${itemName}" tidak tersedia (stock: 0). Tidak dapat menyimpan draft.` 
            }
          }
        }
      } catch (e) {
        // If can't fetch stock, skip validation for this item
        console.warn('Could not fetch stock for item:', e)
      }
    }
  }
  
  return { valid: true }
}

// Check stock availability before submit (more strict validation)
async function validateStockBeforeSubmit() {
  if (!form.value.warehouse_outlet_id || !form.value.outlet_id) {
    return { valid: false, message: 'Pilih outlet dan warehouse outlet terlebih dahulu' }
  }
  
  // Check if there are any items
  const validItems = form.value.items.filter(item => item.item_id && item.qty > 0)
  if (validItems.length === 0) {
    return { valid: false, message: 'Tidak ada item yang dapat di-submit. Silakan tambahkan item terlebih dahulu.' }
  }
  
  for (let idx = 0; idx < form.value.items.length; idx++) {
    const item = form.value.items[idx]
    if (!item.item_id || !item.qty || item.qty <= 0) {
      continue // Skip empty items
    }
    
    // Fetch stock if not available
    if (!item.stock) {
      try {
        await fetchStock(idx)
      } catch (e) {
        console.warn('Could not fetch stock for item:', e)
      }
    }
    
    // Check stock
    if (item.stock) {
      const stockSmall = Number(item.stock.qty_small || 0)
      const qtyInput = Number(item.qty || 0)
      
      // Check if stock is 0 or negative
      if (stockSmall <= 0) {
        const itemName = item.item_name || 'Item'
        return { 
          valid: false, 
          message: `Stok item "${itemName}" tidak tersedia (stock: 0). Tidak dapat di-submit.` 
        }
      }
      
      // Check if qty input is greater than stock available
      // Note: We need to convert qty to small unit for comparison
      // For now, we'll do a simple comparison assuming qty is already in small unit
      // If unit conversion is needed, we should convert qty to small unit first
      if (qtyInput > stockSmall) {
        const itemName = item.item_name || 'Item'
        return { 
          valid: false, 
          message: `Qty item "${itemName}" (${qtyInput}) melebihi stok yang tersedia (${stockSmall.toFixed(2)}). Tidak dapat di-submit.` 
        }
      }
    } else {
      // Stock not found
      const itemName = item.item_name || 'Item'
      return { 
        valid: false, 
        message: `Stok item "${itemName}" tidak ditemukan. Tidak dapat di-submit.` 
      }
    }
  }
  
  return { valid: true }
}

// Autosave function
async function autosave() {
  // Prevent multiple simultaneous autosaves
  if (autosaveInProgress.value) {
    console.log('Autosave already in progress, skipping...')
    return
  }
  
  // Skip autosave if form is not valid enough
  if (!form.value.type || !form.value.date || !form.value.outlet_id || !form.value.warehouse_outlet_id) {
    console.log('Autosave skipped: Missing required fields', {
      type: form.value.type,
      date: form.value.date,
      outlet_id: form.value.outlet_id,
      warehouse_outlet_id: form.value.warehouse_outlet_id
    })
    return
  }
  
  // For autosave, allow saving even with empty items (user might be filling form)
  // But we need at least basic data: type, date, outlet, warehouse
  
  // Filter valid items (with item_id and qty > 0)
  const validItems = form.value.items.filter(item => item.item_id && item.qty > 0)
  
  // For autosave, we don't need strict stock validation
  // Stock validation will be done on submit
  // Allow autosave even if no items yet (user might be filling form)
  
  console.log('Autosave: Triggered', {
    type: form.value.type,
    date: form.value.date,
    outlet_id: form.value.outlet_id,
    warehouse_outlet_id: form.value.warehouse_outlet_id,
    validItemsCount: validItems.length,
    totalItemsCount: form.value.items.length
  })
  
  autosaveInProgress.value = true
  isAutosaving.value = true
  
  // Prepare form data - convert approvers array to IDs array for backend
  // Backend akan mencari draft berdasarkan outlet_id, warehouse_outlet_id, type, dan user
  // Jadi tidak perlu kirim header_id, backend akan otomatis cari atau buat baru
  const formData = {
    type: form.value.type,
    date: form.value.date,
    outlet_id: form.value.outlet_id,
    warehouse_outlet_id: form.value.warehouse_outlet_id,
    notes: form.value.notes,
    items: form.value.items
      .filter(item => item.item_id && item.qty > 0) // Only send valid items
      .map(item => ({
        item_id: item.item_id,
        qty: item.qty,
        unit_id: item.unit_id,
        note: item.note || null
      })),
    approvers: form.value.approvers.map(a => a.id),
    autosave: true
  }
  
  // Tidak perlu kirim header_id, backend akan otomatis cari draft berdasarkan kombinasi
  // outlet_id + warehouse_outlet_id + type + user
  console.log('Autosave: Saving draft for outlet:', form.value.outlet_id, 'warehouse:', form.value.warehouse_outlet_id, 'type:', form.value.type)
  
  try {
    const response = await axios.post(route('outlet-internal-use-waste.store'), formData, {
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      }
    })
    
    if (response.data.success) {
      // Always update header_id from response
      // Backend akan mencari draft berdasarkan kombinasi outlet_id + warehouse_outlet_id + type + user
      // Jika ada, akan return header_id draft yang sudah ada
      // Jika tidak ada, akan buat baru dan return header_id baru
      if (response.data.header_id) {
        form.value.header_id = response.data.header_id
        console.log('✅ Autosave: Draft saved/updated with header_id:', response.data.header_id)
      }
      lastSaved.value = new Date()
    } else {
      console.error('❌ Autosave failed: Response not successful', response.data)
    }
  } catch (error) {
    console.error('❌ Autosave failed:', error)
    console.error('Error details:', {
      message: error.message,
      response: error.response?.data,
      status: error.response?.status
    })
    // Don't show error for autosave failures (silent fail)
  } finally {
    isAutosaving.value = false
    autosaveInProgress.value = false
  }
}

// Watch form changes for autosave
watch(() => [
  form.value.type,
  form.value.date,
  form.value.outlet_id,
  form.value.warehouse_outlet_id,
  form.value.notes,
  form.value.items,
  form.value.approvers
], () => {
  // Don't autosave if already in progress
  if (autosaveInProgress.value) {
    return
  }
  
  // Don't autosave if submit is in progress
  if (submitInProgress.value || loading.value) {
    console.log('Autosave skipped: Submit or save in progress')
    return
  }
  
  // Clear existing timeout
  if (autosaveTimeout.value) {
    clearTimeout(autosaveTimeout.value)
  }
  
  // Set new timeout for autosave (debounce 3 seconds to reduce frequency)
  // Backend akan otomatis cari draft berdasarkan kombinasi, jadi tidak perlu khawatir multiple drafts
  autosaveTimeout.value = setTimeout(() => {
    // Double check before autosave
    if (!submitInProgress.value && !loading.value && !autosaveInProgress.value) {
      console.log('Autosave: Triggered after 3 seconds')
      autosave()
    }
  }, 3000)
}, { deep: true })

// Save as draft function
async function saveDraft() {
  // Prevent multiple simultaneous saves
  if (loading.value || submitInProgress.value) {
    console.log('Save draft: Already in progress, skipping...')
    return
  }
  
  loading.value = true
  
  // Cancel any pending autosave
  if (autosaveTimeout.value) {
    clearTimeout(autosaveTimeout.value)
    autosaveTimeout.value = null
  }
  
  // Wait for any in-progress autosave to finish
  while (autosaveInProgress.value) {
    await new Promise(resolve => setTimeout(resolve, 100))
  }
  
  // Validate stock before saving
  const stockValidation = await validateStockBeforeSave()
  if (!stockValidation.valid) {
    Swal.fire({
      icon: 'error',
      title: 'Validasi Gagal',
      text: stockValidation.message,
      confirmButtonText: 'OK',
      confirmButtonColor: '#EF4444'
    })
    loading.value = false
    return
  }
  
  // Prepare form data - backend akan mencari draft berdasarkan outlet_id, warehouse_outlet_id, type, dan user
  const formData = {
    type: form.value.type,
    date: form.value.date,
    outlet_id: form.value.outlet_id,
    warehouse_outlet_id: form.value.warehouse_outlet_id,
    notes: form.value.notes,
    items: form.value.items.map(item => ({
      item_id: item.item_id,
      qty: item.qty,
      unit_id: item.unit_id,
      note: item.note || null
    })),
    approvers: form.value.approvers.map(a => a.id),
    autosave: false
  }
  
  // Tidak perlu kirim header_id, backend akan otomatis cari draft berdasarkan kombinasi
  // outlet_id + warehouse_outlet_id + type + user
  
  try {
    const response = await axios.post(route('outlet-internal-use-waste.store'), formData, {
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      }
    })
    
    if (response.data.success) {
      // Always update header_id from response
      // Backend akan mencari draft berdasarkan kombinasi outlet_id + warehouse_outlet_id + type + user
      if (response.data.header_id) {
        form.value.header_id = response.data.header_id
        console.log('Save draft: Draft saved/updated with header_id:', response.data.header_id)
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
    const errorMessage = error.response?.data?.message || error.message || 'Gagal menyimpan draft'
    Swal.fire({
      icon: 'error',
      title: 'Gagal',
      text: errorMessage,
      confirmButtonText: 'OK',
      confirmButtonColor: '#EF4444'
    })
  } finally {
    loading.value = false
  }
}

// Submit function
async function submit() {
  // Prevent multiple simultaneous submits
  if (submitInProgress.value) {
    console.log('Submit already in progress, ignoring duplicate call')
    return
  }
  
  // Prevent submit if already loading
  if (loading.value) {
    console.log('Already loading, ignoring submit call')
    return
  }
  
  submitInProgress.value = true
  
  // Cancel any pending autosave
  if (autosaveTimeout.value) {
    clearTimeout(autosaveTimeout.value)
    autosaveTimeout.value = null
  }
  
  // Wait for any in-progress autosave to finish
  while (autosaveInProgress.value) {
    await new Promise(resolve => setTimeout(resolve, 100))
  }
  
  try {
    // Validation: check if approval required and approvers are set
    // Only r_and_d, marketing, wrong_maker, and training require approval
    const requiresApproval = form.value.type === 'r_and_d' || form.value.type === 'marketing' || form.value.type === 'wrong_maker' || form.value.type === 'training'
    if (requiresApproval && (!form.value.approvers || form.value.approvers.length === 0)) {
      Swal.fire({
        icon: 'error',
        title: 'Validasi Gagal',
        text: 'Tipe ini wajib memiliki minimal 1 approver',
      })
      submitInProgress.value = false
      return
    }

    // Validate stock before submit
    const stockValidation = await validateStockBeforeSubmit()
    if (!stockValidation.valid) {
      Swal.fire({
        icon: 'error',
        title: 'Validasi Stock Gagal',
        text: stockValidation.message,
        confirmButtonText: 'OK',
        confirmButtonColor: '#EF4444'
      })
      submitInProgress.value = false
      return
    }

    loading.value = true
    
    // Prepare form data
    const formData = {
      type: form.value.type,
      date: form.value.date,
      outlet_id: form.value.outlet_id,
      warehouse_outlet_id: form.value.warehouse_outlet_id,
      notes: form.value.notes,
      items: form.value.items
        .filter(item => item.item_id && item.qty > 0)
        .map(item => ({
          item_id: item.item_id,
          qty: item.qty,
          unit_id: item.unit_id,
          note: item.note || null
        })),
      approvers: form.value.approvers.map(a => a.id)
    }
    
    // If header_id exists, submit existing draft
    // Otherwise, create and submit directly
    const submitUrl = form.value.header_id 
      ? route('outlet-internal-use-waste.submit', form.value.header_id)
      : route('outlet-internal-use-waste.store-and-submit')
    
    // If submitting existing draft, only send approvers
    const submitData = form.value.header_id 
      ? { approvers: form.value.approvers.map(a => a.id) }
      : formData
    
    console.log('Calling submit endpoint:', submitUrl, submitData)
    const response = await axios.post(submitUrl, submitData, {
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      }
    })
    
    console.log('Submit response:', response.data)
    
    if (response.data.success) {
      // Check if status was already changed (prevent double submit)
      if (response.data.current_status && response.data.current_status !== 'DRAFT' && response.data.current_status !== response.data.status) {
        Swal.fire({
          icon: 'info',
          title: 'Sudah Di-submit',
          text: 'Data ini sudah di-submit sebelumnya. Status: ' + response.data.current_status,
          timer: 2000,
          showConfirmButton: false
        }).then(() => {
          router.visit(route('outlet-internal-use-waste.index'))
        })
        return
      }
      
      Swal.fire({
        icon: 'success',
        title: 'Berhasil',
        text: response.data.message || 'Data berhasil di-submit',
        timer: 1500,
        showConfirmButton: false
      }).then(() => {
        router.visit(route('outlet-internal-use-waste.index'))
      })
    } else {
      Swal.fire({
        icon: 'error',
        title: 'Gagal Submit',
        text: response.data.message || 'Gagal submit data',
        confirmButtonText: 'OK',
        confirmButtonColor: '#EF4444'
      })
    }
  } catch (error) {
    console.error('Submit failed:', error)
    console.error('Error response:', error.response?.data)
    const errorMessage = error.response?.data?.message || error.message || 'Gagal submit data'
    Swal.fire({
      icon: 'error',
      title: 'Gagal Submit',
      text: errorMessage,
      confirmButtonText: 'OK',
      confirmButtonColor: '#EF4444'
    })
  } finally {
    loading.value = false
    submitInProgress.value = false
  }
}

// Old submit function (kept for reference but not used)
async function submitOld() {
  // Validation: check if approval required and approvers are set
  // Only r_and_d, marketing, and wrong_maker require approval
  const requiresApproval = form.value.type === 'r_and_d' || form.value.type === 'marketing' || form.value.type === 'wrong_maker'
  if (requiresApproval && (!form.value.approvers || form.value.approvers.length === 0)) {
    Swal.fire({
      icon: 'error',
      title: 'Validasi Gagal',
      text: 'Tipe ini wajib memiliki minimal 1 approver',
    })
    return
  }

  loading.value = true
  console.log('Submitting form data:', form.value)
  
  // Prepare form data - convert approvers array to IDs array for backend
  const formData = {
    ...form.value,
    approvers: form.value.approvers.map(a => a.id)
  }
  
  try {
    await router.post(route('outlet-internal-use-waste.store'), formData, {
      onSuccess: (page) => {
        console.log('Form submitted successfully', page.props)
        
        // PRIORITAS: Cek error terlebih dahulu sebelum menampilkan sukses
        if (page.props.flash?.error) {
          console.error('Error from backend:', page.props.flash.error)
          Swal.fire({
            icon: 'error',
            title: 'Gagal Menyimpan Data',
            html: page.props.flash.error,
            confirmButtonText: 'OK',
            confirmButtonColor: '#EF4444',
            width: '600px'
          })
          loading.value = false
          return
        }
        
        // Hanya tampilkan sukses jika benar-benar tidak ada error
        // Cek juga apakah ada error di response
        if (page.props.errors && Object.keys(page.props.errors).length > 0) {
          console.error('Validation errors:', page.props.errors)
          const errorMessages = Object.values(page.props.errors).flat().join('<br>')
          Swal.fire({
            icon: 'error',
            title: 'Gagal Menyimpan Data',
            html: 'Terjadi kesalahan validasi:<br><br>' + errorMessages,
            confirmButtonText: 'OK',
            confirmButtonColor: '#EF4444',
            width: '600px'
          })
          loading.value = false
          return
        }
        
        // Cek apakah ada pesan success
        if (page.props.flash?.success) {
          Swal.fire({
            icon: 'success',
            title: 'Berhasil',
            text: page.props.flash.success,
            timer: 1500,
            showConfirmButton: false
          }).then(() => {
            // Redirect ke index setelah sukses
            router.visit(route('outlet-internal-use-waste.index'))
          })
        } else {
          // Jika tidak ada flash message, tetap tampilkan sukses tapi dengan peringatan
          Swal.fire({
            icon: 'success',
            title: 'Berhasil',
            text: 'Data berhasil disimpan!',
            timer: 1500,
            showConfirmButton: false
          }).then(() => {
            router.visit(route('outlet-internal-use-waste.index'))
          })
        }
        loading.value = false
      },
      onError: (errors) => {
        console.error('Error submitting form:', errors)
        
        // Buat pesan error yang lebih detail
        let errorMessage = 'Gagal menyimpan data. '
        
        // Cek apakah ada error message langsung
        if (errors.message) {
          errorMessage = errors.message
        } else if (typeof errors === 'string') {
          errorMessage = errors
        } else if (errors.error) {
          errorMessage = errors.error
        } else {
          // Jika ada validation errors, format dengan lebih baik
          const errorList = []
          
          // Loop semua field errors
          if (typeof errors === 'object') {
            for (const [field, messages] of Object.entries(errors)) {
              if (Array.isArray(messages)) {
                errorList.push(`<strong>${field}:</strong> ${messages.join(', ')}`)
              } else if (typeof messages === 'string') {
                errorList.push(`<strong>${field}:</strong> ${messages}`)
              }
            }
          }
          
          // Fallback untuk field-field spesifik
          if (errorList.length === 0) {
            if (errors.items) {
              errorList.push('Items: ' + (Array.isArray(errors.items) ? errors.items.join(', ') : errors.items))
            }
            if (errors.type) {
              errorList.push('Type: ' + (Array.isArray(errors.type) ? errors.type.join(', ') : errors.type))
            }
            if (errors.outlet_id) {
              errorList.push('Outlet: ' + (Array.isArray(errors.outlet_id) ? errors.outlet_id.join(', ') : errors.outlet_id))
            }
            if (errors.warehouse_outlet_id) {
              errorList.push('Warehouse Outlet: ' + (Array.isArray(errors.warehouse_outlet_id) ? errors.warehouse_outlet_id.join(', ') : errors.warehouse_outlet_id))
            }
            if (errors.date) {
              errorList.push('Tanggal: ' + (Array.isArray(errors.date) ? errors.date.join(', ') : errors.date))
            }
            if (errors.approvers) {
              errorList.push('Approvers: ' + (Array.isArray(errors.approvers) ? errors.approvers.join(', ') : errors.approvers))
            }
          }
          
          if (errorList.length > 0) {
            errorMessage += '<br><br><strong>Detail error:</strong><br>' + errorList.join('<br>')
          } else {
            errorMessage += 'Silakan cek input Anda dan pastikan semua data valid. Jika masalah berlanjut, hubungi administrator.'
          }
        }
        
        Swal.fire({
          icon: 'error',
          title: 'Gagal Menyimpan Data',
          html: errorMessage,
          confirmButtonText: 'OK',
          confirmButtonColor: '#EF4444',
          width: '600px'
        })
        loading.value = false
      },
      onFinish: () => {
        loading.value = false
      }
    })
  } catch (e) {
    console.error('Exception during form submission:', e)
    loading.value = false
    Swal.fire({
      icon: 'error',
      title: 'Gagal',
      text: 'Terjadi kesalahan sistem: ' + (e.message || 'Unknown error'),
      confirmButtonText: 'OK',
      confirmButtonColor: '#EF4444'
    })
  }
}

function goBack() {
  router.visit(route('outlet-internal-use-waste.index'))
}


// Approval functions
let approverSearchTimeout = null

const handleApproverSearch = () => {
  // Clear previous timeout
  if (approverSearchTimeout) {
    clearTimeout(approverSearchTimeout)
  }
  
  // Only search if at least 2 characters
  if (approverSearch.value.length >= 2) {
    approverSearchTimeout = setTimeout(() => {
      loadApprovers(approverSearch.value)
    }, 300) // Debounce 300ms
  } else {
    approverResults.value = []
    showApproverDropdown.value = false
  }
}

const handleApproverFocus = () => {
  // If already has search text, show results
  if (approverSearch.value.length >= 2) {
    loadApprovers(approverSearch.value)
  }
}

const handleApproverBlur = () => {
  // Delay closing dropdown to allow click on results
  setTimeout(() => {
    showApproverDropdown.value = false
  }, 200)
}

const loadApprovers = async (search = '') => {
  if (!search || search.length < 2) {
    approverResults.value = []
    showApproverDropdown.value = false
    return
  }
  
  try {
    const response = await axios.get('/outlet-internal-use-waste/approvers', {
      params: { search },
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json'
      }
    })
    
    if (response.data.success) {
      approverResults.value = response.data.users
      showApproverDropdown.value = true
    } else {
      approverResults.value = []
      showApproverDropdown.value = false
    }
  } catch (error) {
    console.error('Failed to load approvers:', error)
    approverResults.value = []
    showApproverDropdown.value = false
  }
}

const addApprover = (user) => {
  // Check if user already exists
  if (!form.value.approvers.find(approver => approver.id === user.id)) {
    form.value.approvers.push(user)
  }
  approverSearch.value = ''
  showApproverDropdown.value = false
  approverResults.value = []
}

const removeApprover = (index) => {
  form.value.approvers.splice(index, 1)
}

const reorderApprover = (fromIndex, toIndex) => {
  const approver = form.value.approvers.splice(fromIndex, 1)[0]
  form.value.approvers.splice(toIndex, 0, approver)
}

// Handle flash messages from backend
watch(() => page.props.flash, (flash) => {
  if (flash?.error) {
    Swal.fire({
      icon: 'error',
      title: 'Gagal Menyimpan Data',
      html: flash.error,
      confirmButtonText: 'OK',
      confirmButtonColor: '#EF4444',
      width: '600px'
    })
  }
}, { immediate: true })

// Tambahkan fungsi fetchStock untuk mengambil stok dari warehouse outlet
async function fetchStock(idx) {
  const item = form.value.items[idx];
  if (!item.item_id || !form.value.warehouse_outlet_id || !form.value.outlet_id) return;
  try {
    const res = await axios.get('/api/outlet-inventory/stock', {
      params: { 
        item_id: item.item_id, 
        warehouse_outlet_id: form.value.warehouse_outlet_id,
        outlet_id: form.value.outlet_id
      }
    });
    // Simpan stok ke item
    item.stock = res.data;
  } catch (e) {
    console.error('Error fetching stock:', e);
    item.stock = { 
      qty_small: 0, 
      qty_medium: 0, 
      qty_large: 0,
      unit_small: '',
      unit_medium: '',
      unit_large: ''
    };
  }
}

function formatNumber(val) {
  if (val == null) return 0;
  if (Number(val) % 1 === 0) return Number(val);
  return Number(val).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

function formatStockDisplay(item) {
  if (!item.stock) return 'Stok: 0';
  const small = Number(item.stock.qty_small || 0);
  const medium = Number(item.stock.qty_medium || 0);
  const large = Number(item.stock.qty_large || 0);
  
  let display = 'Stok: ';
  const parts = [];
  
  if (small > 0 || item.stock.unit_small) {
    parts.push(`${formatNumber(small)} ${item.stock.unit_small || ''}`);
  }
  if (medium > 0 || item.stock.unit_medium) {
    parts.push(`${formatNumber(medium)} ${item.stock.unit_medium || ''}`);
  }
  if (large > 0 || item.stock.unit_large) {
    parts.push(`${formatNumber(large)} ${item.stock.unit_large || ''}`);
  }
  
  if (parts.length === 0) {
    return 'Stok: 0';
  }
  
  return display + parts.join(' | ');
}

// Panggil fetchStock setiap kali warehouse_outlet_id berubah
watch(() => form.value.warehouse_outlet_id, (newVal) => {
  form.value.items.forEach((item, idx) => {
    if (item.item_id && newVal) fetchStock(idx);
  });
});

// Cleanup timeout on unmount
onBeforeUnmount(() => {
  if (autosaveTimeout.value) {
    clearTimeout(autosaveTimeout.value)
  }
})

</script>

<style scoped>
.input { @apply border border-gray-300 rounded px-3 py-2; }

/* Autocomplete dropdown styling */
.autocomplete-dropdown {
  z-index: 9999 !important;
  position: absolute !important;
}

/* Ensure table cells don't clip dropdown */
td {
  overflow: visible !important;
  position: relative !important;
}

/* Ensure table and tbody don't clip */
table {
  position: relative;
  overflow: visible !important;
}

tbody {
  position: relative;
  overflow: visible !important;
}

tr {
  position: relative;
  overflow: visible !important;
}

/* Ensure container doesn't clip */
.overflow-x-auto {
  overflow-y: auto !important;
  overflow-x: auto !important;
}

/* When dropdown is open, ensure parent doesn't clip */
.relative:has(.autocomplete-dropdown) {
  overflow: visible !important;
  z-index: 10;
}
</style>