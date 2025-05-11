<template>
  <AppLayout>
    <div class="max-w-7xl w-full mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-truck"></i> Good Receive
        </h1>
        <button @click="showForm = true" class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
          + Buat Good Receive
        </button>
      </div>
      <div class="bg-white rounded-2xl shadow-2xl overflow-x-auto transition-all">
        <table class="w-full min-w-full divide-y divide-gray-200">
          <thead class="bg-gradient-to-r from-blue-50 to-blue-100">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tl-2xl">Tanggal</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">No. PO</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Supplier</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Petugas</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!goodReceives.length">
              <td colspan="4" class="text-center py-10 text-gray-400">Belum ada data Good Receive.</td>
            </tr>
            <tr v-for="gr in goodReceives" :key="gr.id" class="hover:bg-blue-50 transition shadow-sm">
              <td class="px-6 py-3">{{ gr.receive_date }}</td>
              <td class="px-6 py-3">{{ gr.po_number }}</td>
              <td class="px-6 py-3">{{ gr.supplier_name }}</td>
              <td class="px-6 py-3">{{ gr.received_by_name }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <FormGoodReceive v-if="showForm" @close="showForm = false" />
    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import FormGoodReceive from './Form.vue';

const props = defineProps({
  goodReceives: Array
});

const showForm = ref(false);
</script> 