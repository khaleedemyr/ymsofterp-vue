<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-60">
    <div class="bg-gradient-to-br from-yellow-200 via-white to-yellow-100 rounded-3xl shadow-2xl p-8 min-w-[350px] max-w-4xl w-full relative animate-fade-in-3d">
      <button @click="$emit('close')" class="absolute top-3 right-4 text-2xl text-yellow-700 hover:text-red-500 font-bold">&times;</button>
      <h2 class="text-xl font-bold mb-4 text-yellow-800 flex items-center gap-2">
        <i class="fas fa-list-alt"></i> 
        Detail FJ: {{ modalCustomer }}
      </h2>
      
      <!-- Download Buttons -->
      <div class="mb-4 flex justify-end gap-2">
        <button 
          @click="downloadExcel"
          :disabled="loadingDetail || Object.keys(detailData).length === 0"
          class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-md font-semibold hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
        >
          <i class="fas fa-file-excel mr-2"></i>
          Download Excel
        </button>
        <button 
          @click="downloadPDF"
          :disabled="loadingDetail || Object.keys(detailData).length === 0"
          class="inline-flex items-center px-4 py-2 bg-red-600 text-white rounded-md font-semibold hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
        >
          <i class="fas fa-file-pdf mr-2"></i>
          Download PDF
        </button>
      </div>
      
      <div v-if="loadingDetail" class="text-center py-8 text-yellow-600">
        <i class="fa fa-spinner fa-spin mr-2"></i>Loading...
      </div>
      
      <div v-else-if="Object.keys(detailData).length === 0" class="text-center py-8 text-gray-400">
        Tidak ada data detail.
      </div>
      
      <div v-else class="space-y-6 max-h-[60vh] overflow-y-auto">
        <!-- Main Kitchen Section -->
        <div v-if="sectionItems('main_kitchen').length > 0" class="bg-white rounded-lg p-4 shadow-sm">
          <h3 class="text-lg font-semibold text-blue-700 mb-3 flex items-center gap-2">
            <i class="fas fa-utensils"></i>
            Main Kitchen
          </h3>
          <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead>
                <tr class="bg-blue-50 text-blue-900">
                  <th class="px-3 py-2 text-left">Item</th>
                  <th class="px-3 py-2 text-left">Category</th>
                  <th class="px-3 py-2 text-left">Unit</th>
                  <th class="px-3 py-2 text-right">Qty</th>
                  <th class="px-3 py-2 text-right">Price</th>
                  <th class="px-3 py-2 text-right">Total</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in sectionItems('main_kitchen')" :key="item.item_name + (item.category||'') + (item.unit||'')" class="border-b last:border-b-0 hover:bg-gray-50">
                  <td class="px-3 py-2">{{ item.item_name }}</td>
                  <td class="px-3 py-2">{{ item.category }}</td>
                  <td class="px-3 py-2">{{ item.unit }}</td>
                  <td class="px-3 py-2 text-right">{{ formatQty(item.received_qty) }}</td>
                  <td class="px-3 py-2 text-right">{{ formatRupiah(item.price) }}</td>
                  <td class="px-3 py-2 text-right font-semibold">{{ formatRupiah(item.subtotal) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Main Store Section -->
        <div v-if="sectionItems('main_store').length > 0" class="bg-white rounded-lg p-4 shadow-sm">
          <h3 class="text-lg font-semibold text-green-700 mb-3 flex items-center gap-2">
            <i class="fas fa-store"></i>
            Main Store
          </h3>
          <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead>
                <tr class="bg-green-50 text-green-900">
                  <th class="px-3 py-2 text-left">Item</th>
                  <th class="px-3 py-2 text-left">Category</th>
                  <th class="px-3 py-2 text-left">Unit</th>
                  <th class="px-3 py-2 text-right">Qty</th>
                  <th class="px-3 py-2 text-right">Price</th>
                  <th class="px-3 py-2 text-right">Total</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in sectionItems('main_store')" :key="item.item_name + (item.category||'') + (item.unit||'')" class="border-b last:border-b-0 hover:bg-gray-50">
                  <td class="px-3 py-2">{{ item.item_name }}</td>
                  <td class="px-3 py-2">{{ item.category }}</td>
                  <td class="px-3 py-2">{{ item.unit }}</td>
                  <td class="px-3 py-2 text-right">{{ formatQty(item.received_qty) }}</td>
                  <td class="px-3 py-2 text-right">{{ formatRupiah(item.price) }}</td>
                  <td class="px-3 py-2 text-right font-semibold">{{ formatRupiah(item.subtotal) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Chemical Section -->
        <div v-if="sectionItems('chemical').length > 0" class="bg-white rounded-lg p-4 shadow-sm">
          <h3 class="text-lg font-semibold text-purple-700 mb-3 flex items-center gap-2">
            <i class="fas fa-flask"></i>
            Chemical
          </h3>
          <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead>
                <tr class="bg-purple-50 text-purple-900">
                  <th class="px-3 py-2 text-left">Item</th>
                  <th class="px-3 py-2 text-left">Category</th>
                  <th class="px-3 py-2 text-left">Unit</th>
                  <th class="px-3 py-2 text-right">Qty</th>
                  <th class="px-3 py-2 text-right">Price</th>
                  <th class="px-3 py-2 text-right">Total</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in sectionItems('chemical')" :key="item.item_name + (item.category||'') + (item.unit||'')" class="border-b last:border-b-0 hover:bg-gray-50">
                  <td class="px-3 py-2">{{ item.item_name }}</td>
                  <td class="px-3 py-2">{{ item.category }}</td>
                  <td class="px-3 py-2">{{ item.unit }}</td>
                  <td class="px-3 py-2 text-right">{{ formatQty(item.received_qty) }}</td>
                  <td class="px-3 py-2 text-right">{{ formatRupiah(item.price) }}</td>
                  <td class="px-3 py-2 text-right font-semibold">{{ formatRupiah(item.subtotal) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Stationary Section -->
        <div v-if="sectionItems('stationary').length > 0" class="bg-white rounded-lg p-4 shadow-sm">
          <h3 class="text-lg font-semibold text-orange-700 mb-3 flex items-center gap-2">
            <i class="fas fa-pencil-alt"></i>
            Stationary
          </h3>
          <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead>
                <tr class="bg-orange-50 text-orange-900">
                  <th class="px-3 py-2 text-left">Item</th>
                  <th class="px-3 py-2 text-left">Category</th>
                  <th class="px-3 py-2 text-left">Unit</th>
                  <th class="px-3 py-2 text-right">Qty</th>
                  <th class="px-3 py-2 text-right">Price</th>
                  <th class="px-3 py-2 text-right">Total</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in sectionItems('stationary')" :key="item.item_name + (item.category||'') + (item.unit||'')" class="border-b last:border-b-0 hover:bg-gray-50">
                  <td class="px-3 py-2">{{ item.item_name }}</td>
                  <td class="px-3 py-2">{{ item.category }}</td>
                  <td class="px-3 py-2">{{ item.unit }}</td>
                  <td class="px-3 py-2 text-right">{{ formatQty(item.received_qty) }}</td>
                  <td class="px-3 py-2 text-right">{{ formatRupiah(item.price) }}</td>
                  <td class="px-3 py-2 text-right font-semibold">{{ formatRupiah(item.subtotal) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Marketing Section -->
        <div v-if="sectionItems('marketing').length > 0" class="bg-white rounded-lg p-4 shadow-sm">
          <h3 class="text-lg font-semibold text-pink-700 mb-3 flex items-center gap-2">
            <i class="fas fa-bullhorn"></i>
            Marketing
          </h3>
          <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead>
                <tr class="bg-pink-50 text-pink-900">
                  <th class="px-3 py-2 text-left">Item</th>
                  <th class="px-3 py-2 text-left">Category</th>
                  <th class="px-3 py-2 text-left">Unit</th>
                  <th class="px-3 py-2 text-right">Qty</th>
                  <th class="px-3 py-2 text-right">Price</th>
                  <th class="px-3 py-2 text-right">Total</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in sectionItems('marketing')" :key="item.item_name + (item.category||'') + (item.unit||'')" class="border-b last:border-b-0 hover:bg-gray-50">
                  <td class="px-3 py-2">{{ item.item_name }}</td>
                  <td class="px-3 py-2">{{ item.category }}</td>
                  <td class="px-3 py-2">{{ item.unit }}</td>
                  <td class="px-3 py-2 text-right">{{ formatQty(item.received_qty) }}</td>
                  <td class="px-3 py-2 text-right">{{ formatRupiah(item.price) }}</td>
                  <td class="px-3 py-2 text-right font-semibold">{{ formatRupiah(item.subtotal) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Summary -->
      <div v-if="Object.keys(detailData).length > 0" class="mt-6 bg-gray-50 p-4 rounded-lg">
        <h3 class="text-lg font-semibold mb-3 text-gray-800">Summary</h3>
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 text-sm">
          <div class="text-center">
            <div class="font-semibold text-blue-600">Main Kitchen</div>
            <div class="text-lg font-bold">{{ formatRupiah(summary.main_kitchen) }}</div>
          </div>
          <div class="text-center">
            <div class="font-semibold text-green-600">Main Store</div>
            <div class="text-lg font-bold">{{ formatRupiah(summary.main_store) }}</div>
          </div>
          <div class="text-center">
            <div class="font-semibold text-purple-600">Chemical</div>
            <div class="text-lg font-bold">{{ formatRupiah(summary.chemical) }}</div>
          </div>
          <div class="text-center">
            <div class="font-semibold text-orange-600">Stationary</div>
            <div class="text-lg font-bold">{{ formatRupiah(summary.stationary) }}</div>
          </div>
          <div class="text-center">
            <div class="font-semibold text-pink-600">Marketing</div>
            <div class="text-lg font-bold">{{ formatRupiah(summary.marketing) }}</div>
          </div>
        </div>
        <div class="mt-4 pt-4 border-t border-gray-200 text-center">
          <div class="font-semibold text-gray-800">Total</div>
          <div class="text-2xl font-bold text-green-600">{{ formatRupiah(summary.total) }}</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import axios from 'axios'

const props = defineProps({
  show: {
    type: Boolean,
    required: true
  },
  customer: {
    type: String,
    required: true
  },
  from: {
    type: String,
    required: true
  },
  to: {
    type: String,
    required: true
  }
})

const emit = defineEmits(['close'])

const modalCustomer = ref('')
const detailData = ref({})
const loadingDetail = ref(false)

// Watch for show prop changes
watch(() => props.show, async (newVal) => {
  if (newVal && props.customer) {
    modalCustomer.value = props.customer
    await fetchDetail()
  }
})

async function fetchDetail() {
  loadingDetail.value = true
  detailData.value = {}
  
  try {
    const { data } = await axios.post('/api/report/fj-detail', {
      customer: props.customer,
      from: props.from,
      to: props.to
    })
    detailData.value = data
  } catch (error) {
    console.error('Error fetching detail:', error)
    detailData.value = {}
  } finally {
    loadingDetail.value = false
  }
}

async function downloadExcel() {
  try {
    const response = await axios.post('/api/report/fj-detail-excel', {
      customer: props.customer,
      from: props.from,
      to: props.to
    }, {
      responseType: 'blob'
    })
    
    const blob = new Blob([response.data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' })
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `FJ_Detail_${props.customer}_${props.from}_${props.to}.xlsx`
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    window.URL.revokeObjectURL(url)
  } catch (error) {
    console.error('Error downloading Excel:', error)
    alert('Terjadi kesalahan saat download Excel')
  }
}

async function downloadPDF() {
  try {
    const response = await axios.post('/api/report/fj-detail-pdf', {
      customer: props.customer,
      from: props.from,
      to: props.to
    }, {
      responseType: 'blob'
    })
    
    const blob = new Blob([response.data], { type: 'application/pdf' })
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `FJ_Detail_${props.customer}_${props.from}_${props.to}.pdf`
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    window.URL.revokeObjectURL(url)
  } catch (error) {
    console.error('Error downloading PDF:', error)
    alert('Terjadi kesalahan saat download PDF')
  }
}

const summary = computed(() => {
  const sumSection = (key) => {
    const arr = sectionItems(key)
    return arr.reduce((sum, item) => sum + (Number(item.subtotal) || 0), 0)
  }
  const main_kitchen = sumSection('main_kitchen')
  const main_store = sumSection('main_store')
  const chemical = sumSection('chemical')
  const stationary = sumSection('stationary')
  const marketing = sumSection('marketing')
  
  return {
    main_kitchen,
    main_store,
    chemical,
    stationary,
    marketing,
    total: main_kitchen + main_store + chemical + stationary + marketing
  }
})

function formatRupiah(value) {
  if (!value) return 'Rp 0'
  return 'Rp ' + Number(value).toLocaleString('id-ID')
}

function formatQty(value) {
  if (value == null) return ''
  return Number(value).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

// Helper to normalize API differences: if section is an object with 'all', use it; if it's an array, use directly
function sectionItems(sectionKey) {
  const section = detailData.value?.[sectionKey]
  if (!section) return []
  // Prioritaskan dataset GR (menyamakan dengan export Excel)
  if (Array.isArray(section.gr)) return section.gr
  // Jika respons sudah dalam bentuk array biasa
  if (Array.isArray(section)) return section
  // Jika tidak ada gr namun ada all, gunakan all sebagai fallback terakhir
  if (Array.isArray(section.all)) return section.all
  // Fallback akhir: kosong
  return []
}
</script>

<style scoped>
@keyframes fade-in-3d {
  from { opacity: 0; transform: scale(0.85) rotateX(10deg); }
  to { opacity: 1; transform: scale(1) rotateX(0); }
}
.animate-fade-in-3d {
  animation: fade-in-3d 0.5s cubic-bezier(.4,2,.3,1) both;
}
</style>
