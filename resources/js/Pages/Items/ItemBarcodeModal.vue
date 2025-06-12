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
                                        @click="downloadPDF(barcode.barcode, qtyPrint[barcode.id] || 1)"
                                        class="text-blue-600 hover:text-blue-900 mr-2"
                                        title="Download PDF"
                                    >
                                        <i class="fa-solid fa-print"></i>
                                    </button>
                                    <button
                                        @click="printBarcode(barcode.barcode, props.item?.name, qtyPrint[barcode.id] || 1)"
                                        class="text-green-600 hover:text-green-900 mr-2"
                                        title="Print ZPL"
                                    >
                                        <i class="fa-solid fa-barcode"></i> Print ZPL
                                    </button>
                                    <button
                                        @click="downloadZPL(barcode.barcode, props.item?.name)"
                                        class="text-orange-600 hover:text-orange-900 mr-2"
                                        title="Download ZPL"
                                    >
                                        <i class="fa-solid fa-download"></i> ZPL
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
      <div v-if="showPrintPreview" class="fixed inset-0 z-[99999] flex items-center justify-center bg-black/60">
        <div class="bg-white rounded-xl shadow-lg p-4">
          <PrintPreview
            :sku="printPreviewData.sku"
            :name="printPreviewData.name"
            :qty="printPreviewData.qty"
          />
          <div class="flex justify-end mt-4">
            <button @click="showPrintPreview = false" class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300">Tutup</button>
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
import PrintPreview from './PrintPreview.vue';
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
const showPrintPreview = ref(false);
const printPreviewData = ref({ sku: '', name: '', qty: 1 });

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

function generateZplBarcode(sku, name) {
    // Konfigurasi label
    const labelWidth = 30; // 3cm = 30mm
    const labelHeight = 15; // 1.5cm = 15mm
    const gap = 2; // 0.2cm = 2mm
    const dpi = 203; // DPI Zebra ZD220
    
    // Konversi mm ke dots (1mm = 8 dots pada 203 DPI)
    const widthInDots = labelWidth * 8;
    const heightInDots = labelHeight * 8;
    const gapInDots = gap * 8;
    
    // Generate ZPL code
    let zpl = '';
    
    // Set label size dan gap
    zpl += `^Q${heightInDots},${gapInDots}\n`; // Set label height and gap
    zpl += `^W${widthInDots}\n`; // Set label width
    
    // Set font dan ukuran
    zpl += `^A0N,20,20\n`; // Font 0, height 20, width 20
    
    // Barcode Code128
    zpl += `^FO10,10^BY2\n`; // Field Origin, Barcode field default
    zpl += `^BCN,50,Y,N,N\n`; // Code128, height 50, print human readable, no check digit
    zpl += `^FD${sku}^FS\n`; // Field Data, Field Separator
    
    // Nama item (2 baris jika panjang)
    const maxCharsPerLine = 20;
    if (name.length > maxCharsPerLine) {
        const firstLine = name.substring(0, maxCharsPerLine);
        const secondLine = name.substring(maxCharsPerLine);
        zpl += `^FO10,70^A0N,15,15^FD${firstLine}^FS\n`;
        zpl += `^FO10,90^A0N,15,15^FD${secondLine}^FS\n`;
    } else {
        zpl += `^FO10,70^A0N,15,15^FD${name}^FS\n`;
    }
    
    return zpl;
}

function printBarcode(sku, name, qty = 1) {
    // Generate array barcode sesuai qty
    const barcodes = Array(qty).fill({ sku, name });
    
    // Bagi per 3 untuk 1 baris label
    const rows = [];
    for (let i = 0; i < barcodes.length; i += 3) {
        rows.push(barcodes.slice(i, i + 3));
    }
    
    // Gabungkan ZPL untuk semua baris
    let zplData = '';
    rows.forEach(row => {
        row.forEach(item => {
            zplData += generateZplBarcode(item.sku, item.name);
        });
        zplData += '\n';
    });
    
    // Print menggunakan Web USB API
    async function printWithWebUSB() {
        try {
            // Request printer device (filter kosong)
            const device = await navigator.usb.requestDevice({ filters: [] });
            console.log(device);
            await device.open();
            await device.selectConfiguration(1);
            await device.claimInterface(device.configuration.interfaces[0].interfaceNumber);
            const encoder = new TextEncoder();
            const data = encoder.encode(zplData);
            await device.transferOut(1, data);
            await device.close();
            alert('Print berhasil!');
        } catch (error) {
            console.error('Print error:', error);
            alert('Gagal print: ' + error.message);
        }
    }
    
    // Cek apakah Web USB API didukung
    if (navigator.usb) {
        printWithWebUSB();
    } else {
        alert('Browser Anda tidak mendukung Web USB API. Gunakan Chrome, Edge, atau Opera terbaru.');
    }
}

function openPrintPreview(barcode, qty) {
    printPreviewData.value = {
        sku: barcode,
        name: props.item?.name || '',
        qty: qty || 1,
    };
    showPrintPreview.value = true;
}

function downloadZPL(sku, name) {
    const zpl = generateZplBarcode(sku, name);
    const blob = new Blob([zpl], { type: 'text/plain' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `${sku}.zpl`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

function downloadPDF(barcode, qty) {
    // Setup label dan PDF
    const labelWidth = 30; // 3cm
    const labelHeight = 15; // 1.5cm
    const gap = 3; // 0.3cm
    const numLabels = qty || 1;
    const numRows = Math.ceil(numLabels / 3);
    const pdfWidth = 94; // 9.4cm
    const pdfHeight = numRows * labelHeight;
    const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: [pdfWidth, pdfHeight] });

    // Generate array barcode sesuai qty
    const barcodes = Array(numLabels).fill(barcode);
    let y = 0;
    for (let rowIdx = 0; rowIdx < numRows; rowIdx++) {
        for (let colIdx = 0; colIdx < 3; colIdx++) {
            const idx = rowIdx * 3 + colIdx;
            if (idx >= barcodes.length) continue;
            const x = colIdx * (labelWidth + gap);
            const sku = barcodes[idx];
            // Render barcode ke canvas proporsional, scale 3x
            const areaBarcodeW = labelWidth - 4; // 26mm
            const areaBarcodeH = 10; // mm, diperkecil agar ada ruang untuk teks
            const scale = 3;
            const canvas = document.createElement('canvas');
            canvas.width = areaBarcodeW * scale;
            canvas.height = areaBarcodeH * scale;
            JsBarcode(canvas, sku, { width: 1.5 * scale, height: areaBarcodeH * scale, displayValue: false });
            // Masukkan barcode ke PDF (ukuran asli)
            doc.addImage(canvas, 'PNG', x + 2, y + 1.5, areaBarcodeW, areaBarcodeH);
            doc.setFontSize(8);
            // Geser teks lebih ke bawah dari barcode
            doc.text(sku, x + labelWidth / 2, y + areaBarcodeH + 4, { align: 'center' }); // Geser ke bawah
        }
        y += labelHeight;
    }
    doc.save(`${barcode}_labels.pdf`);
}
</script>

<style>
.swal2-container {
  z-index: 2147483647 !important;
}
</style> 