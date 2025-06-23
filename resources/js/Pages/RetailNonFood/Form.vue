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
                <option value="">Pilih Outlet</option>
                <option v-for="o in props.outlets" :key="o.id_outlet" :value="o.id_outlet">{{ o.nama_outlet }}</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-bold text-gray-600 mb-2">Warehouse Outlet</label>
              <select v-model="form.warehouse_outlet_id" class="input input-bordered w-full shadow-inner rounded-xl focus:ring-2 focus:ring-green-300 transition-all duration-200">
                <option value="">Pilih Warehouse</option>
                <option v-for="wo in props.warehouse_outlets" :key="wo.id" :value="wo.id">{{ wo.name }}</option>
              </select>
            </div>
          </div>

          <!-- Items Section -->
          <div class="space-y-4">
            <div class="flex justify-between items-center">
              <h3 class="text-lg font-semibold text-gray-800">Items</h3>
              <button type="button" @click="addItem" class="bg-green-500 text-white px-3 py-1 rounded-lg text-sm hover:bg-green-600 transition-colors">
                <i class="fa-solid fa-plus mr-1"></i> Tambah Item
              </button>
            </div>

            <div v-for="(item, idx) in form.items" :key="idx" class="bg-gray-50 p-4 rounded-xl space-y-4">
              <div class="flex justify-between items-center">
                <h4 class="font-medium text-gray-700">Item {{ idx + 1 }}</h4>
                <button v-if="form.items.length > 1" type="button" @click="removeItem(idx)" class="text-red-500 hover:text-red-700">
                  <i class="fa-solid fa-trash"></i>
                </button>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                  <label class="block text-xs font-bold text-gray-600 mb-2">Nama Item</label>
                  <input 
                    type="text" 
                    v-model="item.item_name" 
                    class="input input-bordered w-full shadow-inner rounded-xl focus:ring-2 focus:ring-green-300 transition-all duration-200" 
                    placeholder="Masukkan nama item"
                    required 
                  />
                </div>
                <div>
                  <label class="block text-xs font-bold text-gray-600 mb-2">Qty</label>
                  <input 
                    type="number" 
                    v-model="item.qty" 
                    @input="calculateSubtotal(idx)"
                    step="0.01"
                    min="0"
                    class="input input-bordered w-full shadow-inner rounded-xl focus:ring-2 focus:ring-green-300 transition-all duration-200" 
                    required 
                  />
                </div>
                <div>
                  <label class="block text-xs font-bold text-gray-600 mb-2">Unit</label>
                  <input 
                    type="text" 
                    v-model="item.unit" 
                    class="input input-bordered w-full shadow-inner rounded-xl focus:ring-2 focus:ring-green-300 transition-all duration-200" 
                    placeholder="pcs, kg, dll"
                    required 
                  />
                </div>
                <div>
                  <label class="block text-xs font-bold text-gray-600 mb-2">Harga</label>
                  <input 
                    type="number" 
                    v-model="item.price" 
                    @input="calculateSubtotal(idx)"
                    step="0.01"
                    min="0"
                    class="input input-bordered w-full shadow-inner rounded-xl focus:ring-2 focus:ring-green-300 transition-all duration-200" 
                    required 
                  />
                </div>
              </div>

              <div class="flex justify-end">
                <div class="text-right">
                  <div class="text-sm text-gray-600">Subtotal:</div>
                  <div class="text-lg font-bold text-green-600">{{ formatRupiah(item.subtotal) }}</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Notes -->
          <div>
            <label class="block text-xs font-bold text-gray-600 mb-2">Catatan</label>
            <textarea v-model="form.notes" rows="3" class="input input-bordered w-full shadow-inner rounded-xl focus:ring-2 focus:ring-green-300 transition-all duration-200" placeholder="Tambahkan catatan jika diperlukan"></textarea>
          </div>

          <!-- Total Section -->
          <div class="bg-green-50 p-6 rounded-xl">
            <div class="flex justify-between items-center mb-4">
              <h3 class="text-lg font-semibold text-gray-800">Total Transaksi</h3>
              <div class="text-right">
                <div class="text-2xl font-bold text-green-600">{{ formatRupiah(totalAmount) }}</div>
                <div class="text-sm text-gray-600">Total hari ini: {{ formatRupiah(dailyTotal) }}</div>
              </div>
            </div>

            <!-- Alert jika melebihi limit -->
            <div v-if="showLimitAlert" class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded-lg">
              <div class="flex">
                <i class="fa-solid fa-exclamation-triangle mr-2"></i>
                <div>
                  <strong>Peringatan:</strong> Total pembelian hari ini sudah melebihi Rp 500.000
                </div>
              </div>
            </div>
          </div>

          <!-- Buttons -->
          <div class="flex justify-end gap-4">
            <button type="button" @click="goBack" class="px-6 py-3 border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition-colors">
              Batal
            </button>
            <button type="submit" :disabled="loading" class="px-6 py-3 bg-gradient-to-r from-green-500 to-green-700 text-white rounded-xl hover:from-green-600 hover:to-green-800 transition-all font-semibold disabled:opacity-50">
              <span v-if="loading">
                <i class="fa-solid fa-spinner fa-spin mr-2"></i> Menyimpan...
              </span>
              <span v-else>
                <i class="fa-solid fa-save mr-2"></i> Simpan Transaksi
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
import { ref, computed, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import Swal from 'sweetalert2'

const props = defineProps({
  outlets: Array,
  warehouse_outlets: Array
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

const form = ref({
  transaction_date: new Date().toISOString().split('T')[0],
  outlet_id: userOutletId.value == 1 ? '' : userOutletId.value,
  warehouse_outlet_id: '',
  notes: '',
  items: [newItem()]
})

const outletDisabled = computed(() => userOutletId.value != 1)
const loading = ref(false)
const dailyTotal = ref(0)
const showLimitAlert = computed(() => (dailyTotal.value + totalAmount.value) >= 500000)

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

watch([
  () => form.value.outlet_id,
  () => form.value.transaction_date,
  () => form.value.items.map(i => [i.qty, i.price])
], fetchDailyTotal, { immediate: true, deep: true })

async function submit() {
  if (loading.value) return
  
  const confirm = await Swal.fire({
    title: 'Simpan Data?',
    text: 'Apakah Anda yakin ingin menyimpan transaksi ini?',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Ya, Simpan',
    cancelButtonText: 'Batal',
    confirmButtonColor: '#16a34a',
    cancelButtonColor: '#6b7280',
    reverseButtons: true
  })
  
  if (!confirm.isConfirmed) return
  
  loading.value = true
  try {
    const res = await axios.post('/retail-non-food', {
      outlet_id: form.value.outlet_id,
      warehouse_outlet_id: form.value.warehouse_outlet_id,
      transaction_date: form.value.transaction_date,
      notes: form.value.notes,
      items: form.value.items.map(item => ({
        item_name: item.item_name,
        qty: item.qty,
        unit: item.unit,
        price: item.price
      }))
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
  } catch (error) {
    Swal.fire({
      icon: 'error',
      title: 'Gagal',
      text: error.response?.data?.message || 'Terjadi kesalahan saat menyimpan data'
    })
  } finally {
    loading.value = false
  }
}

function goBack() {
  router.visit('/retail-non-food')
}
</script> 