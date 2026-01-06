<template>
  <AppLayout>
    <div class="min-h-screen bg-gradient-to-br from-gray-50 via-blue-50 to-indigo-50 p-4 md:p-8">
      <!-- Header -->
      <div class="mb-8">
        <h1 class="text-4xl font-extrabold text-gray-900 mb-2 flex items-center gap-3">
          <div class="p-3 bg-gradient-to-br from-blue-600 to-indigo-600 rounded-xl shadow-lg">
            <i class="fa-solid fa-chart-pie text-white text-2xl"></i>
          </div>
          <span class="bg-gradient-to-r from-blue-600 to-indigo-600 bg-clip-text text-transparent">
            Asset Management Dashboard
          </span>
        </h1>
        <p class="text-gray-600 ml-16">Overview and insights for asset management</p>
      </div>

      <!-- Statistics Cards -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-500">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">Total Assets</p>
              <p class="text-3xl font-bold text-gray-900 mt-2">{{ statistics.total_assets }}</p>
            </div>
            <div class="p-3 bg-blue-100 rounded-full">
              <i class="fa-solid fa-boxes text-blue-600 text-2xl"></i>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-500">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">Active Assets</p>
              <p class="text-3xl font-bold text-gray-900 mt-2">{{ statistics.active_assets }}</p>
            </div>
            <div class="p-3 bg-green-100 rounded-full">
              <i class="fa-solid fa-check-circle text-green-600 text-2xl"></i>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-yellow-500">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">Maintenance Due</p>
              <p class="text-3xl font-bold text-gray-900 mt-2">{{ statistics.maintenance_due }}</p>
            </div>
            <div class="p-3 bg-yellow-100 rounded-full">
              <i class="fa-solid fa-tools text-yellow-600 text-2xl"></i>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-red-500">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">Overdue Maintenance</p>
              <p class="text-3xl font-bold text-gray-900 mt-2">{{ statistics.overdue_maintenance }}</p>
            </div>
            <div class="p-3 bg-red-100 rounded-full">
              <i class="fa-solid fa-exclamation-triangle text-red-600 text-2xl"></i>
            </div>
          </div>
        </div>
      </div>

      <!-- Additional Stats -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white rounded-xl shadow-lg p-6">
          <p class="text-sm font-medium text-gray-600 mb-2">Total Asset Value</p>
          <p class="text-2xl font-bold text-gray-900">{{ formatCurrency(statistics.total_value) }}</p>
        </div>

        <div class="bg-white rounded-xl shadow-lg p-6">
          <p class="text-sm font-medium text-gray-600 mb-2">Pending Transfers</p>
          <p class="text-2xl font-bold text-gray-900">{{ statistics.pending_transfers }}</p>
        </div>

        <div class="bg-white rounded-xl shadow-lg p-6">
          <p class="text-sm font-medium text-gray-600 mb-2">Pending Disposals</p>
          <p class="text-2xl font-bold text-gray-900">{{ statistics.pending_disposals }}</p>
        </div>
      </div>

      <!-- Charts Section -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Assets by Status -->
        <div class="bg-white rounded-xl shadow-lg p-6">
          <h3 class="text-lg font-bold text-gray-900 mb-4">Assets by Status</h3>
          <div class="space-y-3">
            <div v-for="(count, status) in assetsByStatus" :key="status" class="flex items-center justify-between">
              <div class="flex items-center gap-3">
                <div class="w-3 h-3 rounded-full" :class="getStatusColor(status)"></div>
                <span class="text-sm font-medium text-gray-700">{{ status }}</span>
              </div>
              <span class="text-sm font-bold text-gray-900">{{ count }}</span>
            </div>
          </div>
        </div>

        <!-- Assets by Category -->
        <div class="bg-white rounded-xl shadow-lg p-6">
          <h3 class="text-lg font-bold text-gray-900 mb-4">Assets by Category</h3>
          <div class="space-y-3">
            <div v-for="item in assetsByCategory" :key="item.category" class="flex items-center justify-between">
              <span class="text-sm font-medium text-gray-700">{{ item.category }}</span>
              <span class="text-sm font-bold text-gray-900">{{ item.count }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Recent Activities -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Recent Transfers -->
        <div class="bg-white rounded-xl shadow-lg p-6">
          <h3 class="text-lg font-bold text-gray-900 mb-4">Recent Transfers</h3>
          <div class="space-y-3">
            <div v-for="transfer in recentTransfers" :key="transfer.id" class="border-b border-gray-200 pb-3 last:border-b-0">
              <div class="flex items-center justify-between">
                <div>
                  <p class="text-sm font-medium text-gray-900">{{ transfer.asset?.asset_code }} - {{ transfer.asset?.name }}</p>
                  <p class="text-xs text-gray-500 mt-1">
                    {{ transfer.from_outlet?.name }} → {{ transfer.to_outlet?.name }}
                  </p>
                </div>
                <span class="px-2 py-1 rounded-full text-xs font-semibold" :class="getStatusBadgeClass(transfer.status)">
                  {{ transfer.status }}
                </span>
              </div>
            </div>
            <div v-if="recentTransfers.length === 0" class="text-center text-gray-500 py-4">
              No recent transfers
            </div>
          </div>
        </div>

        <!-- Recent Maintenances -->
        <div class="bg-white rounded-xl shadow-lg p-6">
          <h3 class="text-lg font-bold text-gray-900 mb-4">Recent Maintenances</h3>
          <div class="space-y-3">
            <div v-for="maintenance in recentMaintenances" :key="maintenance.id" class="border-b border-gray-200 pb-3 last:border-b-0">
              <div class="flex items-center justify-between">
                <div>
                  <p class="text-sm font-medium text-gray-900">{{ maintenance.asset?.asset_code }} - {{ maintenance.asset?.name }}</p>
                  <p class="text-xs text-gray-500 mt-1">
                    {{ maintenance.maintenance_type }} - {{ formatDate(maintenance.maintenance_date) }}
                  </p>
                </div>
                <span class="px-2 py-1 rounded-full text-xs font-semibold" :class="getStatusBadgeClass(maintenance.status)">
                  {{ maintenance.status }}
                </span>
              </div>
            </div>
            <div v-if="recentMaintenances.length === 0" class="text-center text-gray-500 py-4">
              No recent maintenances
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  statistics: Object,
  assetsByStatus: Object,
  assetsByCategory: Array,
  assetsByOutlet: Array,
  maintenanceDue: Number,
  overdueMaintenance: Number,
  recentTransfers: Array,
  recentMaintenances: Array,
  filters: Object,
});

function formatCurrency(value) {
  if (value == null || value === undefined) return 'Rp 0';
  return new Intl.NumberFormat('id-ID', { 
    style: 'currency', 
    currency: 'IDR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
  }).format(value);
}

function formatDate(date) {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('id-ID', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  });
}

function getStatusColor(status) {
  const colors = {
    'Active': 'bg-green-500',
    'Maintenance': 'bg-yellow-500',
    'Disposed': 'bg-red-500',
    'Lost': 'bg-gray-500',
    'Transfer': 'bg-blue-500',
  };
  return colors[status] || 'bg-gray-500';
}

function getStatusBadgeClass(status) {
  const classes = {
    'Pending': 'bg-yellow-100 text-yellow-800',
    'Approved': 'bg-green-100 text-green-800',
    'Completed': 'bg-blue-100 text-blue-800',
    'Rejected': 'bg-red-100 text-red-800',
    'Scheduled': 'bg-gray-100 text-gray-800',
    'In Progress': 'bg-yellow-100 text-yellow-800',
    'Cancelled': 'bg-red-100 text-red-800',
  };
  return classes[status] || 'bg-gray-100 text-gray-800';
}
</script>

