<template>
  <AppLayout :title="`Sub-Ledger: ${ledger.partner_name}`">
    <div class="py-8 px-4">
      <div class="max-w-7xl mx-auto">
        <div class="mb-6">
          <Link :href="route('partner-ledger.index', { ledger_type: ledger.ledger_type })" class="text-blue-600 hover:text-blue-800 text-sm">
            <i class="fa fa-arrow-left mr-1"></i> Kembali
          </Link>
          <h1 class="text-3xl font-bold text-gray-800 mt-3">{{ ledger.partner_name }}</h1>
          <p class="text-gray-600 mt-1">
            {{ ledger.ledger_type === 'payable' ? 'Hutang Usaha' : 'Piutang Usaha' }}
            — Saldo: <span class="font-semibold" :class="ledger.balance > 0 ? 'text-red-600' : 'text-green-600'">{{ formatCurrency(ledger.balance) }}</span>
          </p>
        </div>

        <div v-if="$page.props.flash?.success" class="mb-4 p-4 rounded-lg bg-green-100 text-green-800 text-sm">
          {{ $page.props.flash.success }}
        </div>
        <div v-if="$page.props.flash?.error" class="mb-4 p-4 rounded-lg bg-red-100 text-red-800 text-sm">
          {{ $page.props.flash.error }}
        </div>

        <div v-if="ledger.balance > 0" class="bg-white rounded-xl shadow-lg p-6 mb-6 border border-indigo-100">
          <h2 class="text-lg font-semibold text-gray-800 mb-1">
            {{ ledger.ledger_type === 'payable' ? 'Pelunasan Hutang' : 'Penerimaan Piutang' }}
          </h2>
          <p class="text-sm text-gray-500 mb-4">
            Bisa bayar/terima <strong>sebagian</strong> atau <strong>lunas sekaligus</strong>. Sisa saldo: {{ formatCurrency(ledger.balance) }}
          </p>
          <form @submit.prevent="submitSettlement" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal</label>
              <input v-model="settlementForm.entry_date" type="date" class="w-full border border-gray-300 rounded-md px-3 py-2" required />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Jumlah</label>
              <div class="flex gap-2">
                <input
                  v-model="settlementForm.amount"
                  type="number"
                  min="0.01"
                  :max="ledger.balance"
                  step="0.01"
                  class="w-full border border-gray-300 rounded-md px-3 py-2"
                  required
                />
                <button
                  type="button"
                  @click="settlementForm.amount = ledger.balance"
                  class="whitespace-nowrap px-3 py-2 text-xs font-semibold rounded-md bg-gray-100 text-gray-700 hover:bg-gray-200"
                >
                  Lunas
                </button>
              </div>
              <p class="text-xs text-gray-500 mt-1">Maks: {{ formatCurrency(ledger.balance) }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Rekening Bank</label>
              <select v-model="settlementForm.bank_account_id" class="w-full border border-gray-300 rounded-md px-3 py-2" required>
                <option value="">Pilih rekening...</option>
                <option v-for="bank in bankAccounts" :key="bank.id" :value="bank.id">{{ bank.label }}</option>
              </select>
            </div>
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-1">Keterangan</label>
              <input v-model="settlementForm.description" type="text" class="w-full border border-gray-300 rounded-md px-3 py-2" placeholder="Opsional" />
            </div>
            <div class="flex items-end">
              <button
                type="submit"
                :disabled="settlementForm.processing"
                class="bg-indigo-600 text-white px-6 py-2 rounded-md hover:bg-indigo-700 disabled:opacity-50 w-full md:w-auto"
              >
                {{ settlementForm.processing ? 'Menyimpan...' : (ledger.ledger_type === 'payable' ? 'Simpan Pelunasan' : 'Simpan Penerimaan') }}
              </button>
            </div>
          </form>
        </div>

        <div class="bg-white rounded-xl shadow-lg overflow-hidden">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tanggal</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Jenis</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Keterangan</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Sumber</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Jumlah</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="entry in entries.data" :key="entry.id">
                <td class="px-6 py-4 text-sm text-gray-700">{{ formatDate(entry.entry_date) }}</td>
                <td class="px-6 py-4 text-sm">
                  <span class="px-2 py-1 rounded text-xs font-medium" :class="entryTypeClass(entry.entry_type)">
                    {{ entryTypeLabel(entry) }}
                  </span>
                </td>
                <td class="px-6 py-4 text-sm text-gray-700">{{ entry.description || '-' }}</td>
                <td class="px-6 py-4 text-sm text-gray-500">
                  <span v-if="entry.source_type">{{ sourceLabel(entry) }}</span>
                  <span v-else>-</span>
                </td>
                <td class="px-6 py-4 text-sm text-right font-semibold" :class="entry.amount >= 0 ? 'text-red-600' : 'text-green-600'">
                  {{ formatCurrency(entry.amount) }}
                </td>
              </tr>
              <tr v-if="!entries.data.length">
                <td colspan="5" class="px-6 py-8 text-center text-gray-500">Belum ada mutasi.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="flex justify-center gap-2 mt-6" v-if="entries.links?.length > 3">
          <Link
            v-for="link in entries.links"
            :key="link.label"
            :href="link.url || '#'"
            class="px-3 py-1 rounded border text-sm"
            :class="link.active ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-700 border-gray-300'"
            v-html="link.label"
          />
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  ledger: Object,
  entries: Object,
  bankAccounts: {
    type: Array,
    default: () => [],
  },
});

const settlementForm = useForm({
  amount: '',
  entry_date: new Date().toISOString().slice(0, 10),
  bank_account_id: '',
  description: '',
});

function submitSettlement() {
  settlementForm.post(route('partner-ledger.settlement', props.ledger.id), {
    preserveScroll: true,
    onSuccess: () => {
      settlementForm.reset('amount', 'description');
      settlementForm.entry_date = new Date().toISOString().slice(0, 10);
    },
  });
}

function formatDate(value) {
  if (!value) return '-';
  const str = String(value);
  const datePart = str.slice(0, 10);
  const [year, month, day] = datePart.split('-').map(Number);
  if (!year || !month || !day) return str;
  return new Date(year, month - 1, day).toLocaleDateString('id-ID', {
    day: '2-digit',
    month: 'long',
    year: 'numeric',
  });
}

function formatCurrency(value) {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
  }).format(Number(value || 0));
}

function entryTypeClass(type) {
  const map = {
    accrual: 'bg-red-100 text-red-700',
    settlement: 'bg-green-100 text-green-700',
    opening_balance: 'bg-blue-100 text-blue-700',
    reversal: 'bg-yellow-100 text-yellow-700',
    manual: 'bg-gray-100 text-gray-700',
  };

  return map[type] || 'bg-gray-100 text-gray-700';
}

function entryTypeLabel(entry) {
  if (entry.entry_type === 'settlement' && entry.source_type === 'manual_settlement') {
    return 'pelunasan manual';
  }

  return entry.entry_type;
}

function sourceLabel(entry) {
  if (entry.source_type === 'manual_settlement') {
    return `Pelunasan manual #${entry.source_id}`;
  }

  return `${entry.source_type} #${entry.source_id}`;
}
</script>
