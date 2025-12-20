<template>
  <AppLayout>
    <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-green-50 to-white py-12 px-4 md:px-8">
      <div class="w-full max-w-4xl bg-white rounded-3xl shadow-3xl p-10 border border-green-100 transition-all duration-300 hover:shadow-4xl">
        <h1 class="text-3xl font-extrabold mb-10 flex items-center gap-3 text-green-800 drop-shadow-lg">
          <i class="fa-solid fa-shopping-bag text-green-500 text-3xl"></i> Input Retail Non Food
        </h1>
        <form @submit.prevent="submit" class="space-y-7">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
              <label class="block text-xs font-bold text-gray-600 mb-2">Tanggal</label>
              <input type="date" v-model="form.transaction_date" class="input input-bordered w-full shadow-inner rounded-xl focus:ring-2 focus:ring-green-300 transition-all duration-200" required />
            </div>
            <div>
              <label class="block text-xs font-bold text-gray-600 mb-2">Outlet</label>
              <select v-model="form.outlet_id" :disabled="outletDisabled" class="input input-bordered w-full shadow-inner rounded-xl focus:ring-2 focus:ring-green-300 transition-all duration-200" required>
                <option v-if="userOutletId == 1" value="">Pilih Outlet</option>
                <option v-for="o in props.outlets" :key="o.id_outlet" :value="o.id_outlet">{{ o.nama_outlet }}</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-bold text-gray-600 mb-2">Category Budget <span class="text-red-500 text-xs">*</span></label>
              <select v-model="form.category_budget_id" @change="fetchBudgetInfo" :disabled="loadingCategories" class="input input-bordered w-full shadow-inner rounded-xl focus:ring-2 focus:ring-green-300 transition-all duration-200" required>
                <option value="">{{ loadingCategories ? 'Memuat...' : 'Pilih Category Budget' }}</option>
                <option v-for="cb in categoryBudgets" :key="cb.id" :value="cb.id">
                  [{{ cb.division }}] {{ cb.name }}
                </option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label class="block text-xs font-bold text-gray-600 mb-2">Metode Pembayaran</label>
              <select v-model="form.payment_method" class="input input-bordered w-full shadow-inner rounded-xl focus:ring-2 focus:ring-green-300 transition-all duration-200" required>
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

          <!-- Budget Information Section -->
          <div v-if="budgetInfo" class="mb-6 p-4 bg-yellow-50 border border-yellow-200 rounded-xl">
            <h3 class="text-lg font-semibold text-yellow-800 mb-3 flex items-center gap-2">
              <i class="fa-solid fa-chart-pie text-yellow-600"></i>
              Informasi Budget Category
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
              <div class="bg-white p-3 rounded-lg border border-yellow-200 shadow-sm">
                <div class="text-sm text-gray-600 mb-1">Category</div>
                <div class="font-semibold text-gray-800">{{ budgetInfo.category_name }}</div>
                <div class="text-xs text-gray-500 mt-1">{{ budgetInfo.division }} - {{ budgetInfo.subcategory }}</div>
              </div>
              <div class="bg-white p-3 rounded-lg border border-yellow-200 shadow-sm">
                <div class="text-sm text-gray-600 mb-1">Budget Limit</div>
                <div class="font-semibold text-green-600">{{ formatRupiah(budgetInfo.budget_limit) }}</div>
              </div>
              <div class="bg-white p-3 rounded-lg border border-yellow-200 shadow-sm">
                <div class="text-sm text-gray-600 mb-1">Total Digunakan</div>
                <div class="font-semibold text-blue-600">{{ formatRupiah(budgetInfo.total_used) }}</div>
                <div class="text-xs text-gray-500 mt-1">
                  RNF: {{ formatRupiah(budgetInfo.retail_non_food_used) }} | PR: {{ formatRupiah(budgetInfo.purchase_requisition_used) }}
                </div>
              </div>
              <div class="bg-white p-3 rounded-lg border border-yellow-200 shadow-sm">
                <div class="text-sm text-gray-600 mb-1">Sisa Budget</div>
                <div :class="budgetInfo.remaining_budget > 0 ? 'text-green-600' : 'text-red-600'" class="font-semibold">
                  {{ formatRupiah(budgetInfo.remaining_budget) }}
                </div>
                <div class="mt-2">
                  <div class="flex justify-between text-xs mb-1">
                    <span>Penggunaan</span>
                    <span>{{ budgetInfo.budget_percentage }}%</span>
                  </div>
                  <div class="w-full bg-gray-200 rounded-full h-2">
                    <div :class="budgetInfo.budget_percentage >= 90 ? 'bg-red-500' : budgetInfo.budget_percentage >= 70 ? 'bg-yellow-500' : 'bg-green-500'"
                         class="h-2 rounded-full transition-all duration-300"
                         :style="{ width: Math.min(budgetInfo.budget_percentage, 100) + '%' }">
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div v-if="budgetInfo.total_used + totalAmount > budgetInfo.budget_limit" class="mt-3 p-3 bg-red-50 border border-red-200 rounded-lg">
              <div class="flex items-center gap-2 text-red-800">
                <i class="fa fa-exclamation-triangle"></i>
                <span class="font-semibold">Peringatan: Total transaksi ini akan melebihi budget limit!</span>
              </div>
              <div class="text-sm text-red-700 mt-1">
                Total setelah transaksi: {{ formatRupiah(budgetInfo.total_used + totalAmount) }} 
                (Kelebihan: {{ formatRupiah((budgetInfo.total_used + totalAmount) - budgetInfo.budget_limit) }})
              </div>
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
                      <input
                        type="text"
                        v-model="item.item_name"
                        class="input input-bordered w-full"
                        required
                        placeholder="Masukkan nama item..."
                      />
                    </td>
                    <td class="px-3 py-2 min-w-[100px]">
                      <input type="number" min="0.01" step="0.01" v-model.number="item.qty" @input="calculateSubtotal(idx)" class="input input-bordered w-full" required />
                    </td>
                    <td class="px-3 py-2 min-w-[100px]">
                      <input type="text" v-model="item.unit" class="input input-bordered w-full" required placeholder="pcs, kg, dll" />
                    </td>
                    <td class="px-3 py-2 min-w-[150px]">
                      <input type="number" min="0" step="0.01" v-model.number="item.price" @input="calculateSubtotal(idx)" class="input input-bordered w-full" required />
                    </td>
                    <td class="px-3 py-2 min-w-[150px] text-right">
                      {{ formatRupiah((parseFloat(item.qty) || 0) * (parseFloat(item.price) || 0)) }}
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
              <button type="button" @click="addItem" class="text-green-500 hover:text-green-700">
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

          <div v-if="showLimitAlert" class="mb-4 p-4 rounded-xl bg-yellow-100 border border-yellow-300 text-yellow-800 shadow flex items-center gap-2 animate-pulse">
            <i class="fa fa-triangle-exclamation text-xl"></i>
            <span>Total transaksi retail non food outlet hari ini sudah melebihi Rp 500.000!</span>
          </div>

          <div class="flex justify-end gap-2">
            <button type="button" @click="goBack" class="btn px-6 py-2 rounded-lg font-bold bg-gradient-to-r from-gray-200 to-gray-400 text-gray-700 shadow-md hover:from-gray-300 hover:to-gray-500 active:scale-95 transition-all">
              Batal
            </button>
            <button type="submit" class="btn px-6 py-2 rounded-lg font-bold bg-gradient-to-r from-green-500 to-green-700 text-white shadow-lg hover:from-green-600 hover:to-green-800 active:scale-95 transition-all flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed" :disabled="loading || !form.category_budget_id">
              <span v-if="loading"><i class="fa fa-spinner fa-spin"></i> Menyimpan...</span>
              <span v-else-if="!form.category_budget_id"><i class="fa fa-exclamation-triangle"></i> Pilih Category Dulu</span>
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
  outlets: Array,
  warehouse_outlets: Array,
  categoryBudgets: Array,
  suppliers: Array
})

const page = usePage()
const userOutletId = computed(() => page.props.auth?.user?.id_outlet || '')

function newItem() {
  return {
    item_name: '',
    qty: '',
    unit: '',
    price: 0,
    subtotal: 0
  }
}

const outletDisabled = computed(() => userOutletId.value != 1)
const loading = ref(false)
const dailyTotal = ref(0)
const showLimitAlert = computed(() => (dailyTotal.value + totalAmount.value) >= 500000)
const budgetInfo = ref(null)
const categoryBudgets = ref(props.categoryBudgets || [])
const loadingCategories = ref(false)

const form = ref({
  transaction_date: new Date().toISOString().split('T')[0],
  outlet_id: userOutletId.value == 1 ? '' : userOutletId.value,
  category_budget_id: '',
  payment_method: '',
  supplier: null,
  notes: '',
  items: [newItem()]
})

const files = ref([])
const filePreviews = ref([])

function addItem() {
  form.value.items.push(newItem())
}

function removeItem(idx) {
  if (form.value.items.length === 1) return
  form.value.items.splice(idx, 1)
}

function calculateSubtotal(idx) {
  const item = form.value.items[idx]
  const qty = parseFloat(item.qty) || 0
  const price = parseFloat(item.price) || 0
  item.subtotal = qty * price
}

const totalAmount = computed(() => {
  return form.value.items.reduce((sum, item) => {
    const qty = parseFloat(item.qty) || 0
    const price = parseFloat(item.price) || 0
    const subtotal = qty * price
    return sum + subtotal
  }, 0)
})

function formatRupiah(val) {
  if (!val) return 'Rp 0'
  return 'Rp ' + Number(val).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

async function fetchDailyTotal() {
  if (!form.value.outlet_id || !form.value.transaction_date) {
    dailyTotal.value = 0
    return
  }
  try {
    const res = await axios.get('/retail-non-food/daily-total', {
      params: {
        outlet_id: form.value.outlet_id,
        transaction_date: form.value.transaction_date
      }
    })
    dailyTotal.value = res.data.total || 0
  } catch {
    dailyTotal.value = 0
  }
}

async function fetchCategoryBudgets() {
  if (!form.value.outlet_id) {
    // Jika tidak ada outlet yang dipilih, hanya ambil GLOBAL categories
    categoryBudgets.value = props.categoryBudgets.filter(cb => cb.budget_type === 'GLOBAL')
    return
  }
  
  loadingCategories.value = true
  try {
    const res = await axios.get('/retail-non-food/category-budgets', {
      params: {
        outlet_id: form.value.outlet_id
      }
    })
    if (res.data.success && res.data.data) {
      categoryBudgets.value = res.data.data
      // Reset category_budget_id jika tidak ada di list baru
      if (form.value.category_budget_id) {
        const exists = categoryBudgets.value.find(cb => cb.id == form.value.category_budget_id)
        if (!exists) {
          form.value.category_budget_id = ''
          budgetInfo.value = null
        }
      }
    }
  } catch (error) {
    console.error('Error fetching category budgets:', error)
    categoryBudgets.value = props.categoryBudgets.filter(cb => cb.budget_type === 'GLOBAL')
  } finally {
    loadingCategories.value = false
  }
}

async function fetchBudgetInfo() {
  if (!form.value.category_budget_id) {
    budgetInfo.value = null
    return
  }
  
  try {
    const res = await axios.post('/retail-non-food/get-budget-info', {
      category_budget_id: form.value.category_budget_id
    })
    budgetInfo.value = res.data.budget_info
  } catch (error) {
    console.error('Error fetching budget info:', error)
    budgetInfo.value = null
  }
}

watch([
  () => form.value.outlet_id,
  () => form.value.transaction_date,
  () => form.value.items.map(i => [i.qty, i.price])
], fetchDailyTotal, { immediate: true, deep: true })

// Watch outlet_id untuk fetch category budgets
watch(() => form.value.outlet_id, (newOutletId, oldOutletId) => {
  // Reset category_budget_id dan budgetInfo ketika outlet berubah
  if (oldOutletId !== undefined && newOutletId !== oldOutletId) {
    form.value.category_budget_id = ''
    budgetInfo.value = null
  }
  fetchCategoryBudgets()
}, { immediate: true })

// Watch untuk fetch budget info ketika category budget atau total amount berubah
watch([
  () => form.value.category_budget_id,
  () => totalAmount.value
], () => {
  if (form.value.category_budget_id) {
    fetchBudgetInfo()
  } else {
    budgetInfo.value = null
  }
}, { immediate: true })

// Watch untuk update subtotal setiap kali qty atau price berubah
watch(() => form.value.items, () => {
  form.value.items.forEach((item, idx) => {
    calculateSubtotal(idx)
  })
}, { deep: true })

// Watch untuk otomatis pilih CASH SUPPLIER jika metode pembayaran cash
watch(() => form.value.payment_method, async (newValue) => {
  if (newValue === 'cash' && props.suppliers) {
    // Cari supplier dengan nama "CASH SUPPLIER"
    const cashSupplier = props.suppliers.find(s => s.name.toLowerCase().includes('cash supplier'))
    if (cashSupplier) {
      form.value.supplier = cashSupplier
    }
  }
})

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
  
  // Validate category_budget_id is required
  if (!form.value.category_budget_id) {
    loading.value = false
    await Swal.fire({
      icon: 'warning',
      title: 'Category Diperlukan',
      text: 'Silakan pilih Category Budget sebelum menyimpan data.',
      confirmButtonText: 'OK',
      confirmButtonColor: '#F59E0B'
    })
    return
  }

  // Validate payment_method is required
  if (!form.value.payment_method) {
    loading.value = false
    await Swal.fire({
      icon: 'warning',
      title: 'Metode Pembayaran Diperlukan',
      text: 'Silakan pilih Metode Pembayaran sebelum menyimpan data.',
      confirmButtonText: 'OK',
      confirmButtonColor: '#F59E0B'
    })
    return
  }

  // Validate supplier is required
  if (!form.value.supplier || !form.value.supplier.id) {
    loading.value = false
    await Swal.fire({
      icon: 'warning',
      title: 'Supplier Diperlukan',
      text: 'Silakan pilih Supplier sebelum menyimpan data.',
      confirmButtonText: 'OK',
      confirmButtonColor: '#F59E0B'
    })
    return
  }
  
  loading.value = true
  try {
    const formData = new FormData()
    formData.append('outlet_id', form.value.outlet_id)
    formData.append('transaction_date', form.value.transaction_date)
    formData.append('category_budget_id', form.value.category_budget_id)
    formData.append('payment_method', form.value.payment_method)
    formData.append('supplier_id', form.value.supplier ? form.value.supplier.id : '')
    formData.append('notes', form.value.notes)
    form.value.items.forEach((item, idx) => {
      formData.append(`items[${idx}][item_name]`, item.item_name)
      formData.append(`items[${idx}][qty]`, parseFloat(item.qty) || 0)
      formData.append(`items[${idx}][unit]`, item.unit)
      formData.append(`items[${idx}][price]`, parseFloat(item.price) || 0)
    })
    files.value.forEach((file, idx) => {
      formData.append('invoices[]', file)
    })
    const res = await axios.post('/retail-non-food', formData, {
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
      router.visit('/retail-non-food')
    }
  } catch (e) {
    console.error('Error saving retail non food:', e)
    console.error('Error response:', e.response)
    console.error('Error data:', e.response?.data)
    
    let errorMessage = 'Gagal menyimpan transaksi'
    
    if (e.response?.data?.message) {
      errorMessage = e.response.data.message
    } else if (e.response?.data?.error) {
      errorMessage = e.response.data.error
    } else if (e.message) {
      errorMessage = e.message
    }
    
    // Jika ada validation errors, tampilkan detail
    if (e.response?.data?.errors) {
      const validationErrors = Object.values(e.response.data.errors).flat()
      errorMessage = 'Validasi gagal:\n' + validationErrors.join('\n')
    }
    
    await Swal.fire({
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

function goBack() {
  router.visit('/retail-non-food')
}
</script> 