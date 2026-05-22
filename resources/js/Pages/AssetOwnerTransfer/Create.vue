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
                <input v-model="itemQuery" :disabled="!form.warehouse_outlet_from_id" placeholder="Cari item..." class="w-full rounded-lg border-gray-300 text-sm mb-2" />
                <div v-if="itemResults.length" class="border rounded-lg max-h-48 overflow-auto mb-3">
                    <div v-for="it in itemResults" :key="it.id" @click="addItem(it)" class="px-3 py-2 hover:bg-violet-50 cursor-pointer text-sm">{{ it.name }} — stok {{ it.stock_small }}</div>
                </div>
                <table v-if="form.items.length" class="min-w-full text-sm">
                    <tr v-for="(it, idx) in form.items" :key="idx" class="border-t">
                        <td class="py-2">{{ it.item_name }}</td>
                        <td class="py-2">{{ it.stock_small }}</td>
                        <td class="py-2"><input v-model.number="it.qty" type="number" min="0.01" :max="it.stock_small" class="w-20 border rounded" /></td>
                        <td class="py-2"><button @click="removeItem(idx)" class="text-red-500"><i class="fa fa-times"></i></button></td>
                    </tr>
                </table>
            </div>

            <div class="bg-white rounded-xl shadow p-6 mb-6">
                <h2 class="font-semibold mb-2">Approver</h2>
                <input v-model="approverSearch" class="w-full rounded-lg border-gray-300 text-sm mb-2" placeholder="Cari approver" />
                <div v-for="u in approverResults" :key="u.id" @click="toggleApprover(u)" class="px-3 py-1 hover:bg-gray-50 cursor-pointer text-sm">{{ u.name }}</div>
                <div class="flex flex-wrap gap-2 mt-2">
                    <span v-for="a in selectedApprovers" :key="a.id" class="bg-violet-100 text-violet-800 px-2 py-1 rounded text-xs">{{ a.name }}</span>
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <button @click="router.visit('/asset-owner-transfers')" class="px-4 py-2 border rounded-lg text-sm">Batal</button>
                <button @click="submitForm" :disabled="form.processing" class="px-6 py-2 bg-violet-600 text-white rounded-lg text-sm font-semibold">Simpan Draft</button>
            </div>
        </div>
    </AppLayout>
</template>
