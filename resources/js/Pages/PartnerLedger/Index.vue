<template>
  <AppLayout title="Hutang & Piutang">
    <div class="py-8 px-4">
      <div class="max-w-7xl mx-auto">
        <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          <div>
            <h1 class="text-3xl font-bold text-gray-800 flex items-center gap-2">
              <i class="fa fa-scale-balanced text-indigo-500"></i>
              Hutang & Piutang
            </h1>
            <p class="text-gray-600 mt-2">Sub-ledger hutang supplier dan piutang outlet</p>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
          <div class="bg-white rounded-xl shadow-lg p-6">
            <p class="text-sm text-gray-600">Total Saldo ({{ filters.ledger_type === 'payable' ? 'Hutang' : 'Piutang' }})</p>
            <p class="text-2xl font-bold" :class="filters.ledger_type === 'payable' ? 'text-red-600' : 'text-green-600'">
              {{ formatCurrency(summary.total_balance) }}
            </p>
          </div>
          <div class="bg-white rounded-xl shadow-lg p-6">
            <p class="text-sm text-gray-600">Partner dengan Saldo</p>
            <p class="text-2xl font-bold text-gray-800">{{ summary.partner_count }}</p>
          </div>
        </div>

        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
          <form @submit.prevent="applyFilters" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Jenis</label>
              <select v-model="filters.ledger_type" class="w-full border border-gray-300 rounded-md px-3 py-2">
                <option value="payable">Hutang (Supplier)</option>
                <option value="receivable">Piutang (Outlet)</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                {{ filters.ledger_type === 'payable' ? 'Supplier' : 'Outlet' }}
              </label>
              <select v-model="filters.partner_id" class="w-full border border-gray-300 rounded-md px-3 py-2">
                <option value="">Semua</option>
                <option
                  v-for="partner in partnerOptions"
                  :key="partner.id"
                  :value="partner.id"
                >
                  {{ partner.name }}
                </option>
              </select>
            </div>
            <div class="md:col-span-2 flex items-end gap-2">
              <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700">
                <i class="fa fa-filter mr-2"></i>Filter
              </button>
              <button type="button" @click="resetFilters" class="bg-gray-500 text-white px-6 py-2 rounded-md hover:bg-gray-600">
                Reset
              </button>
            </div>
          </form>
        </div>

        <div class="bg-white rounded-xl shadow-lg overflow-hidden mb-6">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Partner</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Saldo</th>
                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Aksi</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="ledger in ledgers.data" :key="ledger.id">
                <td class="px-6 py-4 text-sm text-gray-900">{{ ledger.partner_name }}</td>
                <td class="px-6 py-4 text-sm text-right font-semibold">{{ formatCurrency(ledger.balance) }}</td>
                <td class="px-6 py-4 text-center">
                  <Link
                    :href="route('partner-ledger.show', ledger.id)"
                    class="text-blue-600 hover:text-blue-800 text-sm"
                  >
                    Detail
                  </Link>
                </td>
              </tr>
              <tr v-if="!ledgers.data.length">
                <td colspan="3" class="px-6 py-8 text-center text-gray-500">Belum ada data sub-ledger.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="flex justify-center gap-2" v-if="ledgers.links?.length > 3">
          <Link
            v-for="link in ledgers.links"
            :key="link.label"
            :href="link.url || '#'"
            class="px-3 py-1 rounded border text-sm"
            :class="link.active ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-700 border-gray-300'"
            v-html="link.label"
          />
        </div>

        <div class="bg-white rounded-xl shadow-lg p-6 mt-6">
          <h2 class="text-lg font-semibold text-gray-800 mb-4">Input Saldo Awal Manual</h2>
          <form @submit.prevent="submitOpeningBalance" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Jenis</label>
              <select v-model="openingForm.ledger_type" class="w-full border border-gray-300 rounded-md px-3 py-2">
                <option value="payable">Hutang</option>
                <option value="receivable">Piutang</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Partner</label>
              <select v-model="openingForm.partner_id" class="w-full border border-gray-300 rounded-md px-3 py-2" required>
                <option value="">Pilih...</option>
                <option v-for="partner in openingPartnerOptions" :key="partner.id" :value="partner.id">
                  {{ partner.name }}
                </option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal</label>
              <input v-model="openingForm.entry_date" type="date" class="w-full border border-gray-300 rounded-md px-3 py-2" required />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Jumlah</label>
              <input v-model="openingForm.amount" type="number" min="0.01" step="0.01" class="w-full border border-gray-300 rounded-md px-3 py-2" required />
            </div>
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-1">Keterangan</label>
              <input v-model="openingForm.description" type="text" class="w-full border border-gray-300 rounded-md px-3 py-2" placeholder="Opsional" />
            </div>
            <div class="flex items-end">
              <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-md hover:bg-indigo-700 w-full md:w-auto">
                Simpan Saldo Awal
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed, reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  ledgers: Object,
  summary: Object,
  filters: Object,
  suppliers: Array,
  outlets: Array,
});

const filters = reactive({
  ledger_type: props.filters.ledger_type || 'payable',
  partner_id: props.filters.partner_id || '',
});

const openingForm = reactive({
  ledger_type: 'payable',
  partner_type: 'supplier',
  partner_id: '',
  amount: '',
  entry_date: new Date().toISOString().slice(0, 10),
  description: '',
});

const partnerOptions = computed(() => {
  return filters.ledger_type === 'payable' ? props.suppliers : props.outlets;
});

const openingPartnerOptions = computed(() => {
  return openingForm.ledger_type === 'payable' ? props.suppliers : props.outlets;
});

function formatCurrency(value) {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
  }).format(Number(value || 0));
}

function applyFilters() {
  router.get(route('partner-ledger.index'), {
    ledger_type: filters.ledger_type,
    partner_id: filters.partner_id || undefined,
  }, { preserveState: true });
}

function resetFilters() {
  filters.ledger_type = 'payable';
  filters.partner_id = '';
  applyFilters();
}

function submitOpeningBalance() {
  openingForm.partner_type = openingForm.ledger_type === 'payable' ? 'supplier' : 'outlet';

  router.post(route('partner-ledger.opening-balance'), { ...openingForm }, {
    preserveScroll: true,
    onSuccess: () => {
      openingForm.amount = '';
      openingForm.description = '';
    },
  });
}
</script>
