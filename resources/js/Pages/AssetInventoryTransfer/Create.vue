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
    owner_outlet_id: props.user.id_outlet == 1 ? '' : props.user.id_outlet,
    transfer_date: new Date().toISOString().slice(0, 10),
    warehouse_outlet_from_id: '',
    warehouse_outlet_to_id: '',
    notes: '',
    items: [],
    approvers: [],
});

const outletFromId = ref(props.user.id_outlet == 1 ? '' : props.user.id_outlet);
const outletToId = ref('');

const isHO = computed(() => props.user.id_outlet == 1);

const warehouseFromOptions = computed(() =>
    props.warehouseOutlets.filter(w => outletFromId.value && w.outlet_id == outletFromId.value)
);
const warehouseToOptions = computed(() =>
    props.warehouseOutlets.filter(w => outletToId.value && w.outlet_id == outletToId.value)
);

watch(outletFromId, () => { form.warehouse_outlet_from_id = ''; });
watch(outletToId, () => { form.warehouse_outlet_to_id = ''; });

// Item search
const itemQuery = ref('');
const itemResults = ref([]);
const isSearching = ref(false);

const searchItems = debounce(async () => {
    if (!itemQuery.value || itemQuery.value.length < 2 || !form.warehouse_outlet_from_id) return;
    isSearching.value = true;
    try {
        const { data } = await axios.get('/items/search-for-asset-transfer', {
            params: {
                q: itemQuery.value,
                owner_outlet_id: form.owner_outlet_id,
                warehouse_outlet_id: form.warehouse_outlet_from_id,
            }
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
    form.items.push({
        item_id: item.id,
        item_name: item.name,
        sku: item.sku,
        unit_id: item.small_unit_id,
        unit_name: item.unit_small || '-',
        stock_small: item.stock_small || 0,
        qty: '',
        note: '',
    });
    itemQuery.value = '';
    itemResults.value = [];
}

function removeItem(index) {
    form.items.splice(index, 1);
}

// Approvers
const approverSearch = ref('');
const approverResults = ref([]);
const selectedApprovers = ref([]);

const searchApprovers = debounce(async () => {
    try {
        const { data } = await axios.get('/asset-inventory-transfer/approvers', {
            params: { search: approverSearch.value }
        });
        approverResults.value = data.users || [];
    } catch (e) { console.error(e); }
}, 350);

watch(approverSearch, searchApprovers);

function toggleApprover(user) {
    const idx = selectedApprovers.value.findIndex(a => a.id === user.id);
    if (idx >= 0) {
        selectedApprovers.value.splice(idx, 1);
    } else {
        selectedApprovers.value.push(user);
    }
}

function isApproverSelected(userId) {
    return selectedApprovers.value.some(a => a.id === userId);
}

function moveApprover(idx, dir) {
    const newIdx = idx + dir;
    if (newIdx < 0 || newIdx >= selectedApprovers.value.length) return;
    const tmp = selectedApprovers.value[idx];
    selectedApprovers.value[idx] = selectedApprovers.value[newIdx];
    selectedApprovers.value[newIdx] = tmp;
    selectedApprovers.value = [...selectedApprovers.value];
}

function submitForm() {
    if (!form.owner_outlet_id) {
        Swal.fire('Error', 'Pilih outlet pemilik stok.', 'error');
        return;
    }
    if (!form.warehouse_outlet_from_id || !form.warehouse_outlet_to_id) {
        Swal.fire('Error', 'Pilih warehouse outlet asal dan tujuan.', 'error');
        return;
    }
    if (form.warehouse_outlet_from_id === form.warehouse_outlet_to_id) {
        Swal.fire('Error', 'Warehouse asal dan tujuan tidak boleh sama.', 'error');
        return;
    }
    if (!form.items.length) {
        Swal.fire('Error', 'Tambahkan minimal 1 item.', 'error');
        return;
    }
    for (const item of form.items) {
        if (!item.qty || item.qty <= 0) {
            Swal.fire('Error', `Qty untuk ${item.item_name} harus > 0.`, 'error');
            return;
        }
        if (item.qty > item.stock_small) {
            Swal.fire('Error', `Qty untuk ${item.item_name} melebihi stok (${item.stock_small}).`, 'error');
            return;
        }
    }

    form.approvers = selectedApprovers.value.map(a => a.id);

    form.post('/asset-inventory-transfers', {
        onSuccess: () => {
            Swal.fire('Berhasil', 'Transfer asset berhasil dibuat.', 'success');
        },
        onError: (errors) => {
            const msg = Object.values(errors).flat().join('\n');
            Swal.fire('Error', msg || 'Gagal membuat transfer.', 'error');
        }
    });
}
</script>

<template>
    <AppLayout>
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="flex items-center gap-3 mb-6">
                <button @click="router.visit('/asset-inventory-transfers')" class="text-teal-600 hover:text-teal-800">
                    <i class="fa-solid fa-arrow-left text-lg"></i>
                </button>
                <h1 class="text-2xl font-bold text-teal-700">Buat Transfer Asset Baru</h1>
            </div>

            <!-- Owner -->
            <div class="bg-white rounded-xl shadow p-6 mb-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4"><i class="fa-solid fa-store text-teal-500 mr-2"></i>Pemilik Stok</h2>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Outlet Pemilik</label>
                    <select v-if="isHO" v-model="form.owner_outlet_id"
                        class="w-full rounded-lg border-gray-300 text-sm focus:ring-teal-500 focus:border-teal-500">
                        <option value="">Pilih Outlet Pemilik</option>
                        <option v-for="o in outlets" :key="o.id_outlet" :value="o.id_outlet">{{ o.nama_outlet }}</option>
                    </select>
                    <input v-else type="text" :value="outlets.find(o => o.id_outlet == user.id_outlet)?.nama_outlet" disabled
                        class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm" />
                    <p class="text-xs text-gray-500 mt-1">Kepemilikan tetap saat pindah lokasi gudang/outlet.</p>
                </div>
            </div>

            <!-- From / To Selection -->
            <div class="bg-white rounded-xl shadow p-6 mb-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4"><i class="fa-solid fa-route text-teal-500 mr-2"></i>Lokasi Asal & Tujuan</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-3">
                        <h3 class="text-sm font-semibold text-gray-500 uppercase">Dari</h3>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Outlet Lokasi Asal</label>
                            <select v-if="isHO" v-model="outletFromId"
                                class="w-full rounded-lg border-gray-300 text-sm focus:ring-teal-500 focus:border-teal-500">
                                <option value="">Pilih Outlet</option>
                                <option v-for="o in outlets" :key="o.id_outlet" :value="o.id_outlet">{{ o.nama_outlet }}</option>
                            </select>
                            <input v-else type="text" :value="outlets.find(o => o.id_outlet == user.id_outlet)?.nama_outlet" disabled
                                class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Warehouse Asal</label>
                            <select v-model="form.warehouse_outlet_from_id"
                                class="w-full rounded-lg border-gray-300 text-sm focus:ring-teal-500 focus:border-teal-500">
                                <option value="">Pilih Warehouse</option>
                                <option v-for="w in warehouseFromOptions" :key="w.id" :value="w.id">{{ w.name }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="space-y-3">
                        <h3 class="text-sm font-semibold text-gray-500 uppercase">Ke</h3>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Outlet Lokasi Tujuan</label>
                            <select v-model="outletToId"
                                class="w-full rounded-lg border-gray-300 text-sm focus:ring-teal-500 focus:border-teal-500">
                                <option value="">Pilih Outlet</option>
                                <option v-for="o in outlets" :key="o.id_outlet" :value="o.id_outlet">{{ o.nama_outlet }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Warehouse Tujuan</label>
                            <select v-model="form.warehouse_outlet_to_id"
                                class="w-full rounded-lg border-gray-300 text-sm focus:ring-teal-500 focus:border-teal-500">
                                <option value="">Pilih Warehouse</option>
                                <option v-for="w in warehouseToOptions" :key="w.id" :value="w.id">{{ w.name }}</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-5">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Tanggal Transfer</label>
                        <input v-model="form.transfer_date" type="date"
                            class="w-full rounded-lg border-gray-300 text-sm focus:ring-teal-500 focus:border-teal-500" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Catatan</label>
                        <textarea v-model="form.notes" rows="2"
                            class="w-full rounded-lg border-gray-300 text-sm focus:ring-teal-500 focus:border-teal-500"
                            placeholder="Catatan transfer (opsional)"></textarea>
                    </div>
                </div>
            </div>

            <!-- Item Selection -->
            <div class="bg-white rounded-xl shadow p-6 mb-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4"><i class="fa-solid fa-boxes-stacked text-teal-500 mr-2"></i>Item Transfer</h2>
                <div class="relative mb-4">
                    <input v-model="itemQuery" type="text" placeholder="Cari item asset (nama / SKU)..."
                        :disabled="!form.warehouse_outlet_from_id"
                        class="w-full rounded-lg border-gray-300 text-sm focus:ring-teal-500 focus:border-teal-500 pl-10" />
                    <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    <div v-if="itemResults.length"
                        class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-auto">
                        <div v-for="item in itemResults" :key="item.id"
                            @click="addItem(item)"
                            class="px-4 py-2.5 hover:bg-teal-50 cursor-pointer border-b last:border-0">
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

                <div v-if="!form.warehouse_outlet_from_id" class="text-sm text-amber-600 mb-3">
                    <i class="fa-solid fa-info-circle mr-1"></i> Pilih warehouse asal terlebih dahulu untuk mencari item.
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
                                <td class="px-3 py-2 text-sm text-gray-600">{{ item.unit_name }}</td>
                                <td class="px-3 py-2">
                                    <input v-model.number="item.qty" type="number" min="0.01" :max="item.stock_small" step="0.01"
                                        class="w-24 rounded border-gray-300 text-sm focus:ring-teal-500 focus:border-teal-500" />
                                </td>
                                <td class="px-3 py-2">
                                    <input v-model="item.note" type="text" placeholder="..."
                                        class="w-32 rounded border-gray-300 text-sm focus:ring-teal-500 focus:border-teal-500" />
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <button @click="removeItem(idx)" class="text-red-400 hover:text-red-600">
                                        <i class="fa-solid fa-times"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-else class="text-sm text-gray-400 text-center py-4">Belum ada item ditambahkan.</p>
                </div>
            </div>

            <!-- Approver Selection -->
            <div class="bg-white rounded-xl shadow p-6 mb-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4"><i class="fa-solid fa-user-check text-teal-500 mr-2"></i>Approver</h2>

                <div v-if="selectedApprovers.length" class="flex flex-wrap gap-2 mb-4">
                    <div v-for="(a, idx) in selectedApprovers" :key="a.id"
                        class="flex items-center gap-1.5 bg-teal-50 border border-teal-200 rounded-full px-3 py-1 text-sm">
                        <span class="bg-teal-600 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs font-bold">{{ idx + 1 }}</span>
                        <span class="text-teal-800 font-medium">{{ a.name }}</span>
                        <span class="text-teal-500 text-xs">({{ a.jabatan || '-' }})</span>
                        <div class="flex gap-0.5 ml-1">
                            <button @click="moveApprover(idx, -1)" :disabled="idx === 0" class="text-teal-400 hover:text-teal-600 disabled:opacity-30">
                                <i class="fa-solid fa-chevron-up text-xs"></i>
                            </button>
                            <button @click="moveApprover(idx, 1)" :disabled="idx === selectedApprovers.length - 1" class="text-teal-400 hover:text-teal-600 disabled:opacity-30">
                                <i class="fa-solid fa-chevron-down text-xs"></i>
                            </button>
                        </div>
                        <button @click="toggleApprover(a)" class="text-red-400 hover:text-red-600 ml-1">
                            <i class="fa-solid fa-times text-xs"></i>
                        </button>
                    </div>
                </div>

                <input v-model="approverSearch" type="text" placeholder="Cari approver (nama / jabatan)..."
                    class="w-full rounded-lg border-gray-300 text-sm focus:ring-teal-500 focus:border-teal-500 mb-3" />

                <div v-if="approverResults.length" class="border rounded-lg divide-y max-h-48 overflow-auto">
                    <div v-for="u in approverResults" :key="u.id"
                        @click="toggleApprover(u)"
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
                <button @click="router.visit('/asset-inventory-transfers')"
                    class="px-5 py-2.5 rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-50 text-sm font-medium">
                    Batal
                </button>
                <button @click="submitForm" :disabled="form.processing"
                    class="px-6 py-2.5 rounded-lg bg-teal-600 hover:bg-teal-700 text-white font-semibold text-sm shadow disabled:opacity-50 transition">
                    <i class="fa-solid fa-paper-plane mr-1"></i> Simpan Transfer
                </button>
            </div>
        </div>
    </AppLayout>
</template>
