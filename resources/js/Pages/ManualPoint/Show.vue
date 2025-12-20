<template>
  <AppLayout>
    <div class="w-full py-8 px-0">
      <!-- Header -->
      <div class="flex justify-between items-center mb-6">
        <div>
          <Link
            href="/manual-point"
            class="text-blue-600 hover:text-blue-800 mb-2 inline-flex items-center gap-2"
          >
            <i class="fa-solid fa-arrow-left"></i> Kembali ke Daftar
          </Link>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-coins"></i> Detail Point Injection
          </h1>
        </div>
      </div>

      <!-- Transaction Info -->
      <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4">Informasi Point Injection</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="text-sm font-medium text-gray-600">Transaction ID</label>
            <p class="text-lg font-semibold text-gray-800">#{{ transaction.id }}</p>
          </div>
          <div>
            <label class="text-sm font-medium text-gray-600">Reference ID</label>
            <p class="text-sm text-gray-800">{{ transaction.reference_id || '-' }}</p>
          </div>
          <div>
            <label class="text-sm font-medium text-gray-600">Member</label>
            <p class="text-lg font-semibold text-gray-800">
              {{ transaction.member?.nama_lengkap || '-' }}
            </p>
            <p class="text-sm text-gray-600">
              {{ transaction.member?.member_id || '-' }} | {{ transaction.member?.email || '-' }}
            </p>
          </div>
          <div>
            <label class="text-sm font-medium text-gray-600">Point Amount</label>
            <p class="text-lg font-semibold text-green-600">
              +{{ formatNumber(transaction.point_amount) }} points
            </p>
          </div>
          <div>
            <label class="text-sm font-medium text-gray-600">Tanggal Transaksi</label>
            <p class="text-gray-800">{{ formatDate(transaction.transaction_date) }}</p>
          </div>
          <div>
            <label class="text-sm font-medium text-gray-600">Expiry Date</label>
            <p class="text-gray-800">{{ formatDate(transaction.expires_at) }}</p>
          </div>
          <div class="md:col-span-2">
            <label class="text-sm font-medium text-gray-600">Keterangan</label>
            <p class="text-gray-800 whitespace-pre-wrap">{{ transaction.description }}</p>
          </div>
          <div>
            <label class="text-sm font-medium text-gray-600">Dibuat Pada</label>
            <p class="text-gray-800">{{ formatDateTime(transaction.created_at) }}</p>
          </div>
          <div>
            <label class="text-sm font-medium text-gray-600">Terakhir Diupdate</label>
            <p class="text-gray-800">{{ formatDateTime(transaction.updated_at) }}</p>
          </div>
        </div>
      </div>

      <!-- Point Earning Info -->
      <div v-if="transaction.earning" class="bg-white rounded-xl shadow-lg p-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4">Detail Point Earning</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="text-sm font-medium text-gray-600">Point Amount</label>
            <p class="text-lg font-semibold text-gray-800">
              {{ formatNumber(transaction.earning.point_amount) }} points
            </p>
          </div>
          <div>
            <label class="text-sm font-medium text-gray-600">Remaining Points</label>
            <p class="text-lg font-semibold text-blue-600">
              {{ formatNumber(transaction.earning.remaining_points) }} points
            </p>
          </div>
          <div>
            <label class="text-sm font-medium text-gray-600">Earned At</label>
            <p class="text-gray-800">{{ formatDate(transaction.earning.earned_at) }}</p>
          </div>
          <div>
            <label class="text-sm font-medium text-gray-600">Status</label>
            <div class="flex gap-2 mt-1">
              <span v-if="transaction.earning.is_expired" class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">
                Expired
              </span>
              <span v-else class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                Active
              </span>
              <span v-if="transaction.earning.is_fully_redeemed" class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">
                Fully Redeemed
              </span>
              <span v-else class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                Available
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  transaction: Object,
});

const formatDate = (date) => {
  if (!date) return '-';
  const d = new Date(date);
  return d.toLocaleDateString('id-ID', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
};

const formatDateTime = (date) => {
  if (!date) return '-';
  const d = new Date(date);
  return d.toLocaleDateString('id-ID', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

const formatNumber = (num) => {
  if (!num) return '0';
  return new Intl.NumberFormat('id-ID').format(num);
};
</script>

