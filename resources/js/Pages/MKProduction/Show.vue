<template>
  <Head title="MK Production Details" />
  <AppLayout>
    <div class="max-w-7xl mx-auto py-8 px-2">
      <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-2">
          <button @click="$inertia.visit('/mk-production')" class="text-blue-500 hover:underline"><i class="fa fa-arrow-left"></i> Kembali</button>
          <h1 class="text-2xl font-bold flex items-center gap-2 ml-4">
            <i class="fa-solid fa-industry text-blue-500"></i> MK Production Detail
          </h1>
        </div>
        <button @click="showLabelModal = true" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors">
          <i class="fas fa-print mr-2"></i> Print Label
        </button>
      </div>
      <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 bg-white border-b border-gray-200">
              <!-- Basic Information -->
              <div class="mb-8">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Basic Information</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <div>
                    <label class="block text-sm font-medium text-gray-700">Production Date</label>
                    <p class="mt-1 text-sm text-gray-900">{{ formatDate(prod.production_date) }}</p>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700">Batch Number</label>
                    <p class="mt-1 text-sm text-gray-900">{{ prod.batch_number }}</p>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700">Item</label>
                    <p class="mt-1 text-sm text-gray-900">{{ item?.name }}</p>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700">Warehouse</label>
                    <p class="mt-1 text-sm text-gray-900">{{ warehouse?.name }}</p>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700">Qty Produksi</label>
                    <p class="mt-1 text-sm text-gray-900">{{ Number(prod.qty).toFixed(2) }}</p>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700">Qty Jadi</label>
                    <p class="mt-1 text-sm text-gray-900">{{ Number(prod.qty_jadi).toFixed(2) }}</p>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700">Notes</label>
                    <p class="mt-1 text-sm text-gray-900">{{ prod.notes }}</p>
                  </div>
                </div>
              </div>

              <!-- Stock Card -->
              <div class="mb-8">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Stock Card</h3>
                <table class="min-w-full divide-y divide-gray-200">
                  <thead class="bg-gray-50">
                    <tr>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">In Qty</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Out Qty</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Saldo Qty</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                    </tr>
                  </thead>
                  <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="card in stockCard" :key="card.id">
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ formatDate(card.date) }}</td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ Number(card.in_qty_small).toFixed(2) }}</td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ Number(card.out_qty_small).toFixed(2) }}</td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ Number(card.saldo_qty_small).toFixed(2) }}</td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ card.description }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <!-- BOM -->
              <div class="mb-8">
                <h3 class="text-lg font-medium text-gray-900 mb-4">BOM (Bill of Materials)</h3>
                <table class="min-w-full divide-y divide-gray-200">
                  <thead class="bg-gray-50">
                    <tr>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Material</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Qty per 1</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit</th>
                    </tr>
                  </thead>
                  <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="b in bom" :key="b.id">
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ b.material_name }}</td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ Number(b.qty).toFixed(2) }}</td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ b.unit_name }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>

            </div>
          </div>
        </div>
      </div>
      
      <!-- Label Print Modal -->
      <div v-if="showLabelModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
          <div class="p-6">
            <div class="flex justify-between items-center mb-4">
              <h3 class="text-lg font-semibold text-gray-900">Print Production Label</h3>
              <button @click="showLabelModal = false" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times"></i>
              </button>
            </div>
            
            <div class="space-y-4">
              <!-- Item Name -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Item Name</label>
                <input type="text" v-model="labelData.itemName" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500" placeholder="Enter item name">
              </div>
              
                             <!-- Volume -->
               <div>
                 <label class="block text-sm font-medium text-gray-700 mb-1">Volume</label>
                 <div class="flex gap-2">
                   <input type="number" v-model.number="labelData.volume" class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500" placeholder="Volume">
                   <input type="text" v-model="labelData.volumeUnit" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 bg-gray-100" readonly>
                 </div>
               </div>
              
              <!-- Production Date -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Production Date</label>
                <input type="date" v-model="labelData.productionDate" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500">
              </div>
              
              <!-- Batch Number -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Batch Number</label>
                <input type="text" v-model="labelData.batchNumber" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500" placeholder="Batch number">
              </div>
              
                             <!-- Expired Date -->
               <div>
                 <label class="block text-sm font-medium text-gray-700 mb-1">Expired Date</label>
                 <div class="px-3 py-2 bg-gray-100 border border-gray-300 rounded-md text-sm text-gray-900">
                   {{ labelData.expiredDate || 'Calculating...' }}
                 </div>
               </div>
              
              <!-- Barcode Selection -->
              <div v-if="barcodes.length > 1">
                <label class="block text-sm font-medium text-gray-700 mb-1">Select Barcode</label>
                <select v-model="selectedBarcode" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500">
                  <option v-for="barcode in barcodes" :key="barcode.barcode" :value="barcode.barcode">
                    {{ barcode.barcode }}
                  </option>
                </select>
              </div>
              
              
            </div>
            
            <div class="flex justify-end gap-3 mt-6">
              <button @click="showLabelModal = false" class="px-4 py-2 text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300 transition-colors">
                Cancel
              </button>
              <button @click="printLabel" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
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