<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed, watch } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import axios from 'axios';
import Swal from 'sweetalert2';
import debounce from 'lodash/debounce';

const props = defineProps({
    user: Object,
    outlets: Array,
    warehouseOutlets: Array,
});

const form = useForm({
    date: new Date().toISOString().slice(0, 10),
    outlet_id: props.user.id_outlet == 1 ? '' : props.user.id_outlet,
    warehouse_outlet_id: '',
    type: 'discard',
    description: '',
    buyer_name: '',
    buyer_contact: '',
    items: [],
    photo_paths: [],
    approvers: [],
});

const isHO = computed(() => props.user.id_outlet == 1);
const warehouseOptions = computed(() =>
    props.warehouseOutlets.filter(w => form.outlet_id && w.outlet_id == form.outlet_id)
);

watch(() => form.outlet_id, () => { form.warehouse_outlet_id = ''; });

// Photos
const photos = ref([]);
const uploadingPhoto = ref(false);
const lightboxOpen = ref(false);
const lightboxIndex = ref(0);
const fileInput = ref(null);
const cameraInput = ref(null);

function triggerFileUpload() { fileInput.value?.click(); }
function triggerCamera() { cameraInput.value?.click(); }

async function handleFileSelect(event) {
    const files = event.target.files;
    if (!files.length) return;
    for (const file of files) {
        await uploadSinglePhoto(file);
    }
    event.target.value = '';
}

async function uploadSinglePhoto(file) {
    uploadingPhoto.value = true;
    try {
        const formData = new FormData();
        formData.append('photo', file);
        const { data } = await axios.post('/asset-disposals/upload-photo', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        if (data.success) {
            photos.value.push({ path: data.path, url: data.url });
        }
    } catch (e) {
        Swal.fire('Error', 'Gagal upload foto.', 'error');
    }
    uploadingPhoto.value = false;
}

function removePhoto(index) {
    photos.value.splice(index, 1);
}

function openLightbox(index) {
    lightboxIndex.value = index;
    lightboxOpen.value = true;
}

function closeLightbox() { lightboxOpen.value = false; }
function prevPhoto() { if (lightboxIndex.value > 0) lightboxIndex.value--; }
function nextPhoto() { if (lightboxIndex.value < photos.value.length - 1) lightboxIndex.value++; }

// Item search
const itemQuery = ref('');
const itemResults = ref([]);
const isSearching = ref(false);

const searchItems = debounce(async () => {
    if (!itemQuery.value || itemQuery.value.length < 2 || !form.warehouse_outlet_id) return;
    isSearching.value = true;
    try {
        const { data } = await axios.get('/items/search-for-asset-transfer', {
            params: { q: itemQuery.value, warehouse_outlet_id: form.warehouse_outlet_id }
        });
        itemResults.value = data;
    } catch (e) { console.error(e); }
    isSearching.value = false;
}, 350);

watch(itemQuery, searchItems);

function addItem(item) {
    if (form.items.find(i => i.item_id === item.id)) {
        Swal.fire('Info', 'Item sudah ditambahkan.', 'info');
        return;
    }
    const units = [];
    if (item.unit_small) units.push(item.unit_small);
    if (item.unit_medium && item.unit_medium !== item.unit_small) units.push(item.unit_medium);
    if (item.unit_large && item.unit_large !== item.unit_small && item.unit_large !== item.unit_medium) units.push(item.unit_large);
    form.items.push({
        item_id: item.id,
        item_name: item.name,
        sku: item.sku,
        available_units: units,
        selected_unit: item.unit_small || '-',
        stock_small: item.stock_small || 0,
        qty: '',
        sale_price: '',
        note: '',
    });
    itemQuery.value = '';
    itemResults.value = [];
}

function removeItem(index) { form.items.splice(index, 1); }

// Approvers
const approverSearch = ref('');
const approverResults = ref([]);
const selectedApprovers = ref([]);

const searchApprovers = debounce(async () => {
    try {
        const { data } = await axios.get('/asset-disposal/approvers', { params: { search: approverSearch.value } });
        approverResults.value = data.users || [];
    } catch (e) { console.error(e); }
}, 350);

watch(approverSearch, searchApprovers);

function toggleApprover(user) {
    const idx = selectedApprovers.value.findIndex(a => a.id === user.id);
    if (idx >= 0) selectedApprovers.value.splice(idx, 1);
    else selectedApprovers.value.push(user);
}

function isApproverSelected(userId) { return selectedApprovers.value.some(a => a.id === userId); }

function moveApprover(idx, dir) {
    const newIdx = idx + dir;
    if (newIdx < 0 || newIdx >= selectedApprovers.value.length) return;
    const tmp = selectedApprovers.value[idx];
    selectedApprovers.value[idx] = selectedApprovers.value[newIdx];
    selectedApprovers.value[newIdx] = tmp;
    selectedApprovers.value = [...selectedApprovers.value];
}

function formatRupiah(val) {
    if (!val) return '';
    return new Intl.NumberFormat('id-ID').format(val);
}

const totalSalePrice = computed(() => {
    if (form.type !== 'sold') return 0;
    return form.items.reduce((sum, i) => sum + ((i.sale_price || 0) * (i.qty || 0)), 0);
});

function submitForm() {
    if (!form.outlet_id) return Swal.fire('Error', 'Pilih outlet.', 'error');
    if (!form.warehouse_outlet_id) return Swal.fire('Error', 'Pilih warehouse outlet.', 'error');
    if (!form.description.trim()) return Swal.fire('Error', 'Deskripsi wajib diisi.', 'error');
    if (!form.items.length) return Swal.fire('Error', 'Tambahkan minimal 1 item.', 'error');
    for (const item of form.items) {
        if (!item.qty || item.qty <= 0) return Swal.fire('Error', `Qty untuk ${item.item_name} harus > 0.`, 'error');
    }
    if (!selectedApprovers.value.length) return Swal.fire('Error', 'Pilih minimal 1 approver.', 'error');

    form.approvers = selectedApprovers.value.map(a => a.id);
    form.photo_paths = photos.value.map(p => p.path);

    form.post('/asset-disposals', {
        onSuccess: () => Swal.fire('Berhasil', 'Disposal berhasil dibuat.', 'success'),
        onError: (errors) => {
            const msg = Object.values(errors).flat().join('\n');
            Swal.fire('Error', msg || 'Gagal membuat disposal.', 'error');
        }
    });
}
</script>

<template>
    <AppLayout>
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="flex items-center gap-3 mb-6">
                <button @click="router.visit('/asset-disposals')" class="text-teal-600 hover:text-teal-800">
                    <i class="fa-solid fa-arrow-left text-lg"></i>
                </button>
                <h1 class="text-2xl font-bold text-teal-700">Buat Asset Disposal</h1>
            </div>

            <!-- Type Toggle -->
            <div class="bg-white rounded-xl shadow p-6 mb-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4"><i class="fa-solid fa-dumpster text-teal-500 mr-2"></i>Tipe Disposal</h2>
                <div class="flex gap-4">
                    <button @click="form.type = 'discard'"
                        :class="form.type === 'discard' ? 'bg-gray-700 text-white ring-2 ring-gray-400' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                        class="flex-1 py-4 rounded-xl font-bold text-lg transition text-center">
                        <i class="fa-solid fa-trash-can mr-2"></i> Dibuang
                    </button>
                    <button @click="form.type = 'sold'"
                        :class="form.type === 'sold' ? 'bg-blue-600 text-white ring-2 ring-blue-300' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                        class="flex-1 py-4 rounded-xl font-bold text-lg transition text-center">
                        <i class="fa-solid fa-hand-holding-dollar mr-2"></i> Dijual
                    </button>
                </div>
            </div>

            <!-- Location & Detail -->
            <div class="bg-white rounded-xl shadow p-6 mb-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4"><i class="fa-solid fa-building text-teal-500 mr-2"></i>Lokasi & Detail</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Outlet</label>
                        <select v-if="isHO" v-model="form.outlet_id" class="w-full rounded-lg border-gray-300 text-sm focus:ring-teal-500 focus:border-teal-500">
                            <option value="">Pilih Outlet</option>
                            <option v-for="o in outlets" :key="o.id_outlet" :value="o.id_outlet">{{ o.nama_outlet }}</option>
                        </select>
                        <input v-else type="text" :value="outlets.find(o => o.id_outlet == user.id_outlet)?.nama_outlet" disabled class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Warehouse Outlet</label>
                        <select v-model="form.warehouse_outlet_id" class="w-full rounded-lg border-gray-300 text-sm focus:ring-teal-500 focus:border-teal-500">
                            <option value="">Pilih Warehouse</option>
                            <option v-for="w in warehouseOptions" :key="w.id" :value="w.id">{{ w.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Tanggal</label>
                        <input v-model="form.date" type="date" class="w-full rounded-lg border-gray-300 text-sm focus:ring-teal-500 focus:border-teal-500" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Deskripsi <span class="text-red-500">*</span></label>
                        <textarea v-model="form.description" rows="2" class="w-full rounded-lg border-gray-300 text-sm focus:ring-teal-500 focus:border-teal-500" placeholder="Alasan disposal (wajib)..."></textarea>
                    </div>
                </div>

                <!-- Buyer info for sold type -->
                <div v-if="form.type === 'sold'" class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6 pt-4 border-t border-blue-100">
                    <div>
                        <label class="block text-xs font-medium text-blue-600 mb-1"><i class="fa-solid fa-user mr-1"></i>Nama Pembeli</label>
                        <input v-model="form.buyer_name" type="text" class="w-full rounded-lg border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500" placeholder="Nama pembeli..." />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-blue-600 mb-1"><i class="fa-solid fa-phone mr-1"></i>Kontak Pembeli</label>
                        <input v-model="form.buyer_contact" type="text" class="w-full rounded-lg border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500" placeholder="No. HP / email..." />
                    </div>
                </div>
            </div>

            <!-- Item Selection -->
            <div class="bg-white rounded-xl shadow p-6 mb-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4"><i class="fa-solid fa-boxes-stacked text-teal-500 mr-2"></i>Item Disposal</h2>
                <div class="relative mb-4">
                    <input v-model="itemQuery" type="text" placeholder="Cari item asset (nama / SKU)..."
                        :disabled="!form.warehouse_outlet_id"
                        class="w-full rounded-lg border-gray-300 text-sm focus:ring-teal-500 focus:border-teal-500 pl-10" />
                    <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    <div v-if="itemResults.length" class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-auto">
                        <div v-for="item in itemResults" :key="item.id" @click="addItem(item)" class="px-4 py-2.5 hover:bg-teal-50 cursor-pointer border-b last:border-0">
                            <div class="flex justify-between">
                                <div>
                                    <span class="font-medium text-sm text-gray-800">{{ item.name }}</span>
                                    <span class="text-xs text-gray-400 ml-2">{{ item.sku }}</span>
                                </div>
                                <span class="text-xs text-gray-500">Stok: {{ item.stock_small }} {{ item.unit_small }}</span>
                            </div>
                            <div class="text-xs text-gray-400">{{ item.category_name }}</div>
                        </div>
                    </div>
                </div>
                <div v-if="!form.warehouse_outlet_id" class="text-sm text-amber-600 mb-3">
                    <i class="fa-solid fa-info-circle mr-1"></i> Pilih warehouse outlet terlebih dahulu.
                </div>
                <div class="overflow-x-auto">
                    <table v-if="form.items.length" class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500">#</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500">Item</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500">Stok</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500">Unit</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500">Qty</th>
                                <th v-if="form.type === 'sold'" class="px-3 py-2 text-left text-xs font-semibold text-gray-500">Harga Jual</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500">Catatan</th>
                                <th class="px-3 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="(item, idx) in form.items" :key="idx">
                                <td class="px-3 py-2 text-sm text-gray-500">{{ idx + 1 }}</td>
                                <td class="px-3 py-2">
                                    <span class="text-sm font-medium text-gray-800">{{ item.item_name }}</span>
                                    <span class="text-xs text-gray-400 ml-1">{{ item.sku }}</span>
                                </td>
                                <td class="px-3 py-2 text-sm text-gray-600">{{ item.stock_small }}</td>
                                <td class="px-3 py-2">
                                    <select v-model="item.selected_unit" class="rounded border-gray-300 text-sm focus:ring-teal-500 focus:border-teal-500">
                                        <option v-for="u in item.available_units" :key="u" :value="u">{{ u }}</option>
                                    </select>
                                </td>
                                <td class="px-3 py-2">
                                    <input v-model.number="item.qty" type="number" min="0.01" step="0.01" class="w-24 rounded border-gray-300 text-sm focus:ring-teal-500 focus:border-teal-500" />
                                </td>
                                <td v-if="form.type === 'sold'" class="px-3 py-2">
                                    <input v-model.number="item.sale_price" type="number" min="0" step="100" placeholder="Rp" class="w-32 rounded border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500" />
                                </td>
                                <td class="px-3 py-2">
                                    <input v-model="item.note" type="text" placeholder="..." class="w-32 rounded border-gray-300 text-sm focus:ring-teal-500 focus:border-teal-500" />
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <button @click="removeItem(idx)" class="text-red-400 hover:text-red-600"><i class="fa-solid fa-times"></i></button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-else class="text-sm text-gray-400 text-center py-4">Belum ada item ditambahkan.</p>
                </div>
                <div v-if="form.type === 'sold' && form.items.length" class="mt-4 text-right">
                    <span class="text-sm text-gray-500">Total Harga Jual:</span>
                    <span class="ml-2 text-lg font-bold text-blue-700">Rp {{ formatRupiah(totalSalePrice) }}</span>
                </div>
            </div>

            <!-- Photo Documentation -->
            <div class="bg-white rounded-xl shadow p-6 mb-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4"><i class="fa-solid fa-camera text-teal-500 mr-2"></i>Dokumentasi Foto</h2>
                <div class="flex gap-3 mb-4">
                    <button @click="triggerFileUpload" :disabled="uploadingPhoto"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-teal-50 text-teal-700 rounded-lg border border-teal-200 hover:bg-teal-100 text-sm font-medium transition disabled:opacity-50">
                        <i class="fa-solid fa-upload"></i> Upload Foto
                    </button>
                    <button @click="triggerCamera" :disabled="uploadingPhoto"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-teal-50 text-teal-700 rounded-lg border border-teal-200 hover:bg-teal-100 text-sm font-medium transition disabled:opacity-50">
                        <i class="fa-solid fa-camera"></i> Ambil Foto
                    </button>
                    <span v-if="uploadingPhoto" class="text-sm text-gray-400 self-center"><i class="fa-solid fa-spinner animate-spin mr-1"></i> Uploading...</span>
                </div>
                <input ref="fileInput" type="file" accept="image/*" multiple class="hidden" @change="handleFileSelect" />
                <input ref="cameraInput" type="file" accept="image/*" capture="environment" class="hidden" @change="handleFileSelect" />

                <div v-if="photos.length" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                    <div v-for="(photo, idx) in photos" :key="idx" class="relative group">
                        <img :src="photo.url" @click="openLightbox(idx)"
                            class="w-full h-36 object-cover rounded-lg cursor-pointer border border-gray-200 hover:border-teal-400 transition" />
                        <button @click="removePhoto(idx)"
                            class="absolute top-1 right-1 w-6 h-6 bg-red-500 text-white rounded-full flex items-center justify-center text-xs opacity-0 group-hover:opacity-100 transition hover:bg-red-600">
                            <i class="fa-solid fa-times"></i>
                        </button>
                    </div>
                </div>
                <p v-else class="text-sm text-gray-400 text-center py-3">Belum ada foto ditambahkan.</p>
            </div>

            <!-- Lightbox Modal -->
            <Teleport to="body">
                <div v-if="lightboxOpen" class="fixed inset-0 z-[9999] bg-black/90 flex items-center justify-center" @click.self="closeLightbox">
                    <button @click="closeLightbox" class="absolute top-4 right-4 text-white text-3xl hover:text-gray-300 z-10">&times;</button>
                    <button v-if="lightboxIndex > 0" @click="prevPhoto" class="absolute left-4 text-white text-4xl hover:text-gray-300"><i class="fa-solid fa-chevron-left"></i></button>
                    <img :src="photos[lightboxIndex]?.url" class="max-h-[90vh] max-w-[90vw] object-contain rounded-lg shadow-2xl" />
                    <button v-if="lightboxIndex < photos.length - 1" @click="nextPhoto" class="absolute right-4 text-white text-4xl hover:text-gray-300"><i class="fa-solid fa-chevron-right"></i></button>
                    <div class="absolute bottom-4 text-white text-sm">{{ lightboxIndex + 1 }} / {{ photos.length }}</div>
                </div>
            </Teleport>

            <!-- Approver Selection -->
            <div class="bg-white rounded-xl shadow p-6 mb-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4"><i class="fa-solid fa-user-check text-teal-500 mr-2"></i>Approver <span class="text-red-500">*</span></h2>
                <div v-if="selectedApprovers.length" class="flex flex-wrap gap-2 mb-4">
                    <div v-for="(a, idx) in selectedApprovers" :key="a.id" class="flex items-center gap-1.5 bg-teal-50 border border-teal-200 rounded-full px-3 py-1 text-sm">
                        <span class="bg-teal-600 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs font-bold">{{ idx + 1 }}</span>
                        <span class="text-teal-800 font-medium">{{ a.name }}</span>
                        <span class="text-teal-500 text-xs">({{ a.jabatan || '-' }})</span>
                        <div class="flex gap-0.5 ml-1">
                            <button @click="moveApprover(idx, -1)" :disabled="idx === 0" class="text-teal-400 hover:text-teal-600 disabled:opacity-30"><i class="fa-solid fa-chevron-up text-xs"></i></button>
                            <button @click="moveApprover(idx, 1)" :disabled="idx === selectedApprovers.length - 1" class="text-teal-400 hover:text-teal-600 disabled:opacity-30"><i class="fa-solid fa-chevron-down text-xs"></i></button>
                        </div>
                        <button @click="toggleApprover(a)" class="text-red-400 hover:text-red-600 ml-1"><i class="fa-solid fa-times text-xs"></i></button>
                    </div>
                </div>
                <input v-model="approverSearch" type="text" placeholder="Cari approver (nama / jabatan)..."
                    class="w-full rounded-lg border-gray-300 text-sm focus:ring-teal-500 focus:border-teal-500 mb-3" />
                <div v-if="approverResults.length" class="border rounded-lg divide-y max-h-48 overflow-auto">
                    <div v-for="u in approverResults" :key="u.id" @click="toggleApprover(u)"
                        :class="isApproverSelected(u.id) ? 'bg-teal-50' : 'hover:bg-gray-50'"
                        class="px-4 py-2 flex items-center justify-between cursor-pointer">
                        <div>
                            <span class="text-sm font-medium">{{ u.name }}</span>
                            <span class="text-xs text-gray-400 ml-2">{{ u.jabatan || '-' }}</span>
                        </div>
                        <i v-if="isApproverSelected(u.id)" class="fa-solid fa-check text-teal-600"></i>
                    </div>
                </div>
                <p class="text-xs text-gray-400 mt-2">Urutan approver menentukan level approval (1 = pertama).</p>
            </div>

            <!-- Submit -->
            <div class="flex justify-end gap-3">
                <button @click="router.visit('/asset-disposals')"
                    class="px-5 py-2.5 rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-50 text-sm font-medium">
                    Batal
                </button>
                <button @click="submitForm" :disabled="form.processing"
                    class="px-6 py-2.5 rounded-lg bg-teal-600 hover:bg-teal-700 text-white font-semibold text-sm shadow disabled:opacity-50 transition">
                    <i class="fa-solid fa-paper-plane mr-1"></i> Simpan Disposal
                </button>
            </div>
        </div>
    </AppLayout>
</template>
