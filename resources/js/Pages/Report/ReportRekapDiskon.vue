<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto py-8 px-4">
      <h1 class="text-2xl font-bold mb-4 flex items-center gap-2">
        <i class="fas fa-tags text-blue-600"></i>
        Rekap Diskon
      </h1>

      <!-- Tabs -->
      <div class="flex border-b border-gray-200 mb-6">
        <button
          type="button"
          @click="activeTab = 'promo'"
          class="px-4 py-2.5 text-sm font-medium border-b-2 transition"
          :class="activeTab === 'promo' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
        >
          <i class="fas fa-tag mr-1.5"></i> Diskon Promo
        </button>
        <button
          type="button"
          @click="activeTab = 'global'"
          class="px-4 py-2.5 text-sm font-medium border-b-2 transition"
          :class="activeTab === 'global' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
        >
          <i class="fas fa-university mr-1.5"></i> Diskon Bank & Lainnya
        </button>
      </div>

      <div class="flex flex-wrap items-end gap-4 mb-6">
        <div>
          <label class="block text-sm font-medium mb-1">Tanggal Dari</label>
          <input
            v-model="dateFrom"
            type="date"
            class="rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 px-3 py-2"
          />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Tanggal Sampai</label>
          <input
            v-model="dateTo"
            type="date"
            class="rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 px-3 py-2"
          />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Outlet</label>
          <select
            v-model="kodeOutlet"
            class="rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 px-3 py-2 min-w-[180px]"
          >
            <option value="">Semua Outlet</option>
            <option v-for="opt in outletOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
          </select>
        </div>
        <div class="flex-1 min-w-[180px]">
          <label class="block text-sm font-medium mb-1">Cari</label>
          <input
            v-model="search"
            type="text"
            :placeholder="activeTab === 'promo' ? 'No order, paid number, nama/kode promo...' : 'No order, paid number, alasan diskon...'"
            class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 px-3 py-2"
          />
        </div>
        <div class="flex items-center gap-2">
          <label class="text-sm">Tampilkan</label>
          <select
            v-if="activeTab === 'promo'"
            v-model="perPage"
            class="rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 px-2 py-2"
          >
            <option v-for="n in [10, 25, 50, 100]" :key="n" :value="n">{{ n }}</option>
          </select>
          <select
            v-else
            v-model="perPageGlobal"
            class="rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 px-2 py-2"
          >
            <option v-for="n in [10, 25, 50, 100]" :key="'g'+n" :value="n">{{ n }}</option>
          </select>
          <span class="text-sm">data</span>
        </div>
        <button
          @click="reloadData"
          :disabled="loadingReload"
          class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg font-semibold hover:bg-blue-700 disabled:opacity-50"
        >
          <span v-if="loadingReload" class="animate-spin mr-2"><i class="fas fa-spinner"></i></span>
          <span v-else class="mr-2"><i class="fas fa-sync-alt"></i></span>
          Muat Data
        </button>
        <a
          :href="exportPromoUrl"
          class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg font-semibold hover:bg-green-700"
          title="Export data Diskon Promo ke Excel"
        >
          <i class="fas fa-file-excel mr-2"></i>
          Export Excel (Promo)
        </a>
        <a
          :href="exportGlobalUrl"
          class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg font-semibold hover:bg-green-700"
          title="Export data Diskon Bank & Lainnya ke Excel"
        >
          <i class="fas fa-file-excel mr-2"></i>
          Export Excel (Bank & Lainnya)
        </a>
      </div>

      <!-- Tab: Diskon Promo -->
      <div v-show="activeTab === 'promo'">
      <!-- Summary per promo -->
      <div class="mb-8">
        <h2 class="text-lg font-semibold mb-3 text-gray-800">Rekap per Promo</h2>
        <div class="bg-white rounded-xl shadow overflow-x-auto">
          <table class="min-w-full border border-gray-300">
            <thead>
              <tr class="bg-blue-600 text-white">
                <th class="px-4 py-2 border border-gray-300 text-left">Nama Promo</th>
                <th class="px-4 py-2 border border-gray-300 text-left">Kode</th>
                <th class="px-4 py-2 border border-gray-300 text-left">Tipe</th>
                <th class="px-4 py-2 border border-gray-300 text-right">Value</th>
                <th class="px-4 py-2 border border-gray-300 text-center">Jumlah Pemakaian</th>
                <th class="px-4 py-2 border border-gray-300 text-right">Total Diskon (alokasi)</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!summary.length">
                <td colspan="6" class="text-center py-6 text-gray-400">Tidak ada data.</td>
              </tr>
              <tr v-for="row in summary" :key="row.promo_id" class="hover:bg-gray-50">
                <td class="px-4 py-2 border border-gray-200">{{ row.promo_name }}</td>
                <td class="px-4 py-2 border border-gray-200">{{ row.promo_code }}</td>
                <td class="px-4 py-2 border border-gray-200">{{ row.promo_type || '-' }}</td>
                <td class="px-4 py-2 border border-gray-200 text-right">{{ row.promo_value != null ? row.promo_value : '-' }}</td>
                <td class="px-4 py-2 border border-gray-200 text-center">{{ row.jumlah_pemakaian }}</td>
                <td class="px-4 py-2 border border-gray-200 text-right">{{ formatRupiah(row.total_discount) }}</td>
              </tr>
            </tbody>
            <tfoot v-if="summary.length">
              <tr class="bg-gray-100 font-semibold">
                <td colspan="4" class="px-4 py-3 text-right">Total</td>
                <td class="px-4 py-3 text-center">{{ summaryTotalPemakaian }}</td>
                <td class="px-4 py-3 text-right">{{ formatRupiah(summaryTotalDiscount) }}</td>
              </tr>
            </tfoot>
          </table>
        </div>
        <p class="text-xs text-gray-500 mt-1">Total diskon per promo dialokasikan proporsional jika satu order memakai lebih dari satu promo.</p>
      </div>

      <!-- Detail (paginated) -->
      <div>
        <h2 class="text-lg font-semibold mb-3 text-gray-800">Detail Transaksi (order_promos status=active)</h2>
        <div class="bg-white rounded-xl shadow overflow-x-auto">
          <table class="min-w-full border border-gray-300">
            <thead>
              <tr class="bg-yellow-300 text-gray-900">
                <th class="px-4 py-2 border border-gray-300">Tanggal</th>
                <th class="px-4 py-2 border border-gray-300">No. Order</th>
                <th class="px-4 py-2 border border-gray-300">Paid Number</th>
                <th class="px-4 py-2 border border-gray-300">Nama Outlet</th>
                <th class="px-4 py-2 border border-gray-300">Promo</th>
                <th class="px-4 py-2 border border-gray-300 text-right">Discount Order</th>
                <th class="px-4 py-2 border border-gray-300 text-right">Grand Total</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!detail.length">
                <td colspan="7" class="text-center py-10 text-gray-400">Tidak ada data.</td>
              </tr>
              <tr v-for="row in detail" :key="row.order_promo_id" class="hover:bg-gray-50">
                <td class="px-4 py-2 border border-gray-200">{{ formatDate(row.order_created_at) }}</td>
                <td class="px-4 py-2 border border-gray-200">
                  <button
                    type="button"
                    @click="openOrderDetail(row.order_id)"
                    class="text-blue-600 hover:text-blue-800 underline font-medium text-left"
                  >
                    {{ row.order_nomor }}
                  </button>
                </td>
                <td class="px-4 py-2 border border-gray-200">{{ row.paid_number || '-' }}</td>
                <td class="px-4 py-2 border border-gray-200">{{ row.outlet_name || '-' }}</td>
                <td class="px-4 py-2 border border-gray-200">
                  <span class="font-medium">{{ row.promo_name }}</span>
                  <span v-if="row.promo_code" class="text-gray-500 text-sm"> ({{ row.promo_code }})</span>
                </td>
                <td class="px-4 py-2 border border-gray-200 text-right">{{ formatRupiah(row.order_discount) }}</td>
                <td class="px-4 py-2 border border-gray-200 text-right">{{ formatRupiah(row.order_grand_total) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="flex justify-between items-center mt-4" v-if="total > 0">
          <div class="text-sm text-gray-600">
            Menampilkan {{ startIndex + 1 }} - {{ endIndex }} dari {{ total }} data
          </div>
          <div class="flex gap-1">
            <button
              @click="prevPage"
              :disabled="page === 1"
              class="px-3 py-1 rounded border text-sm disabled:opacity-50"
              :class="page === 1 ? 'bg-gray-200 text-gray-400' : 'bg-white text-blue-700 hover:bg-blue-50'"
            >
              &lt;
            </button>
            <span class="px-2">Halaman {{ page }} / {{ totalPages }}</span>
            <button
              @click="nextPage"
              :disabled="page === totalPages"
              class="px-3 py-1 rounded border text-sm disabled:opacity-50"
              :class="page === totalPages ? 'bg-gray-200 text-gray-400' : 'bg-white text-blue-700 hover:bg-blue-50'"
            >
              &gt;
            </button>
          </div>
        </div>
      </div>
      </div>

      <!-- Tab: Diskon Bank & Lainnya -->
      <div v-show="activeTab === 'global'">
        <div class="mb-6 p-4 bg-violet-50 rounded-xl border border-violet-200">
          <h2 class="text-lg font-semibold text-violet-900 mb-2">Rekap Diskon Global (Bank & Lainnya)</h2>
          <div class="flex flex-wrap gap-6 text-sm">
            <div>
              <span class="text-violet-600">Total Transaksi</span>
              <span class="font-bold text-violet-900 ml-2">{{ summaryGlobal?.total_transaksi ?? 0 }}</span>
            </div>
            <div>
              <span class="text-violet-600">Total Nominal Diskon</span>
              <span class="font-bold text-violet-900 ml-2">{{ formatRupiah(summaryGlobal?.total_nominal) }}</span>
            </div>
          </div>
        </div>
        <div class="bg-white rounded-xl shadow overflow-x-auto">
          <table class="min-w-full border border-gray-300">
            <thead>
              <tr class="bg-violet-600 text-white">
                <th class="px-4 py-2 border border-gray-300">Tanggal</th>
                <th class="px-4 py-2 border border-gray-300">No. Order</th>
                <th class="px-4 py-2 border border-gray-300">Paid Number</th>
                <th class="px-4 py-2 border border-gray-300">Nama Outlet</th>
                <th class="px-4 py-2 border border-gray-300 text-right">Grand Total</th>
                <th class="px-4 py-2 border border-gray-300 text-right">Nominal Diskon</th>
                <th class="px-4 py-2 border border-gray-300">Alasan / Keterangan</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!detailGlobal?.length">
                <td colspan="7" class="text-center py-10 text-gray-400">Tidak ada data.</td>
              </tr>
              <tr v-for="row in detailGlobal" :key="row.id" class="hover:bg-gray-50">
                <td class="px-4 py-2 border border-gray-200">{{ formatDate(row.created_at) }}</td>
                <td class="px-4 py-2 border border-gray-200">
                  <button
                    type="button"
                    @click="openOrderDetail(row.id)"
                    class="text-blue-600 hover:text-blue-800 underline font-medium text-left"
                  >
                    {{ row.nomor }}
                  </button>
                </td>
                <td class="px-4 py-2 border border-gray-200">{{ row.paid_number || '-' }}</td>
                <td class="px-4 py-2 border border-gray-200">{{ row.outlet_name || '-' }}</td>
                <td class="px-4 py-2 border border-gray-200 text-right">{{ formatRupiah(row.grand_total) }}</td>
                <td class="px-4 py-2 border border-gray-200 text-right font-medium text-violet-700">{{ formatRupiah(row.manual_discount_amount) }}</td>
                <td class="px-4 py-2 border border-gray-200">{{ row.manual_discount_reason || '-' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="flex justify-between items-center mt-4" v-if="totalGlobal > 0">
          <div class="text-sm text-gray-600">
            Menampilkan {{ startIndexGlobal + 1 }} - {{ endIndexGlobal }} dari {{ totalGlobal }} data
          </div>
          <div class="flex gap-1">
            <button
              @click="prevPageGlobal"
              :disabled="pageGlobal === 1"
              class="px-3 py-1 rounded border text-sm disabled:opacity-50"
              :class="pageGlobal === 1 ? 'bg-gray-200 text-gray-400' : 'bg-white text-blue-700 hover:bg-blue-50'"
            >
              &lt;
            </button>
            <span class="px-2">Halaman {{ pageGlobal }} / {{ totalPagesGlobal }}</span>
            <button
              @click="nextPageGlobal"
              :disabled="pageGlobal === totalPagesGlobal"
              class="px-3 py-1 rounded border text-sm disabled:opacity-50"
              :class="pageGlobal === totalPagesGlobal ? 'bg-gray-200 text-gray-400' : 'bg-white text-blue-700 hover:bg-blue-50'"
            >
              &gt;
            </button>
          </div>
        </div>
      </div>

      <!-- Modal detail order + items -->
      <OrderDetailModal
        v-if="showOrderDetailModal && selectedOrderDetail"
        :order="selectedOrderDetail"
        @close="showOrderDetailModal = false"
      />
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import OrderDetailModal from './OrderDetailModal.vue';

const props = defineProps({
  detail: Array,
  summary: Array,
  detailGlobal: Array,
  summaryGlobal: Object,
  outlets: Array,
  filters: Object,
  total: Number,
  totalGlobal: Number,
  perPage: Number,
  page: Number,
  perPageGlobal: Number,
  pageGlobal: Number,
});

const activeTab = ref('promo');
const showOrderDetailModal = ref(false);
const selectedOrderDetail = ref(null);
const loadingOrderDetail = ref(false);

// Normalize outlets: backend mengirim [{ value, label }]; fallback jika dapat array string
const outletOptions = computed(() => {
  const raw = props.outlets || [];
  return raw.map((opt) =>
    typeof opt === 'object' && opt !== null && 'value' in opt
      ? { value: opt.value, label: opt.label ?? opt.value }
      : { value: opt, label: opt }
  );
});

const dateFrom = ref(props.filters?.date_from || '');
const dateTo = ref(props.filters?.date_to || '');
const kodeOutlet = ref(props.filters?.kode_outlet || '');
const search = ref(props.filters?.search || '');
const perPage = ref(props.perPage ?? 25);
const page = ref(props.page ?? 1);
const perPageGlobal = ref(props.perPageGlobal ?? 25);
const pageGlobal = ref(props.pageGlobal ?? 1);
const loadingReload = ref(false);

const startIndex = computed(() => (page.value - 1) * perPage.value);
const endIndex = computed(() => Math.min(startIndex.value + (props.detail?.length ?? 0), props.total ?? 0));
const totalPages = computed(() => Math.ceil((props.total ?? 0) / perPage.value) || 1);

const startIndexGlobal = computed(() => (pageGlobal.value - 1) * perPageGlobal.value);
const endIndexGlobal = computed(() => Math.min(startIndexGlobal.value + (props.detailGlobal?.length ?? 0), props.totalGlobal ?? 0));
const totalPagesGlobal = computed(() => Math.ceil((props.totalGlobal ?? 0) / perPageGlobal.value) || 1);

const summaryTotalPemakaian = computed(() =>
  (props.summary ?? []).reduce((s, r) => s + (Number(r.jumlah_pemakaian) || 0), 0)
);
const summaryTotalDiscount = computed(() =>
  (props.summary ?? []).reduce((s, r) => s + (Number(r.total_discount) || 0), 0)
);

const exportQueryParams = computed(() => {
  const p = {};
  if (dateFrom.value) p.date_from = dateFrom.value;
  if (dateTo.value) p.date_to = dateTo.value;
  if (kodeOutlet.value) p.kode_outlet = kodeOutlet.value;
  if (search.value) p.search = search.value;
  return new URLSearchParams(p).toString();
});
const exportPromoUrl = computed(() => {
  const base = route('report.rekap-diskon.export-promo');
  return exportQueryParams.value ? `${base}?${exportQueryParams.value}` : base;
});
const exportGlobalUrl = computed(() => {
  const base = route('report.rekap-diskon.export-global');
  return exportQueryParams.value ? `${base}?${exportQueryParams.value}` : base;
});

function prevPage() {
  if (page.value > 1) {
    page.value--;
    reloadData();
  }
}
function nextPage() {
  if (page.value < totalPages.value) {
    page.value++;
    reloadData();
  }
}

function prevPageGlobal() {
  if (pageGlobal.value > 1) {
    pageGlobal.value--;
    reloadData();
  }
}
function nextPageGlobal() {
  if (pageGlobal.value < totalPagesGlobal.value) {
    pageGlobal.value++;
    reloadData();
  }
}

watch([dateFrom, dateTo, kodeOutlet, search, perPage], () => {
  page.value = 1;
});
watch([dateFrom, dateTo, kodeOutlet, search, perPageGlobal], () => {
  pageGlobal.value = 1;
});

function reloadData() {
  loadingReload.value = true;
  router.get('/report-rekap-diskon', {
    date_from: dateFrom.value,
    date_to: dateTo.value,
    kode_outlet: kodeOutlet.value,
    search: search.value,
    perPage: perPage.value,
    page: page.value,
    perPageGlobal: perPageGlobal.value,
    pageGlobal: pageGlobal.value,
  }, {
    preserveState: true,
    preserveScroll: true,
    onFinish: () => (loadingReload.value = false),
  });
}

async function openOrderDetail(orderId) {
  if (!orderId) return;
  loadingOrderDetail.value = true;
  selectedOrderDetail.value = null;
  showOrderDetailModal.value = false;
  try {
    const { data } = await axios.get(`/api/report-rekap-diskon/order/${encodeURIComponent(orderId)}`);
    selectedOrderDetail.value = data;
    showOrderDetailModal.value = true;
  } catch (e) {
    selectedOrderDetail.value = { nomor: orderId, items: [], error: e?.response?.data?.error || 'Gagal memuat detail order.' };
    showOrderDetailModal.value = true;
  } finally {
    loadingOrderDetail.value = false;
  }
}

function formatRupiah(value) {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
  }).format(value || 0);
}

function formatDate(val) {
  if (!val) return '-';
  const d = new Date(val);
  if (isNaN(d.getTime())) return val;
  return d.toLocaleDateString('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}
</script>
