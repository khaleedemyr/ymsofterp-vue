<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed, watch, onMounted } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    outlets: Array,
    warehouseOutlets: Array,
    user: Object,
    prefill: Object,
    tableReady: { type: Boolean, default: true },
});

const ownerOutletId = ref(props.prefill?.owner_outlet_id || (props.user?.id_outlet != 1 ? String(props.user?.id_outlet) : ''));
const warehouseId = ref(props.prefill?.warehouse_outlet_id || '');
const itemSearch = ref('');
const items = ref([]);
const loadingItems = ref(false);
const selectedItem = ref(null);
const previewSerial = ref('');

const form = useForm({
    inventory_item_id: '',
    owner_outlet_id: '',
    warehouse_outlet_id: '',
    serial_number: '',
    tag_uid: '',
    notes: '',
    unit_level: 'small',
});

const filteredWarehouses = computed(() => {
    const all = Array.isArray(props.warehouseOutlets) ? props.warehouseOutlets : [];
    if (!ownerOutletId.value) return all;
    return all.filter(w => String(w.outlet_id) === String(ownerOutletId.value));
});

async function loadItems() {
    if (!ownerOutletId.value) {
        items.value = [];
        return;
    }
    loadingItems.value = true;
    try {
        const params = { owner_outlet_id: ownerOutletId.value };
        if (warehouseId.value) params.warehouse_outlet_id = warehouseId.value;
        if (itemSearch.value) params.search = itemSearch.value;
        const { data } = await axios.get('/asset-serials/items-with-stock', { params });
        items.value = data.success ? (data.items || []) : [];
    } catch {
        items.value = [];
    } finally {
        loadingItems.value = false;
    }
}

function selectItem(item) {
    selectedItem.value = item;
    form.inventory_item_id = item.inventory_item_id;
    form.owner_outlet_id = item.owner_outlet_id;
    form.warehouse_outlet_id = item.warehouse_outlet_id;
    previewSerial.value = '';
    form.serial_number = '';
}

function generatePreview() {
    const d = new Date();
    const ymd = d.toISOString().slice(0, 10).replace(/-/g, '');
    const rand = Math.random().toString(16).slice(2, 8).toUpperCase();
    previewSerial.value = `AST-${ymd}-${rand}`;
    form.serial_number = previewSerial.value;
}

function submit() {
    if (!selectedItem.value) return;
    if (!form.serial_number) generatePreview();
    form.post('/asset-serials');
}

watch([ownerOutletId, warehouseId], () => {
    selectedItem.value = null;
    loadItems();
});

watch(itemSearch, () => loadItems());

onMounted(() => {
    if (ownerOutletId.value) loadItems();
    if (props.prefill?.inventory_item_id && items.value.length) {
        const found = items.value.find(i => String(i.inventory_item_id) === String(props.prefill.inventory_item_id));
        if (found) selectItem(found);
    }
});
</script>

<template>
    <AppLayout>
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="mb-6">
                <Link href="/asset-serials" class="text-teal-600 hover:underline text-sm">
                    <i class="fa-solid fa-arrow-left mr-1"></i> Kembali ke Daftar
                </Link>
            </div>

            <h1 class="text-2xl font-bold text-teal-700 mb-2">Daftarkan Nomor Seri</h1>
            <p class="text-sm text-gray-500 mb-6">Registrasi manual untuk stok lama — tanpa NFC. Pilih barang yang punya stok, lalu generate nomor seri.</p>

            <div v-if="!tableReady" class="bg-yellow-50 border border-yellow-200 rounded-xl p-5 text-yellow-800 text-sm mb-6">
                Jalankan <code class="bg-yellow-100 px-1 rounded">database/sql/asset_serial_mode.sql</code> terlebih dahulu.
            </div>

            <template v-else>
                <div class="bg-white rounded-xl shadow p-5 mb-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div v-if="user.id_outlet == 1">
                        <label class="block text-xs font-medium text-gray-500 mb-1">Pemilik</label>
                        <select v-model="ownerOutletId"
                            class="w-full rounded-lg border-gray-300 text-sm focus:ring-teal-500 focus:border-teal-500">
                            <option value="">Pilih Pemilik</option>
                            <option v-for="o in outlets" :key="o.id_outlet" :value="String(o.id_outlet)">{{ o.nama_outlet }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Warehouse</label>
                        <select v-model="warehouseId"
                            class="w-full rounded-lg border-gray-300 text-sm focus:ring-teal-500 focus:border-teal-500">
                            <option value="">Semua Warehouse</option>
                            <option v-for="w in filteredWarehouses" :key="w.id" :value="String(w.id)">{{ w.name }}</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-medium text-gray-500 mb-1">Cari Barang</label>
                        <input v-model="itemSearch" type="text" placeholder="Nama barang..."
                            class="w-full rounded-lg border-gray-300 text-sm focus:ring-teal-500 focus:border-teal-500" />
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow mb-6 overflow-hidden">
                    <div class="px-5 py-3 border-b bg-gray-50 font-semibold text-sm text-gray-700">Pilih Barang (stok tersedia)</div>
                    <div v-if="loadingItems" class="p-8 text-center text-gray-400">Memuat...</div>
                    <div v-else-if="!items.length" class="p-8 text-center text-gray-400">Tidak ada stok yang bisa didaftarkan serialnya.</div>
                    <div v-else class="divide-y max-h-64 overflow-y-auto">
                        <button v-for="item in items" :key="`${item.inventory_item_id}-${item.warehouse_outlet_id}`"
                            type="button"
                            class="w-full text-left px-5 py-3 hover:bg-teal-50 transition"
                            :class="selectedItem?.inventory_item_id === item.inventory_item_id && selectedItem?.warehouse_outlet_id === item.warehouse_outlet_id ? 'bg-teal-50 border-l-4 border-teal-500' : ''"
                            @click="selectItem(item)">
                            <div class="font-medium text-gray-800">{{ item.item_name }}</div>
                            <div class="text-xs text-gray-500 mt-0.5">
                                {{ item.warehouse_name }} · Stok: {{ item.stock_qty }} · Sudah: {{ item.tagged_qty }} · Sisa: {{ item.remaining_qty }}
                                <span v-if="item.track_serial" class="ml-2 text-teal-600">[track serial]</span>
                            </div>
                        </button>
                    </div>
                </div>

                <form v-if="selectedItem" @submit.prevent="submit" class="bg-white rounded-xl shadow p-6 space-y-4">
                    <div class="p-4 bg-teal-50 rounded-lg text-sm">
                        <div class="font-semibold text-teal-800">{{ selectedItem.item_name }}</div>
                        <div class="text-teal-700">{{ selectedItem.warehouse_name }} — sisa {{ selectedItem.remaining_qty }} unit</div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Nomor Seri</label>
                            <div class="flex gap-2">
                                <input v-model="form.serial_number" type="text" placeholder="Auto-generate jika kosong"
                                    class="flex-1 rounded-lg border-gray-300 text-sm font-mono focus:ring-teal-500 focus:border-teal-500" />
                                <button type="button" @click="generatePreview"
                                    class="px-3 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-xs font-medium">Generate</button>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">UID Tag RFID <span class="text-gray-400">(opsional)</span></label>
                            <input v-model="form.tag_uid" type="text" placeholder="Isi jika sudah punya UID dari reader"
                                class="w-full rounded-lg border-gray-300 text-sm font-mono focus:ring-teal-500 focus:border-teal-500" />
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-medium text-gray-500 mb-1">Catatan</label>
                            <textarea v-model="form.notes" rows="2"
                                class="w-full rounded-lg border-gray-300 text-sm focus:ring-teal-500 focus:border-teal-500" />
                        </div>
                    </div>

                    <div v-if="form.errors.message" class="text-red-600 text-sm">{{ form.errors.message }}</div>

                    <div class="flex gap-3 pt-2">
                        <button type="submit" :disabled="form.processing"
                            class="bg-teal-600 hover:bg-teal-700 disabled:opacity-50 text-white px-6 py-2.5 rounded-lg font-semibold text-sm">
                            Simpan Nomor Seri
                        </button>
                        <Link href="/asset-serials" class="px-6 py-2.5 rounded-lg border text-sm text-gray-600 hover:bg-gray-50">Batal</Link>
                    </div>
                </form>
            </template>
        </div>
    </AppLayout>
</template>
