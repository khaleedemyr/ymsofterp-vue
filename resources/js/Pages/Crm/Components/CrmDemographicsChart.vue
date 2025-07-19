<script setup>
const props = defineProps({
  data: Object,
});

function formatNumber(number) {
  return number.toLocaleString('id-ID');
}
</script>

<template>
  <div>
    <!-- Gender Distribution -->
    <div class="mb-6">
      <h4 class="text-sm font-semibold text-gray-700 mb-3">Distribusi Jenis Kelamin</h4>
      <div class="space-y-3">
        <div v-for="(count, gender) in data.gender" :key="gender" class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <div 
              class="w-4 h-4 rounded-full"
              :class="gender === 'Laki-laki' ? 'bg-blue-500' : gender === 'Perempuan' ? 'bg-pink-500' : 'bg-gray-500'"
            ></div>
            <span class="text-sm text-gray-700">{{ gender }}</span>
          </div>
          <div class="text-right">
            <span class="text-sm font-semibold text-gray-900">{{ formatNumber(count) }}</span>
            <span class="text-xs text-gray-500 ml-1">
              ({{ Math.round((count / Object.values(data.gender).reduce((a, b) => a + b, 0)) * 100) }}%)
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Age Distribution -->
    <div>
      <h4 class="text-sm font-semibold text-gray-700 mb-3">Distribusi Usia</h4>
      <div class="space-y-3">
        <div v-for="(count, ageGroup) in data.age" :key="ageGroup" class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <div 
              class="w-4 h-4 rounded-full"
              :class="{
                'bg-red-500': ageGroup === 'Under 18',
                'bg-orange-500': ageGroup === '18-25',
                'bg-yellow-500': ageGroup === '26-35',
                'bg-green-500': ageGroup === '36-50',
                'bg-blue-500': ageGroup === 'Over 50',
                'bg-gray-500': ageGroup === 'Unknown'
              }"
            ></div>
            <span class="text-sm text-gray-700">{{ ageGroup }}</span>
          </div>
          <div class="text-right">
            <span class="text-sm font-semibold text-gray-900">{{ formatNumber(count) }}</span>
            <span class="text-xs text-gray-500 ml-1">
              ({{ Math.round((count / Object.values(data.age).reduce((a, b) => a + b, 0)) * 100) }}%)
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Summary -->
    <div class="mt-6 pt-4 border-t border-gray-200">
      <div class="grid grid-cols-2 gap-4 text-center">
        <div class="bg-blue-50 rounded-lg p-3">
          <p class="text-sm text-blue-600 font-medium">Total Data Gender</p>
          <p class="text-lg font-bold text-blue-800">
            {{ Object.values(data.gender).reduce((a, b) => a + b, 0).toLocaleString('id-ID') }}
          </p>
        </div>
        <div class="bg-green-50 rounded-lg p-3">
          <p class="text-sm text-green-600 font-medium">Total Data Usia</p>
          <p class="text-lg font-bold text-green-800">
            {{ Object.values(data.age).reduce((a, b) => a + b, 0).toLocaleString('id-ID') }}
          </p>
        </div>
      </div>
    </div>
  </div>
</template> 