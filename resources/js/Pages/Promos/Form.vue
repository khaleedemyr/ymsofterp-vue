<template>
  <AppLayout>
    <div class="max-w-2xl mx-auto py-8 px-2">
      <h1 class="text-2xl font-bold text-pink-700 mb-6">{{ isEdit ? 'Edit Promo' : 'Tambah Promo' }}</h1>
      <form @submit.prevent="submit" class="space-y-5 bg-white rounded-2xl shadow-xl p-8">
        <div>
          <label class="block text-sm font-medium text-gray-700">Nama Promo</label>
          <input v-model="form.name" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-pink-500 focus:border-pink-500" required maxlength="100" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Tipe Promo</label>
          <select v-model="form.type" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-pink-500 focus:border-pink-500">
            <option value="percent">Diskon Persen</option>
            <option value="nominal">Diskon Nominal</option>
            <option value="bundle">Bundling</option>
            <option value="bogo">Buy 1 Get 1</option>
            <option value="harga_coret">Harga Coret</option>
          </select>
        </div>
        <div v-if="form.type === 'percent' || form.type === 'nominal'">
          <label class="block text-sm font-medium text-gray-700">Value</label>
          <input v-model="form.value" type="number" min="0" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-pink-500 focus:border-pink-500" required />
        </div>
        <div v-if="form.type === 'bundle'">
          <label class="block text-sm font-medium text-gray-700">Harga Paket</label>
          <input v-model="form.value" type="number" min="0" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-pink-500 focus:border-pink-500" required />
        </div>
        <div v-if="form.type === 'bogo'">
          <label class="block text-sm font-medium text-gray-700">Item Promo (Buy)</label>
          <multiselect v-model="form.buy_items" :options="items" :multiple="true" label="name" track-by="id" placeholder="Pilih Item yang Dibeli" />
          <label class="block text-sm font-medium text-gray-700 mt-2">Item Promo (Get)</label>
          <multiselect v-model="form.get_items" :options="items" :multiple="true" label="name" track-by="id" placeholder="Pilih Item yang Didapat" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Minimum Transaksi</label>
          <input v-model="form.min_transaction" type="number" min="0" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-pink-500 focus:border-pink-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Maximum Transaksi</label>
          <input v-model="form.max_transaction" type="number" min="0" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-pink-500 focus:border-pink-500" />
        </div>
        <!-- Pindahkan radio region/outlet dan multiselect ke atas -->
        <div>
          <label class="block text-sm font-medium text-gray-700">Outlet Promo</label>
          <div class="flex gap-6 items-center mb-2">
            <label class="inline-flex items-center">
              <input type="radio" value="region" v-model="outletType" class="form-radio text-pink-600" />
              <span class="ml-2">By Region</span>
            </label>
            <label class="inline-flex items-center">
              <input type="radio" value="outlet" v-model="outletType" class="form-radio text-pink-600" />
              <span class="ml-2">By Outlet</span>
            </label>
          </div>
          <multiselect v-if="outletType === 'region'" v-model="form.regions" :options="regions" :multiple="true" label="name" track-by="id" placeholder="Pilih Region" />
          <multiselect v-if="outletType === 'outlet'" v-model="form.outlets" :options="outlets" :multiple="true" label="name" track-by="id" placeholder="Pilih Outlet" />
        </div>
        <!-- Radio item/kategori di bawahnya -->
        <div class="flex gap-6 items-center mt-4">
          <label class="inline-flex items-center">
            <input type="radio" value="kategori" v-model="byType" class="form-radio text-pink-600" />
            <span class="ml-2">By Kategori</span>
          </label>
          <label class="inline-flex items-center">
            <input type="radio" value="item" v-model="byType" class="form-radio text-pink-600" />
            <span class="ml-2">By Item</span>
          </label>
        </div>
        <!-- Kategori atau Item -->
        <div v-if="byType === 'kategori'">
          <label class="block text-sm font-medium text-gray-700">Kategori Promo</label>
          <multiselect v-model="form.categories" :options="categories" :multiple="true" label="name" track-by="id" placeholder="Pilih Kategori" />
        </div>
        <div v-if="byType === 'item'">
          <label class="block text-sm font-medium text-gray-700">Item Promo</label>
          <multiselect v-model="form.items" :options="items" :multiple="true" label="name" track-by="id" placeholder="Pilih Item" />
        </div>
        <div v-if="form.type === 'harga_coret' && itemPriceRows.length">
          <h3 class="font-bold text-pink-700 mb-2 mt-4">Input Harga Promo per Produk & Outlet/Region</h3>
          <table class="min-w-full divide-y divide-gray-200 mb-4">
            <thead class="bg-pink-50">
              <tr>
                <th class="px-4 py-2 text-left text-xs font-bold text-pink-700">Produk</th>
                <th class="px-4 py-2 text-left text-xs font-bold text-pink-700">Outlet/Region</th>
                <th class="px-4 py-2 text-left text-xs font-bold text-pink-700">Harga Promo</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, idx) in itemPriceRows" :key="row.item_id+'-'+(row.outlet_id||row.region_id)">
                <td class="px-4 py-2">{{ row.item_name }}</td>
                <td class="px-4 py-2">{{ row.outlet_name || row.region_name }}</td>
                <td class="px-4 py-2">
                  <input type="number" min="0" v-model.number="row.new_price" class="border rounded px-2 py-1 w-28" />
                </td>
              </tr>
            </tbody>
          </table>
          <div v-if="itemPriceRows.some(row => !row.new_price)" class="text-red-600 font-bold mb-2">Semua harga promo wajib diisi!</div>
        </div>
        <div class="flex gap-4">
          <div class="flex-1">
            <label class="block text-sm font-medium text-gray-700">Tanggal Mulai</label>
            <input v-model="form.start_date" type="date" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-pink-500 focus:border-pink-500" required />
          </div>
          <div class="flex-1">
            <label class="block text-sm font-medium text-gray-700">Tanggal Akhir</label>
            <input v-model="form.end_date" type="date" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-pink-500 focus:border-pink-500" required />
          </div>
        </div>
        <!-- Settingan Jam -->
        <div class="flex gap-4">
          <div class="flex-1">
            <label class="block text-sm font-medium text-gray-700">Jam Mulai</label>
            <VueTimepicker v-model="form.start_time" format="HH:mm" :is24="true" minute-interval="5" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" />
          </div>
          <div class="flex-1">
            <label class="block text-sm font-medium text-gray-700">Jam Akhir</label>
            <VueTimepicker v-model="form.end_time" format="HH:mm" :is24="true" minute-interval="5" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm" />
          </div>
        </div>
        <!-- Deskripsi -->
        <div>
          <label class="block text-sm font-medium text-gray-700">Deskripsi</label>
          <textarea v-model="form.description" rows="2" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-pink-500 focus:border-pink-500"></textarea>
        </div>
        <!-- Term & Condition -->
        <div>
          <label class="block text-sm font-medium text-gray-700">Term & Condition</label>
          <textarea v-model="form.terms" rows="3" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm"></textarea>
        </div>
        <!-- Banner Promo -->
        <div>
          <label class="block text-sm font-medium text-gray-700">Banner Promo</label>
          <input 
            type="file" 
            @change="onBannerChange" 
            accept="image/jpeg,image/png,image/jpg,image/gif" 
            class="mt-1 block w-full" 
          />
          <img 
            v-if="form.banner_preview || form.banner" 
            :src="form.banner_preview || `/storage/${form.banner}`" 
            class="mt-2 max-h-32 rounded shadow" 
          />
          <p class="mt-1 text-sm text-gray-500">
            Format: JPG, JPEG, PNG, GIF. Maksimal 2MB
          </p>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Perlu Member?</label>
          <label class="inline-flex items-center cursor-pointer">
            <input type="checkbox" v-model="form.need_member" true-value="Yes" false-value="No" class="form-checkbox h-5 w-5 text-pink-600">
            <span class="ml-2">Ya, hanya untuk member</span>
          </label>
        </div>
        <div class="flex justify-end gap-2 mt-8">
          <Link :href="route('promos.index')" class="px-4 py-2 rounded bg-gray-100 text-gray-700 font-semibold hover:bg-gray-200">Batal</Link>
          <button :disabled="loading" class="px-6 py-2 rounded bg-pink-600 text-white font-bold hover:bg-pink-700">
            <span v-if="loading"><i class="fa fa-spinner fa-spin"></i> Menyimpan...</span>
            <span v-else>Simpan</span>
          </button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.min.css';
import { ref, computed, watch } from 'vue';
import VueTimepicker from 'vue3-timepicker';
import 'vue3-timepicker/dist/VueTimepicker.css';
import Swal from 'sweetalert2';

const props = defineProps({
  promo: Object,
  categories: Array,
  items: Array,
  outlets: Array,
  regions: Array,
  isEdit: Boolean
});

const byType = ref('kategori');
const outletType = ref('outlet');
const loading = ref(false);

const form = ref({
  name: props.promo?.name || '',
  type: props.promo?.type || 'percent',
  value: props.promo?.value || '',
  min_transaction: props.promo?.min_transaction || '',
  max_transaction: props.promo?.max_transaction || '',
  start_date: props.promo?.start_date || '',
  end_date: props.promo?.end_date || '',
  start_time: props.promo?.start_time || '',
  end_time: props.promo?.end_time || '',
  description: props.promo?.description || '',
  terms: props.promo?.terms || '',
  outlets: props.promo?.outlets || [],
  categories: props.promo?.categories || [],
  items: props.promo?.items || [],
  buy_items: props.promo?.buy_items || [],
  get_items: props.promo?.get_items || [],
  banner: props.promo?.banner || null,
  banner_preview: '',
  status: props.promo?.status || 'active',
  regions: props.promo?.regions || [],
  need_member: props.promo?.need_member || 'No',
});

// Dummy data
const categories = props.categories || [];
const items = props.items || [];
const regions = props.regions || [];
const outlets = props.outlets || [];

console.log('outlets', outlets)

const itemPriceRows = ref([]);
const hasInvalidPromoPrice = computed(() => itemPriceRows.value.some(row => !row.new_price || row.new_price >= row.old_price));

watch([
  () => form.value.type,
  () => form.value.items,
  () => form.value.outlets,
  () => form.value.regions,
  () => outletType.value
], ([type, items, outlets, regions, outletTypeVal]) => {
  if (type !== 'harga_coret' || !items.length || (!outlets.length && !regions.length)) {
    itemPriceRows.value = [];
    return;
  }
  let rows = [];
  if (outletTypeVal === 'outlet') {
    items.forEach(item => {
      outlets.forEach(outlet => {
        rows.push({
          item_id: item.id,
          outlet_id: outlet.id,
          region_id: null,
          item_name: item.name,
          outlet_name: outlet.name,
          new_price: ''
        });
      });
    });
  } else if (outletTypeVal === 'region') {
    items.forEach(item => {
      regions.forEach(region => {
        rows.push({
          item_id: item.id,
          outlet_id: null,
          region_id: region.id,
          item_name: item.name,
          region_name: region.name,
          new_price: ''
        });
      });
    });
  }
  itemPriceRows.value = rows;
}, { immediate: true });

function formatCurrency(val) {
  if (!val) return '-';
  return Number(val).toLocaleString('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 });
}

async function submit() {
  const confirm = await Swal.fire({
    title: 'Simpan Promo?',
    text: 'Apakah Anda yakin ingin menyimpan data promo ini?',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Ya, Simpan',
    cancelButtonText: 'Batal'
  });
  if (!confirm.isConfirmed) return;
  loading.value = true;
  const payload = { ...form.value, status: 'active' };
  if (form.type === 'bogo') {
    payload.items = [];
    payload.categories = [];
    payload.buy_items = form.value.buy_items;
    payload.get_items = form.value.get_items;
  } else if (byType.value === 'kategori') {
    payload.items = [];
  } else {
    payload.categories = [];
  }
  if (outletType.value === 'region') {
    payload.outlets = [];
  } else {
    payload.regions = [];
  }
  if (form.value.type === 'harga_coret') {
    if (hasInvalidPromoPrice.value) {
      Swal.fire('Error', 'Harga promo harus lebih kecil dari harga asli!', 'error');
      return;
    }
    payload.item_prices = itemPriceRows.value.map(row => ({
      item_id: row.item_id,
      outlet_id: row.outlet_id,
      region_id: row.region_id,
      old_price: row.old_price,
      new_price: row.new_price
    }));
  }
  if (props.isEdit) {
    router.put(route('promos.update', props.promo.id), payload, {
      forceFormData: true,
      onSuccess: () => {
        loading.value = false;
        Swal.fire('Sukses', 'Promo berhasil diupdate!', 'success');
      },
      onError: () => {
        loading.value = false;
        Swal.fire('Gagal', 'Terjadi kesalahan saat update promo.', 'error');
      }
    });
  } else {
    router.post(route('promos.store'), payload, {
      forceFormData: true,
      onSuccess: () => {
        loading.value = false;
        Swal.fire('Sukses', 'Promo berhasil disimpan!', 'success');
      },
      onError: () => {
        loading.value = false;
        Swal.fire('Gagal', 'Terjadi kesalahan saat menyimpan promo.', 'error');
      }
    });
  }
}

function onBannerChange(e) {
  const file = e.target.files[0];
  if (file) {
    // Validasi ukuran file (2MB)
    if (file.size > 2 * 1024 * 1024) {
      Swal.fire('Error', 'Ukuran file maksimal 2MB', 'error');
      e.target.value = null;
      return;
    }
    // Validasi tipe file
    const validTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
    if (!validTypes.includes(file.type)) {
      Swal.fire('Error', 'Format file harus JPG, JPEG, PNG, atau GIF', 'error');
      e.target.value = null;
      return;
    }
    form.value.banner = file;
    form.value.banner_preview = URL.createObjectURL(file);
  }
}
</script>

<style scoped>
@import 'vue-multiselect/dist/vue-multiselect.min.css';
</style> 