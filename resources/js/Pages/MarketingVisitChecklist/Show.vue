<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto py-8 px-2">
      <div class="bg-white rounded-2xl shadow-2xl p-6">
        <h1 class="text-2xl font-bold text-blue-800 mb-4 flex items-center gap-2">
          <i class="fa-solid fa-clipboard-check text-blue-500"></i> Detail Marketing Visit Checklist
        </h1>
        <div class="mb-4 text-sm text-gray-700">
          <strong>Outlet:</strong> {{ checklist.outlet?.nama_outlet || checklist.outlet?.name || '-' }}<br />
          <strong>Tanggal Kunjungan:</strong> {{ checklist.visit_date }}<br />
          <strong>User Input:</strong> {{ checklist.user?.nama_lengkap || checklist.user?.name || '-' }}
        </div>
        <div class="overflow-x-auto">
          <table class="w-full min-w-[900px] divide-y divide-gray-200 rounded-xl shadow transition-all">
            <thead class="bg-gradient-to-r from-blue-50 to-blue-100">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tl-2xl">No</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Kategori</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Checklist Point</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Check</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Actual Condition</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Action</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Picture</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tr-2xl">Remarks</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, i) in checklist.items" :key="item.id" class="hover:bg-blue-50 transition">
                <td class="px-6 py-3">{{ item.no }}</td>
                <td v-if="isFirstOfCategory(i)" :rowspan="categoryRowspan(item.category)" class="px-6 py-3 align-top">{{ item.category }}</td>
                <td v-else style="display:none"></td>
                <td class="px-6 py-3">{{ item.checklist_point }}</td>
                <td class="px-6 py-3 text-center">
                  <input type="checkbox" :checked="item.checked" disabled />
                </td>
                <td class="px-6 py-3">{{ item.actual_condition }}</td>
                <td class="px-6 py-3">{{ item.action }}</td>
                <td class="px-6 py-3">
                  <div class="flex flex-wrap gap-1">
                    <img v-for="(photo, idx) in item.photos" :key="idx" :src="photoUrl(photo.photo_path)" class="w-12 h-12 object-cover border rounded cursor-pointer" @click="openModal(photoUrl(photo.photo_path))" v-if="item.photos && item.photos.length" />
                    <span v-if="!item.photos || !item.photos.length" class="text-gray-400 text-xs">-</span>
                  </div>
                </td>
                <td class="px-6 py-3">{{ item.remarks }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <button class="mt-6 px-4 py-2 rounded bg-gray-100 text-gray-700 font-semibold hover:bg-gray-200" @click="goBack">Kembali</button>
      </div>
      <div v-if="modalPhoto" class="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50">
        <div class="bg-white p-4 rounded shadow-lg relative">
          <img :src="modalPhoto" class="max-w-[80vw] max-h-[80vh]" />
          <button class="absolute top-2 right-2 text-xl" @click="modalPhoto = null">&times;</button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import { usePage, router } from '@inertiajs/vue3';

const page = usePage();
const checklist = page.props.checklist;
const modalPhoto = ref(null);

function photoUrl(path) {
  return `/storage/${path}`;
}
function openModal(url) {
  modalPhoto.value = url;
}
function goBack() {
  router.get(route('marketing-visit-checklist.index'));
}
// Merge row kategori
function isFirstOfCategory(idx) {
  if (idx === 0) return true;
  return checklist.items[idx].category !== checklist.items[idx - 1].category;
}
function categoryRowspan(category) {
  return checklist.items.filter(i => i.category === category).length;
}
</script> 