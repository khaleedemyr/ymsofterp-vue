<template>
  <AppLayout>
    <div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 md:px-8">
      <div class="w-full max-w-2xl bg-white rounded-2xl shadow-2xl p-8">
        <h1 class="text-2xl font-bold mb-8 flex items-center gap-2 text-green-700">
          <i class="fa-solid fa-recycle text-green-500"></i> Input Category Cost Outlet
        </h1>
        <form @submit.prevent="submit" class="space-y-5">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-bold text-gray-600 mb-1">Tipe</label>
              <select v-model="form.type" class="input input-bordered w-full" required>
                <option value="">Pilih Tipe</option>
                <option value="internal_use">Internal Use</option>
                <option value="spoil">Spoil</option>
                <option value="waste">Waste</option>
                <option value="r_and_d">R & D</option>
                <option value="marketing">Marketing</option>
                <option value="non_commodity">Non Commodity</option>
                <option value="guest_supplies">Guest Supplies</option>
                <option value="wrong_maker">Wrong Maker</option>
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
          <div>
            <label class="block text-xs font-bold text-gray-600 mb-1">Items</label>
            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Item</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Qty</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Unit</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Note</th>
                    <th class="px-3 py-2"></th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <tr v-for="(item, idx) in form.items" :key="idx">
                    <td class="px-3 py-2 min-w-[200px]">
                      <div class="relative">
                        <input
                          :id="`item-input-${idx}`"
                          type="text"
                          v-model="item.item_name"
                          @input="onItemInput(idx, $event)"
                          @focus="onItemInput(idx, $event)"
                          @blur="onItemBlur(idx)"
                          @keydown.down="onItemKeydown(idx, $event)"
                          @keydown.up="onItemKeydown(idx, $event)"
                          @keydown.enter="onItemKeydown(idx, $event)"
                          @keydown.esc="onItemKeydown(idx, $event)"
                          class="input input-bordered w-full"
                          required
                          autocomplete="off"
                          placeholder="Cari nama item..."
                        />
                        <Teleport to="body">
                          <div v-if="item.showDropdown && item.suggestions && item.suggestions.length > 0"
                            :style="getDropdownStyle(idx)"
                            :id="`autocomplete-dropdown-${idx}`"
                            class="fixed z-[99999] bg-white border border-blue-200 rounded shadow max-w-xs w-[260px] max-h-96 overflow-auto mt-1"
                          >
                            <div v-for="(s, sidx) in item.suggestions" :key="s.id"
                              :id="`autocomplete-item-${idx}-${sidx}`"
                              @mousedown.prevent="selectItem(idx, s)"
                              :class="['px-3 py-2 flex justify-between items-center cursor-pointer', item.highlightedIndex === sidx ? 'bg-blue-100' : 'hover:bg-blue-50']"
                            >
                              <div>
                                <div class="font-medium">{{ s.name }}</div>
                                <div class="text-xs text-gray-500">{{ s.sku }}</div>
                              </div>
                              <div class="text-sm text-gray-600">{{ s.unit_small || s.unit || '' }}</div>
                            </div>
                          </div>
                        </Teleport>
                        <div v-if="item.loading" class="absolute right-2 top-2">
                          <i class="fa fa-spinner fa-spin text-blue-400"></i>
                        </div>
                        <div v-if="!form.warehouse_outlet_id" class="text-xs text-yellow-600 mt-1">
                          Pilih warehouse outlet terlebih dahulu untuk melihat stok.
                        </div>
                        <div v-if="item.stock" class="text-xs text-gray-500 mt-1">
                          Stok: {{ formatStockDisplay(item) }}
                        </div>
                      </div>
                    </td>
                    <td class="px-3 py-2 min-w-[100px]">
                      <input type="number" min="0.01" step="0.01" v-model.number="item.qty" class="input input-bordered w-full" required />
                    </td>
                    <td class="px-3 py-2 min-w-[100px]">
                      <input type="text" :value="getItemUnitName(item)" class="input input-bordered w-full bg-gray-100" readonly />
                    </td>
                    <td class="px-3 py-2 min-w-[120px]">
                      <input type="text" v-model="item.note" class="input input-bordered w-full" />
                    </td>
                    <td class="px-3 py-2">
                      <button type="button" @click="removeItem(idx)" class="text-red-500 hover:text-red-700" :disabled="form.items.length === 1"><i class="fa fa-trash"></i></button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <button type="button" @click="addItem" class="mt-3 px-4 py-2 bg-blue-100 text-blue-700 rounded hover:bg-blue-200 font-semibold"><i class="fa fa-plus"></i> Tambah Item</button>
          </div>
          <div>
            <label class="block text-xs font-bold text-gray-600 mb-1">Catatan Umum</label>
            <textarea v-model="form.notes" class="input input-bordered w-full" rows="2" placeholder="Catatan tambahan"></textarea>
          </div>

          <!-- Approval Flow Section (Only for r_and_d, marketing, wrong_maker) -->
          <div v-if="form.type === 'r_and_d' || form.type === 'marketing' || form.type === 'wrong_maker'" class="mb-6">
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

          <div class="flex justify-end gap-2 mt-8">
            <button type="button" class="btn btn-ghost px-6 py-2 rounded-lg" @click="goBack">Batal</button>
            <button type="submit" class="btn bg-gradient-to-r from-green-500 to-green-700 text-white px-8 py-2 rounded-lg font-bold shadow hover:shadow-xl transition-all" :disabled="loading">
              <span v-if="loading">
                <i class="fa fa-spinner fa-spin"></i> Menyimpan...
              </span>
              <span v-else>
                Simpan
              </span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, watch, computed, onMounted } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import Swal from 'sweetalert2'

const props = defineProps({
  outlets: Array,
  items: Array,
  warehouse_outlets: Array
})

const page = usePage()
const userOutletId = computed(() => page.props.auth?.user?.id_outlet || '')

// Add filtered warehouse outlets computed property
const filteredWarehouseOutlets = ref(props.warehouse_outlets || [])

function newItem() {
  return {
    item_id: '',
    item_name: '',
    qty: '',
    unit_id: '',
    unit_name: '',
    note: '',
    suggestions: [],
    showDropdown: false,
    highlightedIndex: -1,
    loading: false,
    stock: null
  }
}

const form = ref({
  type: 'internal_use',
  date: '',
  outlet_id: userOutletId.value == 1 ? '' : userOutletId.value,
  notes: '',
  items: [newItem()],
  warehouse_outlet_id: '',
  approvers: []
})

const approverSearch = ref('')
const approverResults = ref([])
const showApproverDropdown = ref(false)

const outletDisabled = computed(() => userOutletId.value != 1)
const loading = ref(false)

// Add watch function to monitor outlet changes
watch(() => form.value.outlet_id, async (newOutletId) => {
  // Reset warehouse outlet selection when outlet changes
  form.value.warehouse_outlet_id = ''
  
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

function onItemInput(idx, e) {
  const value = e.target.value;
  form.value.items[idx].item_id = '';
  form.value.items[idx].item_name = value;
  form.value.items[idx].showDropdown = true;
  fetchItemSuggestions(idx, value);
}

function selectItem(idx, item) {
  form.value.items[idx].item_id = item.id;
  form.value.items[idx].item_name = item.name;
  form.value.items[idx].suggestions = [];
  form.value.items[idx].showDropdown = false;
  form.value.items[idx].highlightedIndex = -1;
  // Set small unit directly
  setSmallUnit(idx, item.id);
  // Fetch stock untuk item yang dipilih
  if (form.value.warehouse_outlet_id) {
    fetchStock(idx);
  }
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

function onItemBlur(idx) {
  setTimeout(() => {
    form.value.items[idx].showDropdown = false;
  }, 200);
}

function onItemKeydown(idx, e) {
  const item = form.value.items[idx];
  if (!item.showDropdown || !item.suggestions.length) return;
  if (e.key === 'ArrowDown') {
    e.preventDefault();
    item.highlightedIndex = (item.highlightedIndex + 1) % item.suggestions.length;
  } else if (e.key === 'ArrowUp') {
    e.preventDefault();
    item.highlightedIndex = (item.highlightedIndex - 1 + item.suggestions.length) % item.suggestions.length;
  } else if (e.key === 'Enter') {
    e.preventDefault();
    if (item.highlightedIndex >= 0 && item.suggestions[item.highlightedIndex]) {
      selectItem(idx, item.suggestions[item.highlightedIndex]);
    }
  } else if (e.key === 'Escape') {
    item.showDropdown = false;
  }
}

async function submit() {
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

function getDropdownStyle(idx) {
  const input = document.getElementById(`item-input-${idx}`);
  if (!input) return {};
  const rect = input.getBoundingClientRect();
  return {
    position: 'fixed',
    left: `${rect.left}px`,
    top: `${rect.bottom}px`,
    width: `${rect.width}px`,
    zIndex: 99999
  };
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
  if (!item.item_id || !form.value.warehouse_outlet_id) return;
  try {
    const res = await axios.get('/api/outlet-inventory/stock', {
      params: { item_id: item.item_id, warehouse_outlet_id: form.value.warehouse_outlet_id }
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

</script>

<style scoped>
.input { @apply border border-gray-300 rounded px-3 py-2; }
</style>