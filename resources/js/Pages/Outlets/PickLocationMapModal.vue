<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-xl relative">
      <button @click="$emit('close')" class="absolute top-2 right-2 text-gray-500 hover:text-red-500 text-xl z-10">
        <i class="fas fa-times"></i>
      </button>
      <div class="p-4 pb-0">
        <h2 class="text-lg font-bold mb-2">Pilih Lokasi di Peta</h2>
        <div class="mb-3">
          <form @submit.prevent="searchLocation" class="flex gap-2">
            <input v-model="searchQuery" type="text" class="form-input w-full" placeholder="Cari alamat atau tempat..." />
            <button type="submit" class="px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700"><i class="fas fa-search"></i></button>
          </form>
          <div v-if="searchResults.length" class="bg-white border rounded shadow mt-1 max-h-40 overflow-y-auto z-20 absolute w-[90%]">
            <div v-for="result in searchResults" :key="result.place_id" @click="selectSearchResult(result)" class="px-3 py-2 cursor-pointer hover:bg-blue-50 text-sm border-b last:border-b-0">
              <div class="font-semibold">{{ result.display_name }}</div>
            </div>
          </div>
        </div>
        <div id="pick-map" class="w-full h-64 rounded mb-4"></div>
        <div class="text-sm mt-2">
          <div class="font-semibold">Klik pada peta untuk memilih lokasi.</div>
          <div class="mt-1">Latitude: <span class="font-mono">{{ pickedLat }}</span></div>
          <div>Longitude: <span class="font-mono">{{ pickedLong }}</span></div>
          <div v-if="pickedAddress" class="mt-1">Alamat: <span class="font-mono">{{ pickedAddress }}</span></div>
        </div>
        <div class="flex justify-end mt-4">
          <button @click="emitPicked" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Pilih Lokasi Ini</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, onBeforeUnmount } from 'vue';
const props = defineProps({
  show: Boolean,
  lat: [String, Number],
  long: [String, Number],
});
const emit = defineEmits(['close', 'picked']);

const pickedLat = ref(props.lat || -6.2);
const pickedLong = ref(props.long || 106.8);
const pickedAddress = ref('');
let map = null;
let marker = null;
const mapId = 'pick-map';

// Search state
const searchQuery = ref('');
const searchResults = ref([]);

async function searchLocation() {
  if (!searchQuery.value) return;
  searchResults.value = [];
  try {
    const url = `https://nominatim.openstreetmap.org/search?format=jsonv2&q=${encodeURIComponent(searchQuery.value)}`;
    const res = await fetch(url, { headers: { 'Accept-Language': 'id' } });
    const data = await res.json();
    searchResults.value = data;
  } catch (e) {
    searchResults.value = [];
  }
}

function selectSearchResult(result) {
  const lat = parseFloat(result.lat);
  const lon = parseFloat(result.lon);
  pickedLat.value = lat.toFixed(8);
  pickedLong.value = lon.toFixed(8);
  pickedAddress.value = result.display_name;
  setMarker(lat, lon);
  if (map) map.setView([lat, lon], 16);
  searchResults.value = [];
}

function getDefaultIcon() {
  // Leaflet default marker icon from CDN
  return window.L.icon({
    iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
    iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
    shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
    iconSize: [25, 41],
    iconAnchor: [12, 41],
    popupAnchor: [1, -34],
    shadowSize: [41, 41],
  });
}

async function reverseGeocode(lat, lon) {
  pickedAddress.value = '';
  try {
    const url = `https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lon}`;
    const res = await fetch(url, { headers: { 'Accept-Language': 'id' } });
    const data = await res.json();
    pickedAddress.value = data.display_name || '';
  } catch (e) {
    pickedAddress.value = '';
  }
}

function setMarker(lat, lng) {
  if (marker) {
    marker.setLatLng([lat, lng]);
  } else {
    marker = window.L.marker([lat, lng], { icon: getDefaultIcon(), draggable: false }).addTo(map);
  }
}

function initMap() {
  if (map) return;
  const startLat = props.lat && !isNaN(Number(props.lat)) ? Number(props.lat) : -6.2;
  const startLong = props.long && !isNaN(Number(props.long)) ? Number(props.long) : 106.8;
  pickedLat.value = startLat;
  pickedLong.value = startLong;
  map = window.L.map(mapId).setView([startLat, startLong], 13);
  window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors',
  }).addTo(map);
  setMarker(startLat, startLong);
  reverseGeocode(startLat, startLong);
  map.on('click', function(e) {
    const { lat, lng } = e.latlng;
    pickedLat.value = lat.toFixed(8);
    pickedLong.value = lng.toFixed(8);
    setMarker(lat, lng);
    reverseGeocode(lat, lng);
  });
}

function destroyMap() {
  if (map) {
    map.remove();
    map = null;
    marker = null;
  }
}

function emitPicked() {
  emit('picked', { lat: pickedLat.value, long: pickedLong.value, alamat: pickedAddress.value });
  emit('close');
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
#pick-map {
  min-height: 250px;
  height: 260px;
}
</style>