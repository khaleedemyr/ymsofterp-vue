<template>
  <AppLayout>
    <div class="max-w-xl mx-auto py-8 px-2">
      <h1 class="text-2xl font-bold mb-6 flex items-center gap-2">
        <i class="fa-solid fa-recycle text-green-500"></i> Detail Internal Use & Waste
      </h1>
      <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="mb-2"><b>ID:</b> {{ id }}</div>
        <div class="mb-2"><b>Tipe:</b> Internal Use / Spoil / Waste</div>
        <div class="mb-2"><b>Tanggal:</b> 2024-06-01</div>
        <div class="mb-2"><b>Warehouse:</b> Gudang Pusat</div>
        <div class="mb-2"><b>Item:</b> Gula Pasir</div>
        <div class="mb-2"><b>Qty:</b> 5</div>
        <div class="mb-2"><b>Unit:</b> Kg</div>
        <div class="mb-2"><b>Catatan:</b> Sample dapur</div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
const props = defineProps({ id: [String, Number] })
</script> 