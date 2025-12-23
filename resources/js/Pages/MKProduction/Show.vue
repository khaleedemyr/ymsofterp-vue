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