<template>
  <AppLayout>
    <div class="max-w-7xl w-full mx-auto py-8 px-2">
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-chart-bar text-blue-500"></i> Asset Reports
        </h1>
        <p class="text-gray-600 mt-1">Pilih laporan yang ingin Anda lihat</p>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <!-- Asset Register Report -->
        <div class="bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition">
          <div class="flex items-center gap-4 mb-4">
            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
              <i class="fa-solid fa-list text-blue-600 text-xl"></i>
            </div>
            <div>
              <h3 class="text-lg font-bold text-gray-900">Asset Register</h3>
              <p class="text-sm text-gray-500">Daftar semua asset</p>
            </div>
          </div>
          <button
            @click="openReport('asset-register')"
            class="w-full mt-4 px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg"
          >
            Lihat Laporan
          </button>
        </div>

        <!-- Asset by Outlet Report -->
        <div class="bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition">
          <div class="flex items-center gap-4 mb-4">
            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
              <i class="fa-solid fa-store text-green-600 text-xl"></i>
            </div>
            <div>
              <h3 class="text-lg font-bold text-gray-900">Asset by Outlet</h3>
              <p class="text-sm text-gray-500">Asset per outlet</p>
            </div>
          </div>
          <button
            @click="openReport('asset-by-outlet')"
            class="w-full mt-4 px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg"
          >
            Lihat Laporan
          </button>
        </div>

        <!-- Asset by Category Report -->
        <div class="bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition">
          <div class="flex items-center gap-4 mb-4">
            <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
              <i class="fa-solid fa-tags text-purple-600 text-xl"></i>
            </div>
            <div>
              <h3 class="text-lg font-bold text-gray-900">Asset by Category</h3>
              <p class="text-sm text-gray-500">Asset per kategori</p>
            </div>
          </div>
          <button
            @click="openReport('asset-by-category')"
            class="w-full mt-4 px-4 py-2 bg-purple-500 hover:bg-purple-600 text-white rounded-lg"
          >
            Lihat Laporan
          </button>
        </div>

        <!-- Maintenance History Report -->
        <div class="bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition">
          <div class="flex items-center gap-4 mb-4">
            <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
              <i class="fa-solid fa-wrench text-yellow-600 text-xl"></i>
            </div>
            <div>
              <h3 class="text-lg font-bold text-gray-900">Maintenance History</h3>
              <p class="text-sm text-gray-500">Riwayat maintenance</p>
            </div>
          </div>
          <button
            @click="openReport('maintenance-history')"
            class="w-full mt-4 px-4 py-2 bg-yellow-500 hover:bg-yellow-600 text-white rounded-lg"
          >
            Lihat Laporan
          </button>
        </div>

        <!-- Transfer History Report -->
        <div class="bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition">
          <div class="flex items-center gap-4 mb-4">
            <div class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center">
              <i class="fa-solid fa-exchange-alt text-indigo-600 text-xl"></i>
            </div>
            <div>
              <h3 class="text-lg font-bold text-gray-900">Transfer History</h3>
              <p class="text-sm text-gray-500">Riwayat transfer asset</p>
            </div>
          </div>
          <button
            @click="openReport('transfer-history')"
            class="w-full mt-4 px-4 py-2 bg-indigo-500 hover:bg-indigo-600 text-white rounded-lg"
          >
            Lihat Laporan
          </button>
        </div>

        <!-- Depreciation Report -->
        <div class="bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition">
          <div class="flex items-center gap-4 mb-4">
            <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
              <i class="fa-solid fa-chart-line text-red-600 text-xl"></i>
            </div>
            <div>
              <h3 class="text-lg font-bold text-gray-900">Depreciation Report</h3>
              <p class="text-sm text-gray-500">Laporan depresiasi</p>
            </div>
          </div>
          <button
            @click="openReport('depreciation')"
            class="w-full mt-4 px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg"
          >
            Lihat Laporan
          </button>
        </div>

        <!-- Asset Value Report -->
        <div class="bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition">
          <div class="flex items-center gap-4 mb-4">
            <div class="w-12 h-12 bg-teal-100 rounded-lg flex items-center justify-center">
              <i class="fa-solid fa-dollar-sign text-teal-600 text-xl"></i>
            </div>
            <div>
              <h3 class="text-lg font-bold text-gray-900">Asset Value</h3>
              <p class="text-sm text-gray-500">Nilai asset</p>
            </div>
          </div>
          <button
            @click="openReport('asset-value')"
            class="w-full mt-4 px-4 py-2 bg-teal-500 hover:bg-teal-600 text-white rounded-lg"
          >
            Lihat Laporan
          </button>
        </div>

        <!-- Maintenance Schedule Report -->
        <div class="bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition">
          <div class="flex items-center gap-4 mb-4">
            <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
              <i class="fa-solid fa-calendar-check text-orange-600 text-xl"></i>
            </div>
            <div>
              <h3 class="text-lg font-bold text-gray-900">Maintenance Schedule</h3>
              <p class="text-sm text-gray-500">Jadwal maintenance</p>
            </div>
          </div>
          <button
            @click="openReport('maintenance-schedule')"
            class="w-full mt-4 px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg"
          >
            Lihat Laporan
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

function openReport(type) {
  router.visit(`/asset-management/reports/${type}`);
}
</script>

