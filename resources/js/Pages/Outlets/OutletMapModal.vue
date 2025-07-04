<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-xl relative">
      <button @click="$emit('close')" class="absolute top-2 right-2 text-gray-500 hover:text-red-500 text-xl z-10">
        <i class="fas fa-times"></i>
      </button>
      <div class="p-4 pb-0">
        <h2 class="text-lg font-bold mb-2">Lokasi Outlet</h2>
        <div id="outlet-map" class="w-full h-64 rounded mb-4"></div>
        <div class="text-sm mt-2">
          <div class="font-semibold">Alamat:</div>
          <div class="mb-1">{{ alamat || '-' }}</div>
          <div class="font-semibold">Koordinat:</div>
          <div>{{ lat }}, {{ long }}</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, onBeforeUnmount, watch, ref } from 'vue';
let map = null;
let marker = null;
const props = defineProps({
  show: Boolean,
  lat: [String, Number],
  long: [String, Number],
  alamat: String,
});

const mapId = 'outlet-map';

function initMap() {
  if (!props.lat || !props.long || isNaN(Number(props.lat)) || isNaN(Number(props.long))) return;
  if (map) {
    map.setView([props.lat, props.long], 16);
    if (marker) marker.setLatLng([props.lat, props.long]);
    return;
  }
  map = window.L.map(mapId).setView([props.lat, props.long], 16);
  window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors',
  }).addTo(map);
  marker = window.L.marker([props.lat, props.long]).addTo(map);
}

function destroyMap() {
  if (map) {
    map.remove();
    map = null;
    marker = null;
  }
}

watch(() => props.show, (val) => {
  if (val) {
    setTimeout(() => {
      if (!window.L) {
        // Load Leaflet JS & CSS jika belum ada
        const leafletCss = document.createElement('link');
        leafletCss.rel = 'stylesheet';
        leafletCss.href = 'https://unpkg.com/leaflet/dist/leaflet.css';
        document.head.appendChild(leafletCss);
        const leafletJs = document.createElement('script');
        leafletJs.src = 'https://unpkg.com/leaflet/dist/leaflet.js';
        leafletJs.onload = initMap;
        document.body.appendChild(leafletJs);
      } else {
        initMap();
      }
    }, 100);
  } else {
    destroyMap();
  }
});

onBeforeUnmount(() => {
  destroyMap();
});
</script>

<style scoped>
#outlet-map {
  min-height: 250px;
  height: 260px;
}
</style> 