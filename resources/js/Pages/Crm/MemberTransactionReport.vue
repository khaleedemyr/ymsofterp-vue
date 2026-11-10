<template>
  <AppLayout title="Report Transaksi Member">
    <div class="w-full max-w-none py-6 px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-receipt text-purple-600"></i>
            Report Transaksi Member
          </h1>
          <p class="text-sm text-gray-500 mt-1">
            Riwayat earn / redeem poin member — terhubung ke bill POS bila ada.
          </p>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow p-4 mb-4">
        <form @submit.prevent="applyFilters" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-7 gap-3">
          <div class="xl:col-span-2">
            <label class="mb-1 block text-xs text-gray-500">Cari</label>
            <input
              v-model="filterForm.search"
              type="text"
              placeholder="Member ID / nama / phone / paid number..."
              class="w-full rounded-lg border-gray-300 focus:border-purple-500 focus:ring-purple-500"
            />
          </div>
          <div>
            <label class="mb-1 block text-xs text-gray-500">Tipe</label>
            <select v-model="filterForm.type" class="w-full rounded-lg border-gray-300">
              <option v-for="(label, key) in typeOptions" :key="key || 'all'" :value="key">{{ label }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs text-gray-500">Outlet</label>
            <select v-model="filterForm.outlet_id" class="w-full rounded-lg border-gray-300">
              <option value="">Semua Outlet</option>
              <option v-for="o in outlets" :key="o.id" :value="o.id">{{ o.name }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs text-gray-500">Dari tanggal</label>
            <input v-model="filterForm.date_from" type="date" class="w-full rounded-lg border-gray-300" />
          </div>
          <div>
            <label class="mb-1 block text-xs text-gray-500">Sampai tanggal</label>
            <input v-model="filterForm.date_to" type="date" class="w-full rounded-lg border-gray-300" />
          </div>
          <div class="flex items-end gap-2">
            <button type="submit" class="px-4 py-2 rounded-lg bg-purple-600 text-white hover:bg-purple-700">
              Cari
            </button>
            <button type="button" class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50" @click="resetFilters">
              Reset
            </button>
          </div>
        </form>
      </div>

      <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-3">
          <div class="text-xs text-gray-500">Total Transaksi</div>
          <div class="text-xl font-bold text-gray-900">{{ formatNumber(totals.total_transactions) }}</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-3">
          <div class="text-xs text-gray-500">Unique Member</div>
          <div class="text-xl font-bold text-gray-900">{{ formatNumber(totals.total_members) }}</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-3">
          <div class="text-xs text-gray-500">Point Earned</div>
          <div class="text-xl font-bold text-emerald-700">+{{ formatNumber(totals.total_earned) }}</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-3">
          <div class="text-xs text-gray-500">Point Redeemed</div>
          <div class="text-xl font-bold text-rose-700">-{{ formatNumber(totals.total_redeemed) }}</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-3 col-span-2 md:col-span-1">
          <div class="text-xs text-gray-500">Nilai Transaksi</div>
          <div class="text-lg font-bold text-purple-700">{{ totals.total_amount_formatted || 'Rp 0' }}</div>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 border-b">
            <tr>
              <th class="px-3 py-3 text-left font-semibold text-gray-700">Tanggal</th>
              <th class="px-3 py-3 text-left font-semibold text-gray-700">Member</th>
              <th class="px-3 py-3 text-left font-semibold text-gray-700">Tipe</th>
              <th class="px-3 py-3 text-right font-semibold text-gray-700">Point</th>
              <th class="px-3 py-3 text-right font-semibold text-gray-700">Nilai</th>
              <th class="px-3 py-3 text-left font-semibold text-gray-700">Paid Number</th>
              <th class="px-3 py-3 text-left font-semibold text-gray-700">Outlet</th>
              <th class="px-3 py-3 text-left font-semibold text-gray-700">Channel</th>
              <th class="px-3 py-3 text-left font-semibold text-gray-700">Keterangan</th>
              <th class="px-3 py-3 text-right font-semibold text-gray-700">Detail</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!records.data?.length">
              <td colspan="10" class="px-4 py-10 text-center text-gray-500">
                Tidak ada transaksi untuk filter ini.
              </td>
            </tr>
            <template v-for="row in records.data" :key="row.id">
              <tr class="border-b border-gray-100 hover:bg-slate-50 align-top">
                <td class="px-3 py-3 whitespace-nowrap">
                  <div class="font-medium text-gray-800">{{ row.transaction_date || '-' }}</div>
                  <div class="text-xs text-gray-500">{{ row.created_at || '' }}</div>
                </td>
                <td class="px-3 py-3">
                  <div class="font-medium text-gray-900">{{ row.member_name }}</div>
                  <div class="text-xs text-gray-500 font-mono">{{ row.member_id }}</div>
                  <div v-if="row.member_phone" class="text-xs text-gray-400">{{ row.member_phone }}</div>
                </td>
                <td class="px-3 py-3 whitespace-nowrap">
                  <span
                    :class="[
                      'inline-flex px-2 py-0.5 rounded-full text-xs font-semibold',
                      row.is_earn ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800',
                    ]"
                  >
                    {{ row.type_text }}
                  </span>
                  <div class="text-xs text-gray-500 mt-1">{{ row.transaction_type || '-' }}</div>
                </td>
                <td class="px-3 py-3 text-right whitespace-nowrap">
                  <span :class="row.is_earn ? 'text-emerald-700 font-bold' : 'text-rose-700 font-bold'">
                    {{ row.is_earn ? '+' : '-' }}{{ formatNumber(row.point_abs) }}
                  </span>
                </td>
                <td class="px-3 py-3 text-right whitespace-nowrap text-gray-800">
                  {{ row.transaction_amount_formatted }}
                </td>
                <td class="px-3 py-3 whitespace-nowrap">
                  <div v-if="row.paid_number" class="font-mono text-xs text-blue-700">{{ row.paid_number }}</div>
                  <div v-else-if="row.reference_id" class="font-mono text-xs text-gray-500">{{ row.reference_id }}</div>
                  <div v-else class="text-gray-400">-</div>
                </td>
                <td class="px-3 py-3">{{ row.outlet_name || '-' }}</td>
                <td class="px-3 py-3 whitespace-nowrap text-gray-600">{{ row.channel || '-' }}</td>
                <td class="px-3 py-3 max-w-[220px]">
                  <div class="text-gray-700 line-clamp-2" :title="row.description">{{ row.description || '-' }}</div>
                </td>
                <td class="px-3 py-3 text-right whitespace-nowrap">
                  <button
                    type="button"
                    class="inline-flex items-center gap-1 rounded-lg bg-purple-50 px-2.5 py-1.5 text-xs font-semibold text-purple-700 hover:bg-purple-100 disabled:opacity-50"
                    :disabled="loadingDetailId === row.id"
                    @click="toggleDetail(row)"
                  >
                    <i :class="loadingDetailId === row.id ? 'fa-solid fa-spinner fa-spin' : (expandedId === row.id ? 'fa-solid fa-chevron-up' : 'fa-solid fa-chevron-down')"></i>
                    Bill
                  </button>
                </td>
              </tr>
              <tr v-if="expandedId === row.id" class="bg-slate-50">
                <td colspan="10" class="px-4 py-4">
                  <div v-if="detailCache[row.id]?.order" class="space-y-3">
                    <div class="flex flex-wrap gap-4 text-sm text-gray-700">
                      <div><span class="text-gray-500">Nomor:</span> <span class="font-mono">{{ detailCache[row.id].order.nomor || '-' }}</span></div>
                      <div><span class="text-gray-500">Paid:</span> <span class="font-mono font-semibold text-blue-700">{{ detailCache[row.id].order.paid_number || '-' }}</span></div>
                      <div><span class="text-gray-500">Outlet:</span> {{ detailCache[row.id].order.outlet_name }}</div>
                      <div><span class="text-gray-500">Grand Total:</span> <span class="font-semibold">{{ detailCache[row.id].order.grand_total_formatted }}</span></div>
                      <div><span class="text-gray-500">Status:</span> {{ detailCache[row.id].order.status }}</div>
                      <div><span class="text-gray-500">Waktu:</span> {{ detailCache[row.id].order.created_at }}</div>
                    </div>
                    <div v-if="detailCache[row.id].items?.length" class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
                      <table class="min-w-full text-xs">
                        <thead class="bg-gray-100">
                          <tr>
                            <th class="px-3 py-2 text-left">Item</th>
                            <th class="px-3 py-2 text-right">Qty</th>
                            <th class="px-3 py-2 text-right">Harga</th>
                            <th class="px-3 py-2 text-right">Subtotal</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr v-for="(item, idx) in detailCache[row.id].items" :key="idx" class="border-t border-gray-100">
                            <td class="px-3 py-2">{{ item.item_name }}</td>
                            <td class="px-3 py-2 text-right">{{ item.qty }}</td>
                            <td class="px-3 py-2 text-right">{{ item.price_formatted }}</td>
                            <td class="px-3 py-2 text-right font-medium">{{ item.subtotal_formatted }}</td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                    <div v-else class="text-sm text-gray-500">Order ditemukan, tapi tidak ada item.</div>
                  </div>
                  <div v-else class="text-sm text-gray-500">
                    {{ detailCache[row.id]?.message || 'Tidak ada bill POS yang terhubung ke transaksi ini.' }}
                  </div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>

      <div v-if="records.links?.length" class="mt-4 flex flex-wrap gap-2 justify-end">
        <template v-for="(link, idx) in records.links" :key="idx">
          <button
            v-if="link.url"
            type="button"
            class="px-3 py-1 border rounded text-sm"
            :class="link.active ? 'bg-purple-600 text-white border-purple-600' : 'bg-white text-gray-700 border-gray-300 hover:bg-purple-50'"
            @click="goPage(link.url)"
            v-html="link.label"
          />
          <span
            v-else
            class="px-3 py-1 border border-gray-200 rounded text-sm text-gray-400"
            v-html="link.label"
          />
        </template>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';
import axios from 'axios';

const props = defineProps({
  records: { type: Object, required: true },
  totals: {
    type: Object,
    default: () => ({
      total_transactions: 0,
      total_members: 0,
      total_earned: 0,
      total_redeemed: 0,
      total_amount: 0,
      total_amount_formatted: 'Rp 0',
    }),
  },
  filters: { type: Object, default: () => ({}) },
  outlets: { type: Array, default: () => [] },
  typeOptions: { type: Object, default: () => ({}) },
});

const filterForm = reactive({
  search: props.filters.search || '',
  type: props.filters.type || '',
  outlet_id: props.filters.outlet_id || '',
  date_from: props.filters.date_from || '',
  date_to: props.filters.date_to || '',
  per_page: props.filters.per_page || 25,
});

const expandedId = ref(null);
const loadingDetailId = ref(null);
const detailCache = reactive({});

const formatNumber = (n) => Number(n || 0).toLocaleString('id-ID');

const applyFilters = () => {
  router.get(route('crm.member-transaction-report'), { ...filterForm }, { preserveState: true, replace: true });
};

const resetFilters = () => {
  filterForm.search = '';
  filterForm.type = '';
  filterForm.outlet_id = '';
  filterForm.date_from = '';
  filterForm.date_to = '';
  applyFilters();
};

const goPage = (url) => {
  router.visit(url, { preserveState: true, preserveScroll: true });
};

const toggleDetail = async (row) => {
  if (expandedId.value === row.id) {
    expandedId.value = null;
    return;
  }

  expandedId.value = row.id;
  if (detailCache[row.id]) return;

  loadingDetailId.value = row.id;
  try {
    const { data } = await axios.get(route('crm.member-transaction-report.order-details', row.id));
    if (data.status === 'success') {
      detailCache[row.id] = {
        order: data.order,
        items: data.items || [],
        message: data.order ? null : 'Tidak ada bill POS yang terhubung ke transaksi ini.',
      };
    } else {
      detailCache[row.id] = { order: null, items: [], message: data.message || 'Gagal memuat detail.' };
    }
  } catch (e) {
    detailCache[row.id] = {
      order: null,
      items: [],
      message: e.response?.data?.message || 'Gagal memuat detail bill.',
    };
  } finally {
    loadingDetailId.value = null;
  }
};
</script>
