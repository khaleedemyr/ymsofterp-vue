<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed, watch } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import axios from 'axios';
import Swal from 'sweetalert2';
import debounce from 'lodash/debounce';

const props = defineProps({ user: Object, outlets: Array, warehouseOutlets: Array });

const form = useForm({
    transfer_date: new Date().toISOString().slice(0, 10),
    owner_outlet_from_id: props.user.id_outlet == 1 ? '' : props.user.id_outlet,
    owner_outlet_to_id: '',
    outlet_id: '',
    warehouse_outlet_id: '',
    notes: '',
    items: [],
    approvers: [],
});

const isHO = computed(() => props.user.id_outlet == 1);
const warehouseOptions = computed(() =>
    props.warehouseOutlets.filter(w => form.outlet_id && w.outlet_id == form.outlet_id)
);
watch(() => form.outlet_id, () => { form.warehouse_outlet_id = ''; form.items = []; });

const itemQuery = ref('');
const itemResults = ref([]);
const searchItems = debounce(async () => {
    if (!itemQuery.value || itemQuery.value.length < 2 || !form.owner_outlet_from_id || !form.warehouse_outlet_id) return;
    const { data } = await axios.get('/items/search-for-asset-transfer', {
        params: { q: itemQuery.value, owner_outlet_id: form.owner_outlet_from_id, warehouse_outlet_id: form.warehouse_outlet_id },
    });
    itemResults.value = data;
}, 350);
watch(itemQuery, searchItems);

function addItem(item) {
    if (form.items.find(i => i.item_id === item.id)) return;
    form.items.push({
        item_id: item.id, item_name: item.name, sku: item.sku, unit_id: item.small_unit_id,
        unit_name: item.unit_small || '-', stock_small: item.stock_small || 0, qty: '', note: '',
    });
    itemQuery.value = ''; itemResults.value = [];
}
function removeItem(i) { form.items.splice(i, 1); }

const approverSearch = ref('');
const approverResults = ref([]);
const selectedApprovers = ref([]);
const searchApprovers = debounce(async () => {
    const { data } = await axios.get('/asset-owner-transfer/approvers', { params: { search: approverSearch.value } });
    approverResults.value = data.users || [];
}, 350);
watch(approverSearch, searchApprovers);
function toggleApprover(u) {
    const i = selectedApprovers.value.findIndex(a => a.id === u.id);
    if (i >= 0) selectedApprovers.value.splice(i, 1); else selectedApprovers.value.push(u);
}
function isApproverSelected(id) { return selectedApprovers.value.some(a => a.id === id); }

function removeApprover(idx) {
    selectedApprovers.value.splice(idx, 1);
}

function moveApprover(idx, dir) {
    const newIdx = idx + dir;
    if (newIdx < 0 || newIdx >= selectedApprovers.value.length) return;
    const tmp = selectedApprovers.value[idx];
    selectedApprovers.value[idx] = selectedApprovers.value[newIdx];
    selectedApprovers.value[newIdx] = tmp;
    selectedApprovers.value = [...selectedApprovers.value];
}

function approverMeta(u) {
    const parts = [u.jabatan, u.outlet].filter(Boolean);
    return parts.length ? parts.join(' · ') : '-';
}

function submitForm() {
    if (!form.owner_outlet_from_id || !form.owner_outlet_to_id) return Swal.fire('Error', 'Pilih pemilik asal dan tujuan.', 'error');
    if (form.owner_outlet_from_id === form.owner_outlet_to_id) return Swal.fire('Error', 'Pemilik asal dan tujuan harus berbeda.', 'error');
    if (!form.outlet_id || !form.warehouse_outlet_id) return Swal.fire('Error', 'Pilih lokasi dan gudang.', 'error');
    if (!form.items.length) return Swal.fire('Error', 'Tambahkan minimal 1 item.', 'error');
    form.approvers = selectedApprovers.value.map(a => a.id);
    form.post('/asset-owner-transfers', {
        onSuccess: () => Swal.fire('Berhasil', 'Transfer kepemilikan dibuat.', 'success'),
        onError: (e) => Swal.fire('Error', Object.values(e).flat().join('\n'), 'error'),
    });
}
</script>

<template>
    <AppLayout>
        <div class="max-w-5xl mx-auto px-4 py-8">
            <div class="flex items-center gap-3 mb-6">
                <button @click="router.visit('/asset-owner-transfers')" class="text-violet-600"><i class="fa-solid fa-arrow-left"></i></button>
                <h1 class="text-2xl font-bold text-violet-700">Transfer Kepemilikan</h1>
            </div>
            <p class="text-sm text-gray-500 mb-4">Stok pindah pemilik di gudang yang sama — lokasi fisik tidak berubah.</p>

            <div class="bg-white rounded-xl shadow p-6 mb-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="text-xs text-gray-500">Pemilik Asal *</label>
                    <select v-if="isHO" v-model="form.owner_outlet_from_id" class="w-full rounded-lg border-gray-300 text-sm">
                        <option value="">Pilih</option>
                        <option v-for="o in outlets" :key="o.id_outlet" :value="o.id_outlet">{{ o.nama_outlet }}</option>
                    </select>
                    <input v-else type="text" :value="outlets.find(o => o.id_outlet == user.id_outlet)?.nama_outlet" disabled class="w-full rounded-lg bg-gray-50 text-sm" />
                </div>
                <div>
                    <label class="text-xs text-gray-500">Pemilik Tujuan *</label>
                    <select v-model="form.owner_outlet_to_id" class="w-full rounded-lg border-gray-300 text-sm">
                        <option value="">Pilih</option>
                        <option v-for="o in outlets" :key="o.id_outlet" :value="o.id_outlet" :disabled="o.id_outlet == form.owner_outlet_from_id">{{ o.nama_outlet }}</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs text-gray-500">Outlet Lokasi *</label>
                    <select v-model="form.outlet_id" class="w-full rounded-lg border-gray-300 text-sm">
                        <option value="">Pilih</option>
                        <option v-for="o in outlets" :key="o.id_outlet" :value="o.id_outlet">{{ o.nama_outlet }}</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs text-gray-500">Gudang *</label>
                    <select v-model="form.warehouse_outlet_id" class="w-full rounded-lg border-gray-300 text-sm">
                        <option value="">Pilih</option>
                        <option v-for="w in warehouseOptions" :key="w.id" :value="w.id">{{ w.name }}</option>
                    </select>
                </div>
                <div><label class="text-xs text-gray-500">Tanggal</label><input v-model="form.transfer_date" type="date" class="w-full rounded-lg border-gray-300 text-sm" /></div>
                <div><label class="text-xs text-gray-500">Catatan</label><textarea v-model="form.notes" rows="2" class="w-full rounded-lg border-gray-300 text-sm" /></div>
            </div>

            <div class="bg-white rounded-xl shadow p-6 mb-6">
                <h2 class="font-semibold mb-3">Item</h2>
                <input v-model="itemQuery" :disabled="!form.owner_outlet_from_id || !form.warehouse_outlet_id" placeholder="Cari item..." class="w-full rounded-lg border-gray-300 text-sm mb-2 disabled:bg-gray-50 disabled:cursor-not-allowed" />
                <p v-if="!form.owner_outlet_from_id || !form.warehouse_outlet_id" class="text-sm text-amber-600 mb-2">Pilih pemilik asal dan gudang terlebih dahulu.</p>
                <div v-if="itemResults.length" class="border rounded-lg max-h-48 overflow-auto mb-3">
                    <div v-for="it in itemResults" :key="it.id" @click="addItem(it)" class="px-3 py-2 hover:bg-violet-50 cursor-pointer text-sm border-b last:border-0">
                        <span class="font-medium">{{ it.name }}</span>
                        <span class="text-gray-400 ml-2">{{ it.sku }}</span>
                        <span class="float-right text-gray-500">Stok: {{ it.stock_small }} {{ it.unit_small || '-' }}</span>
                    </div>
                </div>
                <div v-if="form.items.length" class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500">#</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500">Item</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500">Stok</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500">Unit</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500">Qty</th>
                                <th class="px-3 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="(it, idx) in form.items" :key="idx">
                                <td class="px-3 py-2 text-gray-500">{{ idx + 1 }}</td>
                                <td class="px-3 py-2">
                                    <span class="font-medium text-gray-800">{{ it.item_name }}</span>
                                    <span v-if="it.sku" class="text-xs text-gray-400 ml-1">{{ it.sku }}</span>
                                </td>
                                <td class="px-3 py-2 text-gray-600">{{ it.stock_small }}</td>
                                <td class="px-3 py-2 text-gray-600">{{ it.unit_name }}</td>
                                <td class="px-3 py-2">
                                    <input v-model.number="it.qty" type="number" min="0.01" :max="it.stock_small" step="0.01" class="w-24 rounded border-gray-300 text-sm" />
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <button type="button" @click="removeItem(idx)" class="text-red-500 hover:text-red-700"><i class="fa fa-times"></i></button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow p-6 mb-6">
                <h2 class="font-semibold mb-3"><i class="fa-solid fa-user-check text-violet-500 mr-2"></i>Approver</h2>

                <div v-if="selectedApprovers.length" class="space-y-2 mb-4">
                    <div v-for="(a, idx) in selectedApprovers" :key="a.id"
                        class="flex items-center justify-between gap-2 p-3 bg-violet-50 border border-violet-200 rounded-lg">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="bg-violet-600 text-white rounded-full w-6 h-6 flex-shrink-0 flex items-center justify-center text-xs font-bold">{{ idx + 1 }}</span>
                            <div class="min-w-0">
                                <div class="text-sm font-medium text-violet-900 truncate">{{ a.name }}</div>
                                <div class="text-xs text-violet-600 truncate">{{ approverMeta(a) }}</div>
                            </div>
                        </div>
                        <div class="flex items-center gap-1 flex-shrink-0">
                            <button type="button" @click="moveApprover(idx, -1)" :disabled="idx === 0"
                                class="text-violet-400 hover:text-violet-700 disabled:opacity-30 p-1" title="Naikkan urutan">
                                <i class="fa-solid fa-chevron-up text-xs"></i>
                            </button>
                            <button type="button" @click="moveApprover(idx, 1)" :disabled="idx === selectedApprovers.length - 1"
                                class="text-violet-400 hover:text-violet-700 disabled:opacity-30 p-1" title="Turunkan urutan">
                                <i class="fa-solid fa-chevron-down text-xs"></i>
                            </button>
                            <button type="button" @click="removeApprover(idx)" class="text-red-500 hover:text-red-700 p-1 ml-1" title="Hapus">
                                <i class="fa-solid fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <input v-model="approverSearch" type="text" placeholder="Cari approver (nama / jabatan / outlet)..."
                    class="w-full rounded-lg border-gray-300 text-sm focus:ring-violet-500 focus:border-violet-500 mb-2" />

                <div v-if="approverResults.length" class="border rounded-lg divide-y max-h-48 overflow-auto">
                    <div v-for="u in approverResults" :key="u.id" @click="toggleApprover(u)"
                        :class="isApproverSelected(u.id) ? 'bg-violet-50' : 'hover:bg-gray-50'"
                        class="px-4 py-2.5 flex items-center justify-between cursor-pointer">
                        <div class="min-w-0">
                            <div class="text-sm font-medium text-gray-800">{{ u.name }}</div>
                            <div class="text-xs text-gray-500 truncate">{{ approverMeta(u) }}</div>
                        </div>
                        <i v-if="isApproverSelected(u.id)" class="fa-solid fa-check text-violet-600 flex-shrink-0 ml-2"></i>
                    </div>
                </div>
                <p class="text-xs text-gray-400 mt-2">Urutan approver menentukan level approval (1 = pertama). Klik lagi pada nama di daftar untuk membatalkan pilihan.</p>
            </div>

            <div class="flex justify-end gap-2">
                <button @click="router.visit('/asset-owner-transfers')" class="px-4 py-2 border rounded-lg text-sm">Batal</button>
                <button @click="submitForm" :disabled="form.processing" class="px-6 py-2 bg-violet-600 text-white rounded-lg text-sm font-semibold">Simpan Draft</button>
            </div>
        </div>
    </AppLayout>
</template>
