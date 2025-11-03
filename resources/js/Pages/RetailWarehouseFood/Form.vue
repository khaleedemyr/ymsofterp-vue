<template>
  <AppLayout>
    <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-blue-50 to-white py-12 px-4 md:px-8">
      <div class="w-full max-w-4xl bg-white rounded-3xl shadow-3xl p-10 border border-blue-100 transition-all duration-300 hover:shadow-4xl">
        <h1 class="text-3xl font-extrabold mb-6 flex items-center gap-3 text-blue-800 drop-shadow-lg">
          <i class="fa-solid fa-warehouse text-blue-500 text-3xl"></i> Input Warehouse Retail Food
        </h1>

        <!-- Informasi Cara Penggunaan -->
        <div class="mb-8 p-4 bg-blue-50 border border-blue-200 rounded-xl">
          <h3 class="text-lg font-semibold text-blue-800 mb-3 flex items-center gap-2">
            <i class="fa-solid fa-info-circle text-blue-600"></i>
            Cara Penggunaan Warehouse Retail Food
          </h3>
          <div class="text-sm text-blue-700 space-y-2">
            <div class="flex items-start gap-2">
              <span class="bg-blue-600 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs font-bold flex-shrink-0 mt-0.5">1</span>
              <span><strong>Isi Data Transaksi:</strong> Pilih tanggal, warehouse, warehouse division (opsional), metode pembayaran (Cash/Contra Bon), dan supplier. <strong>Jika metode pembayaran Cash, pilih supplier "CASH SUPPLIER".</strong></span>
            </div>
            <div class="flex items-start gap-2">
              <span class="bg-blue-600 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs font-bold flex-shrink-0 mt-0.5">2</span>
              <span><strong>Tambah Items:</strong> Cari item dengan mengetik nama item, pilih unit dan isi quantity serta harga. <strong>Jika metode pembayaran Contra Bon, unit medium dan harga akan otomatis terisi.</strong> Klik "+ Tambah Item" untuk menambah item baru.</span>
            </div>
            <div class="flex items-start gap-2">
              <span class="bg-blue-600 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs font-bold flex-shrink-0 mt-0.5">3</span>
              <span><strong>Upload Bukti:</strong> Upload foto bon/invoice dalam format JPG/PNG (bisa lebih dari 1 file).</span>
            </div>
            <div class="flex items-start gap-2">
              <span class="bg-blue-600 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs font-bold flex-shrink-0 mt-0.5">4</span>
              <span><strong>Simpan:</strong> Klik tombol "Simpan" untuk menyimpan transaksi. Data akan otomatis masuk ke inventory warehouse.</span>
            </div>
          </div>
        </div>

        <form @submit.prevent="submit" class="space-y-7">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
              <label class="block text-xs font-bold text-gray-600 mb-2">Tanggal</label>
              <input type="date" v-model="form.transaction_date" class="input input-bordered w-full shadow-inner rounded-xl focus:ring-2 focus:ring-blue-300 transition-all duration-200" required />
            </div>
            <div>
              <label class="block text-xs font-bold text-gray-600 mb-2">Warehouse</label>
              <select v-model="form.warehouse_id" class="input input-bordered w-full shadow-inner rounded-xl focus:ring-2 focus:ring-blue-300 transition-all duration-200" required>
                <option value="">Pilih Warehouse</option>
                <option v-for="w in props.warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-bold text-gray-600 mb-2">Warehouse Division (Opsional)</label>
              <select v-model="form.warehouse_division_id" class="input input-bordered w-full shadow-inner rounded-xl focus:ring-2 focus:ring-blue-300 transition-all duration-200">
                <option value="">Pilih Warehouse Division</option>
                <option v-for="wd in props.warehouseDivisions" :key="wd.id" :value="wd.id">{{ wd.name }}</option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label class="block text-xs font-bold text-gray-600 mb-2">Metode Pembayaran</label>
              <select v-model="form.payment_method" class="input input-bordered w-full shadow-inner rounded-xl focus:ring-2 focus:ring-blue-300 transition-all duration-200" required>
                <option value="">Pilih Metode Pembayaran</option>
                <option value="cash">Cash</option>
                <option value="contra_bon">Contra Bon</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-bold text-gray-600 mb-2">Supplier</label>
              <Multiselect
                v-model="form.supplier"
                :options="props.suppliers"
                :searchable="true"
                :close-on-select="true"
                :clear-on-select="false"
                :preserve-search="true"
                placeholder="Pilih atau cari supplier..."
                track-by="id"
                label="name"
                :preselect-first="false"
                class="w-full"
              >
                <template #option="{ option }">
                  <div class="flex justify-between items-center">
                    <span>{{ option.name }}</span>
                    <span class="text-xs text-gray-500">{{ option.code }}</span>
                  </div>
                </template>
              </Multiselect>
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
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Harga</th>
                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Subtotal</th>
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
                      </div>
                    </td>
                    <td class="px-3 py-2 min-w-[100px]">
                      <input type="number" min="0.01" step="0.01" v-model.number="item.qty" @input="calculateSubtotal(idx)" class="input input-bordered w-full" required />
                    </td>
                    <td class="px-3 py-2 min-w-[100px]">
                      <div class="relative">
                        <select v-model="item.unit_id" class="input input-bordered w-full" required>
                          <option value="">Pilih Unit</option>
                          <option v-for="u in item.unitOptions" :key="u.id" :value="u.id">
                            {{ u.name }}{{ u.is_medium ? ' (Medium)' : '' }}
                          </option>
                        </select>
                        <div v-if="form.payment_method === 'contra_bon' && item.unitOptions.find(u => u.id === item.unit_id)?.is_medium" 
                             class="absolute -top-1 -right-1 bg-blue-500 text-white text-xs px-1 rounded-full">
                          Auto
                        </div>
                      </div>
                    </td>
                    <td class="px-3 py-2 min-w-[150px]">
                      <div class="relative">
                        <input type="number" min="0" step="0.01" v-model.number="item.price" @input="calculateSubtotal(idx)" class="input input-bordered w-full" required />
                        <div v-if="form.payment_method === 'contra_bon' && item.price > 0" 
                             class="absolute -top-1 -right-1 bg-green-500 text-white text-xs px-1 rounded-full">
                          Auto
                        </div>
                      </div>
                    </td>
                    <td class="px-3 py-2 min-w-[150px] text-right">
                      {{ formatRupiah(item.subtotal) }}
                    </td>
                    <td class="px-3 py-2">
                      <button type="button" @click="removeItem(idx)" class="text-red-500 hover:text-red-700" :disabled="form.items.length === 1">
                        <i class="fa-solid fa-trash"></i>
                      </button>
                    </td>
                  </tr>
                </tbody>
                <tfoot>
                  <tr>
                    <td colspan="4" class="px-3 py-2 text-right font-bold">Total:</td>
                    <td class="px-3 py-2 text-right font-bold">{{ formatRupiah(totalAmount) }}</td>
                    <td></td>
                  </tr>
                </tfoot>
              </table>
            </div>
            <div class="mt-2">
              <button type="button" @click="addItem" class="text-blue-500 hover:text-blue-700">
                <i class="fa fa-plus mr-1"></i> Tambah Item
              </button>
            </div>
          </div>

          <div>
            <label class="block text-xs font-bold text-gray-600 mb-1">Catatan</label>
            <textarea v-model="form.notes" class="input input-bordered w-full" rows="3"></textarea>
          </div>

          <div>
            <label class="block text-xs font-bold text-gray-600 mb-1">Upload Bon/Invoice (jpg/png, bisa lebih dari 1)</label>
            <input type="file" multiple accept="image/jpeg,image/png" @change="onFileChange" />
            <div v-if="filePreviews.length" class="flex flex-wrap gap-2 mt-2">
              <div v-for="(src, idx) in filePreviews" :key="idx" class="w-24 h-24 border rounded overflow-hidden flex items-center justify-center bg-gray-50">
                <img :src="src" class="object-contain w-full h-full" />
              </div>
            </div>
          </div>

          <div class="flex justify-end gap-2">
            <button type="button" @click="goBack" class="btn px-6 py-2 rounded-lg font-bold bg-gradient-to-r from-gray-200 to-gray-400 text-gray-700 shadow-md hover:from-gray-300 hover:to-gray-500 active:scale-95 transition-all">
              Batal
            </button>
            <button type="submit" class="btn px-6 py-2 rounded-lg font-bold bg-gradient-to-r from-blue-500 to-blue-700 text-white shadow-lg hover:from-blue-600 hover:to-blue-800 active:scale-95 transition-all flex items-center gap-2" :disabled="loading">
              <span v-if="loading"><i class="fa fa-spinner fa-spin"></i> Menyimpan...</span>
              <span v-else><i class="fa fa-save"></i> Simpan</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import Swal from 'sweetalert2'
import Multiselect from 'vue-multiselect'
import 'vue-multiselect/dist/vue-multiselect.min.css'

const props = defineProps({
  warehouses: Array,
  warehouseDivisions: Array,
  suppliers: Array
})

const page = usePage()

function newItem() {
  return {
    item_id: '',
    item_name: '',
    qty: '',
    unit_id: '',
    price: 0,
    subtotal: 0,
    unitOptions: [],
    suggestions: [],
    showDropdown: false,
    highlightedIndex: -1,
    loading: false
  }
}

const form = ref({
  transaction_date: new Date().toISOString().split('T')[0],
  warehouse_id: '',
  warehouse_division_id: '',
  notes: '',
  payment_method: '',
  supplier: null,
  items: [newItem()]
})

const loading = ref(false)

function addItem() {
  form.value.items.push(newItem())
}

function removeItem(idx) {
  if (form.value.items.length === 1) return
  form.value.items.splice(idx, 1)
}

function calculateSubtotal(idx) {
  const item = form.value.items[idx]
  item.subtotal = (item.qty || 0) * (item.price || 0)
}

const totalAmount = computed(() => {
  return form.value.items.reduce((sum, item) => sum + (item.subtotal || 0), 0)
})

async function fetchItemSuggestions(idx, q) {
  if (!q || q.length < 2) {
    form.value.items[idx].suggestions = []
    form.value.items[idx].highlightedIndex = -1
    return
  }
  form.value.items[idx].loading = true
  try {
    const res = await axios.get('/items/search-for-outlet-transfer', {
      params: {
        q: q,
        warehouse_id: form.value.warehouse_id
      }
    })
    form.value.items[idx].suggestions = res.data
    form.value.items[idx].showDropdown = true
    form.value.items[idx].highlightedIndex = 0
  } finally {
    form.value.items[idx].loading = false
  }
}

function onItemInput(idx, e) {
  const value = e.target.value
  form.value.items[idx].item_id = ''
  form.value.items[idx].item_name = value
  form.value.items[idx].showDropdown = true
  fetchItemSuggestions(idx, value)
}

async function selectItem(idx, item) {
  form.value.items[idx].item_id = item.id
  form.value.items[idx].item_name = item.name
  form.value.items[idx].suggestions = []
  form.value.items[idx].showDropdown = false
  form.value.items[idx].highlightedIndex = -1
  
  // Get units with payment method
  const res = await axios.get(`/retail-warehouse-food/get-item-units/${item.id}`, {
    params: {
      payment_method: form.value.payment_method
    }
  })
  
  form.value.items[idx].unitOptions = res.data.units
  
  // Auto-fill unit and price for contra bon payment method
  if (form.value.payment_method === 'contra_bon' && res.data.default_unit) {
    form.value.items[idx].unit_id = res.data.default_unit.id
    if (res.data.default_price > 0) {
      form.value.items[idx].price = res.data.default_price
    }
    calculateSubtotal(idx)
  } else {
    form.value.items[idx].unit_id = ''
  }
}

function onItemBlur(idx) {
  setTimeout(() => {
    form.value.items[idx].showDropdown = false
  }, 200)
}

function onItemKeydown(idx, e) {
  const item = form.value.items[idx]
  if (!item.showDropdown || !item.suggestions.length) return
  if (e.key === 'ArrowDown') {
    e.preventDefault()
    item.highlightedIndex = (item.highlightedIndex + 1) % item.suggestions.length
  } else if (e.key === 'ArrowUp') {
    e.preventDefault()
    item.highlightedIndex = (item.highlightedIndex - 1 + item.suggestions.length) % item.suggestions.length
  } else if (e.key === 'Enter') {
    e.preventDefault()
    if (item.highlightedIndex >= 0 && item.suggestions[item.highlightedIndex]) {
      selectItem(idx, item.suggestions[item.highlightedIndex])
    }
  } else if (e.key === 'Escape') {
    item.showDropdown = false
  }
}

function getDropdownStyle(idx) {
  const input = document.getElementById(`item-input-${idx}`)
  if (!input) return {}
  const rect = input.getBoundingClientRect()
  return {
    position: 'fixed',
    left: `${rect.left}px`,
    top: `${rect.bottom}px`,
    width: `${rect.width}px`,
    zIndex: 99999
  }
}

// Watch untuk otomatis pilih CASH SUPPLIER jika metode pembayaran cash
watch(() => form.value.payment_method, (newValue) => {
  if (newValue === 'cash') {
    const cashSupplier = props.suppliers.find(s => s.name.toLowerCase().includes('cash supplier'))
    if (cashSupplier) {
      form.value.supplier = cashSupplier
    }
  } else if (newValue === 'contra_bon') {
    // Auto-fill unit medium and price for existing items when switching to contra bon
    for (let idx = 0; idx < form.value.items.length; idx++) {
      const item = form.value.items[idx]
      if (item.item_id && item.item_id !== '') {
        axios.get(`/retail-warehouse-food/get-item-units/${item.item_id}`, {
          params: {
            payment_method: 'contra_bon'
          }
        }).then(res => {
          if (res.data.default_unit) {
            form.value.items[idx].unit_id = res.data.default_unit.id
            if (res.data.default_price > 0) {
              form.value.items[idx].price = res.data.default_price
            }
            calculateSubtotal(idx)
          }
        }).catch(err => {
          console.error('Error updating item for contra bon:', err)
        })
      }
    }
  }
})

const files = ref([])
const filePreviews = ref([])

function onFileChange(e) {
  files.value = Array.from(e.target.files)
  filePreviews.value = files.value.map(file => URL.createObjectURL(file))
}

async function submit() {
  if (loading.value) return
  const confirm = await Swal.fire({
    title: 'Simpan Data?',
    text: 'Apakah Anda yakin ingin menyimpan transaksi ini?',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Ya, Simpan',
    cancelButtonText: 'Batal',
    confirmButtonColor: '#2563eb',
    cancelButtonColor: '#6b7280',
    reverseButtons: true
  })
  if (!confirm.isConfirmed) return
  loading.value = true
  try {
    const formData = new FormData()
    formData.append('warehouse_id', form.value.warehouse_id)
    formData.append('warehouse_division_id', form.value.warehouse_division_id || '')
    formData.append('transaction_date', form.value.transaction_date)
    formData.append('notes', form.value.notes)
    formData.append('payment_method', form.value.payment_method)
    formData.append('supplier_id', form.value.supplier ? form.value.supplier.id : '')
    form.value.items.forEach((item, idx) => {
      formData.append(`items[${idx}][item_name]`, item.item_name)
      formData.append(`items[${idx}][qty]`, item.qty)
      formData.append(`items[${idx}][unit_id]`, item.unit_id)
      formData.append(`items[${idx}][unit]`, item.unitOptions.find(u => u.id === item.unit_id)?.name || '')
      formData.append(`items[${idx}][price]`, item.price)
    })
    files.value.forEach((file, idx) => {
      formData.append('invoices[]', file)
    })
    const res = await axios.post('/retail-warehouse-food', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    if (res.data.message) {
      await Swal.fire({
        icon: 'success',
        title: 'Berhasil',
        text: res.data.message,
        timer: 1500,
        showConfirmButton: false
      })
      router.visit('/retail-warehouse-food')
    }
  } catch (e) {
    Swal.fire({
      icon: 'error',
      title: 'Gagal',
      text: e.response?.data?.message || 'Gagal menyimpan transaksi'
    })
  } finally {
    loading.value = false
  }
}

function goBack() {
  router.visit('/retail-warehouse-food')
}

function formatRupiah(val) {
  if (!val) return 'Rp 0'
  return 'Rp ' + Number(val).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}
</script>

