<template>
  <Head title="Butcher Process Details" />

  <AppLayout>
    <div class="max-w-7xl mx-auto py-8 px-2">
      <!-- Debug info -->
      <div v-if="!butcherProcess" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        <h3>Error: Butcher Process data not found</h3>
        <p>Props received: {{ props }}</p>
      </div>
      
      <div v-else-if="!butcherProcess.items || butcherProcess.items.length === 0" class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mb-4">
        <h3>Warning: No items found for this Butcher Process</h3>
        <p>Butcher Process ID: {{ butcherProcess.id }}</p>
        <p>Number: {{ butcherProcess.number }}</p>
      </div>
      
      <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-2">
          <button @click="$inertia.visit('/butcher-processes')" class="text-blue-500 hover:underline"><i class="fa fa-arrow-left"></i> Kembali</button>
          <h1 class="text-2xl font-bold flex items-center gap-2 ml-4">
            <i class="fa-solid fa-cut text-red-500"></i> Butcher Detail
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
                    <label class="block text-sm font-medium text-gray-700">Process Number</label>
                    <p class="mt-1 text-sm text-gray-900">{{ butcherProcess.number }}</p>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700">Process Date</label>
                    <p class="mt-1 text-sm text-gray-900">{{ formatDate(butcherProcess.process_date) }}</p>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700">Warehouse</label>
                    <p class="mt-1 text-sm text-gray-900">{{ butcherProcess.warehouse?.name }}</p>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700">Good Receive</label>
                    <p class="mt-1 text-sm text-gray-900">
                      {{ butcherProcess.gr_number || '-' }}
                    </p>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700">Created By</label>
                    <p class="mt-1 text-sm text-gray-900">{{ butcherProcess.created_by_nama_lengkap || '-' }}</p>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700">Created At</label>
                    <p class="mt-1 text-sm text-gray-900">{{ formatDateTime(butcherProcess.created_at) }}</p>
                  </div>
                </div>
              </div>

              <!-- Items -->
              <div class="mb-8">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Items</h3>
                <table class="min-w-full divide-y divide-gray-200">
                  <thead class="bg-gray-50">
                    <tr>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Whole Item</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Whole Qty</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PCS Item</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PCS Qty</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">MAC /Gram</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">MAC /Pcs</th>
                    </tr>
                  </thead>
                  <tbody class="bg-white divide-y divide-gray-200">
                    <template v-for="(group, groupIdx) in groupByWholeItem(butcherProcess.items)" :key="groupIdx">
                      <template v-for="(item, idx) in group.items" :key="item.id">
                        <tr>
                          <td v-if="idx === 0" class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" :rowspan="group.items.length">
                            {{ item.wholeItem?.name || item.whole_item_name || '-' }}
                          </td>
                          <td v-if="idx === 0" class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" :rowspan="group.items.length">
                            {{ item.whole_qty }}
                          </td>
                          <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            {{ item.pcsItem?.name || item.pcs_item_name || '-' }}
                          </td>
                          <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            {{ item.pcs_qty }}
                          </td>
                          <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            {{ item.unit?.name }}
                          </td>
                          <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            {{ item.details && item.details.length > 0 ? Number(item.details[0].mac_pcs).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '-' }}
                          </td>
                          <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            {{ item.details && item.details.length > 0 && item.small_conversion_qty ? (Number(item.details[0].mac_pcs) * Number(item.small_conversion_qty)).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '-' }}
                          </td>
                        </tr>
                      </template>
                    </template>
                  </tbody>
                </table>
              </div>

              <!-- Item Details Card -->
              <div class="max-w-3xl w-full mx-auto">
                <div v-if="butcherProcess.items.length && butcherProcess.items[0].details && butcherProcess.items[0].details.length" class="bg-white rounded-2xl shadow p-6 mb-6">
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div><b>Slaughter Date:</b> {{ formatDate(butcherProcess.items[0].details[0].slaughter_date) }}</div>
                    <div><b>Packing Date:</b> {{ formatDate(butcherProcess.items[0].details[0].packing_date) }}</div>
                    <div><b>Batch Est:</b> {{ butcherProcess.items[0].details[0].batch_est }}</div>
                    <div><b>Qty Purchase:</b> {{ butcherProcess.items[0].details[0].qty_purchase }}</div>
                    <div><b>Susut Air Qty:</b> {{ butcherProcess.items[0].details[0].susut_air_qty }}</div>
                    <div><b>Susut Air Unit:</b> {{ butcherProcess.items[0].details[0].susut_air_unit }}</div>
                    <div>
                      <b>Attachment PDF:</b>
                      <a v-if="butcherProcess.items[0].details[0].attachment_pdf" :href="`/storage/${butcherProcess.items[0].details[0].attachment_pdf}`" target="_blank" class="text-blue-600 underline">PDF</a>
                      <span v-else>-</span>
                    </div>
                    <div>
                      <b>Upload Image:</b>
                      <a v-if="butcherProcess.items[0].details[0].upload_image" :href="`/storage/${butcherProcess.items[0].details[0].upload_image}`" target="_blank" class="text-blue-600 underline">Image</a>
                      <span v-else>-</span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Certificates -->
              <div class="mb-8">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Halal Certificates</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <div v-for="cert in butcherProcess.certificates" :key="cert.id" class="border rounded-lg p-4">
                    <div class="mb-4">
                      <label class="block text-sm font-medium text-gray-700">Producer Name</label>
                      <p class="mt-1 text-sm text-gray-900">{{ cert.producer_name }}</p>
                    </div>
                    <div class="mb-4">
                      <label class="block text-sm font-medium text-gray-700">Certificate Number</label>
                      <p class="mt-1 text-sm text-gray-900">{{ cert.certificate_number }}</p>
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-700">Certificate File</label>
                      <a
                        :href="cert.file_url"
                        target="_blank"
                        class="mt-1 inline-flex items-center text-sm text-indigo-600 hover:text-indigo-900"
                      >
                        <svg class="h-5 w-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        View Certificate
                      </a>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Notes -->
              <div v-if="butcherProcess.notes">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Notes</h3>
                <p class="text-sm text-gray-900">{{ butcherProcess.notes }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Label Print Modal -->
      <div v-if="showLabelModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl mx-4">
          <div class="p-6">
            <div class="flex justify-between items-center mb-4">
              <h3 class="text-lg font-semibold text-gray-900">Print Butcher Process Labels</h3>
              <button @click="showLabelModal = false" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times"></i>
              </button>
            </div>
            
            <div class="space-y-4">
              <!-- Item Selection -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Select PCS Items to Print Labels</label>
                <div class="space-y-2 max-h-60 overflow-y-auto">
                  <div v-for="item in butcherProcess.items" :key="item.id" class="flex items-center gap-3 p-3 border rounded-lg">
                    <input 
                      type="checkbox" 
                      :value="item.id" 
                      v-model="selectedItems"
                      class="rounded border-gray-300"
                    >
                                         <div class="flex-1">
                       <div class="font-medium text-sm">{{ item.pcsItem?.name || item.pcs_item_name }}</div>
                       <div class="text-xs text-gray-600">
                         Qty: {{ item.pcs_qty }} {{ item.unit?.name }} | 
                         Whole: {{ item.wholeItem?.name || item.whole_item_name }} ({{ item.whole_qty }})
                       </div>
                                               <!-- Barcode Selection -->
                         
                         <div v-if="barcodes[item.pcs_item_id] && barcodes[item.pcs_item_id].length > 1" class="mt-2">
                           <label class="block text-xs font-medium text-gray-700 mb-1">Select Barcode:</label>
                           <select 
                             v-model="selectedBarcodes[item.pcs_item_id]" 
                             class="w-full text-xs px-2 py-1 border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-blue-500"
                           >
                             <option v-for="barcode in barcodes[item.pcs_item_id]" :key="barcode.barcode" :value="barcode.barcode">
                               {{ barcode.barcode }}
                             </option>
                           </select>
                         </div>
                         <div v-else-if="barcodes[item.pcs_item_id] && barcodes[item.pcs_item_id].length === 1" class="mt-2">
                           <div class="text-xs text-gray-500">
                             Barcode: {{ barcodes[item.pcs_item_id][0].barcode }}
                           </div>
                         </div>
                        <div v-else class="mt-2">
                          <div class="text-xs text-red-500">
                            No barcode available
                          </div>
                        </div>
                     </div>

                  </div>
                </div>
              </div>
              
              <!-- Process Information -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Process Date</label>
                  <input type="date" v-model="labelData.processDate" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500">
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Batch Number</label>
                  <input type="text" v-model="labelData.batchNumber" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500" placeholder="Batch number">
                </div>
              </div>
            </div>
            
            <div class="flex justify-end gap-3 mt-6">
              <button @click="showLabelModal = false" class="px-4 py-2 text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300 transition-colors">
                Cancel
              </button>
              <button @click="printLabels" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                <i class="fas fa-print mr-2"></i> Print Labels
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import Swal from 'sweetalert2'
import jsPDF from 'jspdf'
import JsBarcode from 'jsbarcode'
import QRCode from 'qrcode'

const props = defineProps({
  butcherProcess: {
    type: Object,
    required: true
  }
})

const showLabelModal = ref(false)
const selectedItems = ref([])
const barcodes = ref({}) // Object untuk menyimpan barcodes per item
const selectedBarcodes = ref({}) // Object untuk menyimpan barcode yang dipilih per item
const labelData = ref({
  processDate: '',
  batchNumber: ''
})

// Load barcodes for all PCS items when component mounts
onMounted(async () => {
  console.log('ButcherProcess Show component mounted')
  console.log('Props butcherProcess:', props.butcherProcess)
  
  // Pre-fill label data
  labelData.value.processDate = props.butcherProcess?.process_date || ''
  labelData.value.batchNumber = props.butcherProcess?.number || ''
  
  console.log('Label data:', labelData.value)
  
  // Load barcodes for all PCS items
  if (props.butcherProcess?.items) {
    console.log('Items count:', props.butcherProcess.items.length)
    for (const item of props.butcherProcess.items) {
      console.log('=== PROCESSING ITEM FOR BARCODE LOADING ===')
      console.log('Full item object:', item)
      console.log('item.pcs_item_id:', item.pcs_item_id)
      console.log('item.pcs_item_name:', item.pcs_item_name)
      
      if (item.pcs_item_id) {
        console.log('Fetching barcodes for PCS Item ID:', item.pcs_item_id)
        try {
          const response = await axios.get(`/api/items/${item.pcs_item_id}/barcodes`)
          console.log('API Response:', response.data)
          barcodes.value[item.pcs_item_id] = response.data.barcodes || []
          
          // Set default selected barcode (first one)
          if (barcodes.value[item.pcs_item_id].length > 0) {
            selectedBarcodes.value[item.pcs_item_id] = barcodes.value[item.pcs_item_id][0].barcode
            console.log('✅ Successfully set barcode for item', item.pcs_item_id, ':', selectedBarcodes.value[item.pcs_item_id])
          } else {
            console.log('❌ No barcodes found for item', item.pcs_item_id)
          }
          
          console.log('Barcodes array for PCS item', item.pcs_item_id, ':', barcodes.value[item.pcs_item_id])
          console.log('Selected barcode for PCS item', item.pcs_item_id, ':', selectedBarcodes.value[item.pcs_item_id])
        } catch (error) {
          console.error('❌ Error loading barcodes for PCS item:', item.pcs_item_id, error)
          barcodes.value[item.pcs_item_id] = []
        }
      } else {
        console.log('❌ PCS Item ID is undefined for item:', item)
      }
      console.log('=== END PROCESSING ITEM ===')
    }
    
    // Final check
    console.log('=== FINAL BARCODE STATUS ===')
    console.log('barcodes.value:', barcodes.value)
    console.log('selectedBarcodes.value:', selectedBarcodes.value)
    console.log('=== END FINAL STATUS ===')
  }
})

const formatDate = (date) => {
  return new Date(date).toLocaleDateString('id-ID')
}

const formatDateTime = (date) => {
  return new Date(date).toLocaleString('id-ID')
}

function groupByWholeItem(items) {
  const groups = [];
  let lastKey = null;
  let current = null;
  items.forEach(item => {
    const key = item.wholeItem?.name || item.whole_item_name || '-';
    if (key !== lastKey) {
      current = { key, items: [] };
      groups.push(current);
      lastKey = key;
    }
    current.items.push(item);
  });
  return groups;
}

const printLabels = () => {
  if (selectedItems.value.length === 0) {
    Swal.fire('Error', 'Please select at least one item to print labels', 'error')
    return
  }
  
  if (!labelData.value.processDate || !labelData.value.batchNumber) {
    Swal.fire('Error', 'Please fill all required fields', 'error')
    return
  }
  
  // Check if all selected items have barcodes
  const selectedButcherItems = props.butcherProcess.items.filter(item => selectedItems.value.includes(item.id))
  for (const item of selectedButcherItems) {
    if (!selectedBarcodes.value[item.pcs_item_id]) {
      Swal.fire('Error', `No barcode selected for item: ${item.pcs_item_name}`, 'error')
      return
    }
  }
  
  // Generate labels PDF
  generateLabelsPDF()
}

const generateLabelsPDF = () => {
  // Setup label dan PDF - Ukuran 10x5 cm (tiru dari menu Items)
  const labelWidth = 100; // 10cm
  const labelHeight = 50; // 5cm
  const gap = 5; // 0.5cm gap antar label
  const marginLeft = 5; // mm, margin kiri
  const marginTop = 5; // mm, margin atas
  const pdfWidth = 297; // A4 landscape width (29.7cm)
  const pdfHeight = 210; // A4 landscape height (21cm)
  
  const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: [pdfWidth, pdfHeight] });

  // Get selected items
  const selectedButcherItems = props.butcherProcess.items.filter(item => selectedItems.value.includes(item.id))
  
  console.log('Selected items:', selectedButcherItems)
  console.log('Barcodes loaded:', barcodes.value)
  
  let labelIndex = 0;
  let y = marginTop;
  let x = marginLeft;
  
  for (const item of selectedButcherItems) {
    console.log('Processing item for PDF:', item)
    console.log('Item ID:', item.id)
    console.log('PCS Item ID:', item.pcs_item_id)
    
    // Get selected barcode for this item
    const selectedBarcode = selectedBarcodes.value[item.pcs_item_id]
    console.log('Selected barcode for PCS item', item.pcs_item_id, ':', selectedBarcode)
    
    if (!selectedBarcode) {
      console.warn('No barcode selected for PCS item:', item.pcs_item_name || 'Unknown')
      continue
    }
    
    const barcode = selectedBarcode
    console.log('Using barcode:', barcode)
    
    // Check if we need to move to next row
    if (x + labelWidth > pdfWidth - marginLeft) {
      x = marginLeft;
      y += labelHeight + gap;
      
      // Check if we need a new page
      if (y + labelHeight > pdfHeight - marginTop) {
        doc.addPage();
        y = marginTop;
      }
    }
    
    // Border untuk label
    doc.setDrawColor(0, 0, 0);
    doc.setLineWidth(0.5);
    doc.rect(x, y, labelWidth, labelHeight);
    
         // Render barcode ke canvas
     const areaBarcodeW = labelWidth - 30; // 70mm (lebih kecil untuk menghindari QR code)
     const areaBarcodeH = 18; // 18mm untuk barcode
     const scale = 3;
     const canvas = document.createElement('canvas');
     canvas.width = areaBarcodeW * scale;
     canvas.height = areaBarcodeH * scale;
     JsBarcode(canvas, barcode, { width: 1.5 * scale, height: areaBarcodeH * scale, displayValue: false });
     
     // Masukkan barcode ke PDF (ukuran asli) - center aligned, dengan margin dari QR code
     const barcodeX = x + 5; // 5mm dari kiri
     doc.addImage(canvas, 'PNG', barcodeX, y + 3, areaBarcodeW, areaBarcodeH);
     
     // Barcode Number/SKU di bawah barcode
     doc.setFontSize(8);
     doc.setFont(undefined, 'bold');
     doc.text(barcode, x + 5, y + areaBarcodeH + 6, { align: 'left' });
     
     // QR Code untuk link ke detail item PCS
     const qrCodeUrl = `${import.meta.env.VITE_APP_URL || window.location.origin}/items/${item.pcs_item_id}/detail`
     console.log('QR Code URL:', qrCodeUrl)
     
     // Generate QR Code
     const qrCanvas = document.createElement('canvas')
     qrCanvas.width = 20 * 3 // 20mm * 3 scale (lebih kecil)
     qrCanvas.height = 20 * 3
     
     try {
       QRCode.toCanvas(qrCanvas, qrCodeUrl, { 
         width: 20 * 3,
         margin: 1,
         color: {
           dark: '#000000',
           light: '#FFFFFF'
         }
       }, function (error) {
         if (error) {
           console.error('QR Code generation error:', error)
         } else {
           // Add QR code to PDF (position di kanan bawah, tidak mengganggu barcode)
           const qrX = x + labelWidth - 25 // 25mm dari kanan
           const qrY = y + labelHeight - 25 // 25mm dari bawah
           doc.addImage(qrCanvas, 'PNG', qrX, qrY, 20, 20)
         }
       })
     } catch (error) {
       console.error('QR Code generation failed:', error)
     }
     
     // Informasi lengkap di bawah barcode
     const startY = y + areaBarcodeH + 12; // Jarak yang tepat dari barcode number
     let currentY = startY;
     
     // Header - Item Name
     doc.setFontSize(9);
     doc.setFont(undefined, 'bold');
     const itemName = item.pcs_item_name || 'Unknown Item'
     doc.text(itemName.toUpperCase(), x + 5, currentY, { align: 'left' });
     currentY += 3;
     
     // Process Date
     doc.setFontSize(10);
     doc.setFont(undefined, 'normal');
     doc.text(`PROCESS DATE: ${formatDateForLabel(labelData.value.processDate)}`, x + 5, currentY, { align: 'left' });
     currentY += 3.5;
     
     // Batch Number
     doc.setFontSize(10);
     doc.setFont(undefined, 'normal');
     doc.text(`BATCH NUMBER: ${labelData.value.batchNumber}`, x + 5, currentY, { align: 'left' });
     currentY += 3.5;
     
     // Expire Date (butcher date + exp from items table)
     const butcherDate = new Date(labelData.value.processDate)
     const expDays = item.pcs_item_exp || 0
     console.log('Expire calculation for item:', item.pcs_item_name)
     console.log('Butcher date:', labelData.value.processDate)
     console.log('Exp days from items table:', expDays)
     
     const expireDate = new Date(butcherDate)
     expireDate.setDate(expireDate.getDate() + expDays)
     console.log('Calculated expire date:', expireDate.toISOString().split('T')[0])
     
     const expireDateFormatted = formatDateForLabel(expireDate.toISOString().split('T')[0])
     doc.setFontSize(10);
     doc.setFont(undefined, 'normal');
     doc.text(`EXP: ${expireDateFormatted}`, x + 5, currentY, { align: 'left' });
     currentY += 3.5;
     
     // Ready To Sale (same as process date)
     const readyToSaleDate = formatDateForLabel(labelData.value.processDate)
     doc.setFontSize(10);
     doc.setFont(undefined, 'normal');
     doc.text(`READY TO SALE: ${readyToSaleDate}`, x + 5, currentY, { align: 'left' });
    
    // Move to next position
    x += labelWidth + gap;
    labelIndex++;
  }
  
  doc.save(`butcher_process_labels_${labelData.value.batchNumber}.pdf`);
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