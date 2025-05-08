<script setup>
import { ref, onMounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const map = ref(null);
const outletMarkers = ref([]);
const outletsData = ref([]);

function initMap() {
  // Center: somewhere between Jabar, Tangerang, Jakarta
  const center = [-6.4, 107.0];
  const zoom = 8;
  map.value = L.map('outlet-map').setView(center, zoom);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors'
  }).addTo(map.value);
}

function formatRupiah(num) {
  return 'Rp ' + Number(num).toLocaleString('id-ID');
}

function addMarkers(outlets) {
  // Remove old markers
  outletMarkers.value.forEach(m => map.value.removeLayer(m));
  outletMarkers.value = [];
  outlets.forEach(outlet => {
    if (!outlet.lat || !outlet.long) return;
    const popupContent = `<b>${outlet.nama_outlet}</b><br>${outlet.lokasi || ''}<br>
      <div>Omzet Today: <span style='color:#2563eb;font-weight:bold;'>${formatRupiah(outlet.omzet_today)}</span></div>
      <div>Omzet MTD: <span style='color:#059669;font-weight:bold;'>${formatRupiah(outlet.omzet_mtd)}</span></div>`;
    const marker = L.marker([parseFloat(outlet.lat), parseFloat(outlet.long)])
      .addTo(map.value)
      .bindPopup(popupContent);
    outletMarkers.value.push(marker);
  });
}

onMounted(async () => {
  initMap();
  // Fetch outlet data
  const res = await fetch('/api/outlets/active');
  const outlets = await res.json();
  outletsData.value = outlets;
  addMarkers(outlets);
});
</script>

<template>
  <AppLayout>
    <div class="max-w-7xl w-full mx-auto py-8 px-2">
      <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2 mb-6">
        <i class="fa-solid fa-map-location-dot text-blue-500"></i> Dashboard Outlet (Peta)
      </h1>
      <div class="bg-white rounded-2xl shadow-2xl p-4">
        <div id="outlet-map" class="w-full h-[70vh] rounded-xl"></div>
      </div>
      <div class="mt-4">
        <div class="overflow-x-hidden whitespace-nowrap w-full">
          <div class="marquee inline-block">
            <span v-for="(outlet, idx) in outletsData" :key="outlet.id_outlet" class="mr-8">
              <b>{{ outlet.nama_outlet }}</b>
              <span class="mx-2">|</span>
              Today: <span class="text-blue-600 font-bold">{{ formatRupiah(outlet.omzet_today) }}</span>
              <span class="mx-2">|</span>
              MTD: <span class="text-green-600 font-bold">{{ formatRupiah(outlet.omzet_mtd) }}</span>
              <span v-if="idx !== outletsData.length - 1">&nbsp;•&nbsp;</span>
            </span>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
#outlet-map {
  min-height: 400px;
  width: 100%;
}
.marquee {
  display: inline-block;
  white-space: nowrap;
  animation: marquee 150s linear infinite;
}
.marquee:hover {
  animation-play-state: paused;
}
@keyframes marquee {
  0% { transform: translateX(100%); }
  100% { transform: translateX(-100%); }
}
</style> 