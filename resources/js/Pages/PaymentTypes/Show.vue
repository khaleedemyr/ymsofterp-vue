<template>
  <AppLayout>
    <div class="max-w-2xl mx-auto py-8 px-2">
      <h1 class="text-2xl font-bold text-pink-700 mb-6">Detail Jenis Pembayaran</h1>
      <div class="bg-white rounded-2xl shadow-xl p-8 space-y-4">
        <div><b>Nama:</b> {{ paymentType.name }}</div>
        <div><b>Kode:</b> {{ paymentType.code }}</div>
        <div><b>Tipe:</b> 
          <span :class="paymentType.is_bank ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'" class="px-2 py-1 rounded-full text-xs font-bold">
            {{ paymentType.is_bank ? 'Bank' : 'Non-Bank' }}
          </span>
        </div>
        <div v-if="paymentType.is_bank">
          <div><b>Nama Bank:</b> {{ paymentType.bank_name }}</div>
          <div><b>Nomor Rekening:</b> {{ paymentType.bank_account }}</div>
          <div><b>Nama Pemilik Rekening:</b> {{ paymentType.bank_account_name }}</div>
        </div>
        <div><b>Status:</b> 
          <span :class="paymentType.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-500'" class="px-2 py-1 rounded-full text-xs font-bold">
            {{ paymentType.status === 'active' ? 'Aktif' : 'Nonaktif' }}
          </span>
        </div>
        <div><b>Deskripsi:</b> {{ paymentType.description || '-' }}</div>
        <div><b>Outlet Pembayaran:</b>
          <span v-if="paymentType.outlets && paymentType.outlets.length">
            <span v-for="o in paymentType.outlets" :key="o.id" class="inline-block bg-green-100 text-green-800 px-2 py-1 rounded-full text-xs font-semibold mr-1">{{ o.name }}</span>
          </span>
          <span v-else-if="paymentType.regions && paymentType.regions.length">
            <span v-for="r in paymentType.regions" :key="r.id" class="inline-block bg-blue-100 text-blue-800 px-2 py-1 rounded-full text-xs font-semibold mr-1">{{ r.name }}</span>
          </span>
          <span v-else>-</span>
        </div>
        <div class="flex justify-end mt-8">
          <Link :href="route('payment-types.index')" class="px-4 py-2 rounded bg-gray-100 text-gray-700 font-semibold hover:bg-gray-200">Kembali</Link>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
  paymentType: Object
});
</script> 