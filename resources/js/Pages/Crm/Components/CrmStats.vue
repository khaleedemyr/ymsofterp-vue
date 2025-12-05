<script setup>
const props = defineProps({
  stats: Object,
});

function formatNumber(number) {
  return number.toLocaleString('id-ID');
}

function getGrowthIcon(growthRate) {
  if (growthRate > 0) return 'fa-solid fa-arrow-up text-green-500';
  if (growthRate < 0) return 'fa-solid fa-arrow-down text-red-500';
  return 'fa-solid fa-minus text-gray-500';
}

function getGrowthColor(growthRate) {
  if (growthRate > 0) return 'text-green-600';
  if (growthRate < 0) return 'text-red-600';
  return 'text-gray-600';
}
</script>

<template>
  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-6">
    <!-- Total Members -->
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-purple-500">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-sm font-medium text-gray-600">Total Member</p>
          <p class="text-2xl font-bold text-gray-900">{{ formatNumber(stats.totalMembers) }}</p>
        </div>
        <div class="bg-purple-100 p-3 rounded-lg">
          <i class="fa-solid fa-users text-purple-600 text-xl"></i>
        </div>
      </div>
    </div>

    <!-- New Members Today -->
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-500">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-sm font-medium text-gray-600">Member Baru Hari Ini</p>
          <p class="text-2xl font-bold text-gray-900">{{ formatNumber(stats.newMembersToday) }}</p>
        </div>
        <div class="bg-green-100 p-3 rounded-lg">
          <i class="fa-solid fa-user-plus text-green-600 text-xl"></i>
        </div>
      </div>
    </div>

    <!-- New Members This Month -->
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-500">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-sm font-medium text-gray-600">Member Baru Bulan Ini</p>
          <p class="text-2xl font-bold text-gray-900">{{ formatNumber(stats.newMembersThisMonth) }}</p>
          <div class="flex items-center gap-1 mt-1">
            <i :class="getGrowthIcon(stats.growthRate)"></i>
            <span :class="['text-sm font-medium', getGrowthColor(stats.growthRate)]">
              {{ stats.growthRate > 0 ? '+' : '' }}{{ stats.growthRate }}%
            </span>
            <span class="text-xs text-gray-500">vs bulan lalu</span>
          </div>
        </div>
        <div class="bg-blue-100 p-3 rounded-lg">
          <i class="fa-solid fa-chart-line text-blue-600 text-xl"></i>
        </div>
      </div>
    </div>

    <!-- Active Members -->
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-emerald-500">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-sm font-medium text-gray-600">Member Aktif</p>
          <p class="text-2xl font-bold text-gray-900">{{ formatNumber(stats.activeMembers) }}</p>
          <p class="text-xs text-gray-500 mt-1">
            {{ stats.totalMembers > 0 ? Math.round((stats.activeMembers / stats.totalMembers) * 100) : 0 }}% dari total
          </p>
        </div>
        <div class="bg-emerald-100 p-3 rounded-lg">
          <i class="fa-solid fa-user-check text-emerald-600 text-xl"></i>
        </div>
      </div>
    </div>

    <!-- Inactive Members -->
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-orange-500">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-sm font-medium text-gray-600">Member Nonaktif</p>
          <p class="text-2xl font-bold text-gray-900">{{ formatNumber(stats.inactiveMembers) }}</p>
          <p class="text-xs text-gray-500 mt-1">
            {{ stats.totalMembers > 0 ? Math.round((stats.inactiveMembers / stats.totalMembers) * 100) : 0 }}% dari total
          </p>
        </div>
        <div class="bg-orange-100 p-3 rounded-lg">
          <i class="fa-solid fa-user-slash text-orange-600 text-xl"></i>
        </div>
      </div>
    </div>

    <!-- Exclusive Members -->
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-pink-500">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-sm font-medium text-gray-600">Member Eksklusif</p>
          <p class="text-2xl font-bold text-gray-900">{{ formatNumber(stats.exclusiveMembers) }}</p>
          <p class="text-xs text-gray-500 mt-1">
            {{ stats.totalMembers > 0 ? Math.round((stats.exclusiveMembers / stats.totalMembers) * 100) : 0 }}% dari total
          </p>
        </div>
        <div class="bg-pink-100 p-3 rounded-lg">
          <i class="fa-solid fa-crown text-pink-600 text-xl"></i>
        </div>
      </div>
    </div>
  </div>
</template> 