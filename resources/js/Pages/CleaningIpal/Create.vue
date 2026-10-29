<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { ref, watch, onBeforeUnmount } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';

const props = defineProps({
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
const scannedOutlet = ref(null);
const manualQr = ref('');
const beforePreview = ref('');
const afterPreview = ref('');
let html5QrCode = null;

const form = useForm({
    outlet_id: '',
    cleaning_date: props.defaultDate || new Date().toISOString().slice(0, 10),
    keterangan: '',
    photo_before: null,
    photo_after: null,
});

async function lookupOutlet(rawValue) {
    const qrCode = String(rawValue || '').trim();
    if (!qrCode) return;

    lookupLoading.value = true;
    try {
        const { data } = await axios.get('/cleaning-ipal/lookup-outlet', {
            params: { qr_code: qrCode },
        });
        if (!data.success || !data.outlet) {
            throw new Error(data.message || 'Outlet tidak ditemukan');
        }
        scannedOutlet.value = data.outlet;
        form.outlet_id = data.outlet.id_outlet;
        closeScanner();
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: 'Outlet ditemukan',
            showConfirmButton: false,
            timer: 1800,
        });
    } catch (error) {
        scannedOutlet.value = null;
        form.outlet_id = '';
        Swal.fire('Gagal', error?.response?.data?.message || error.message || 'Lookup gagal', 'error');
    } finally {
        lookupLoading.value = false;
    }
}

function onManualLookup() {
    lookupOutlet(manualQr.value);
}

function clearOutlet() {
    scannedOutlet.value = null;
    form.outlet_id = '';
    manualQr.value = '';
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
        selectedCameraId.value = cameras.value[0]?.id || '';
        startScanner();
    } catch (e) {
        Swal.fire('Kamera', 'Tidak dapat mengakses kamera. Gunakan input manual QR outlet.', 'warning');
    }
}

function startScanner() {
    if (!window.Html5Qrcode || !selectedCameraId.value || !showScanner.value) return;
    stopScanner();
    html5QrCode = new window.Html5Qrcode('cleaning-ipal-qr-reader');
    scanning.value = true;
    html5QrCode.start(
        selectedCameraId.value,
        { fps: 10, qrbox: { width: 250, height: 250 } },
        (decodedText) => {
            if (lookupLoading.value) return;
            lookupOutlet(decodedText);
        }
    ).catch(() => {
        scanning.value = false;
    });
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

onBeforeUnmount(() => {
    stopScanner();
    if (beforePreview.value) URL.revokeObjectURL(beforePreview.value);
    if (afterPreview.value) URL.revokeObjectURL(afterPreview.value);
});

function onBeforeChange(e) {
    const file = e.target.files?.[0] || null;
    form.photo_before = file;
    if (beforePreview.value) URL.revokeObjectURL(beforePreview.value);
    beforePreview.value = file ? URL.createObjectURL(file) : '';
}

function onAfterChange(e) {
    const file = e.target.files?.[0] || null;
    form.photo_after = file;
    if (afterPreview.value) URL.revokeObjectURL(afterPreview.value);
    afterPreview.value = file ? URL.createObjectURL(file) : '';
}

function submit() {
    if (!form.outlet_id) {
        Swal.fire('Perhatian', 'Scan QR outlet terlebih dahulu.', 'warning');
        return;
    }
    if (!form.photo_before) {
        Swal.fire('Perhatian', 'Foto before wajib diisi saat membuat data.', 'warning');
        return;
    }

    form.post('/cleaning-ipal', {
        forceFormData: true,
        onError: (errors) => {
            const msg = errors.outlet_id || errors.photo_before || errors.message || 'Gagal menyimpan data.';
            Swal.fire('Gagal', msg, 'error');
        },
    });
}
</script>

<template>
    <AppLayout>
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="mb-6">
                <Link href="/cleaning-ipal" class="text-emerald-700 hover:underline text-sm">
                    <i class="fa-solid fa-arrow-left mr-1"></i> Kembali ke Daftar
                </Link>
            </div>

            <h1 class="text-2xl font-bold text-emerald-800 mb-2">Tambah Cleaning IPAL</h1>
            <p class="text-sm text-gray-500 mb-6">
                Scan QR outlet, isi keterangan + foto before. Foto after bisa dilengkapi belakangan lewat Edit.
            </p>

            <div v-if="!tableReady" class="bg-yellow-50 border border-yellow-200 rounded-xl p-5 text-yellow-800 text-sm">
                Tabel belum tersedia. Jalankan SQL create table terlebih dahulu.
            </div>

            <template v-else>
                <div class="bg-white rounded-xl shadow p-5 mb-6">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="font-semibold text-gray-800">1. Scan QR Outlet</h2>
                        <button
                            v-if="!showScanner"
                            type="button"
                            class="text-sm text-emerald-700 hover:underline"
                            @click="showScanner = true"
                        >
                            Buka Scanner
                        </button>
                    </div>

                    <div v-if="showScanner" class="space-y-3">
                        <div v-if="cameras.length > 1">
                            <label class="block text-xs text-gray-500 mb-1">Kamera</label>
                            <select v-model="selectedCameraId" class="w-full rounded-lg border-gray-300 text-sm">
                                <option v-for="cam in cameras" :key="cam.id" :value="cam.id">{{ cam.label || cam.id }}</option>
                            </select>
                        </div>
                        <div id="cleaning-ipal-qr-reader" class="overflow-hidden rounded-xl border border-gray-200 bg-black/5 min-h-[240px]"></div>
                        <p class="text-xs text-gray-500 text-center">
                            {{ scanning ? 'Arahkan kamera ke QR Code outlet...' : 'Menyiapkan kamera...' }}
                        </p>
                        <button type="button" class="text-sm text-gray-500 hover:underline" @click="closeScanner">
                            Tutup scanner / pakai input manual
                        </button>
                    </div>

                    <div class="mt-4 flex gap-2">
                        <input
                            v-model="manualQr"
                            type="text"
                            placeholder="Atau ketik QR code outlet manual..."
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

                <div v-if="scannedOutlet" class="bg-emerald-50 border border-emerald-200 rounded-xl p-5 mb-6">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="text-xs uppercase tracking-wide text-emerald-700 font-semibold mb-1">Outlet terpilih</div>
                            <div class="text-lg font-bold text-gray-900">{{ scannedOutlet.nama_outlet }}</div>
                            <div class="font-mono text-emerald-800 mt-0.5">{{ scannedOutlet.qr_code }}</div>
                            <div class="text-sm text-gray-600 mt-2">{{ scannedOutlet.lokasi || '-' }}</div>
                        </div>
                        <button type="button" class="text-sm text-red-600 hover:underline" @click="clearOutlet">Ganti</button>
                    </div>
                </div>

                <form v-if="scannedOutlet" class="bg-white rounded-xl shadow p-5 space-y-4" @submit.prevent="submit">
                    <h2 class="font-semibold text-gray-800">2. Data Cleaning</h2>

                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Tanggal</label>
                        <input
                            v-model="form.cleaning_date"
                            type="date"
                            required
                            class="w-full rounded-lg border-gray-300 text-sm focus:ring-emerald-500 focus:border-emerald-500"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Keterangan</label>
                        <textarea
                            v-model="form.keterangan"
                            rows="3"
                            placeholder="Catatan cleaning IPAL..."
                            class="w-full rounded-lg border-gray-300 text-sm focus:ring-emerald-500 focus:border-emerald-500"
                        />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Foto Before <span class="text-red-500">*</span></label>
                            <input type="file" accept="image/*" capture="environment" class="w-full text-sm" @change="onBeforeChange" />
                            <img v-if="beforePreview" :src="beforePreview" alt="Before preview" class="mt-2 w-full h-40 object-cover rounded-lg border" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">
                                Foto After <span class="text-gray-400">(opsional)</span>
                            </label>
                            <input type="file" accept="image/*" capture="environment" class="w-full text-sm" @change="onAfterChange" />
                            <img v-if="afterPreview" :src="afterPreview" alt="After preview" class="mt-2 w-full h-40 object-cover rounded-lg border" />
                            <p class="text-xs text-gray-400 mt-1">Bisa diisi nanti lewat Edit / Isi After.</p>
                        </div>
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white px-6 py-2.5 rounded-lg font-semibold text-sm"
                        >
                            Simpan
                        </button>
                        <Link href="/cleaning-ipal" class="px-6 py-2.5 rounded-lg border text-sm text-gray-600 hover:bg-gray-50">
                            Batal
                        </Link>
                    </div>
                </form>
            </template>
        </div>
    </AppLayout>
</template>
