<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { ref, watch, onBeforeUnmount } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import { pickRearCameraId } from '@/utils/pickRearCamera';

const props = defineProps({
    checkTimes: { type: Array, default: () => ['10:00', '16:00', '22:00'] },
    defaultDate: { type: String, default: '' },
    canChooseOutlet: { type: Boolean, default: false },
    userOutletId: { type: Number, default: 0 },
    tableReady: { type: Boolean, default: true },
});

const showScanner = ref(true);
const cameras = ref([]);
const selectedCameraId = ref('');
const scanning = ref(false);
const lookupLoading = ref(false);
const scannedSerial = ref(null);
const manualSerial = ref('');
let html5QrCode = null;

const form = useForm({
    asset_serial_id: '',
    check_date: props.defaultDate || new Date().toISOString().slice(0, 10),
    check_time: '10:00',
    temp_display: '',
    temp_actual: '',
    notes: '',
});

async function lookupSerial(rawValue) {
    const serialNumber = String(rawValue || '').trim();
    if (!serialNumber) return;

    lookupLoading.value = true;
    try {
        const { data } = await axios.get('/chiller-temperature/lookup-serial', {
            params: { serial_number: serialNumber },
        });
        if (!data.success || !data.serial) {
            throw new Error(data.message || 'Nomor seri tidak ditemukan');
        }
        scannedSerial.value = data.serial;
        form.asset_serial_id = data.serial.id;
        closeScanner();
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: 'Unit ditemukan',
            showConfirmButton: false,
            timer: 1800,
        });
    } catch (error) {
        scannedSerial.value = null;
        form.asset_serial_id = '';
        Swal.fire('Gagal', error?.response?.data?.message || error.message || 'Lookup gagal', 'error');
    } finally {
        lookupLoading.value = false;
    }
}

function onManualLookup() {
    lookupSerial(manualSerial.value);
}

function clearSerial() {
    scannedSerial.value = null;
    form.asset_serial_id = '';
    manualSerial.value = '';
    showScanner.value = true;
}

function closeScanner() {
    showScanner.value = false;
    stopScanner();
}

function stopScanner() {
    scanning.value = false;
    if (html5QrCode) {
        html5QrCode.stop().then(() => html5QrCode.clear()).catch(() => {});
        html5QrCode = null;
    }
}

async function setupCameras() {
    if (!window.Html5Qrcode) return;
    try {
        cameras.value = await window.Html5Qrcode.getCameras();
        selectedCameraId.value = pickRearCameraId(cameras.value);
        startScanner();
    } catch (e) {
        startScanner();
    }
}

async function startScanner() {
    if (!window.Html5Qrcode || !showScanner.value) return;
    stopScanner();
    html5QrCode = new window.Html5Qrcode('chiller-qr-reader');
    scanning.value = true;

    const onSuccess = (decodedText) => {
        if (lookupLoading.value) return;
        lookupSerial(decodedText);
    };
    const config = { fps: 10, qrbox: { width: 250, height: 250 } };

    try {
        if (selectedCameraId.value) {
            await html5QrCode.start(selectedCameraId.value, config, onSuccess);
        } else {
            await html5QrCode.start({ facingMode: 'environment' }, config, onSuccess);
        }
    } catch (e) {
        try {
            await html5QrCode.start({ facingMode: 'environment' }, config, onSuccess);
        } catch (err) {
            scanning.value = false;
            Swal.fire('Kamera', 'Tidak dapat mengakses kamera belakang. Gunakan input manual nomor seri.', 'warning');
        }
    }
}

watch(showScanner, async (val) => {
    if (!val) {
        stopScanner();
        return;
    }
    if (!window.Html5Qrcode) {
        const script = document.createElement('script');
        script.src = 'https://unpkg.com/html5-qrcode';
        script.onload = setupCameras;
        document.body.appendChild(script);
    } else {
        setupCameras();
    }
}, { immediate: true });

watch(selectedCameraId, () => {
    if (showScanner.value) startScanner();
});

onBeforeUnmount(() => stopScanner());

function submit() {
    if (!form.asset_serial_id) {
        Swal.fire('Perhatian', 'Scan QR asset serial terlebih dahulu.', 'warning');
        return;
    }
    form.post('/chiller-temperature', {
        onError: (errors) => {
            const msg = errors.check_time || errors.asset_serial_id || errors.message || 'Gagal menyimpan data.';
            Swal.fire('Gagal', msg, 'error');
        },
    });
}
</script>

<template>
    <AppLayout>
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="mb-6">
                <Link href="/chiller-temperature" class="text-cyan-700 hover:underline text-sm">
                    <i class="fa-solid fa-arrow-left mr-1"></i> Kembali ke Daftar
                </Link>
            </div>

            <h1 class="text-2xl font-bold text-cyan-800 mb-2">Tambah Kontrol Suhu</h1>
            <p class="text-sm text-gray-500 mb-6">Scan QR Code dari Asset Serial, pilih jam, lalu isi suhu Display & Actual.</p>

            <div v-if="!tableReady" class="bg-yellow-50 border border-yellow-200 rounded-xl p-5 text-yellow-800 text-sm">
                Tabel belum tersedia. Jalankan SQL create table terlebih dahulu.
            </div>

            <template v-else>
                <div class="bg-white rounded-xl shadow p-5 mb-6">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="font-semibold text-gray-800">1. Scan QR Asset Serial</h2>
                        <button
                            v-if="!showScanner"
                            type="button"
                            class="text-sm text-cyan-700 hover:underline"
                            @click="showScanner = true"
                        >
                            Buka Scanner
                        </button>
                    </div>

                    <div v-if="showScanner" class="space-y-3">
                        <div v-if="cameras.length > 1">
                            <label class="block text-xs text-gray-500 mb-1">Kamera</label>
                            <select
                                v-model="selectedCameraId"
                                class="w-full rounded-lg border-gray-300 text-sm"
                            >
                                <option v-for="cam in cameras" :key="cam.id" :value="cam.id">{{ cam.label || cam.id }}</option>
                            </select>
                            <p class="text-[11px] text-gray-400 mt-1">Default otomatis kamera belakang.</p>
                        </div>
                        <div id="chiller-qr-reader" class="overflow-hidden rounded-xl border border-gray-200 bg-black/5 min-h-[240px]"></div>
                        <p class="text-xs text-gray-500 text-center">
                            {{ scanning ? 'Arahkan kamera belakang ke QR Code asset serial...' : 'Menyiapkan kamera belakang...' }}
                        </p>
                        <button type="button" class="text-sm text-gray-500 hover:underline" @click="closeScanner">
                            Tutup scanner / pakai input manual
                        </button>
                    </div>

                    <div class="mt-4 flex gap-2">
                        <input
                            v-model="manualSerial"
                            type="text"
                            placeholder="Atau ketik nomor seri manual..."
                            class="flex-1 rounded-lg border-gray-300 text-sm font-mono"
                            @keyup.enter="onManualLookup"
                        />
                        <button
                            type="button"
                            class="px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-sm font-medium disabled:opacity-50"
                            :disabled="lookupLoading"
                            @click="onManualLookup"
                        >
                            Cari
                        </button>
                    </div>
                </div>

                <div v-if="scannedSerial" class="bg-cyan-50 border border-cyan-200 rounded-xl p-5 mb-6">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="text-xs uppercase tracking-wide text-cyan-700 font-semibold mb-1">Unit terpilih</div>
                            <div class="text-lg font-bold text-gray-900">{{ scannedSerial.item_name }}</div>
                            <div class="font-mono text-cyan-800 mt-0.5">{{ scannedSerial.serial_number }}</div>
                            <div class="text-sm text-gray-600 mt-2">
                                Outlet: <span class="font-medium">{{ scannedSerial.owner_outlet_name || '-' }}</span>
                            </div>
                            <div class="text-sm text-gray-600">
                                Warehouse: <span class="font-medium">{{ scannedSerial.warehouse_name || '-' }}</span>
                            </div>
                        </div>
                        <button type="button" class="text-sm text-red-600 hover:underline" @click="clearSerial">Ganti</button>
                    </div>
                </div>

                <form v-if="scannedSerial" class="bg-white rounded-xl shadow p-5 space-y-4" @submit.prevent="submit">
                    <h2 class="font-semibold text-gray-800">2. Isi Data Suhu</h2>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Tanggal</label>
                            <input
                                v-model="form.check_date"
                                type="date"
                                required
                                class="w-full rounded-lg border-gray-300 text-sm focus:ring-cyan-500 focus:border-cyan-500"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Jam Pengecekan</label>
                            <select
                                v-model="form.check_time"
                                required
                                class="w-full rounded-lg border-gray-300 text-sm focus:ring-cyan-500 focus:border-cyan-500"
                            >
                                <option v-for="t in checkTimes" :key="t" :value="t">{{ t }}</option>
                            </select>
                            <p v-if="form.errors.check_time" class="text-xs text-red-600 mt-1">{{ form.errors.check_time }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Suhu Display (°C)</label>
                            <input
                                v-model="form.temp_display"
                                type="number"
                                step="0.1"
                                required
                                class="w-full rounded-lg border-gray-300 text-sm focus:ring-cyan-500 focus:border-cyan-500"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Suhu Actual (°C)</label>
                            <input
                                v-model="form.temp_actual"
                                type="number"
                                step="0.1"
                                required
                                class="w-full rounded-lg border-gray-300 text-sm focus:ring-cyan-500 focus:border-cyan-500"
                            />
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-medium text-gray-500 mb-1">Catatan</label>
                            <textarea
                                v-model="form.notes"
                                rows="2"
                                class="w-full rounded-lg border-gray-300 text-sm focus:ring-cyan-500 focus:border-cyan-500"
                            />
                        </div>
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="bg-cyan-600 hover:bg-cyan-700 disabled:opacity-50 text-white px-6 py-2.5 rounded-lg font-semibold text-sm"
                        >
                            Simpan
                        </button>
                        <Link href="/chiller-temperature" class="px-6 py-2.5 rounded-lg border text-sm text-gray-600 hover:bg-gray-50">
                            Batal
                        </Link>
                    </div>
                </form>
            </template>
        </div>
    </AppLayout>
</template>
