<script setup>
const props = defineProps({
  data: Object,
});

function formatNumber(number) {
  return number.toLocaleString('id-ID');
}

function getStatusColor(status) {
  switch (status) {
    case 'Active Exclusive':
      return 'bg-purple-500';
    case 'Active Regular':
      return 'bg-green-500';
    case 'Inactive':
      return 'bg-red-500';
    default:
      return 'bg-gray-500';
  }
}

function getStatusIcon(status) {
  switch (status) {
    case 'Active Exclusive':
      return 'fa-solid fa-crown';
    case 'Active Regular':
      return 'fa-solid fa-user-check';
    case 'Inactive':
      return 'fa-solid fa-user-slash';
    default:
      return 'fa-solid fa-user';
  }
}
</script>

<template>
  <div>
    <div class="space-y-4">
      <div v-for="(count, status) in data" :key="status" class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
        <div class="flex items-center gap-4">
          <div class="w-12 h-12 rounded-full flex items-center justify-center" :class="getStatusColor(status)">
            <i :class="getStatusIcon(status)" class="text-white text-lg"></i>
          </div>
          <div>
            <h4 class="font-semibold text-gray-900">{{ status }}</h4>
            <p class="text-sm text-gray-600">
              {{ Math.round((count / Object.values(data).reduce((a, b) => a + b, 0)) * 100) }}% dari total member
            </p>
          </div>
        </div>
        <div class="text-right">
          <p class="text-2xl font-bold text-gray-900">{{ formatNumber(count) }}</p>
          <p class="text-sm text-gray-500">member</p>
        </div>
      </div>
    </div>

    <!-- Summary -->
    <div class="mt-6 pt-4 border-t border-gray-200">
      <div class="grid grid-cols-3 gap-4 text-center">
        <div class="bg-purple-50 rounded-lg p-3">
          <p class="text-sm text-purple-600 font-medium">Exclusive</p>
          <p class="text-lg font-bold text-purple-800">
            {{ data['Active Exclusive'] ? formatNumber(data['Active Exclusive']) : '0' }}
          </p>
        </div>
        <div class="bg-green-50 rounded-lg p-3">
          <p class="text-sm text-green-600 font-medium">Regular</p>
          <p class="text-lg font-bold text-green-800">
            {{ data['Active Regular'] ? formatNumber(data['Active Regular']) : '0' }}
          </p>
        </div>
        <div class="bg-red-50 rounded-lg p-3">
          <p class="text-sm text-red-600 font-medium">Inactive</p>
          <p class="text-lg font-bold text-red-800">
            {{ data['Inactive'] ? formatNumber(data['Inactive']) : '0' }}
          </p>
        </div>
      </div>
    </div>
  </div>
</template> 