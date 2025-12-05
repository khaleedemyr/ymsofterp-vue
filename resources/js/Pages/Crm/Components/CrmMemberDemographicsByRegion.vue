<script setup>
import { computed } from 'vue';

const props = defineProps({
  demographics: {
    type: Object,
    default: () => ({
      total_members_with_transactions: 0,
      demographics: []
    })
  },
});

const totalMembers = computed(() => props.demographics.total_members_with_transactions || 0);
const demographicsData = computed(() => {
  const data = props.demographics.demographics || [];
  // Additional filter to exclude Reset Point (double safety)
  return data.filter(item => item.region !== 'Reset Point');
});

function getColorClass(index) {
  const colors = [
    'bg-blue-100 text-blue-800 border-blue-200',
    'bg-green-100 text-green-800 border-green-200',
    'bg-yellow-100 text-yellow-800 border-yellow-200',
    'bg-purple-100 text-purple-800 border-purple-200',
    'bg-pink-100 text-pink-800 border-pink-200',
    'bg-indigo-100 text-indigo-800 border-indigo-200',
    'bg-red-100 text-red-800 border-red-200',
    'bg-orange-100 text-orange-800 border-orange-200',
    'bg-teal-100 text-teal-800 border-teal-200',
    'bg-cyan-100 text-cyan-800 border-cyan-200'
  ];
  return colors[index % colors.length];
}
</script>

<template>
  <div class="bg-white rounded-xl shadow-lg p-6">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h3 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-globe text-blue-500"></i>
          Demografi Member Berdasarkan Region Pertama
        </h3>
        <p class="text-sm text-gray-600 mt-1">
          Distribusi member berdasarkan region pertama mereka melakukan transaksi
        </p>
      </div>
      <div class="text-right">
        <p class="text-2xl font-bold text-blue-600">{{ totalMembers }}</p>
        <p class="text-sm text-gray-500">Total Member</p>
      </div>
    </div>

    <div v-if="demographicsData.length === 0" class="text-center py-8 text-gray-500">
      <i class="fa-solid fa-chart-pie text-4xl mb-4"></i>
      <p>Tidak ada data demografi</p>
    </div>

    <div v-else class="space-y-4">
      <!-- Top 5 Cabang -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                  <div
            v-for="(item, index) in demographicsData.slice(0, 6)"
            :key="item.region"
            class="bg-gray-50 rounded-lg p-4 border-l-4"
            :class="getColorClass(index).replace('bg-', 'border-').replace(' text-', '')"
          >
            <div class="flex items-center justify-between">
              <div class="flex-1">
                <h4 class="font-semibold text-gray-900 text-sm truncate" :title="item.region">
                  {{ item.region }}
                </h4>
                <p class="text-2xl font-bold text-gray-800">{{ item.member_count }}</p>
                <p class="text-xs text-gray-500">{{ item.percentage }}% dari total</p>
              </div>
            <div class="flex-shrink-0 ml-3">
              <div class="w-12 h-12 rounded-full flex items-center justify-center text-lg font-bold"
                   :class="getColorClass(index)">
                {{ index + 1 }}
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Detailed List -->
      <div class="mt-6">
        <h4 class="text-md font-semibold text-gray-800 mb-3 flex items-center gap-2">
          <i class="fa-solid fa-list text-gray-500"></i>
          Detail Semua Region
        </h4>
        <div class="bg-gray-50 rounded-lg overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full">
              <thead class="bg-gray-100">
                <tr>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Ranking
                  </th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Region
                  </th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Jumlah Member
                  </th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Persentase
                  </th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Progress Bar
                  </th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr
                  v-for="(item, index) in demographicsData"
                  :key="item.region"
                  class="hover:bg-gray-50"
                >
                  <td class="px-4 py-3 whitespace-nowrap">
                    <div class="flex items-center">
                      <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold"
                           :class="getColorClass(index)">
                        {{ index + 1 }}
                      </div>
                    </div>
                  </td>
                  <td class="px-4 py-3 whitespace-nowrap">
                    <div class="text-sm font-medium text-gray-900">{{ item.region }}</div>
                  </td>
                  <td class="px-4 py-3 whitespace-nowrap">
                    <div class="text-sm text-gray-900 font-semibold">{{ item.member_count }}</div>
                  </td>
                  <td class="px-4 py-3 whitespace-nowrap">
                    <div class="text-sm text-gray-900">{{ item.percentage }}%</div>
                  </td>
                  <td class="px-4 py-3 whitespace-nowrap">
                    <div class="w-full bg-gray-200 rounded-full h-2">
                      <div
                        class="h-2 rounded-full transition-all duration-300"
                        :class="getColorClass(index).replace(' text-', '').replace(' border-', '')"
                        :style="{ width: item.percentage + '%' }"
                      ></div>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Summary Stats -->
      <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-blue-50 rounded-lg p-4 text-center">
          <p class="text-sm text-blue-600 font-medium">Total Region</p>
          <p class="text-2xl font-bold text-blue-800">{{ demographicsData.length }}</p>
        </div>
        <div class="bg-green-50 rounded-lg p-4 text-center">
          <p class="text-sm text-green-600 font-medium">Region Teratas</p>
          <p class="text-lg font-bold text-green-800">
            {{ demographicsData[0]?.region || '-' }}
          </p>
          <p class="text-xs text-green-600">{{ demographicsData[0]?.member_count || 0 }} member</p>
        </div>
        <div class="bg-purple-50 rounded-lg p-4 text-center">
          <p class="text-sm text-purple-600 font-medium">Rata-rata per Region</p>
          <p class="text-2xl font-bold text-purple-800">
            {{ demographicsData.length > 0 ? Math.round(totalMembers / demographicsData.length) : 0 }}
          </p>
        </div>
      </div>
    </div>
  </div>
</template> 