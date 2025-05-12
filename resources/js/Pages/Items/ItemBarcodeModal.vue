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
                            <select 
                                v-model="selectedCameraId" 
                                class="rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                                @change="restartScanner"
                            >
                                <option v-for="camera in cameras" :key="camera.id" :value="camera.id">
                                    {{ camera.label }}
                                </option>
                            </select>
                            <button 
                                @click="toggleScanner" 
                                class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white"
                                :class="isScanning ? 'bg-red-600 hover:bg-red-700' : 'bg-blue-600 hover:bg-blue-700'"
                            >
                                <i :class="isScanning ? 'fa-solid fa-stop' : 'fa-solid fa-camera'" class="mr-2"></i>
                                {{ isScanning ? 'Stop Scanner' : 'Start Scanner' }}
                            </button>
                        </div>
                    </div>
                    <div id="qr-reader" class="w-full aspect-video bg-gray-100 rounded-lg overflow-hidden"></div>
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
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
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
const cameras = ref([]);
const selectedCameraId = ref('');
const isScanning = ref(false);
let html5QrCode = null;
const isSaving = ref(false);

const form = useForm({
    barcode: '',
});

async function setupCameras() {
    if (!window.Html5Qrcode) return;
    try {
        cameras.value = await window.Html5Qrcode.getCameras();
        if (cameras.value.length > 0) {
            selectedCameraId.value = cameras.value[0].id;
        }
    } catch (err) {
        console.error('Error getting cameras:', err);
    }
}

async function stopScanner() {
    if (html5QrCode) {
        try {
            await html5QrCode.stop();
            await html5QrCode.clear();
        } catch (e) {
            // ignore errors
        }
        isScanning.value = false;
    }
}

function clearQrReaderElement() {
    const qrReader = document.getElementById('qr-reader');
    if (qrReader) qrReader.innerHTML = '';
}

function startScanner() {
    if (!window.Html5Qrcode || !selectedCameraId.value) return;
    clearQrReaderElement();
    html5QrCode = new window.Html5Qrcode('qr-reader');
    html5QrCode.start(
        selectedCameraId.value,
        { fps: 10, qrbox: 200 },
        (decodedText) => {
            newBarcode.value = decodedText;
            addBarcode();
        },
        (errorMessage) => {
            // Ignore errors
        }
    ).then(() => {
        isScanning.value = true;
    }).catch((err) => {
        console.error('Error starting scanner:', err);
    });
}

async function restartScanner() {
    await stopScanner();
    startScanner();
}

function toggleScanner() {
    if (isScanning.value) {
        stopScanner();
    } else {
        startScanner();
    }
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
    stopScanner();
    emit('close');
};

watch(() => props.show, (val) => {
    if (val) {
        fetchBarcodes();
    }
});

onMounted(async () => {
    // Tunggu sampai Html5Qrcode tersedia
    while (!window.Html5Qrcode) {
        await new Promise(resolve => setTimeout(resolve, 100));
    }
    setupCameras();
    fetchBarcodes();
});

onBeforeUnmount(() => {
    stopScanner();
});
</script>

<style>
.swal2-container {
  z-index: 2147483647 !important;
}
</style> 