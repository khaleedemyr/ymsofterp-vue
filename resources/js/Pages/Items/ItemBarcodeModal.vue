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
                                        @click="printBarcode(barcode.barcode, qtyPrint[barcode.id] || 1)"
                                        class="text-blue-600 hover:text-blue-900 mr-2"
                                        title="Print Barcode"
                                    >
                                        <i class="fa-solid fa-print"></i>
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
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, watch } from 'vue';
import Modal from '@/Components/Modal.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';

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

function printBarcode(barcode, qty = 1) {
    const printWindow = window.open('', '', 'width=400,height=300');
    const barcodes = Array(qty).fill(barcode);
    const rows = [];
    for (let i = 0; i < barcodes.length; i += 2) {
        rows.push(barcodes.slice(i, i + 2));
    }
    const html = [
        '<html>',
        '<head>',
        '<title>Print Barcode</title>',
        '<style>',
        '@media print { body { margin: 0; } }',
        'body { margin: 0; padding: 0; }',
        '.label-row { display: flex; flex-direction: row; width: 6cm; height: 1.5cm; margin: 0; padding: 0; }',
        '.label-cell { width: 3cm; height: 1.5cm; display: flex; flex-direction: column; align-items: center; justify-content: center; border: none; margin: 0; padding: 0; }',
        '.barcode-svg { width: 2.8cm; height: 1.3cm; }',
        '.barcode-value { font-size: 14px; text-align: center; margin-top: -0.2cm; }',
        '</style>',
        '</head>',
        '<body>',
        '<div id="labels">',
        rows.map(row => `\n      <div class="label-row">\n        ${row.map(bc => '<div class="label-cell"><svg class="barcode-svg"></svg></div>').join('')}\n        ${row.length === 1 ? '<div class="label-cell"></div>' : ''}\n      </div>\n    `).join(''),
        '</div>',
        '<script src="https://cdn.jsdelivr.net/npm/jsbarcode/dist/JsBarcode.all.min.js"><\/script>',
        '<script>',
        'window.onload = function() {',
        '  const svgs = document.querySelectorAll(".barcode-svg");',
        '  svgs.forEach((svg, idx) => {',
        '    JsBarcode(svg, "' + barcode + '", {format: "CODE128", width:3, height:90, displayValue:true, fontSize:14, margin:0});',
        '  });',
        '  window.print();',
        '  setTimeout(() => window.close(), 500);',
        '}',
        '<\/script>',
        '</body>',
        '</html>'
    ].join('');
    printWindow.document.write(html);
    printWindow.document.close();
}
</script>

<style>
.swal2-container {
  z-index: 2147483647 !important;
}
</style> 