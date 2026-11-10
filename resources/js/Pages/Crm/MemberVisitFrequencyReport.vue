<template>
  <AppLayout title="Member Visit Frequency">
    <div class="w-full max-w-none py-6 px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-chart-simple text-sky-600"></i>
            Member Visit Frequency & Repeat Ratio
          </h1>
          <p class="text-sm text-gray-500 mt-1">
            Formula utama: <span class="font-medium text-gray-700">Total Member ÷ Total Pax</span>
            — dari bill member berstatus paid.
          </p>
        </div>
        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-white shadow hover:bg-emerald-700 disabled:opacity-60"
          :disabled="exporting"
          @click="exportExcel"
        >
          <i :class="exporting ? 'fa-solid fa-spinner fa-spin' : 'fa-solid fa-file-excel'"></i>
          {{ exporting ? 'Exporting...' : 'Export Excel' }}
        </button>
      </div>

      <div class="bg-white rounded-xl shadow p-4 mb-4">
        <form @submit.prevent="applyFilters" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-3">
          <div>
            <label class="mb-1 block text-xs text-gray-500">Dari tanggal</label>
            <input v-model="filterForm.date_from" type="date" class="w-full rounded-lg border-gray-300" />
          </div>
          <div>
            <label class="mb-1 block text-xs text-gray-500">Sampai tanggal</label>
            <input v-model="filterForm.date_to" type="date" class="w-full rounded-lg border-gray-300" />
          </div>
          <div class="lg:col-span-2">
            <label class="mb-1 block text-xs text-gray-500">Outlet</label>
            <select v-model="filterForm.outlet" class="w-full rounded-lg border-gray-300">
              <option value="">Semua Outlet</option>
              <option v-for="o in outlets" :key="o.id" :value="o.id">{{ o.name }}</option>
            </select>
          </div>
          <div class="flex items-end gap-2">
            <button type="submit" class="px-4 py-2 rounded-lg bg-sky-600 text-white hover:bg-sky-700">Cari</button>
            <button type="button" class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50" @click="resetFilters">Reset</button>
          </div>
        </form>
      </div>

      <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3 mb-6">
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-3">
          <div class="text-xs text-gray-500 inline-flex items-center gap-1">
            Unique Member
            <InfoTip :text="tips.member" />
          </div>
          <div class="text-xl font-bold text-gray-900">{{ formatNumber(summary.unique_members) }}</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-3">
          <div class="text-xs text-gray-500 inline-flex items-center gap-1">
            Total Pax
            <InfoTip :text="tips.pax" />
          </div>
          <div class="text-xl font-bold text-gray-900">{{ formatNumber(summary.total_pax) }}</div>
        </div>
        <div class="bg-white rounded-xl border border-sky-200 shadow-sm px-4 py-3 ring-1 ring-sky-100">
          <div class="text-xs text-sky-700 inline-flex items-center gap-1">
            Member ÷ Pax
            <InfoTip :text="tips.memberPerPax" />
          </div>
          <div class="text-xl font-bold text-sky-800">{{ summary.member_per_pax }}</div>
          <div class="text-xs text-sky-600">{{ summary.member_per_pax_pct }}%</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-3">
          <div class="text-xs text-gray-500 inline-flex items-center gap-1">
            Visit Frequency
            <InfoTip :text="tips.visitFreq" />
          </div>
          <div class="text-xl font-bold text-indigo-700">{{ summary.visit_frequency }}</div>
          <div class="text-xs text-gray-500">visits / member</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-3">
          <div class="text-xs text-gray-500 inline-flex items-center gap-1">
            Repeat Ratio
            <InfoTip :text="tips.repeat" />
          </div>
          <div class="text-xl font-bold text-emerald-700">{{ summary.repeat_ratio }}%</div>
          <div class="text-xs text-gray-500">{{ formatNumber(summary.repeat_members) }} repeat members</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-3">
          <div class="text-xs text-gray-500 inline-flex items-center gap-1">
            Total Visits / Revenue
            <InfoTip :text="tips.visitsRevenue" />
          </div>
          <div class="text-lg font-bold text-gray-900">{{ formatNumber(summary.total_visits) }}</div>
          <div class="text-xs text-purple-700 font-medium">{{ summary.revenue_formatted }}</div>
        </div>
      </div>

      <div class="grid grid-cols-1 xl:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow p-4 xl:col-span-1">
          <h2 class="text-sm font-semibold text-gray-800 mb-3 flex items-center gap-2">
            <i class="fa-solid fa-layer-group text-indigo-500"></i>
            Distribusi Frekuensi Visit
          </h2>
          <p class="text-xs text-gray-500 mb-2">Klik baris untuk lihat detail member & lokasi transaksi.</p>
          <div class="space-y-2">
            <button
              v-for="bucket in frequencyBuckets"
              :key="bucket.key"
              type="button"
              class="w-full flex items-center justify-between rounded-lg border border-gray-100 px-3 py-2 text-left transition hover:border-indigo-300 hover:bg-indigo-50 disabled:opacity-50 disabled:hover:bg-white disabled:hover:border-gray-100"
              :disabled="!bucket.members"
              @click="openBucketDetail(bucket)"
            >
              <div>
                <div class="text-sm font-medium text-gray-800">{{ bucket.label }}</div>
                <div class="text-xs text-gray-500">{{ bucket.pct }}% dari member</div>
              </div>
              <div class="flex items-center gap-2">
                <div class="text-lg font-bold text-indigo-700">{{ formatNumber(bucket.members) }}</div>
                <i class="fa-solid fa-chevron-right text-xs text-indigo-400"></i>
              </div>
            </button>
          </div>
        </div>

        <div class="bg-white rounded-xl shadow overflow-x-auto xl:col-span-2">
          <div class="px-4 py-3 border-b">
            <h2 class="text-sm font-semibold text-gray-800 flex items-center gap-2">
              <i class="fa-solid fa-store text-sky-500"></i>
              Breakdown per Outlet
            </h2>
            <p class="text-xs text-gray-500 mt-1">Klik baris outlet untuk lihat member & transaksi di outlet tersebut.</p>
          </div>
          <table class="min-w-full text-sm">
            <thead class="bg-gray-50 border-b">
              <tr>
                <th class="px-3 py-2 text-left font-semibold text-gray-700">
                  <span class="inline-flex items-center gap-1">Outlet <InfoTip :text="tips.outlet" /></span>
                </th>
                <th class="px-3 py-2 text-right font-semibold text-gray-700">
                  <span class="inline-flex items-center justify-end gap-1">Member <InfoTip :text="tips.member" /></span>
                </th>
                <th class="px-3 py-2 text-right font-semibold text-gray-700">
                  <span class="inline-flex items-center justify-end gap-1">Pax <InfoTip :text="tips.pax" /></span>
                </th>
                <th class="px-3 py-2 text-right font-semibold text-gray-700">
                  <span class="inline-flex items-center justify-end gap-1">Member÷Pax <InfoTip :text="tips.memberPerPax" /></span>
                </th>
                <th class="px-3 py-2 text-right font-semibold text-gray-700">
                  <span class="inline-flex items-center justify-end gap-1">Visits <InfoTip :text="tips.visits" /></span>
                </th>
                <th class="px-3 py-2 text-right font-semibold text-gray-700">
                  <span class="inline-flex items-center justify-end gap-1">Visit Freq <InfoTip :text="tips.visitFreq" /></span>
                </th>
                <th class="px-3 py-2 text-right font-semibold text-gray-700">
                  <span class="inline-flex items-center justify-end gap-1">Repeat % <InfoTip :text="tips.repeat" /></span>
                </th>
                <th class="px-3 py-2 text-right font-semibold text-gray-700">
                  <span class="inline-flex items-center justify-end gap-1">Revenue <InfoTip :text="tips.revenue" /></span>
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!byOutlet.length">
                <td colspan="8" class="px-4 py-8 text-center text-gray-500">Tidak ada data untuk filter ini.</td>
              </tr>
              <tr
                v-for="row in byOutlet"
                :key="row.kode_outlet"
                class="border-b border-gray-100 hover:bg-sky-50 cursor-pointer"
                @click="openOutletDetail(row)"
              >
                <td class="px-3 py-2">
                  <div class="font-medium text-sky-800 underline decoration-sky-300 underline-offset-2">{{ row.outlet_name }}</div>
                  <div class="text-xs text-gray-500 font-mono">{{ row.kode_outlet }}</div>
                </td>
                <td class="px-3 py-2 text-right">{{ formatNumber(row.unique_members) }}</td>
                <td class="px-3 py-2 text-right">{{ formatNumber(row.total_pax) }}</td>
                <td class="px-3 py-2 text-right font-semibold text-sky-700">{{ row.member_per_pax }}</td>
                <td class="px-3 py-2 text-right">{{ formatNumber(row.total_visits) }}</td>
                <td class="px-3 py-2 text-right text-indigo-700">{{ row.visit_frequency }}</td>
                <td class="px-3 py-2 text-right text-emerald-700">{{ row.repeat_ratio }}%</td>
                <td class="px-3 py-2 text-right whitespace-nowrap">{{ row.revenue_formatted }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Modal detail bucket -->
      <div v-if="showBucketModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-5xl max-h-[90vh] overflow-hidden flex flex-col">
          <div class="bg-gradient-to-r from-indigo-600 to-sky-600 text-white px-5 py-4 flex items-start justify-between gap-3">
            <div>
              <h3 class="text-lg font-bold">Detail Member — {{ activeBucketLabel }}</h3>
              <p class="text-sm text-indigo-100">
                {{ drilldownSource === 'outlet' ? 'Outlet' : 'Bucket' }} ·
                {{ formatNumber(bucketMembers.length) }} member
                <span v-if="bucketLimited"> (ditampilkan maks. 200)</span>
              </p>
            </div>
            <button type="button" class="text-white/90 hover:text-white" @click="closeBucketDetail">
              <i class="fa-solid fa-times text-xl"></i>
            </button>
          </div>

          <div class="p-4 border-b bg-slate-50">
            <input
              v-model="bucketSearch"
              type="text"
              placeholder="Cari nama / member ID / phone di hasil..."
              class="w-full rounded-lg border-gray-300 text-sm"
            />
          </div>

          <div class="overflow-y-auto p-4 flex-1">
            <div v-if="loadingBucket" class="py-12 text-center text-gray-500">
              <i class="fa-solid fa-spinner fa-spin text-2xl text-indigo-600 mb-2"></i>
              <div>Memuat detail member...</div>
            </div>
            <div v-else-if="!filteredBucketMembers.length" class="py-12 text-center text-gray-500">
              Tidak ada member di bucket ini.
            </div>
            <div v-else class="space-y-3">
              <div
                v-for="member in filteredBucketMembers"
                :key="member.member_id"
                class="rounded-xl border border-gray-200 overflow-hidden"
              >
                <button
                  type="button"
                  class="w-full px-4 py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-left hover:bg-slate-50"
                  @click="toggleMemberExpand(member.member_id)"
                >
                  <div>
                    <div class="font-semibold text-gray-900">{{ member.member_name }}</div>
                    <div class="text-xs text-gray-500 font-mono">{{ member.member_id }}</div>
                    <div v-if="member.phone" class="text-xs text-gray-400">{{ member.phone }}</div>
                  </div>
                  <div class="flex flex-wrap items-center gap-3 text-sm">
                    <span class="rounded-full bg-indigo-100 text-indigo-800 px-2 py-0.5 font-semibold">{{ member.visit_count }} visits</span>
                    <span class="text-gray-600">Pax {{ formatNumber(member.total_pax) }}</span>
                    <span class="text-purple-700 font-medium">{{ member.revenue_formatted }}</span>
                    <i :class="expandedMembers.has(member.member_id) ? 'fa-solid fa-chevron-up' : 'fa-solid fa-chevron-down'" class="text-gray-400"></i>
                  </div>
                </button>

                <div v-if="expandedMembers.has(member.member_id)" class="border-t bg-slate-50 px-4 py-3">
                  <div class="text-xs text-gray-500 mb-2">
                    First: {{ member.first_visit_at || '-' }} · Last: {{ member.last_visit_at || '-' }}
                  </div>
                  <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
                    <table class="min-w-full text-xs">
                      <thead class="bg-gray-100">
                        <tr>
                          <th class="px-3 py-2 text-left">Waktu</th>
                          <th class="px-3 py-2 text-left">Outlet</th>
                          <th class="px-3 py-2 text-left">Paid Number</th>
                          <th class="px-3 py-2 text-right">Pax</th>
                          <th class="px-3 py-2 text-right">Grand Total</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-for="(visit, idx) in member.visits" :key="visit.order_id || idx" class="border-t border-gray-100">
                          <td class="px-3 py-2 whitespace-nowrap">{{ visit.created_at || '-' }}</td>
                          <td class="px-3 py-2">
                            <div>{{ visit.outlet_name }}</div>
                            <div class="text-gray-400 font-mono">{{ visit.kode_outlet }}</div>
                          </td>
                          <td class="px-3 py-2 font-mono text-blue-700">{{ visit.paid_number || visit.nomor || '-' }}</td>
                          <td class="px-3 py-2 text-right">{{ visit.pax }}</td>
                          <td class="px-3 py-2 text-right font-medium">{{ visit.grand_total_formatted }}</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import { computed, defineComponent, h, reactive, ref } from 'vue';
import axios from 'axios';

const InfoTip = defineComponent({
  name: 'InfoTip',
  props: {
    text: { type: String, required: true },
  },
  setup(props) {
    return () =>
      h(
        'span',
        { class: 'relative inline-flex group align-middle' },
        [
          h('i', {
            class: 'fa-solid fa-circle-info text-[11px] text-gray-400 cursor-help group-hover:text-sky-600',
            'aria-label': 'info',
          }),
          h(
            'span',
            {
              class:
                'pointer-events-none absolute z-30 hidden group-hover:block w-64 sm:w-72 -right-1 top-5 rounded-lg bg-gray-900 text-white text-[11px] leading-relaxed px-3 py-2 shadow-lg whitespace-normal text-left font-normal',
            },
            props.text
          ),
        ]
      );
  },
});

const tips = {
  outlet: 'Nama outlet dari tbl_data_outlet (join orders.kode_outlet = qr_code). Hanya bill ber-member.',
  member:
    'Sumber: orders (status=paid, member_id terisi). Perhitungan: COUNT(DISTINCT member_id) di periode & outlet filter.',
  pax:
    'Sumber: kolom orders.pax pada bill member paid. Perhitungan: SUM(pax) di periode & outlet filter.',
  memberPerPax:
    'Rumus: Unique Member ÷ Total Pax. Semakin tinggi = semakin banyak member relatif terhadap jumlah tamu/pax.',
  visits:
    'Sumber: jumlah bill/orders member berstatus paid. Perhitungan: COUNT(*) bill di periode & outlet filter.',
  visitFreq:
    'Rumus Visit Frequency: Total Visits ÷ Unique Member. Rata-rata berapa kali tiap member datang di periode ini.',
  repeat:
    'Rumus Repeat %: (member dengan ≥2 visit ÷ Unique Member) × 100. Member yang kembali datang di periode filter.',
  revenue:
    'Sumber: orders.grand_total bill member paid. Perhitungan: SUM(grand_total) di periode & outlet filter.',
  visitsRevenue:
    'Visits = jumlah bill member paid. Revenue = SUM(grand_total) bill tersebut, di periode & outlet filter.',
};

const props = defineProps({
  summary: {
    type: Object,
    default: () => ({
      unique_members: 0,
      total_visits: 0,
      total_pax: 0,
      revenue: 0,
      revenue_formatted: 'Rp 0',
      member_per_pax: 0,
      member_per_pax_pct: 0,
      visit_frequency: 0,
      repeat_members: 0,
      repeat_ratio: 0,
    }),
  },
  byOutlet: { type: Array, default: () => [] },
  frequencyBuckets: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
  outlets: { type: Array, default: () => [] },
});

const filterForm = reactive({
  date_from: props.filters.date_from || '',
  date_to: props.filters.date_to || '',
  outlet: props.filters.outlet || '',
});

const exporting = ref(false);
const showBucketModal = ref(false);
const loadingBucket = ref(false);
const drilldownSource = ref('bucket');
const activeBucketLabel = ref('');
const bucketMembers = ref([]);
const bucketLimited = ref(false);
const bucketSearch = ref('');
const expandedMembers = ref(new Set());

const formatNumber = (n) => Number(n || 0).toLocaleString('id-ID');

const filteredBucketMembers = computed(() => {
  const q = bucketSearch.value.trim().toLowerCase();
  if (!q) return bucketMembers.value;
  return bucketMembers.value.filter((m) => {
    const hay = `${m.member_name} ${m.member_id} ${m.phone} ${m.email}`.toLowerCase();
    return hay.includes(q);
  });
});

const applyFilters = () => {
  router.get(route('crm.member-visit-frequency-report'), { ...filterForm }, { preserveState: true, replace: true });
};

const resetFilters = () => {
  filterForm.date_from = '';
  filterForm.date_to = '';
  filterForm.outlet = '';
  applyFilters();
};

const exportExcel = () => {
  exporting.value = true;
  const params = new URLSearchParams();
  Object.entries(filterForm).forEach(([key, value]) => {
    if (value !== '' && value !== null && value !== undefined) {
      params.append(key, String(value));
    }
  });
  window.location.href = `${route('crm.member-visit-frequency-report.export')}?${params.toString()}`;
  setTimeout(() => {
    exporting.value = false;
  }, 2000);
};

const resetDrilldownState = (label) => {
  activeBucketLabel.value = label;
  showBucketModal.value = true;
  loadingBucket.value = true;
  bucketMembers.value = [];
  bucketLimited.value = false;
  bucketSearch.value = '';
  expandedMembers.value = new Set();
};

const openBucketDetail = async (bucket) => {
  drilldownSource.value = 'bucket';
  resetDrilldownState(bucket.label);

  try {
    const { data } = await axios.get(route('crm.member-visit-frequency-report.bucket-members'), {
      params: {
        bucket: bucket.key,
        date_from: filterForm.date_from,
        date_to: filterForm.date_to,
        outlet: filterForm.outlet,
      },
    });
    if (data.status === 'success') {
      bucketMembers.value = data.members || [];
      bucketLimited.value = !!data.limited;
      activeBucketLabel.value = data.label || bucket.label;
    }
  } catch (e) {
    bucketMembers.value = [];
  } finally {
    loadingBucket.value = false;
  }
};

const openOutletDetail = async (row) => {
  drilldownSource.value = 'outlet';
  resetDrilldownState(row.outlet_name || row.kode_outlet);

  try {
    const { data } = await axios.get(route('crm.member-visit-frequency-report.outlet-members'), {
      params: {
        kode_outlet: row.kode_outlet,
        date_from: filterForm.date_from,
        date_to: filterForm.date_to,
      },
    });
    if (data.status === 'success') {
      bucketMembers.value = data.members || [];
      bucketLimited.value = !!data.limited;
      activeBucketLabel.value = data.label || row.outlet_name || row.kode_outlet;
    }
  } catch (e) {
    bucketMembers.value = [];
  } finally {
    loadingBucket.value = false;
  }
};

const closeBucketDetail = () => {
  showBucketModal.value = false;
  bucketMembers.value = [];
  expandedMembers.value = new Set();
};

const toggleMemberExpand = (memberId) => {
  const next = new Set(expandedMembers.value);
  if (next.has(memberId)) next.delete(memberId);
  else next.add(memberId);
  expandedMembers.value = next;
};
</script>
