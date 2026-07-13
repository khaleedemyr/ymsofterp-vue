<template>
  <Head title="MK Production Details" />
  <AppLayout>
    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
      <!-- Header Section -->
      <div class="mb-8">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
          <div class="flex items-center gap-4">
            <button 
              @click="$inertia.visit('/mk-production')" 
              class="p-2 hover:bg-gray-100 rounded-lg transition-colors"
            >
              <i class="fa fa-arrow-left text-gray-600 text-xl"></i>
            </button>
            <div>
              <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3 mb-2">
                <div class="p-3 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg">
                  <i class="fa-solid fa-industry text-white text-xl"></i>
                </div>
                <span>MK Production Detail</span>
              </h1>
              <p class="text-gray-600 ml-16">Detail informasi produksi dan stock card</p>
            </div>
          </div>
          <button 
            @click="showLabelModal = true" 
            class="group inline-flex items-center gap-2 bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white px-6 py-3 rounded-xl shadow-lg hover:shadow-xl transition-all duration-200 font-semibold transform hover:-translate-y-0.5"
          >
            <i class="fas fa-print text-lg"></i>
            <span>Print Label</span>
          </button>
          <button
            @click="generateSerial"
            :disabled="serialInUse > 0"
            :title="serialInUse > 0 ? 'Ada serial yang sudah digunakan — tidak bisa generate ulang.' : ''"
            class="group inline-flex items-center gap-2 bg-gradient-to-r from-indigo-500 to-indigo-600 hover:from-indigo-600 hover:to-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed text-white px-6 py-3 rounded-xl shadow-lg hover:shadow-xl transition-all duration-200 font-semibold transform hover:-translate-y-0.5"
          >
            <i class="fas fa-barcode text-lg"></i>
            <span>Generate Serial</span>
          </button>
          <button
            @click="showSerials"
            class="group inline-flex items-center gap-2 bg-gradient-to-r from-slate-500 to-slate-600 hover:from-slate-600 hover:to-slate-700 text-white px-6 py-3 rounded-xl shadow-lg hover:shadow-xl transition-all duration-200 font-semibold transform hover:-translate-y-0.5"
          >
            <i class="fas fa-list text-lg"></i>
            <span>Serial List ({{ serialTotal }})</span>
          </button>
          <button
            @click="rollbackSerial"
            :disabled="serialInUse > 0"
            :title="serialInUse > 0 ? 'Ada serial yang sudah digunakan — tidak bisa rollback.' : ''"
            class="group inline-flex items-center gap-2 bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 disabled:opacity-50 disabled:cursor-not-allowed text-white px-6 py-3 rounded-xl shadow-lg hover:shadow-xl transition-all duration-200 font-semibold transform hover:-translate-y-0.5"
          >
            <i class="fas fa-rotate-left text-lg"></i>
            <span>Rollback Serial</span>
          </button>
        </div>
      </div>

      <!-- Basic Information Cards -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl p-5 border border-blue-200 shadow-sm">
          <div class="flex items-center justify-between mb-2">
            <p class="text-sm font-medium text-blue-700">Production Date</p>
            <div class="p-2 bg-blue-500 rounded-lg">
              <i class="fa-solid fa-calendar text-white text-sm"></i>
            </div>
          </div>
          <p class="text-xl font-bold text-blue-900">{{ formatDate(prod.production_date) }}</p>
        </div>
        
        <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-xl p-5 border border-purple-200 shadow-sm">
          <div class="flex items-center justify-between mb-2">
            <p class="text-sm font-medium text-purple-700">Batch Number</p>
            <div class="p-2 bg-purple-500 rounded-lg">
              <i class="fa-solid fa-barcode text-white text-sm"></i>
            </div>
          </div>
          <p class="text-xl font-bold text-purple-900">{{ prod.batch_number }}</p>
        </div>
        
        <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-xl p-5 border border-green-200 shadow-sm">
          <div class="flex items-center justify-between mb-2">
            <p class="text-sm font-medium text-green-700">Qty Produksi</p>
            <div class="p-2 bg-green-500 rounded-lg">
              <i class="fa-solid fa-calculator text-white text-sm"></i>
            </div>
          </div>
          <p class="text-xl font-bold text-green-900">{{ Number(prod.qty).toFixed(2) }}</p>
        </div>
        
        <div class="bg-gradient-to-br from-orange-50 to-orange-100 rounded-xl p-5 border border-orange-200 shadow-sm">
          <div class="flex items-center justify-between mb-2">
            <p class="text-sm font-medium text-orange-700">Qty Jadi</p>
            <div class="p-2 bg-orange-500 rounded-lg">
              <i class="fa-solid fa-check-circle text-white text-sm"></i>
            </div>
          </div>
          <p class="text-xl font-bold text-orange-900">{{ Number(prod.qty_jadi).toFixed(2) }}</p>
        </div>
      </div>

      <!-- Detail Information -->
      <div class="bg-white rounded-2xl shadow-xl border border-gray-100 p-6 mb-6">
        <div class="flex items-center gap-2 mb-5">
          <div class="p-2 bg-blue-100 rounded-lg">
            <i class="fa-solid fa-info-circle text-blue-600"></i>
          </div>
          <h3 class="text-lg font-bold text-gray-800">Detail Information</h3>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">
                <i class="fa-solid fa-box text-blue-500 mr-2"></i> Item
              </label>
              <p class="text-base font-medium text-gray-900 bg-gray-50 px-4 py-2.5 rounded-lg border border-gray-200">
                {{ item?.name || '-' }}
              </p>
            </div>
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">
                <i class="fa-solid fa-warehouse text-blue-500 mr-2"></i> Warehouse
              </label>
              <p class="text-base font-medium text-gray-900 bg-gray-50 px-4 py-2.5 rounded-lg border border-gray-200">
                {{ warehouse?.name || '-' }}
              </p>
            </div>
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">
              <i class="fa-solid fa-note-sticky text-blue-500 mr-2"></i> Notes
            </label>
            <p class="text-base text-gray-900 bg-gray-50 px-4 py-2.5 rounded-lg border border-gray-200 min-h-[60px]">
              {{ prod.notes || '-' }}
            </p>
          </div>
        </div>
      </div>

      <!-- Stock Card Section -->
      <div class="bg-white rounded-2xl shadow-xl border border-gray-100 p-6 mb-6">
        <div class="flex items-center gap-2 mb-5">
          <div class="p-2 bg-indigo-100 rounded-lg">
            <i class="fa-solid fa-clipboard-list text-indigo-600"></i>
          </div>
          <h3 class="text-lg font-bold text-gray-800">Stock Card</h3>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full min-w-full">
            <thead class="bg-gradient-to-r from-indigo-600 to-indigo-700">
              <tr>
                <th class="px-6 py-4 text-left text-xs font-bold text-white uppercase tracking-wider">
                  <div class="flex items-center gap-2">
                    <i class="fa-solid fa-calendar text-indigo-200"></i>
                    <span>Date</span>
                  </div>
                </th>
                <th class="px-6 py-4 text-right text-xs font-bold text-white uppercase tracking-wider">
                  <div class="flex items-center justify-end gap-2">
                    <i class="fa-solid fa-arrow-down text-green-200"></i>
                    <span>In Qty</span>
                  </div>
                </th>
                <th class="px-6 py-4 text-right text-xs font-bold text-white uppercase tracking-wider">
                  <div class="flex items-center justify-end gap-2">
                    <i class="fa-solid fa-arrow-up text-red-200"></i>
                    <span>Out Qty</span>
                  </div>
                </th>
                <th class="px-6 py-4 text-right text-xs font-bold text-white uppercase tracking-wider">
                  <div class="flex items-center justify-end gap-2">
                    <i class="fa-solid fa-balance-scale text-indigo-200"></i>
                    <span>Saldo Qty</span>
                  </div>
                </th>
                <th class="px-6 py-4 text-left text-xs font-bold text-white uppercase tracking-wider">
                  <div class="flex items-center gap-2">
                    <i class="fa-solid fa-note-sticky text-indigo-200"></i>
                    <span>Description</span>
                  </div>
                </th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-100">
              <tr v-if="!stockCard || stockCard.length === 0">
                <td colspan="5" class="px-6 py-12 text-center">
                  <div class="flex flex-col items-center justify-center">
                    <div class="p-4 bg-gray-100 rounded-full mb-4">
                      <i class="fa-solid fa-inbox text-4xl text-gray-400"></i>
                    </div>
                    <p class="text-gray-500 text-lg font-medium">Tidak ada data stock card</p>
                  </div>
                </td>
              </tr>
              <tr 
                v-for="card in stockCard" 
                :key="card.id"
                class="hover:bg-indigo-50 transition-all duration-150"
              >
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm font-semibold text-gray-900">{{ formatDate(card.date) }}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right">
                  <span v-if="Number(card.in_qty_small) > 0" class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">
                    <i class="fa-solid fa-arrow-down mr-1.5"></i>
                    {{ Number(card.in_qty_small).toFixed(2) }}
                  </span>
                  <span v-else class="text-sm text-gray-400">-</span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right">
                  <span v-if="Number(card.out_qty_small) > 0" class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-800">
                    <i class="fa-solid fa-arrow-up mr-1.5"></i>
                    {{ Number(card.out_qty_small).toFixed(2) }}
                  </span>
                  <span v-else class="text-sm text-gray-400">-</span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right">
                  <div class="text-sm font-bold text-gray-900">{{ Number(card.saldo_qty_small).toFixed(2) }}</div>
                </td>
                <td class="px-6 py-4">
                  <div class="text-sm text-gray-700 max-w-md">{{ card.description || '-' }}</div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- BOM Section -->
      <div class="bg-white rounded-2xl shadow-xl border border-gray-100 p-6 mb-6">
        <div class="flex items-center gap-2 mb-5">
          <div class="p-2 bg-amber-100 rounded-lg">
            <i class="fa-solid fa-list-check text-amber-600"></i>
          </div>
          <h3 class="text-lg font-bold text-gray-800">BOM (Bill of Materials)</h3>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full min-w-full">
            <thead class="bg-gradient-to-r from-amber-600 to-amber-700">
              <tr>
                <th class="px-6 py-4 text-left text-xs font-bold text-white uppercase tracking-wider">
                  <div class="flex items-center gap-2">
                    <i class="fa-solid fa-box text-amber-200"></i>
                    <span>Material</span>
                  </div>
                </th>
                <th class="px-6 py-4 text-right text-xs font-bold text-white uppercase tracking-wider">
                  <div class="flex items-center justify-end gap-2">
                    <i class="fa-solid fa-calculator text-amber-200"></i>
                    <span>Qty per 1</span>
                  </div>
                </th>
                <th class="px-6 py-4 text-left text-xs font-bold text-white uppercase tracking-wider">
                  <div class="flex items-center gap-2">
                    <i class="fa-solid fa-ruler text-amber-200"></i>
                    <span>Unit</span>
                  </div>
                </th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-100">
              <tr v-if="!bom || bom.length === 0">
                <td colspan="3" class="px-6 py-12 text-center">
                  <div class="flex flex-col items-center justify-center">
                    <div class="p-4 bg-gray-100 rounded-full mb-4">
                      <i class="fa-solid fa-inbox text-4xl text-gray-400"></i>
                    </div>
                    <p class="text-gray-500 text-lg font-medium">Tidak ada data BOM</p>
                  </div>
                </td>
              </tr>
              <tr 
                v-for="b in bom" 
                :key="b.id"
                class="hover:bg-amber-50 transition-all duration-150"
              >
                <td class="px-6 py-4">
                  <div class="text-sm font-medium text-gray-900">{{ b.material_name }}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right">
                  <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">
                    {{ Number(b.qty).toFixed(2) }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-gray-100 text-gray-800">
                    {{ b.unit_name }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      
      <!-- Label Print Modal -->
      <div v-if="showLabelModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg mx-4 transform transition-all">
          <div class="p-6">
            <div class="flex justify-between items-center mb-6">
              <div class="flex items-center gap-3">
                <div class="p-2 bg-green-100 rounded-lg">
                  <i class="fa-solid fa-print text-green-600 text-xl"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-900">Print Production Label</h3>
              </div>
              <button 
                @click="showLabelModal = false" 
                class="p-2 hover:bg-gray-100 rounded-lg transition-colors text-gray-400 hover:text-gray-600"
              >
                <i class="fas fa-times text-xl"></i>
              </button>
            </div>
            
            <div class="space-y-5">
              <!-- Item Name -->
              <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                  <i class="fa-solid fa-box text-blue-500 mr-2"></i> Item Name
                </label>
                <input 
                  type="text" 
                  v-model="labelData.itemName" 
                  class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all" 
                  placeholder="Enter item name"
                >
              </div>
              
              <!-- Volume -->
              <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                  <i class="fa-solid fa-flask text-blue-500 mr-2"></i> Volume
                </label>
                <div class="flex gap-2">
                  <input 
                    type="number" 
                    v-model.number="labelData.volume" 
                    class="flex-1 px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all" 
                    placeholder="Volume"
                  >
                  <input 
                    type="text" 
                    v-model="labelData.volumeUnit" 
                    class="w-24 px-4 py-2.5 border-2 border-gray-200 rounded-xl bg-gray-50 text-gray-700 font-medium" 
                    readonly
                  >
                </div>
              </div>
              
              <!-- Production Date -->
              <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                  <i class="fa-solid fa-calendar-day text-blue-500 mr-2"></i> Production Date
                </label>
                <input 
                  type="date" 
                  v-model="labelData.productionDate" 
                  class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
                >
              </div>
              
              <!-- Batch Number -->
              <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                  <i class="fa-solid fa-barcode text-blue-500 mr-2"></i> Batch Number
                </label>
                <input 
                  type="text" 
                  v-model="labelData.batchNumber" 
                  class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all" 
                  placeholder="Batch number"
                >
              </div>
              
              <!-- Expired Date -->
              <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                  <i class="fa-solid fa-calendar-xmark text-blue-500 mr-2"></i> Expired Date
                </label>
                <div class="px-4 py-2.5 bg-gradient-to-r from-orange-50 to-orange-100 border-2 border-orange-200 rounded-xl text-sm font-semibold text-orange-900">
                  {{ labelData.expiredDate || 'Calculating...' }}
                </div>
              </div>
              
              <!-- Barcode Selection -->
              <div v-if="barcodes.length > 1">
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                  <i class="fa-solid fa-qrcode text-blue-500 mr-2"></i> Select Barcode
                </label>
                <select 
                  v-model="selectedBarcode" 
                  class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all bg-white"
                >
                  <option v-for="barcode in barcodes" :key="barcode.barcode" :value="barcode.barcode">
                    {{ barcode.barcode }}
                  </option>
                </select>
              </div>
            </div>
            
            <div class="flex justify-end gap-3 mt-8 pt-6 border-t border-gray-200">
              <button 
                @click="showLabelModal = false" 
                class="px-6 py-2.5 text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-xl transition-all font-semibold border-2 border-gray-200 hover:border-gray-300"
              >
                Cancel
              </button>
              <button 
                @click="printLabel" 
                class="px-6 py-2.5 bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white rounded-xl shadow-lg hover:shadow-xl transition-all font-semibold transform hover:-translate-y-0.5"
              >
                <i class="fas fa-print mr-2"></i> Print Label
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed, onMounted, watch } from 'vue'
import axios from 'axios'
import Swal from 'sweetalert2'
import jsPDF from 'jspdf'
import JsBarcode from 'jsbarcode'

const props = defineProps({
  prod: Object,
  item: Object,
  warehouse: Object,
  stockCard: Array,
  bom: Array
})

const showLabelModal = ref(false)
const barcodes = ref([])
const selectedBarcode = ref('')
const serialTotal = ref(0)
const serialInUse = ref(0)
const labelData = ref({
  itemName: '',
  volume: '',
  volumeUnit: 'ML',
  productionDate: '',
  batchNumber: '',
  expiredDate: ''
})

// Load barcodes when component mounts
onMounted(async () => {
  if (props.item?.id) {
    try {
      const response = await axios.get(`/api/items/${props.item.id}/barcodes`)
      barcodes.value = response.data.barcodes || []
      if (barcodes.value.length > 0) {
        selectedBarcode.value = barcodes.value[0].barcode
      }
    } catch (error) {
    }
  }
  
  // Pre-fill label data
  labelData.value.itemName = props.item?.name || ''
  labelData.value.productionDate = props.prod?.production_date || ''
  labelData.value.batchNumber = props.prod?.batch_number || ''
  labelData.value.volume = 0
  labelData.value.volumeUnit = props.item?.small_unit_name || ''
  
  // Calculate expired date: production date + expiry days from item
  if (props.prod?.production_date && props.item?.exp) {
    const productionDate = new Date(props.prod.production_date)
    const expiryDays = parseInt(props.item.exp) || 0
    const expiredDate = new Date(productionDate)
    expiredDate.setDate(productionDate.getDate() + expiryDays)
    
    // Format date untuk display (DD-MM-YYYY)
    const day = String(expiredDate.getDate()).padStart(2, '0')
    const month = String(expiredDate.getMonth() + 1).padStart(2, '0')
    const year = expiredDate.getFullYear()
    labelData.value.expiredDate = `${day}-${month}-${year}`
  } else {
    labelData.value.expiredDate = 'Data tidak tersedia'
  }

  await loadSerialSummary()
})

// Watch for production date changes to auto-calculate expired date
watch(() => labelData.value.productionDate, (newProductionDate) => {
  if (newProductionDate && props.item?.exp) {
    const productionDate = new Date(newProductionDate)
    const expiryDays = parseInt(props.item.exp) || 0
    const expiredDate = new Date(productionDate)
    expiredDate.setDate(productionDate.getDate() + expiryDays)
    
    // Format date untuk display (DD-MM-YYYY)
    const day = String(expiredDate.getDate()).padStart(2, '0')
    const month = String(expiredDate.getMonth() + 1).padStart(2, '0')
    const year = expiredDate.getFullYear()
    labelData.value.expiredDate = `${day}-${month}-${year}`
  } else {
    labelData.value.expiredDate = 'Data tidak tersedia'
  }
})

const formatDate = (date) => {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('id-ID')
}

const loadSerialSummary = async () => {
  try {
    const { data } = await axios.get(`/api/mk-production/${props.prod.id}/serial-summary`)
    serialTotal.value = Number(data?.total || 0)
    serialInUse.value = Number(data?.in_use || 0)
  } catch (error) {
    serialTotal.value = 0
    serialInUse.value = 0
  }
}

const generateSerial = async () => {
  const qtyIn = Number(props.prod?.qty_jadi || 0)
  const itemName = props.item?.name || ''
  const sourceUnitName = itemUnitName.value || 'unit'
  const itemExpDays = Number(props.item?.exp || 0)
  const defaultProductionDate = props.prod?.production_date
    ? String(props.prod.production_date).slice(0, 10)
    : new Date().toISOString().slice(0, 10)

  const formatExpPreview = (productionDate) => {
    if (!productionDate || itemExpDays <= 0) return '-'
    const d = new Date(productionDate)
    if (Number.isNaN(d.getTime())) return '-'
    d.setDate(d.getDate() + itemExpDays)
    return d.toLocaleDateString('id-ID')
  }

  let unitOptions = ''
  try {
    const { data: units } = await axios.get('/api/mk-serial/units')
    unitOptions = (units || []).map((u) => `<option value="${u.id}">${u.name}</option>`).join('')
  } catch (e) {
    unitOptions = ''
  }

  const { value: formValues, isConfirmed } = await Swal.fire({
    title: 'Generate Serial',
    html: `
      <div style="text-align:left;font-size:14px;">
        <div style="margin-bottom:10px;">
          <strong>Item:</strong> ${itemName}<br>
          <strong>Qty In:</strong> ${qtyIn} ${sourceUnitName}<br>
          <strong>Exp Item:</strong> ${itemExpDays > 0 ? `${itemExpDays} hari` : '-'}
        </div>
        <div style="margin-bottom:10px;">
          <label style="font-weight:600;display:block;margin-bottom:4px;">Tanggal Produksi:</label>
          <input type="date" id="swal-production-date" value="${defaultProductionDate}" class="swal2-input" style="width:100%;margin:0;">
        </div>
        <div style="margin-bottom:10px;padding:8px;background:#ecfdf5;border-radius:6px;">
          <span style="font-weight:600;">Exp Date (otomatis):</span>
          <span id="swal-exp-preview">${formatExpPreview(defaultProductionDate)}</span>
        </div>
        <div style="margin-bottom:10px;">
          <label style="font-weight:600;display:block;margin-bottom:4px;">Mode:</label>
          <div style="display:flex;gap:16px;">
            <label style="cursor:pointer;"><input type="radio" name="swal-conv-mode" value="no" checked> Tanpa Konversi</label>
            <label style="cursor:pointer;"><input type="radio" name="swal-conv-mode" value="yes"> Konversi Unit</label>
          </div>
        </div>
        <div id="swal-conv-wrapper" style="display:none;margin-bottom:10px;">
          <div style="margin-bottom:8px;">
            <label style="font-weight:600;display:block;margin-bottom:4px;">Unit Tujuan Serial:</label>
            <select id="swal-repack-unit" class="swal2-select" style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:6px;">
              <option value="">-- Pilih Unit --</option>
              ${unitOptions}
            </select>
          </div>
          <div>
            <label style="font-weight:600;display:block;margin-bottom:4px;">1 <span id="swal-target-unit-label">[unit tujuan]</span> = berapa ${sourceUnitName}?</label>
            <input type="number" id="swal-repack-qty" min="0.01" step="0.01" value="1" class="swal2-input" style="width:100%;margin:0;">
          </div>
        </div>
        <div style="margin-top:12px;padding:8px;background:#f3f4f6;border-radius:6px;">
          <span style="font-weight:600;">Jumlah serial:</span> <span id="swal-serial-count">${qtyIn}</span>
        </div>
      </div>
    `,
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Ya, generate',
    cancelButtonText: 'Batal',
    didOpen: () => {
      const radios = document.querySelectorAll('input[name="swal-conv-mode"]')
      const convWrapper = document.getElementById('swal-conv-wrapper')
      const unitSelect = document.getElementById('swal-repack-unit')
      const qtyInput = document.getElementById('swal-repack-qty')
      const countDisplay = document.getElementById('swal-serial-count')
      const targetUnitLabel = document.getElementById('swal-target-unit-label')
      const productionDateInput = document.getElementById('swal-production-date')
      const expPreview = document.getElementById('swal-exp-preview')

      const updateExpPreview = () => {
        if (expPreview) {
          expPreview.textContent = formatExpPreview(productionDateInput?.value || '')
        }
      }

      const updateCount = () => {
        const mode = document.querySelector('input[name="swal-conv-mode"]:checked')?.value || 'no'
        if (mode === 'yes') {
          const repackQty = Math.max(0.01, parseFloat(qtyInput.value) || 1)
          countDisplay.textContent = Math.ceil(qtyIn / repackQty)
        } else {
          countDisplay.textContent = qtyIn
        }
      }

      productionDateInput?.addEventListener('input', updateExpPreview)
      productionDateInput?.addEventListener('change', updateExpPreview)

      radios.forEach((r) => r.addEventListener('change', (e) => {
        convWrapper.style.display = e.target.value === 'yes' ? 'block' : 'none'
        updateCount()
      }))

      unitSelect.addEventListener('change', () => {
        const selectedOption = unitSelect.options[unitSelect.selectedIndex]
        targetUnitLabel.textContent = selectedOption?.text || '[unit tujuan]'
      })

      qtyInput.addEventListener('input', updateCount)
    },
    preConfirm: () => {
      const productionDate = document.getElementById('swal-production-date')?.value
      if (!productionDate) {
        Swal.showValidationMessage('Tanggal produksi wajib dipilih')
        return false
      }
      const mode = document.querySelector('input[name="swal-conv-mode"]:checked')?.value || 'no'
      if (mode === 'yes') {
        const repackUnitId = document.getElementById('swal-repack-unit')?.value
        const repackQty = parseFloat(document.getElementById('swal-repack-qty')?.value) || 0
        if (!repackUnitId) {
          Swal.showValidationMessage('Pilih unit tujuan terlebih dahulu')
          return false
        }
        if (repackQty <= 0) {
          Swal.showValidationMessage('Qty konversi harus lebih dari 0')
          return false
        }
        return { production_date: productionDate, repack_unit_id: parseInt(repackUnitId), repack_qty: repackQty }
      }
      return { production_date: productionDate, repack_unit_id: null, repack_qty: null }
    }
  })

  if (!isConfirmed || !formValues) return

  try {
    const { data } = await axios.post(`/api/mk-production/${props.prod.id}/generate-serials`, {
      production_date: formValues.production_date,
      repack_unit_id: formValues.repack_unit_id,
      repack_qty: formValues.repack_qty,
    })
    await Swal.fire('Berhasil', data?.message || 'Serial berhasil digenerate', 'success')
    await loadSerialSummary()
  } catch (error) {
    const message = error?.response?.data?.message || 'Gagal generate serial'
    await Swal.fire('Error', message, 'error')
  }
}

const showSerials = async () => {
  try {
    const { data } = await axios.get(`/api/mk-production/${props.prod.id}/serials`)
    if (!data || !data.length) {
      await Swal.fire('Info', 'Belum ada serial MK Production.', 'info')
      return
    }

    const fmtQty = (v) => (v != null ? parseFloat(Number(v).toFixed(4)).toString() : '')
    const fmtDate = (v) => {
      if (!v) return '-'
      const d = new Date(v)
      if (Number.isNaN(d.getTime())) return v
      return d.toLocaleDateString('id-ID')
    }

    const rowsHtml = data.slice(0, 200).map((row, idx) => {
      const convInfo = row.repack_unit_id && row.repack_qty
        ? `<span style="background:#f3e8ff;color:#7c3aed;padding:1px 6px;border-radius:4px;font-size:10px;font-weight:600;">1 ${row.repack_unit_name || '?'} = ${fmtQty(row.repack_qty)} ${row.unit_name || ''}</span>`
        : `<span style="background:#e0f2fe;color:#0369a1;padding:1px 6px;border-radius:4px;font-size:10px;">Tanpa konversi</span>`
      return `
      <tr>
        <td style="border:1px solid #ddd;padding:4px;text-align:center;">${idx + 1}</td>
        <td style="border:1px solid #ddd;padding:4px;">${row.serial_number}</td>
        <td style="border:1px solid #ddd;padding:4px;">${row.unit_name || '-'}</td>
        <td style="border:1px solid #ddd;padding:4px;">${convInfo}</td>
        <td style="border:1px solid #ddd;padding:4px;">${fmtDate(row.production_date)}</td>
        <td style="border:1px solid #ddd;padding:4px;">${fmtDate(row.exp_date)}</td>
        <td style="border:1px solid #ddd;padding:4px;">${row.generated_at || '-'}</td>
        <td style="border:1px solid #ddd;padding:4px;text-align:center;">
          <button
            type="button"
            class="serial-pdf-btn"
            data-serial="${row.serial_number}"
            data-repack-unit-name="${row.repack_unit_name || ''}"
            data-repack-qty="${row.repack_qty || ''}"
            data-unit-name="${row.unit_name || ''}"
            data-production-date="${row.production_date || ''}"
            data-exp-date="${row.exp_date || ''}"
            style="padding:2px 8px;background:#dbeafe;color:#1d4ed8;border-radius:4px;border:0;cursor:pointer;"
          >
            PDF 10x5
          </button>
        </td>
      </tr>`
    }).join('')

    await Swal.fire({
      title: `Serial - ${props.item?.name || ''}`,
      width: 980,
      html: `
        <div style="display:flex;justify-content:flex-end;margin-bottom:8px;">
          <button
            id="download-all-serial-pdf-btn"
            type="button"
            style="padding:6px 10px;background:#dbeafe;color:#1d4ed8;border-radius:6px;border:0;cursor:pointer;font-size:12px;font-weight:600;"
          >
            Download All PDF (10x5cm)
          </button>
        </div>
        <div style="max-height:420px;overflow:auto;">
          <table style="width:100%;border-collapse:collapse;font-size:12px;">
            <thead>
              <tr>
                <th style="border:1px solid #ddd;padding:4px;">No</th>
                <th style="border:1px solid #ddd;padding:4px;">Serial</th>
                <th style="border:1px solid #ddd;padding:4px;">Unit Asal</th>
                <th style="border:1px solid #ddd;padding:4px;">Konversi</th>
                <th style="border:1px solid #ddd;padding:4px;">Production Date</th>
                <th style="border:1px solid #ddd;padding:4px;">Exp Date</th>
                <th style="border:1px solid #ddd;padding:4px;">Generated At</th>
                <th style="border:1px solid #ddd;padding:4px;">Print</th>
              </tr>
            </thead>
            <tbody>${rowsHtml}</tbody>
          </table>
        </div>
      `,
      didOpen: () => {
        const downloadAllBtn = document.getElementById('download-all-serial-pdf-btn')
        if (downloadAllBtn) {
          downloadAllBtn.addEventListener('click', () => {
            downloadSerialPDF(
              data.map((row) => ({
                serial: row.serial_number,
                repackQty: row.repack_qty,
                repackUnitName: row.repack_unit_name,
                unitName: row.unit_name,
                productionDate: row.production_date || null,
                expDate: row.exp_date || null,
              })),
              {
                itemName: props.item?.name || '',
                batch: props.prod?.batch_number || '-',
                productionDate: props.prod?.production_date || null,
                expDays: Number(props.item?.exp || 0),
              }
            )
          })
        }

        document.querySelectorAll('.serial-pdf-btn').forEach((btn) => {
          btn.addEventListener('click', (event) => {
            const serial = event.target?.getAttribute('data-serial')
            const repackUnitName = event.target?.getAttribute('data-repack-unit-name') || null
            const repackQty = event.target?.getAttribute('data-repack-qty') || null
            const unitName = event.target?.getAttribute('data-unit-name') || ''
            const productionDate = event.target?.getAttribute('data-production-date') || null
            const expDate = event.target?.getAttribute('data-exp-date') || null
            if (serial) {
              downloadSerialPDF([{
                serial,
                repackUnitName,
                repackQty: repackQty ? parseFloat(repackQty) : null,
                unitName,
                productionDate,
                expDate,
              }], {
                itemName: props.item?.name || '',
                batch: props.prod?.batch_number || '-',
                productionDate: productionDate || props.prod?.production_date || null,
                expDays: Number(props.item?.exp || 0),
              })
            }
          })
        })
      }
    })
  } catch (error) {
    const message = error?.response?.data?.message || 'Gagal mengambil serial'
    await Swal.fire('Error', message, 'error')
  }
}

const downloadSerialPDF = (serials, meta) => {
  if (!serials?.length) return

  const entries = serials.map((entry) => {
    if (typeof entry === 'string') {
      return {
        serial: entry,
        repackQty: meta?.repackQty ?? null,
        repackUnitName: meta?.repackUnitName ?? null,
        unitName: meta?.unitName ?? '',
      }
    }

    return {
      serial: entry.serial,
      repackQty: entry.repackQty ?? meta?.repackQty ?? null,
      repackUnitName: entry.repackUnitName ?? meta?.repackUnitName ?? null,
      unitName: entry.unitName ?? meta?.unitName ?? '',
      productionDate: entry.productionDate ?? meta?.productionDate ?? null,
      expDate: entry.expDate ?? meta?.expDate ?? null,
    }
  })

  const labelWidth = 100 // 10cm
  const labelHeight = 50 // 5cm
  const gap = 5 // 0.5cm
  const marginLeft = 5
  const marginTop = 5
  const columnsPerRow = 3
  const pdfWidth = 297 // A4 landscape
  const pdfHeight = 210
  const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: [pdfWidth, pdfHeight] })
  const rowHeight = labelHeight + gap
  const usableHeight = pdfHeight - (marginTop * 2)
  const rowsPerPage = Math.max(1, Math.floor(usableHeight / rowHeight))

  entries.forEach((entry, idx) => {
    const serial = entry.serial
    const itemsPerPage = columnsPerRow * rowsPerPage
    const indexInPage = idx % itemsPerPage
    if (idx > 0 && indexInPage === 0) {
      doc.addPage([pdfWidth, pdfHeight], 'landscape')
    }

    const rowIdx = Math.floor(indexInPage / columnsPerRow)
    const colIdx = indexInPage % columnsPerRow
    const x = marginLeft + colIdx * (labelWidth + gap)
    const y = marginTop + rowIdx * rowHeight

    doc.setDrawColor(0, 0, 0)
    doc.setLineWidth(0.5)
    doc.rect(x, y, labelWidth, labelHeight)

    const areaBarcodeW = labelWidth - 10
    const areaBarcodeH = 20
    const scale = 3
    const canvas = document.createElement('canvas')
    canvas.width = areaBarcodeW * scale
    canvas.height = areaBarcodeH * scale
    JsBarcode(canvas, serial, { width: 1.5 * scale, height: areaBarcodeH * scale, displayValue: false })

    const barcodeX = x + (labelWidth - areaBarcodeW) / 2
    doc.addImage(canvas, 'PNG', barcodeX, y + 3, areaBarcodeW, areaBarcodeH)

    let currentY = y + areaBarcodeH + 5
    doc.setFontSize(7)
    doc.setFont(undefined, 'bold')
    doc.text(`SERIAL: ${serial}`, x + labelWidth / 2, currentY, { align: 'center' })
    currentY += 3.5
    doc.setFontSize(8)
    doc.setFont(undefined, 'bold')
    doc.text(`${meta?.itemName || ''}`, x + labelWidth / 2, currentY, { align: 'center' })
    currentY += 3.2

    if (entry.repackUnitName && entry.repackQty) {
      doc.setFontSize(7)
      doc.setFont(undefined, 'bold')
      const fmtRepackQty = parseFloat(Number(entry.repackQty).toFixed(4)).toString()
      doc.text(`1 ${entry.repackUnitName.toUpperCase()} = ${fmtRepackQty} ${(entry.unitName || '').toUpperCase()}`, x + labelWidth / 2, currentY, { align: 'center' })
      currentY += 3
    }

    doc.setFontSize(7)
    doc.setFont(undefined, 'normal')
    doc.text(`BATCH: ${meta?.batch || '-'}`, x + labelWidth / 2, currentY, { align: 'center' })
    currentY += 3
    const prodLabel = entry.productionDate
      ? formatDateForLabel(entry.productionDate)
      : formatDateForLabel(meta?.productionDate)
    doc.text(`PROD: ${prodLabel}`, x + labelWidth / 2, currentY, { align: 'center' })
    currentY += 3
    const expLabel = entry.expDate
      ? formatDateForLabel(entry.expDate)
      : calculateExpDate(meta?.productionDate, meta?.expDays)
    doc.text(`EXP: ${expLabel}`, x + labelWidth / 2, currentY, { align: 'center' })
  })

  const firstSerial = entries[0]?.serial || 'serial'
  doc.save(`${firstSerial}_mk_production_labels_10x5cm.pdf`)
}

const calculateExpDate = (productionDate, expDays) => {
  if (!productionDate) return '-'
  const d = new Date(productionDate)
  if (Number.isNaN(d.getTime())) return '-'
  d.setDate(d.getDate() + (Number(expDays) || 0))
  return formatDateForLabel(d.toISOString().split('T')[0])
}

const rollbackSerial = async () => {
  const confirm = await Swal.fire({
    title: 'Rollback serial MK Production?',
    text: 'Semua serial untuk produksi ini akan dihapus.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, rollback',
    cancelButtonText: 'Batal',
    confirmButtonColor: '#d33'
  })

  if (!confirm.isConfirmed) return

  try {
    const { data } = await axios.delete(`/api/mk-production/${props.prod.id}/serials`)
    await Swal.fire('Berhasil', data?.message || 'Rollback serial berhasil', 'success')
    await loadSerialSummary()
  } catch (error) {
    const message = error?.response?.data?.message || 'Gagal rollback serial'
    await Swal.fire('Error', message, 'error')
  }
}

const itemUnitName = computed(() => {
  if (!props.item) return ''
  if (props.prod?.unit_id === props.item.small_unit_id) return props.item.small_unit_name || ''
  if (props.prod?.unit_id === props.item.medium_unit_id) return props.item.medium_unit_name || ''
  if (props.prod?.unit_id === props.item.large_unit_id) return props.item.large_unit_name || ''
  return ''
})

const printLabel = () => {
  if (!selectedBarcode.value) {
    Swal.fire('Error', 'Please select a barcode', 'error')
    return
  }
  
  if (!labelData.value.itemName || !labelData.value.productionDate || !labelData.value.batchNumber) {
    Swal.fire('Error', 'Please fill all required fields', 'error')
    return
  }
  
  // Generate label PDF
  generateLabelPDF()
}

const generateLabelPDF = () => {
  // Setup label dan PDF - Ukuran 10x5 cm (tiru dari menu Items)
  const labelWidth = 100; // 10cm
  const labelHeight = 50; // 5cm
  const gap = 5; // 0.5cm gap antar label
  const marginLeft = 5; // mm, margin kiri
  const marginTop = 5; // mm, margin atas
  const numLabels = 1; // Selalu print 1 label saja
  const numRows = 1; // 1 baris saja
  const pdfWidth = 297; // A4 landscape width (29.7cm)
  const pdfHeight = 210; // A4 landscape height (21cm)
  
  const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: [pdfWidth, pdfHeight] });

  // Generate array barcode sesuai qty
  const barcodes = Array(numLabels).fill(selectedBarcode.value);
  let y = marginTop;
  for (let rowIdx = 0; rowIdx < numRows; rowIdx++) {
    for (let colIdx = 0; colIdx < 3; colIdx++) {
      const idx = rowIdx * 3 + colIdx;
      if (idx >= barcodes.length) continue;
      const x = marginLeft + colIdx * (labelWidth + gap);
      const sku = barcodes[idx];
      
      // Border untuk label
      doc.setDrawColor(0, 0, 0);
      doc.setLineWidth(0.5);
      doc.rect(x, y, labelWidth, labelHeight);
      
      // Render barcode ke canvas - ukuran lebih besar
      const areaBarcodeW = labelWidth - 10; // 90mm
      const areaBarcodeH = 20; // 20mm untuk barcode
      const scale = 3;
      const canvas = document.createElement('canvas');
      canvas.width = areaBarcodeW * scale;
      canvas.height = areaBarcodeH * scale;
      JsBarcode(canvas, sku, { width: 1.5 * scale, height: areaBarcodeH * scale, displayValue: false });
      
             // Masukkan barcode ke PDF (ukuran asli) - center aligned
       const barcodeX = x + (labelWidth - areaBarcodeW) / 2;
       doc.addImage(canvas, 'PNG', barcodeX, y + 3, areaBarcodeW, areaBarcodeH);
       
       // Barcode Number/SKU di bawah barcode
       doc.setFontSize(8);
       doc.setFont(undefined, 'bold');
       doc.text(sku, x + labelWidth/2, y + areaBarcodeH + 4, { align: 'center' });
       
       // Informasi lengkap di bawah barcode
       const startY = y + areaBarcodeH + 8;
       let currentY = startY;
      
      // Header - Item Name
      doc.setFontSize(10);
      doc.setFont(undefined, 'bold');
      doc.text(labelData.value.itemName.toUpperCase(), x + labelWidth/2, currentY, { align: 'center' });
      currentY += 4;
      
      // Volume
      doc.setFontSize(7);
      doc.setFont(undefined, 'normal');
      doc.text(`VOLUME: ${labelData.value.volume} ${labelData.value.volumeUnit}`, x + labelWidth/2, currentY, { align: 'center' });
      currentY += 3;
      
      // Production Date
      doc.text(`PRODUCTION DATE: ${formatDateForLabel(labelData.value.productionDate)}`, x + labelWidth/2, currentY, { align: 'center' });
      currentY += 3;
      
      // Batch Number
      doc.text(`BATCH NUMBER: ${labelData.value.batchNumber}`, x + labelWidth/2, currentY, { align: 'center' });
      currentY += 3;
      
      // Expired Date
      doc.text(`EXPIRED DATE: ${formatDateForLabel(labelData.value.expiredDate)}`, x + labelWidth/2, currentY, { align: 'center' });
    }
    y += labelHeight + gap;
  }
  
  doc.save(`${selectedBarcode.value}_production_labels_10x5cm.pdf`);
}

const formatDateForLabel = (date) => {
  if (!date) return '-'
  
  // Jika date sudah dalam format DD-MM-YYYY, return langsung
  if (typeof date === 'string' && date.includes('-') && date.split('-').length === 3) {
    const parts = date.split('-')
    if (parts[0].length === 2 && parts[1].length === 2 && parts[2].length === 4) {
      // Format DD-MM-YYYY, convert ke format label
      const day = parts[0]
      const month = parts[1]
      const year = parts[2]
      const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
      const monthName = monthNames[parseInt(month) - 1] || month
      return `${day}-${monthName}-${year}`
    }
  }
  
  // Jika date dalam format lain, parse sebagai Date object
  const d = new Date(date)
  const day = String(d.getDate()).padStart(2, '0')
  const month = d.toLocaleDateString('en-US', { month: 'short' })
  const year = d.getFullYear()
  return `${day}-${month}-${year}`
}
</script> 