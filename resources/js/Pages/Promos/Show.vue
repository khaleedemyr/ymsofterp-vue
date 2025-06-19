<template>
  <AppLayout>
    <div class="max-w-2xl mx-auto py-8 px-2">
      <h1 class="text-2xl font-bold text-pink-700 mb-6">Detail Promo</h1>
      <div class="bg-white rounded-2xl shadow-xl p-8 space-y-4">
        <div><b>Nama Promo:</b> {{ promo.name }}</div>
        <div><b>Kode Promo:</b> {{ promo.code || '-' }}</div>
        <div><b>Tipe Promo:</b> {{ getPromoTypeText(promo.type) }}</div>
        <div><b>Value:</b> {{ promo.type === 'percent' || promo.type === 'bill_discount' ? promo.value + '%' : formatCurrency(promo.value) }}</div>
        <div v-if="(promo.type === 'percent' || promo.type === 'bill_discount') && promo.max_discount"><b>Maximum Diskon:</b> {{ formatCurrency(promo.max_discount) }}</div>
        <div><b>Berlaku Kelipatan:</b> {{ promo.is_multiple === 'Yes' ? 'Ya' : 'Tidak' }}</div>
        <div><b>Minimum Transaksi:</b> {{ promo.min_transaction || '-' }}</div>
        <div><b>Maximum Transaksi:</b> {{ promo.max_transaction || '-' }}</div>
        <div><b>Tanggal Mulai:</b> {{ formatDate(promo.start_date) }}</div>
        <div><b>Tanggal Akhir:</b> {{ formatDate(promo.end_date) }}</div>
        <div><b>Status:</b> <span :class="promo.status === 'active' ? 'text-green-700' : 'text-gray-500'">{{ promo.status === 'active' ? 'Aktif' : 'Nonaktif' }}</span></div>
        <div><b>Perlu Member?:</b> {{ promo.need_member === 'Yes' ? 'Ya' : 'Tidak' }}</div>
        <div><b>Deskripsi:</b> {{ promo.description || '-' }}</div>
        <div><b>Kategori Promo:</b>
          <span v-if="promo.categories && promo.categories.length">
            <span v-for="c in promo.categories" :key="c.id" class="inline-block bg-pink-100 text-pink-800 px-2 py-1 rounded-full text-xs font-semibold mr-1">{{ c.name }}</span>
          </span>
          <span v-else>-</span>
        </div>
        <div><b>Item Promo:</b>
          <span v-if="promo.items && promo.items.length">
            <span v-for="i in promo.items" :key="i.id" class="inline-block bg-blue-100 text-blue-800 px-2 py-1 rounded-full text-xs font-semibold mr-1">{{ i.name }}</span>
          </span>
          <span v-else>-</span>
        </div>
        <div><b>Outlet Promo:</b>
          <span v-if="promo.outlets && promo.outlets.length">
            <span v-for="o in promo.outlets" :key="o.id" class="inline-block bg-green-100 text-green-800 px-2 py-1 rounded-full text-xs font-semibold mr-1">{{ o.name }}</span>
          </span>
          <span v-else>-</span>
        </div>
        <div><b>Jam Mulai:</b> {{ promo.start_time || '-' }}</div>
        <div><b>Jam Akhir:</b> {{ promo.end_time || '-' }}</div>
        <div><b>Term & Condition:</b> {{ promo.terms || '-' }}</div>
        <div><b>Banner:</b> <img v-if="promo.banner" :src="`/storage/${promo.banner}`" class="max-h-24" /></div>
        <div><b>Region Promo:</b>
          <span v-if="promo.regions && promo.regions.length">
            <span v-for="r in promo.regions" :key="r.id" class="inline-block bg-green-100 text-green-800 px-2 py-1 rounded-full text-xs font-semibold mr-1">{{ r.name }}</span>
          </span>
          <span v-else>-</span>
        </div>
        <div v-if="promo.type === 'bogo'">
          <b>Item Promo (Buy):</b>
          <span v-if="promo.buy_items && promo.buy_items.length">
            <span v-for="i in promo.buy_items" :key="i.id" class="inline-block bg-blue-100 text-blue-800 px-2 py-1 rounded-full text-xs font-semibold mr-1">{{ i.name }}</span>
          </span>
          <span v-else>-</span>
          <br>
          <b>Item Promo (Get):</b>
          <span v-if="promo.get_items && promo.get_items.length">
            <span v-for="i in promo.get_items" :key="i.id" class="inline-block bg-pink-100 text-pink-800 px-2 py-1 rounded-full text-xs font-semibold mr-1">{{ i.name }}</span>
          </span>
          <span v-else>-</span>
        </div>
        <div v-if="promo.type === 'harga_coret' && promo.item_prices && promo.item_prices.length">
          <h3 class="font-bold text-pink-700 mb-2 mt-4">Harga Promo per Produk & Outlet/Region</h3>
          <table class="min-w-full divide-y divide-gray-200 mb-4">
            <thead class="bg-pink-50">
              <tr>
                <th class="px-4 py-2 text-left text-xs font-bold text-pink-700">Produk</th>
                <th class="px-4 py-2 text-left text-xs font-bold text-pink-700">Outlet/Region</th>
                <th class="px-4 py-2 text-left text-xs font-bold text-pink-700">Harga Promo</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in promo.item_prices" :key="row.item_id+'-'+(row.outlet_id||row.region_id)">
                <td class="px-4 py-2">{{ row.item_name }}</td>
                <td class="px-4 py-2">{{ row.outlet_name || row.region_name }}</td>
                <td class="px-4 py-2">{{ formatCurrency(row.new_price) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="flex justify-end mt-8">
          <Link :href="route('promos.index')" class="px-4 py-2 rounded bg-gray-100 text-gray-700 font-semibold hover:bg-gray-200">Kembali</Link>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
  promo: Object
});

function getPromoTypeText(type) {
  const types = {
    'percent': 'Diskon Persen',
    'nominal': 'Diskon Nominal',
    'bundle': 'Bundling',
    'bogo': 'Buy 1 Get 1',
    'harga_coret': 'Harga Coret',
    'bill_discount': 'Diskon Bill'
  };
  return types[type] || type;
}

function formatDate(date) {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('id-ID');
}

function formatCurrency(val) {
  if (!val) return '-';
  return Number(val).toLocaleString('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 });
}
</script> 