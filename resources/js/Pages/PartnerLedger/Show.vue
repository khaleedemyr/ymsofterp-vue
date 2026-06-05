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
            — Saldo: <span class="font-semibold">{{ formatCurrency(ledger.balance) }}</span>
          </p>
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
                <td class="px-6 py-4 text-sm text-gray-700">{{ entry.entry_date }}</td>
                <td class="px-6 py-4 text-sm">
                  <span class="px-2 py-1 rounded text-xs font-medium" :class="entryTypeClass(entry.entry_type)">
                    {{ entry.entry_type }}
                  </span>
                </td>
                <td class="px-6 py-4 text-sm text-gray-700">{{ entry.description || '-' }}</td>
                <td class="px-6 py-4 text-sm text-gray-500">
                  <span v-if="entry.source_type">{{ entry.source_type }} #{{ entry.source_id }}</span>
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
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
  ledger: Object,
  entries: Object,
});

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
</script>
