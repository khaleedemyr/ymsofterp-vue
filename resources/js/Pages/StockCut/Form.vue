<template>
  <AppLayout>
    <div class="max-w-lg mx-auto py-8 px-2">
      <h2 class="text-2xl font-bold mb-6 flex items-center gap-2">
        <i class="fa-solid fa-scissors text-blue-500"></i> Potong Stock
      </h2>
      <form @submit.prevent="cekEngineering" class="space-y-4 bg-white rounded-xl shadow-xl p-8">
        <div>
          <label class="block text-sm font-medium text-gray-700">Tanggal</label>
          <input type="date" v-model="tanggal" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" :max="today" required />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Outlet</label>
          <select
            v-model="selectedOutlet"
            :disabled="user.id_outlet !== 1"
            class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm"
            required
          >
            <option value="">Pilih Outlet</option>
            <option v-for="o in outlets" :key="o.id" :value="o.id">
              {{ o.name }}
            </option>
          </select>
        </div>
        <div class="flex gap-2 mt-4">
          <button type="submit" :disabled="loading" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">
            <span v-if="loading" class="animate-spin mr-2">⏳</span>
            Cek Kebutuhan Stock
          </button>
          <button v-if="bolehPotong" type="button" @click="potongStock" :disabled="loadingPotong" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 transition">
            <span v-if="loadingPotong" class="animate-spin mr-2">⏳</span>
            Potong Stock Sekarang
          </button>
        </div>
      </form>
      <div v-if="engineeringChecked && engineering.length === 0" class="mt-6">
        <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded">
          Tidak ada transaksi/menu terjual pada tanggal dan outlet yang dipilih.
        </div>
      </div>
      <div v-if="engineeringChecked && Object.keys(engineering).length" class="mt-6">
        <h3 class="font-bold mb-2">Engineering (Item Terjual)</h3>
        <div v-for="(catObj, type) in engineering" :key="type" class="mb-4 border rounded-lg">
          <button @click="toggleType(type)" class="w-full text-left px-4 py-2 bg-blue-50 font-bold rounded-t-lg flex items-center justify-between">
            <span>{{ type }}</span>
            <span><i :class="expandedType[type] ? 'fa fa-chevron-down' : 'fa fa-chevron-right'"></i></span>
          </button>
          <div v-show="expandedType[type]">
            <div v-for="(subcatObj, cat) in catObj" :key="cat" class="ml-4 mb-2 border-l-2 border-blue-200">
              <button @click="toggleCat(type, cat)" class="w-full text-left px-4 py-2 bg-blue-100 font-semibold flex items-center justify-between">
                <span>{{ cat }}</span>
                <span><i :class="expandedCat[type+cat] ? 'fa fa-chevron-down' : 'fa fa-chevron-right'"></i></span>
              </button>
              <div v-show="expandedCat[type+cat]">
                <div v-for="(items, subcat) in subcatObj" :key="subcat" class="ml-4 mb-2 border-l-2 border-blue-100">
                  <button @click="toggleSubcat(type, cat, subcat)" class="w-full text-left px-4 py-2 bg-blue-200 font-medium flex items-center justify-between">
                    <span>{{ subcat }}</span>
                    <span><i :class="expandedSubcat[type+cat+subcat] ? 'fa fa-chevron-down' : 'fa fa-chevron-right'"></i></span>
                  </button>
                  <div v-show="expandedSubcat[type+cat+subcat]">
                    <table class="min-w-full divide-y divide-gray-200 bg-white rounded shadow mt-2">
                      <thead>
                        <tr>
                          <th class="px-4 py-2 text-left">Nama Item</th>
                          <th class="px-4 py-2 text-right">Qty Terjual</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-for="row in items" :key="row.item_name">
                          <td class="px-4 py-2">{{ row.item_name }}</td>
                          <td class="px-4 py-2 text-right">{{ row.total_qty }}</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div v-if="missingBom.length" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mt-4">
          <strong class="font-bold">Perhatian!</strong> Ada item yang belum punya BOM:
          <ul class="mt-2 list-disc ml-6">
            <li v-for="item in missingBom" :key="item.item_id">
              {{ item.item_name }} (ID: {{ item.item_id }})
            </li>
          </ul>
        </div>
        <div class="mt-4">
          <button @click="cekKebutuhan" :disabled="loading || missingBom.length" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">
            <span v-if="loading" class="animate-spin mr-2">⏳</span>
            Cek Kebutuhan Stock Engineering
          </button>
        </div>
      </div>
      <div v-if="kurang.length" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mt-4">
        <strong class="font-bold">Stock Kurang!</strong>
        <ul class="mt-2 list-disc ml-6">
          <li v-for="item in kurang" :key="item.item_id + '-' + item.warehouse_id">
            {{ getItemName(item.item_id) }} (Warehouse: {{ getWarehouseName(item.warehouse_id) }}) kurang {{ item.kurang }}
          </li>
        </ul>
      </div>
      <div v-if="successMsg" class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mt-4">{{ successMsg }}</div>
      <div v-if="errorMsg" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mt-4">{{ errorMsg }}</div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed, onMounted, watch } from 'vue'
import axios from 'axios'

const props = defineProps({
  outlets: Array
})
const user = ref({
  id_outlet: 1, // Ganti dengan real user outlet id
  outlet_name: 'Outlet Demo'
})

const tanggal = ref(new Date().toISOString().slice(0, 10))
const today = new Date().toISOString().slice(0, 10)
const loading = ref(false)
const loadingPotong = ref(false)
const kurang = ref([])
const successMsg = ref('')
const errorMsg = ref('')
const bolehPotong = ref(false)
const itemMaster = ref({})
const warehouseMaster = ref({})
const outlets = ref(props.outlets && props.outlets.length ? props.outlets : [])
const selectedOutlet = ref(user.value.id_outlet == 1 ? '' : user.value.id_outlet)
const engineering = ref({})
const engineeringChecked = ref(false)
const expandedType = ref({})
const expandedCat = ref({})
const expandedSubcat = ref({})
const missingBom = ref([])

const userOutletName = computed(() => user.value.outlet_name)

onMounted(async () => {
  if (!outlets.value.length) {
    const res = await axios.get('/api/outlets')
    outlets.value = res.data
  }
  await fetchMasters()
  bolehPotong.value = false // hide tombol potong stock saat awal
})

async function fetchMasters() {
  const [itemRes, whRes] = await Promise.all([
    axios.get('/api/items'),
    axios.get('/api/warehouse-outlets', { params: { outlet_id: selectedOutlet.value || user.value.id_outlet } })
  ])
  itemMaster.value = Object.fromEntries(itemRes.data.map(i => [i.id, i.name]))
  warehouseMaster.value = Object.fromEntries(whRes.data.map(w => [w.id, w.name]))
}
function getItemName(id) {
  return itemMaster.value[id] || id
}
function getWarehouseName(id) {
  return warehouseMaster.value[id] || id
}

watch(selectedOutlet, async (val) => {
  if (user.value.id_outlet === 1 && val) {
    const whRes = await axios.get('/api/warehouse-outlets', { params: { outlet_id: val } })
    warehouseMaster.value = Object.fromEntries(whRes.data.map(w => [w.id, w.name]))
  }
})

async function cekEngineering() {
  loading.value = true
  successMsg.value = ''
  errorMsg.value = ''
  bolehPotong.value = false
  kurang.value = []
  engineering.value = {}
  engineeringChecked.value = false
  missingBom.value = []
  try {
    // Fetch engineering
    const engRes = await axios.post('/stock-cut/engineering', {
      tanggal: tanggal.value,
      id_outlet: selectedOutlet.value || user.value.id_outlet
    })
    engineering.value = engRes.data.engineering
    missingBom.value = engRes.data.missing_bom
    engineeringChecked.value = true
  } catch (e) {
    errorMsg.value = e.response?.data?.message || e.message
  } finally {
    loading.value = false
  }
}

async function cekKebutuhan() {
  loading.value = true
  successMsg.value = ''
  errorMsg.value = ''
  bolehPotong.value = false
  kurang.value = []
  try {
    const res = await axios.post('/stock-cut/order-items', {
      tanggal: tanggal.value,
      id_outlet: selectedOutlet.value || user.value.id_outlet
    })
    if (res.data.status === 'error' && res.data.kurang) {
      kurang.value = res.data.kurang
      errorMsg.value = 'Ada stock yang kurang, tidak bisa potong stock!'
    } else {
      kurang.value = []
      bolehPotong.value = true
      successMsg.value = 'Stock cukup, silakan klik Potong Stock Sekarang.'
    }
  } catch (e) {
    errorMsg.value = e.response?.data?.message || e.message
  } finally {
    loading.value = false
  }
}

async function potongStock() {
  loadingPotong.value = true
  errorMsg.value = ''
  successMsg.value = ''
  try {
    const res = await axios.post('/stock-cut/order-items', {
      tanggal: tanggal.value,
      id_outlet: selectedOutlet.value || user.value.id_outlet
    })
    if (res.data.status === 'success') {
      successMsg.value = res.data.message
      bolehPotong.value = false
      kurang.value = []
    } else if (res.data.kurang) {
      kurang.value = res.data.kurang
      errorMsg.value = 'Stock kurang, tidak bisa potong stock!'
    }
  } catch (e) {
    errorMsg.value = e.response?.data?.message || e.message
  } finally {
    loadingPotong.value = false
  }
}

function toggleType(type) {
  expandedType.value[type] = !expandedType.value[type]
}
function toggleCat(type, cat) {
  expandedCat.value[type+cat] = !expandedCat.value[type+cat]
}
function toggleSubcat(type, cat, subcat) {
  expandedSubcat.value[type+cat+subcat] = !expandedSubcat.value[type+cat+subcat]
}
</script> 