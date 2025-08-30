<template>
    <Modal :show="show" @close="closeModal">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900">
                Manage Barcodes for {{ item?.name }}
            </h2>

            <div class="mt-6">
                <!-- Camera Scanner Section -->
                <div class="mb-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-md font-medium text-gray-700">Scan Barcode</h3>
                        <div class="flex items-center gap-2">
                            <button 
                                @click="showScanner = true" 
                                class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-green-500 hover:bg-green-600"
                            >
                                <i class="fa-solid fa-qrcode mr-2"></i>
                                Scan Barcode
                            </button>
                        </div>
                    </div>
                    <div v-if="showScanner" class="mb-2">
                        <div v-if="cameras.length > 1" class="mb-2">
                            <select v-model="selectedCameraId" @change="restartScanner" class="border rounded px-2 py-1">
                                <option v-for="cam in cameras" :key="cam.id" :value="cam.id">{{ cam.label }}</option>
                            </select>
                        </div>
                        <div id="qr-reader" style="width: 100%"></div>
                        <button @click="closeScanner" class="mt-2 bg-gray-300 hover:bg-gray-400 text-gray-700 px-3 py-1 rounded">Tutup Scanner</button>
                    </div>
                </div>

                <!-- Manual Input Section -->
                <div class="flex gap-2">
                    <TextInput
                        v-model="newBarcode"
                        type="text"
                        class="flex-1"
                        placeholder="Enter barcode"
                        @keyup.enter="addBarcode"
                    />
                    <PrimaryButton @click="addBarcode" :disabled="isSaving">
                        <span v-if="isSaving">
                            <i class="fa fa-spinner fa-spin"></i> Menyimpan...
                        </span>
                        <span v-else>
                            Add
                        </span>
                    </PrimaryButton>
                </div>

                <div class="mt-4">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Barcode
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="barcode in barcodes" :key="barcode.id">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ barcode.barcode }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium flex items-center justify-end gap-2">
                                                                         <input type="number" v-model.number="qtyPrint[barcode.id]" min="1" class="w-14 border rounded px-1 mr-2" />
                                      <button
                                          @click="openSmallPrintPreview(barcode.barcode, qtyPrint[barcode.id] || 1)"
                                          class="text-teal-600 hover:text-teal-900 mr-2"
                                          title="Small Label Preview"
                                      >
                                          <i class="fa-solid fa-tag"></i>
                                      </button>
                                                                          <button
                                          @click="downloadPDF(barcode.barcode, qtyPrint[barcode.id] || 1)"
                                          class="text-blue-600 hover:text-blue-900 mr-2"
                                          title="Download PDF (10x5cm)"
                                      >
                                          <i class="fa-solid fa-file-pdf"></i>
                                      </button>
                                      <button
                                          @click="downloadSmallPDF(barcode.barcode, qtyPrint[barcode.id] || 1)"
                                          class="text-indigo-600 hover:text-indigo-900 mr-2"
                                          title="Download Small Label PDF (3x1.5cm)"
                                      >
                                          <i class="fa-solid fa-tag"></i>
                                      </button>
                                     <button
                                         @click="deleteBarcode(barcode)"
                                         class="text-red-600 hover:text-red-900"
                                     >
                                         Delete
                                     </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-6 flex justify-end">
                <SecondaryButton @click="closeModal">
                    Close
                </SecondaryButton>
            </div>
        </div>
    </Modal>
         
     <teleport to="body">
       <div v-if="showSmallPrintPreview" class="fixed inset-0 z-[99999] flex items-center justify-center bg-black/60">
         <div class="bg-white rounded-xl shadow-lg p-4">
           <SmallPrintPreview
             :sku="printPreviewData.sku"
             :name="printPreviewData.name"
             :qty="printPreviewData.qty"
           />
           <div class="flex justify-end mt-4">
             <button @click="showSmallPrintPreview = false" class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300">Tutup</button>
           </div>
         </div>
       </div>
     </teleport>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, watch } from 'vue';
import Modal from '@/Components/Modal.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';
import SmallPrintPreview from './SmallPrintPreview.vue';
import JsBarcode from 'jsbarcode';
import jsPDF from 'jspdf';

const props = defineProps({
    show: Boolean,
    item: Object,
});

const emit = defineEmits(['close']);

const barcodes = ref(props.item?.barcodes || []);
const newBarcode = ref('');
const showScanner = ref(false);
const cameras = ref([]);
const selectedCameraId = ref('');
let html5QrCode = null;
const isSaving = ref(false);
const qtyPrint = ref({});
const showSmallPrintPreview = ref(false);
const printPreviewData = ref({ 
    sku: '', 
    name: '', 
    qty: 1
});

const form = useForm({
    barcode: '',
});

watch(showScanner, async (val) => {
    if (val) {
        if (!window.Html5Qrcode) {
            const script = document.createElement('script');
            script.src = 'https://unpkg.com/html5-qrcode';
            script.onload = setupCameras;
            document.body.appendChild(script);
        } else {
            setupCameras();
        }
    } else {
        // Cleanup when scanner is closed
        if (html5QrCode) {
            try {
                await html5QrCode.stop();
                html5QrCode.clear();
                const qrReader = document.getElementById('qr-reader');
                if (qrReader) qrReader.innerHTML = '';
            } catch (e) {
                console.error('Error cleaning up scanner:', e);
            }
        }
    }
});

async function setupCameras() {
    if (!window.Html5Qrcode) return;
    try {
        cameras.value = await window.Html5Qrcode.getCameras();
        selectedCameraId.value = cameras.value[0]?.id || '';
        startScanner();
    } catch (e) {
        console.error('Error setting up cameras:', e);
    }
}

function startScanner() {
    if (!window.Html5Qrcode || !selectedCameraId.value) return;
    
    // Cleanup existing scanner if any
    if (html5QrCode) {
        try {
            html5QrCode.stop().then(() => {
                html5QrCode.clear();
                const qrReader = document.getElementById('qr-reader');
                if (qrReader) qrReader.innerHTML = '';
            });
        } catch (e) {
            console.error('Error cleaning up existing scanner:', e);
        }
    }

    // Create new scanner instance
    html5QrCode = new window.Html5Qrcode('qr-reader');
    html5QrCode.start(
        selectedCameraId.value,
        { fps: 10, qrbox: 200 },
        (decodedText) => {
            newBarcode.value = decodedText;
            addBarcode();
        }
    ).catch(e => {
        console.error('Error starting scanner:', e);
    });
}

function restartScanner() {
    startScanner();
}

function closeScanner() {
    showScanner.value = false;
}

const fetchBarcodes = async () => {
    try {
        const res = await axios.get(route('items.barcodes.index', props.item.id));
        barcodes.value = res.data.barcodes;
    } catch (e) {
        // Optional: tampilkan error
    }
};

const addBarcode = async () => {
    if (!newBarcode.value) return;
    isSaving.value = true;
    try {
        await axios.post(route('items.barcodes.store', props.item.id), {
            barcode: newBarcode.value
        });
        await fetchBarcodes();
        newBarcode.value = '';
    } catch (e) {
        if (e.response && e.response.status === 422) {
            const msg = e.response.data?.errors?.barcode?.[0] || 'Gagal menyimpan barcode';
            alert(msg);
        } else {
            alert('Terjadi kesalahan server');
        }
    } finally {
        isSaving.value = false;
    }
};

const deleteBarcode = async (barcode) => {
    if (!window.confirm('Hapus barcode ini?')) return;
    form.delete(route('items.barcodes.destroy', [props.item.id, barcode.id]), {
        preserveScroll: true,
        onSuccess: () => {
            fetchBarcodes();
        },
    });
};

const closeModal = () => {
    showScanner.value = false;
    if (html5QrCode) {
        try {
            html5QrCode.stop().then(() => {
                html5QrCode.clear();
                const qrReader = document.getElementById('qr-reader');
                if (qrReader) qrReader.innerHTML = '';
            });
        } catch (e) {
            console.error('Error cleaning up scanner:', e);
        }
    }
    emit('close');
};

watch(() => props.show, (val) => {
    if (val) {
        fetchBarcodes();
        showScanner.value = false;
    }
});

onMounted(async () => {
    fetchBarcodes();
});

onBeforeUnmount(() => {
    if (html5QrCode) {
        try {
            html5QrCode.stop().then(() => {
                html5QrCode.clear();
                const qrReader = document.getElementById('qr-reader');
                if (qrReader) qrReader.innerHTML = '';
            });
        } catch (e) {
            console.error('Error cleaning up scanner:', e);
        }
    }
});



function openSmallPrintPreview(barcode, qty) {
    printPreviewData.value = {
        sku: barcode,
        name: props.item?.name || '',
        qty: qty || 1
    };
    showSmallPrintPreview.value = true;
}



function downloadPDF(barcode, qty) {
    // Setup label dan PDF - Ukuran baru 10x5 cm
    const labelWidth = 100; // 10cm
    const labelHeight = 50; // 5cm
    const gap = 5; // 0.5cm gap antar label
    const marginLeft = 5; // mm, margin kiri
    const marginTop = 5; // mm, margin atas
    const numLabels = qty || 1;
    const numRows = Math.ceil(numLabels / 3); // 3 label per baris untuk landscape
    const pdfWidth = 297; // A4 landscape width (29.7cm)
    const pdfHeight = 210; // A4 landscape height (21cm)
    
    const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: [pdfWidth, pdfHeight] });

    // Generate array barcode sesuai qty
    const barcodes = Array(numLabels).fill(barcode);
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
             
             // Informasi lengkap di bawah barcode
             const startY = y + areaBarcodeH + 5;
             let currentY = startY;
            
                         // SKU/Barcode
             doc.setFontSize(8);
             doc.setFont(undefined, 'bold');
             doc.text(`SKU: ${sku}`, x + labelWidth/2, currentY, { align: 'center' });
             currentY += 4;
             
             // Nama Item
             const itemName = props.item?.name || '';
             doc.setFontSize(9);
             doc.setFont(undefined, 'bold');
             doc.text(`Nama: ${itemName}`, x + labelWidth/2, currentY, { align: 'center' });
             currentY += 4.5;
             
             // Warehouse Division
             const warehouseDivision = props.item?.warehouse_division?.name || '-';
             doc.setFontSize(6);
             doc.setFont(undefined, 'normal');
             doc.text(`Warehouse: ${warehouseDivision}`, x + labelWidth/2, currentY, { align: 'center' });
             currentY += 3;
             
             // Category
             const category = props.item?.category?.name || '-';
             doc.text(`Category: ${category}`, x + labelWidth/2, currentY, { align: 'center' });
             currentY += 3;
             
             // Sub Category
             const subCategory = props.item?.sub_category?.name || '-';
             doc.text(`Sub Category: ${subCategory}`, x + labelWidth/2, currentY, { align: 'center' });
             currentY += 3;
             
             // Item Code/SKU (jika ada)
             const itemCode = props.item?.sku || props.item?.code || '-';
             doc.text(`Code: ${itemCode}`, x + labelWidth/2, currentY, { align: 'center' });
        }
        y += labelHeight + gap;
    }
         doc.save(`${barcode}_labels_10x5cm.pdf`);
 }
 
 function downloadSmallPDF(barcode, qty) {
    // Setup label dan PDF - Ukuran kecil yang disesuaikan
    const labelWidth = 25; // 2.5cm - dikurangi untuk menghindari cutoff
    const labelHeight = 12; // 1.2cm - dikurangi untuk menghindari cutoff
    const gap = 3; // 0.3cm gap antar label - ditambah untuk spacing yang lebih baik
    const marginLeft = 25; // mm, margin kiri yang lebih besar lagi untuk alignment yang tepat
    const marginTop = 2; // mm, margin atas minimal
    const numLabels = qty || 1;
    const numRows = Math.ceil(numLabels / 3); // 3 label per baris
    const pdfWidth = 297; // A4 landscape width (29.7cm)
    const pdfHeight = 210; // A4 landscape height (21cm)
     
     const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: [pdfWidth, pdfHeight] });
 
     // Generate array barcode sesuai qty
     const barcodes = Array(numLabels).fill(barcode);
     let y = marginTop;
     for (let rowIdx = 0; rowIdx < numRows; rowIdx++) {
         for (let colIdx = 0; colIdx < 3; colIdx++) {
             const idx = rowIdx * 3 + colIdx;
             if (idx >= barcodes.length) continue;
             
             // Posisi tetap untuk setiap label
             let x;
             if (colIdx === 0) {
                 x = 5; // Label kiri: 5mm dari tepi
             } else if (colIdx === 1) {
                 x = 35; // Label tengah: 35mm dari tepi (5 + 30)
             } else {
                 x = 65; // Label kanan: 65mm dari tepi (35 + 30)
             }
             
             const sku = barcodes[idx];
             
                           // Border dihilangkan untuk tampilan yang lebih bersih
             
                                          // Render barcode ke canvas - ukuran diperbesar
               const areaBarcodeW = labelWidth - 1; // 24mm - diperbesar
               const areaBarcodeH = 9; // 9mm untuk barcode - disesuaikan
              const scale = 2;
              const canvas = document.createElement('canvas');
              canvas.width = areaBarcodeW * scale;
              canvas.height = areaBarcodeH * scale;
              JsBarcode(canvas, sku, { width: 1 * scale, height: areaBarcodeH * scale, displayValue: false });
              
                             // Masukkan barcode ke PDF (ukuran asli) - center aligned
               const barcodeX = x + (labelWidth - areaBarcodeW) / 2;
               doc.addImage(canvas, 'PNG', barcodeX, y + 0.2, areaBarcodeW, areaBarcodeH);
              
                             // Informasi di bawah barcode
               const startY = y + areaBarcodeH + 0.5;
              let currentY = startY;
             
                                          // SKU/Barcode
               doc.setFontSize(4);
               doc.setFont(undefined, 'bold');
               doc.text(sku, x + labelWidth/2, currentY, { align: 'center' });
               currentY += 1.5;
               
               // Nama Item
               const itemName = props.item?.name || '';
               doc.setFontSize(3);
               doc.setFont(undefined, 'bold');
               doc.text(itemName, x + labelWidth/2, currentY, { align: 'center' });
         }
         y += labelHeight + gap;
     }
     doc.save(`${barcode}_small_labels_3x1.5cm.pdf`);
 }
</script>

<style>
.swal2-container {
  z-index: 2147483647 !important;
}
</style> 