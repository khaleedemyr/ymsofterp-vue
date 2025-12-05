<template>
  <AppLayout>
    <div class="max-w-2xl w-full mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-clipboard-list text-blue-500"></i> Detail Action Plan Guest Review
        </h1>
        <button @click="$inertia.visit('/ops-kitchen/action-plan-guest-review')" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg flex items-center gap-2">
          <i class="fa-solid fa-arrow-left"></i> Kembali
        </button>
      </div>
      <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
          <div><b>Outlet:</b> {{ plan.outlet }}</div>
          <div><b>Tanggal:</b> {{ plan.tanggal }}</div>
          <div><b>Dept. Concern:</b> {{ plan.dept }}</div>
          <div><b>PIC:</b> {{ plan.pic }}</div>
          <div><b>Status:</b> {{ plan.status }}</div>
        </div>
        <div class="mb-4"><b>Problem:</b><br/>{{ plan.problem }}</div>
        <div class="mb-4"><b>Analisa:</b><br/>{{ plan.analisa }}</div>
        <div class="mb-4"><b>Preventive Action:</b><br/>{{ plan.preventive }}</div>
        <div class="mb-4">
          <b>Dokumentasi:</b>
          <div v-if="plan.images && plan.images.length" class="flex gap-2 mt-2">
            <img v-for="(img, idx) in plan.images" :key="img.id" :src="'/storage/' + img.path" class="w-24 h-24 object-cover rounded cursor-pointer border border-gray-200" @click="openLightbox(plan.images.map(i => '/storage/' + i.path), idx)" />
          </div>
          <span v-else>-</span>
        </div>
        <VueEasyLightbox :visible="lightboxVisible" :imgs="lightboxImages" :index="lightboxIndex" @hide="lightboxVisible = false" />
      </div>
    </div>
  </AppLayout>
</template>
<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref } from 'vue'
import VueEasyLightbox from 'vue-easy-lightbox'
const props = defineProps({ plan: Object })
const lightboxVisible = ref(false)
const lightboxImages = ref([])
const lightboxIndex = ref(0)
function openLightbox(imgs, idx) {
  lightboxImages.value = imgs
  lightboxIndex.value = idx
  lightboxVisible.value = true
}
</script> 