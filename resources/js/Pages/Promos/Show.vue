<template>
  <AppLayout>
    <div class="max-w-5xl mx-auto py-8 px-4">
      <!-- Header -->
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-pink-700">Detail Promo</h1>
        <div class="flex gap-2">
          <Link :href="route('promos.edit', promo.id)" class="px-4 py-2 rounded-lg bg-pink-600 text-white font-semibold hover:bg-pink-700 transition">
            <i class="fa fa-edit mr-2"></i>Edit
          </Link>
          <Link :href="route('promos.index')" class="px-4 py-2 rounded-lg bg-gray-100 text-gray-700 font-semibold hover:bg-gray-200 transition">
            <i class="fa fa-arrow-left mr-2"></i>Kembali
          </Link>
        </div>
      </div>

      <!-- Banner Section -->
      <div v-if="promo.banner" class="mb-6">
        <img :src="`/storage/${promo.banner}`" class="w-full h-64 object-cover rounded-2xl shadow-lg" />
      </div>

      <!-- Main Content -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Column - Main Info -->
        <div class="lg:col-span-2 space-y-6">
          <!-- Basic Info Card -->
          <div class="bg-white rounded-2xl shadow-xl p-6">
            <h2 class="text-xl font-bold text-pink-700 mb-4 pb-2 border-b border-gray-200">
              <i class="fa fa-info-circle mr-2"></i>Informasi Dasar
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="text-sm font-medium text-gray-500">Nama Promo</label>
                <p class="text-lg font-semibold text-gray-900">{{ promo.name }}</p>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-500">Kode Promo</label>
                <p class="text-lg font-semibold text-gray-900">{{ promo.code || '-' }}</p>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-500">Tipe Promo</label>
                <p class="text-lg font-semibold text-pink-600">{{ getPromoTypeText(promo.type) }}</p>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-500">Value</label>
                <p class="text-lg font-semibold text-gray-900">
                  {{ promo.type === 'percent' ? promo.value + '%' : formatCurrency(promo.value) }}
                </p>
              </div>
              <div v-if="promo.type === 'percent' && promo.max_discount">
                <label class="text-sm font-medium text-gray-500">Maximum Diskon</label>
                <p class="text-lg font-semibold text-gray-900">{{ formatCurrency(promo.max_discount) }}</p>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-500">Status</label>
                <span :class="[
                  'inline-block px-3 py-1 rounded-full text-sm font-semibold',
                  promo.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'
                ]">
                  {{ promo.status === 'active' ? 'Aktif' : 'Nonaktif' }}
                </span>
              </div>
            </div>
          </div>

          <!-- Schedule Card -->
          <div class="bg-white rounded-2xl shadow-xl p-6">
            <h2 class="text-xl font-bold text-pink-700 mb-4 pb-2 border-b border-gray-200">
              <i class="fa fa-calendar-alt mr-2"></i>Jadwal Promo
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
              <div>
                <label class="text-sm font-medium text-gray-500">Tanggal Mulai</label>
                <p class="text-lg font-semibold text-gray-900">{{ formatDate(promo.start_date) }}</p>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-500">Tanggal Akhir</label>
                <p class="text-lg font-semibold text-gray-900">{{ formatDate(promo.end_date) }}</p>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-500">Jam Mulai</label>
                <p class="text-lg font-semibold text-gray-900">{{ promo.start_time || '-' }}</p>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-500">Jam Akhir</label>
                <p class="text-lg font-semibold text-gray-900">{{ promo.end_time || '-' }}</p>
              </div>
            </div>
            <div v-if="promo.days && promo.days.length">
              <label class="text-sm font-medium text-gray-500 mb-2 block">Hari Berlaku</label>
              <div class="flex flex-wrap gap-2">
                <span v-for="day in promo.days" :key="day" class="inline-block bg-pink-100 text-pink-800 px-3 py-1 rounded-full text-sm font-semibold">
                  {{ day }}
                </span>
              </div>
            </div>
            <div v-else>
              <label class="text-sm font-medium text-gray-500 mb-2 block">Hari Berlaku</label>
              <p class="text-gray-600 italic">Berlaku setiap hari</p>
            </div>
          </div>

          <!-- Transaction Rules Card -->
          <div class="bg-white rounded-2xl shadow-xl p-6">
            <h2 class="text-xl font-bold text-pink-700 mb-4 pb-2 border-b border-gray-200">
              <i class="fa fa-shopping-cart mr-2"></i>Aturan Transaksi
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="text-sm font-medium text-gray-500">Minimum Transaksi</label>
                <p class="text-lg font-semibold text-gray-900">{{ formatCurrency(promo.min_transaction) || '-' }}</p>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-500">Maximum Transaksi</label>
                <p class="text-lg font-semibold text-gray-900">{{ formatCurrency(promo.max_transaction) || '-' }}</p>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-500">Berlaku Kelipatan</label>
                <span :class="[
                  'inline-block px-3 py-1 rounded-full text-sm font-semibold',
                  promo.is_multiple === 'Yes' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'
                ]">
                  {{ promo.is_multiple === 'Yes' ? 'Ya' : 'Tidak' }}
                </span>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-500">Perlu Member</label>
                <span :class="[
                  'inline-block px-3 py-1 rounded-full text-sm font-semibold',
                  promo.need_member === 'Yes' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'
                ]">
                  {{ promo.need_member === 'Yes' ? 'Ya' : 'Tidak' }}
                </span>
              </div>
            </div>
            <div v-if="promoData.need_member === 'Yes'" class="mt-4">
              <label class="text-sm font-medium text-gray-500 mb-2 block">Tier Member</label>
              <div v-if="promoData.all_tiers" class="flex items-center gap-2">
                <span class="inline-block bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm font-semibold">
                  Semua Tier
                </span>
              </div>
              <div v-else-if="promoData.tiers && promoData.tiers.length > 0" class="flex flex-wrap gap-2">
                <span v-for="tier in promoData.tiers" :key="tier" class="inline-block bg-purple-100 text-purple-800 px-3 py-1 rounded-full text-sm font-semibold">
                  {{ tier }}
                </span>
              </div>
              <div v-else class="text-gray-500 italic text-sm">
                Tidak ada tier yang dipilih
              </div>
            </div>
          </div>

          <!-- Categories/Items Card -->
          <div class="bg-white rounded-2xl shadow-xl p-6">
            <h2 class="text-xl font-bold text-pink-700 mb-4 pb-2 border-b border-gray-200">
              <i class="fa fa-tags mr-2"></i>Kategori & Item
            </h2>
            <div v-if="promo.categories && promo.categories.length" class="mb-4">
              <label class="text-sm font-medium text-gray-500 mb-2 block">Kategori Promo</label>
              <div class="flex flex-wrap gap-2">
                <span v-for="c in promo.categories" :key="c.id" class="inline-block bg-pink-100 text-pink-800 px-3 py-1 rounded-full text-sm font-semibold">
                  {{ c.name }}
                </span>
              </div>
            </div>
            <div v-if="promo.items && promo.items.length">
              <label class="text-sm font-medium text-gray-500 mb-2 block">Item Promo</label>
              <div class="flex flex-wrap gap-2">
                <span v-for="i in promo.items" :key="i.id" class="inline-block bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm font-semibold">
                  {{ i.name }}
                </span>
              </div>
            </div>
            <div v-if="(!promo.categories || !promo.categories.length) && (!promo.items || !promo.items.length)" class="text-gray-500 italic">
              Tidak ada kategori atau item spesifik
            </div>
          </div>

          <!-- BOGO Items Card -->
          <div v-if="promo.type === 'bogo' && promo.bogo_items && promo.bogo_items.length" class="bg-white rounded-2xl shadow-xl p-6">
            <h2 class="text-xl font-bold text-pink-700 mb-4 pb-2 border-b border-gray-200">
              <i class="fa fa-gift mr-2"></i>Item BOGO
            </h2>
            <div class="space-y-3">
              <div v-for="(bogo, idx) in promo.bogo_items" :key="idx" class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                <div class="flex-1">
                  <span class="text-sm text-gray-500">Buy:</span>
                  <span class="ml-2 font-semibold text-blue-700">{{ bogo.buy_item.name }}</span>
                </div>
                <i class="fa fa-arrow-right text-pink-500"></i>
                <div class="flex-1">
                  <span class="text-sm text-gray-500">Get:</span>
                  <span class="ml-2 font-semibold text-pink-700">{{ bogo.get_item.name }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Harga Coret Table -->
          <div v-if="promo.type === 'harga_coret' && promo.item_prices && promo.item_prices.length" class="bg-white rounded-2xl shadow-xl p-6">
            <h2 class="text-xl font-bold text-pink-700 mb-4 pb-2 border-b border-gray-200">
              <i class="fa fa-table mr-2"></i>Harga Promo per Produk
            </h2>
            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-pink-50">
                  <tr>
                    <th class="px-4 py-3 text-left text-xs font-bold text-pink-700 uppercase">Produk</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-pink-700 uppercase">Outlet/Region</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-pink-700 uppercase">Harga Promo</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <tr v-for="row in promo.item_prices" :key="row.item_id+'-'+(row.outlet_id||row.region_id)" class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ row.item_name }}</td>
                    <td class="px-4 py-3 text-sm text-gray-700">{{ row.outlet_name || row.region_name }}</td>
                    <td class="px-4 py-3 text-sm font-semibold text-pink-600">{{ formatCurrency(row.new_price) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Right Column - Sidebar -->
        <div class="space-y-6">
          <!-- Outlet/Region Card -->
          <div class="bg-white rounded-2xl shadow-xl p-6">
            <h2 class="text-xl font-bold text-pink-700 mb-4 pb-2 border-b border-gray-200">
              <i class="fa fa-store mr-2"></i>Lokasi
            </h2>
            <div v-if="promo.outlets && promo.outlets.length" class="mb-4">
              <label class="text-sm font-medium text-gray-500 mb-2 block">Outlet</label>
              <div class="flex flex-wrap gap-2">
                <span v-for="o in promo.outlets" :key="o.id" class="inline-block bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm font-semibold">
                  {{ o.name }}
                </span>
              </div>
            </div>
            <div v-if="promo.regions && promo.regions.length">
              <label class="text-sm font-medium text-gray-500 mb-2 block">Region</label>
              <div class="flex flex-wrap gap-2">
                <span v-for="r in promo.regions" :key="r.id" class="inline-block bg-purple-100 text-purple-800 px-3 py-1 rounded-full text-sm font-semibold">
                  {{ r.name }}
                </span>
              </div>
            </div>
            <div v-if="(!promo.outlets || !promo.outlets.length) && (!promo.regions || !promo.regions.length)" class="text-gray-500 italic text-sm">
              Semua outlet
            </div>
          </div>

          <!-- Description Card -->
          <div v-if="promo.description" class="bg-white rounded-2xl shadow-xl p-6">
            <h2 class="text-xl font-bold text-pink-700 mb-4 pb-2 border-b border-gray-200">
              <i class="fa fa-align-left mr-2"></i>Deskripsi
            </h2>
            <p class="text-gray-700 leading-relaxed">{{ promo.description }}</p>
          </div>

          <!-- Terms Card -->
          <div v-if="promo.terms" class="bg-white rounded-2xl shadow-xl p-6">
            <h2 class="text-xl font-bold text-pink-700 mb-4 pb-2 border-b border-gray-200">
              <i class="fa fa-file-contract mr-2"></i>Syarat & Ketentuan
            </h2>
            <p class="text-gray-700 leading-relaxed whitespace-pre-line">{{ promo.terms }}</p>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
  promo: Object
});

// Parse tiers dari database (bisa JSON string atau array)
function parseTiers(tiers) {
  if (!tiers) return [];
  if (Array.isArray(tiers)) return tiers;
  if (typeof tiers === 'string') {
    try {
      const parsed = JSON.parse(tiers);
      return Array.isArray(parsed) ? parsed : [];
    } catch (e) {
      return [];
    }
  }
  return [];
}

// Parse all_tiers dari database (bisa boolean, number, atau string)
function parseAllTiers(allTiers) {
  if (allTiers === true || allTiers === 1 || allTiers === '1') return true;
  if (allTiers === false || allTiers === 0 || allTiers === '0' || allTiers === null || allTiers === undefined) return false;
  return false;
}

// Computed untuk mendapatkan tiers yang sudah di-parse
const promoData = computed(() => {
  return {
    ...props.promo,
    tiers: parseTiers(props.promo?.tiers),
    all_tiers: parseAllTiers(props.promo?.all_tiers)
  };
});

function getPromoTypeText(type) {
  const types = {
    'percent': 'Diskon Persen',
    'nominal': 'Diskon Nominal',
    'bundle': 'Bundling',
    'bogo': 'Buy 1 Get 1'
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