<template>
  <AppLayout>
    <div class="w-full py-8 px-0">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-book"></i> Detail Entri Buku Bank
        </h1>
        <div class="flex gap-2">
          <button @click="goToEdit" class="bg-yellow-500 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
            <i class="fa fa-pencil-alt mr-1"></i> Edit
          </button>
          <button @click="goBack" class="bg-gray-500 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
            <i class="fa fa-arrow-left mr-1"></i> Kembali
          </button>
        </div>
      </div>

      <div class="bg-white rounded-2xl shadow-2xl p-6">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Bank Account</label>
            <div class="text-lg font-medium text-gray-900">
              {{ bankBook.bank_account?.bank_name }} - {{ bankBook.bank_account?.account_number }}
            </div>
            <div class="text-sm text-gray-500">
              {{ bankBook.bank_account?.outlet?.nama_outlet || 'Head Office' }}
            </div>
          </div>

          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Tanggal Transaksi</label>
            <div class="text-lg font-medium text-gray-900">{{ formatDate(bankBook.transaction_date) }}</div>
          </div>

          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Tipe Transaksi</label>
            <span 
              :class="bankBook.transaction_type === 'credit' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'"
              class="px-3 py-1 rounded-full text-sm font-semibold"
            >
              {{ bankBook.transaction_type === 'credit' ? 'Credit (Masuk)' : 'Debit (Keluar)' }}
            </span>
          </div>

          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Jumlah</label>
            <div 
              :class="bankBook.transaction_type === 'credit' ? 'text-green-600' : 'text-red-600'"
              class="text-2xl font-bold"
            >
              {{ bankBook.transaction_type === 'credit' ? '+' : '-' }}{{ formatCurrency(bankBook.amount) }}
            </div>
          </div>

          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Saldo Setelah Transaksi</label>
            <div class="text-2xl font-bold text-blue-600">{{ formatCurrency(bankBook.balance) }}</div>
          </div>

          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Referensi</label>
            <div v-if="bankBook.reference_type" class="text-sm">
              <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                {{ bankBook.reference_type }} #{{ bankBook.reference_id }}
              </span>
            </div>
            <span v-else class="text-gray-400">-</span>
          </div>

          <div class="lg:col-span-2">
            <label class="block text-sm font-semibold text-gray-700 mb-2">Keterangan</label>
            <div class="text-gray-900">{{ bankBook.description || '-' }}</div>
          </div>

          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Dibuat Oleh</label>
            <div class="text-sm text-gray-600">{{ bankBook.creator?.nama_lengkap || '-' }}</div>
            <div class="text-xs text-gray-400">{{ formatDateTime(bankBook.created_at) }}</div>
          </div>

          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Diupdate Oleh</label>
            <div class="text-sm text-gray-600">{{ bankBook.updater?.nama_lengkap || '-' }}</div>
            <div class="text-xs text-gray-400">{{ formatDateTime(bankBook.updated_at) }}</div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  bankBook: Object,
});

function goBack() {
  router.visit('/bank-books');
}

function goToEdit() {
  router.visit(`/bank-books/${props.bankBook.id}/edit`);
}

function formatDate(date) {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('id-ID', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
}

function formatDateTime(dateTime) {
  if (!dateTime) return '-';
  return new Date(dateTime).toLocaleString('id-ID', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function formatCurrency(amount) {
  if (!amount) return 'Rp 0';
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
  }).format(amount);
}
</script>
