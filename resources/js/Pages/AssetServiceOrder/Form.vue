<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import debounce from 'lodash/debounce';

const props = defineProps({
    user: Object,
    outlets: Array,
    warehouseOutlets: Array,
});

const isHQ = computed(() => Number(props.user?.id_outlet) === 1);

const form = useForm({
    date: new Date().toISOString().slice(0, 10),
    outlet_id: isHQ.value ? '' : props.user?.id_outlet,
    warehouse_outlet_id: '',
    service_type: 'external',
    supplier_id: '',
    description: '',
    estimated_cost: '',
    items: [],
    approvers: [],
});

const filteredWarehouses = computed(() => {
    if (!form.outlet_id) return [];
    return props.warehouseOutlets.filter(w => Number(w.outlet_id) === Number(form.outlet_id));
});

watch(() => form.outlet_id, () => {
    form.warehouse_outlet_id = '';
    form.items = [];
});

watch(() => form.warehouse_outlet_id, () => {
    form.items = [];
});

const isExternal = computed(() => form.service_type === 'external');

watch(() => form.service_type, (v) => {
    if (v === 'internal') {
        clearSupplier();
    }
});

// ── Supplier search ──
const supplierSearch = ref('');
const supplierResults = ref([]);
const selectedSupplier = ref(null);
const showSupplierDropdown = ref(false);

const searchSuppliers = debounce(async () => {
    if (supplierSearch.value.length < 2) { supplierResults.value = []; return; }
    try {
        const { data } = await axios.get('/api/suppliers', { params: { q: supplierSearch.value } });
        supplierResults.value = data.data || data || [];
        showSupplierDropdown.value = true;
    } catch { supplierResults.value = []; }
}, 300);

watch(supplierSearch, searchSuppliers);

function selectSupplier(s) {
    selectedSupplier.value = s;
    form.supplier_id = s.id;
    supplierSearch.value = s.name;
    showSupplierDropdown.value = false;
    supplierResults.value = [];
}

function clearSupplier() {
    selectedSupplier.value = null;
    form.supplier_id = '';
    supplierSearch.value = '';
}

// ── Item search ──
const itemSearch = ref('');
const itemResults = ref([]);
const showItemDropdown = ref(false);

const searchItems = debounce(async () => {
    if (itemSearch.value.length < 2 || !form.warehouse_outlet_id) { itemResults.value = []; return; }
    try {
        const { data } = await axios.get('/items/search-for-asset-transfer', {
            params: { q: itemSearch.value, warehouse_outlet_id: form.warehouse_outlet_id }
        });
        itemResults.value = data;
        showItemDropdown.value = true;
    } catch { itemResults.value = []; }
}, 300);

watch(itemSearch, searchItems);

function addItem(item) {
    if (form.items.find(i => i.item_id === item.id)) {
        Swal.fire('Info', 'Item sudah ditambahkan.', 'info');
        return;
    }
    const units = [];
    if (item.small_unit_name) units.push(item.small_unit_name);
    if (item.medium_unit_name) units.push(item.medium_unit_name);
    if (item.large_unit_name) units.push(item.large_unit_name);

    form.items.push({
        item_id: item.id,
        name: item.name,
        sku: item.sku || '',
        stock_small: item.stock_qty_small || 0,
        stock_medium: item.stock_qty_medium || 0,
        stock_large: item.stock_qty_large || 0,
        small_unit_name: item.small_unit_name || '',
        medium_unit_name: item.medium_unit_name || '',
        large_unit_name: item.large_unit_name || '',
        units,
        selected_unit: units[0] || '',
        qty_out: 1,
        note: '',
    });
    itemSearch.value = '';
    itemResults.value = [];
    showItemDropdown.value = false;
}

function removeItem(idx) {
    form.items.splice(idx, 1);
}

function getStockDisplay(item) {
    const parts = [];
    if (item.stock_small > 0) parts.push(`${item.stock_small} ${item.small_unit_name}`);
    if (item.stock_medium > 0) parts.push(`${item.stock_medium} ${item.medium_unit_name}`);
    if (item.stock_large > 0) parts.push(`${item.stock_large} ${item.large_unit_name}`);
    return parts.length ? parts.join(' / ') : '0';
}

// ── Approvers ──
const approverSearch = ref('');
const approverResults = ref([]);
const showApproverDropdown = ref(false);
const selectedApprovers = ref([]);

const searchApprovers = debounce(async () => {
    if (approverSearch.value.length < 2) { approverResults.value = []; return; }
    try {
        const { data } = await axios.get('/asset-service-order/approvers', {
            params: { search: approverSearch.value }
        });
        approverResults.value = data.users || [];
        showApproverDropdown.value = true;
    } catch { approverResults.value = []; }
}, 300);

watch(approverSearch, searchApprovers);

function toggleApprover(user) {
    const idx = selectedApprovers.value.findIndex(a => a.id === user.id);
    if (idx >= 0) {
        selectedApprovers.value.splice(idx, 1);
    } else {
        selectedApprovers.value.push({ id: user.id, name: user.name, jabatan: user.jabatan });
    }
    form.approvers = selectedApprovers.value.map(a => a.id);
}

function isApproverSelected(userId) {
    return selectedApprovers.value.some(a => a.id === userId);
}

function moveApprover(idx, dir) {
    const newIdx = idx + dir;
    if (newIdx < 0 || newIdx >= selectedApprovers.value.length) return;
    const arr = [...selectedApprovers.value];
    [arr[idx], arr[newIdx]] = [arr[newIdx], arr[idx]];
    selectedApprovers.value = arr;
    form.approvers = arr.map(a => a.id);
}

function removeApprover(idx) {
    selectedApprovers.value.splice(idx, 1);
    form.approvers = selectedApprovers.value.map(a => a.id);
}

function submit() {
    if (isExternal.value && !form.supplier_id) {
        Swal.fire('Error', 'Pilih supplier (External).', 'error');
        return;
    }
    if (!form.items.length) { Swal.fire('Error', 'Tambahkan minimal 1 item.', 'error'); return; }
    if (!form.approvers.length) { Swal.fire('Error', 'Tambahkan minimal 1 approver.', 'error'); return; }

    form.post('/asset-service-orders', {
        onSuccess: () => Swal.fire('Berhasil', 'Service order berhasil dibuat.', 'success'),
        onError: (errors) => {
            const msg = Object.values(errors).flat().join('\n');
            Swal.fire('Error', msg || 'Gagal menyimpan.', 'error');
        },
    });
}
</script>

<template>
    <AppLayout>
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="flex items-center gap-3 mb-6">
                <button @click="router.visit('/asset-service-orders')"
                    class="text-gray-500 hover:text-teal-700 transition"><i class="fa-solid fa-arrow-left text-lg"></i></button>
                <h1 class="text-2xl font-bold text-teal-700">Buat Service Order Baru</h1>
            </div>

            <div class="bg-white rounded-xl shadow p-6 space-y-6">
                <!-- Outlet & Warehouse -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Outlet <span class="text-red-500">*</span></label>
                        <select v-if="isHQ" v-model="form.outlet_id"
                            class="w-full rounded-lg border-gray-300 text-sm focus:ring-teal-500 focus:border-teal-500">
                            <option value="" disabled>-- Pilih Outlet --</option>
                            <option v-for="o in outlets" :key="o.id_outlet" :value="o.id_outlet">{{ o.nama_outlet }}</option>
                        </select>
                        <input v-else type="text" :value="outlets.find(o => o.id_outlet == user.id_outlet)?.nama_outlet || '-'" readonly
                            class="w-full rounded-lg bg-gray-100 border-gray-300 text-sm" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Warehouse <span class="text-red-500">*</span></label>
                        <select v-model="form.warehouse_outlet_id"
                            class="w-full rounded-lg border-gray-300 text-sm focus:ring-teal-500 focus:border-teal-500">
                            <option value="" disabled>-- Pilih Warehouse --</option>
                            <option v-for="w in filteredWarehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal <span class="text-red-500">*</span></label>
                        <input v-model="form.date" type="date"
                            class="w-full rounded-lg border-gray-300 text-sm focus:ring-teal-500 focus:border-teal-500" />
                    </div>
                </div>

                <!-- Tipe service -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tipe service <span class="text-red-500">*</span></label>
                    <div class="flex flex-wrap gap-4">
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input v-model="form.service_type" type="radio" value="external" class="text-teal-600 focus:ring-teal-500" />
                            <span class="text-sm text-gray-800 font-semibold">External</span>
                        </label>
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input v-model="form.service_type" type="radio" value="internal" class="text-teal-600 focus:ring-teal-500" />
                            <span class="text-sm text-gray-800 font-semibold">Internal</span>
                        </label>
                    </div>
                </div>

                <!-- Supplier -->
                <div v-if="isExternal">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Supplier <span class="text-red-500">*</span></label>
                    <div v-if="selectedSupplier" class="flex items-center gap-2 bg-teal-50 border border-teal-200 rounded-lg px-3 py-2">
                        <span class="text-sm font-medium text-teal-800">{{ selectedSupplier.name }}</span>
                        <button @click="clearSupplier" class="ml-auto text-red-400 hover:text-red-600"><i class="fa-solid fa-xmark"></i></button>
                    </div>
                    <div v-else class="relative">
                        <input v-model="supplierSearch" type="text" placeholder="Cari supplier..."
                            @focus="showSupplierDropdown = supplierResults.length > 0"
                            class="w-full rounded-lg border-gray-300 text-sm focus:ring-teal-500 focus:border-teal-500" />
                        <div v-if="showSupplierDropdown && supplierResults.length"
                            class="absolute z-30 w-full mt-1 bg-white border rounded-lg shadow-lg max-h-48 overflow-y-auto">
                            <button v-for="s in supplierResults" :key="s.id" @click="selectSupplier(s)"
                                class="w-full text-left px-4 py-2 hover:bg-teal-50 text-sm border-b last:border-b-0">
                                <span class="font-medium">{{ s.name }}</span>
                                <span v-if="s.code" class="text-gray-400 ml-2">({{ s.code }})</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Description & Cost -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi / Alasan Service <span class="text-red-500">*</span></label>
                        <textarea v-model="form.description" rows="3" placeholder="Jelaskan alasan service..."
                            class="w-full rounded-lg border-gray-300 text-sm focus:ring-teal-500 focus:border-teal-500"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Estimasi Biaya</label>
                        <input v-model="form.estimated_cost" type="number" step="0.01" min="0" placeholder="0"
                            class="w-full rounded-lg border-gray-300 text-sm focus:ring-teal-500 focus:border-teal-500" />
                    </div>
                </div>

                <!-- Item Search -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tambah Item</label>
                    <div v-if="!form.warehouse_outlet_id" class="text-xs text-gray-400">Pilih warehouse terlebih dahulu.</div>
                    <div v-else class="relative">
                        <input v-model="itemSearch" type="text" placeholder="Cari item asset..."
                            @focus="showItemDropdown = itemResults.length > 0"
                            class="w-full rounded-lg border-gray-300 text-sm focus:ring-teal-500 focus:border-teal-500" />
                        <div v-if="showItemDropdown && itemResults.length"
                            class="absolute z-30 w-full mt-1 bg-white border rounded-lg shadow-lg max-h-56 overflow-y-auto">
                            <button v-for="item in itemResults" :key="item.id" @click="addItem(item)"
                                class="w-full text-left px-4 py-2 hover:bg-teal-50 text-sm border-b last:border-b-0">
                                <div class="font-medium">{{ item.name }}</div>
                                <div class="text-xs text-gray-400">{{ item.sku }} &middot; {{ item.category_name }}</div>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Items table -->
                <div v-if="form.items.length" class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-teal-50">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-teal-700">#</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-teal-700">Item</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-teal-700">Stok</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-teal-700">Unit</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-teal-700">Qty</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-teal-700">Catatan</th>
                                <th class="px-3 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="(item, idx) in form.items" :key="item.item_id">
                                <td class="px-3 py-2 text-sm text-gray-500">{{ idx + 1 }}</td>
                                <td class="px-3 py-2 text-sm font-medium text-gray-800">{{ item.name }}</td>
                                <td class="px-3 py-2 text-xs text-gray-500">{{ getStockDisplay(item) }}</td>
                                <td class="px-3 py-2">
                                    <select v-model="item.selected_unit"
                                        class="rounded border-gray-300 text-xs focus:ring-teal-500 focus:border-teal-500">
                                        <option v-for="u in item.units" :key="u" :value="u">{{ u }}</option>
                                    </select>
                                </td>
                                <td class="px-3 py-2">
                                    <input v-model.number="item.qty_out" type="number" min="0.01" step="0.01"
                                        class="w-20 rounded border-gray-300 text-sm focus:ring-teal-500 focus:border-teal-500" />
                                </td>
                                <td class="px-3 py-2">
                                    <input v-model="item.note" type="text" placeholder="Catatan..."
                                        class="w-full rounded border-gray-300 text-xs focus:ring-teal-500 focus:border-teal-500" />
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <button @click="removeItem(idx)" class="text-red-400 hover:text-red-600"><i class="fa-solid fa-trash-can"></i></button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Approvers -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Approver <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <input v-model="approverSearch" type="text" placeholder="Cari approver..."
                            @focus="showApproverDropdown = approverResults.length > 0"
                            class="w-full rounded-lg border-gray-300 text-sm focus:ring-teal-500 focus:border-teal-500" />
                        <div v-if="showApproverDropdown && approverResults.length"
                            class="absolute z-30 w-full mt-1 bg-white border rounded-lg shadow-lg max-h-48 overflow-y-auto">
                            <button v-for="u in approverResults" :key="u.id" @click="toggleApprover(u)"
                                class="w-full text-left px-4 py-2 hover:bg-teal-50 text-sm border-b last:border-b-0 flex items-center gap-2">
                                <input type="checkbox" :checked="isApproverSelected(u.id)" class="rounded border-gray-300 text-teal-600" readonly />
                                <span>{{ u.name }}</span>
                                <span class="text-xs text-gray-400">{{ u.jabatan }}</span>
                            </button>
                        </div>
                    </div>
                    <div v-if="selectedApprovers.length" class="flex flex-wrap gap-2 mt-3">
                        <div v-for="(a, idx) in selectedApprovers" :key="a.id"
                            class="flex items-center gap-1 bg-teal-50 border border-teal-200 rounded-lg px-3 py-1.5">
                            <span class="bg-teal-600 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs font-bold">{{ idx + 1 }}</span>
                            <span class="text-sm font-medium text-teal-800">{{ a.name }}</span>
                            <span class="text-xs text-gray-400">({{ a.jabatan }})</span>
                            <button @click="moveApprover(idx, -1)" :disabled="idx === 0"
                                class="text-gray-400 hover:text-teal-600 disabled:opacity-30"><i class="fa-solid fa-chevron-up text-xs"></i></button>
                            <button @click="moveApprover(idx, 1)" :disabled="idx === selectedApprovers.length - 1"
                                class="text-gray-400 hover:text-teal-600 disabled:opacity-30"><i class="fa-solid fa-chevron-down text-xs"></i></button>
                            <button @click="removeApprover(idx)" class="text-red-400 hover:text-red-600 ml-1"><i class="fa-solid fa-xmark text-xs"></i></button>
                        </div>
                    </div>
                </div>

                <!-- Submit -->
                <div class="flex justify-end gap-3 pt-4 border-t">
                    <button @click="router.visit('/asset-service-orders')"
                        class="px-5 py-2.5 rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-50 text-sm font-medium">Batal</button>
                    <button @click="submit" :disabled="form.processing"
                        class="px-6 py-2.5 rounded-lg bg-teal-600 hover:bg-teal-700 text-white font-semibold text-sm shadow transition disabled:opacity-50">
                        <i class="fa-solid fa-paper-plane mr-1"></i> Simpan Service Order
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
