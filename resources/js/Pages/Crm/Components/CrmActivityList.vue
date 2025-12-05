<script setup>
import { router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
  activities: {
    type: Array,
    default: () => []
  },
});

// Ensure activities is always an array
const safeActivities = computed(() => {
  if (!props.activities) return [];
  if (Array.isArray(props.activities)) return props.activities;
  
  // If it's an object with numeric keys (like Laravel collection), convert to array
  if (typeof props.activities === 'object' && props.activities !== null) {
    try {
      // Check if it has numeric keys (like Laravel collection)
      const keys = Object.keys(props.activities);
      const hasNumericKeys = keys.some(key => !isNaN(parseInt(key)));
      
      if (hasNumericKeys) {
        // Convert object with numeric keys to array
        return Object.values(props.activities);
      } else {
        // If it's a regular object, try Array.from
        return Array.from(props.activities);
      }
    } catch (e) {
      return [];
    }
  }
  
  return [];
});

function getActivityIcon(type) {
  switch (type) {
    case 'registration':
      return 'fa-solid fa-user-plus text-green-500';
    case 'topup':
      return 'fa-solid fa-plus-circle text-blue-500';
    case 'redeem':
      return 'fa-solid fa-minus-circle text-orange-500';
    case 'update':
      return 'fa-solid fa-user-edit text-blue-500';
    case 'login':
      return 'fa-solid fa-sign-in-alt text-purple-500';
    default:
      return 'fa-solid fa-circle text-gray-500';
  }
}

function getActivityColor(type) {
  switch (type) {
    case 'registration':
      return 'bg-green-100 border-green-200';
    case 'topup':
      return 'bg-blue-100 border-blue-200';
    case 'redeem':
      return 'bg-orange-100 border-orange-200';
    case 'update':
      return 'bg-blue-100 border-blue-200';
    case 'login':
      return 'bg-purple-100 border-purple-200';
    default:
      return 'bg-gray-100 border-gray-200';
  }
}

function getActivityBadge(type) {
  switch (type) {
    case 'registration':
      return { text: 'Registrasi', color: 'bg-green-100 text-green-800' };
    case 'topup':
      return { text: 'Top Up', color: 'bg-blue-100 text-blue-800' };
    case 'redeem':
      return { text: 'Redeem', color: 'bg-orange-100 text-orange-800' };
    default:
      return { text: 'Aktivitas', color: 'bg-gray-100 text-gray-800' };
  }
}
</script>

<template>
  <div class="space-y-4">
    <div v-if="safeActivities.length === 0" class="text-center py-8 text-gray-500">
      <i class="fa-solid fa-clock text-4xl mb-4"></i>
      <p>Tidak ada aktivitas terbaru</p>
    </div>
    
    <div v-else class="space-y-3">
      <div
        v-for="(activity, index) in safeActivities"
        :key="index"
        class="flex items-start gap-3 p-3 rounded-lg border"
        :class="getActivityColor(activity.type)"
      >
        <div class="flex-shrink-0 w-8 h-8 rounded-full bg-white flex items-center justify-center shadow-sm">
          <i :class="getActivityIcon(activity.type)"></i>
        </div>
        
        <div class="flex-1 min-w-0">
          <div class="flex items-center justify-between mb-1">
            <p class="text-sm font-medium text-gray-900">
              {{ activity.name }}
            </p>
            <span class="text-xs text-gray-500">
              {{ activity.created_at }}
            </span>
          </div>
          
          <p class="text-sm text-gray-600">
            {{ activity.activity }}
          </p>
          
          <!-- Detailed info for top up and redeem -->
          <div v-if="activity.type === 'topup' || activity.type === 'redeem'" class="mt-2 space-y-1">
            <div class="flex items-center gap-4 text-xs text-gray-600">
              <span class="flex items-center">
                <i class="fa-solid fa-map-marker-alt mr-1 text-gray-400"></i>
                {{ activity.cabang_name }}
              </span>
              <span class="flex items-center">
                <i class="fa-solid fa-coins mr-1 text-gray-400"></i>
                {{ activity.point_formatted }} point
              </span>
              <span v-if="activity.jml_trans_formatted" class="flex items-center">
                <i class="fa-solid fa-money-bill mr-1 text-gray-400"></i>
                {{ activity.jml_trans_formatted }}
              </span>
            </div>
          </div>
          
          <!-- Simple info for registration -->
          <div v-if="activity.type === 'registration'" class="mt-1">
            <p class="text-xs text-gray-500">
              <i class="fa-solid fa-calendar mr-1"></i>
              Member baru terdaftar
            </p>
          </div>
          
          <div class="mt-2 flex items-center gap-2">
            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-white text-gray-700">
              <i class="fa-solid fa-id-card mr-1"></i>
              {{ activity.member_id }}
            </span>
            
            <span :class="['inline-flex items-center px-2 py-1 rounded-full text-xs font-medium', getActivityBadge(activity.type).color]">
              {{ getActivityBadge(activity.type).text }}
            </span>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Activity Summary -->
    <div class="mt-6 pt-4 border-t border-gray-200">
      <div class="grid grid-cols-4 gap-3 text-center">
        <div class="bg-green-50 rounded-lg p-3">
          <p class="text-sm text-green-600 font-medium">Registrasi</p>
          <p class="text-lg font-bold text-green-800">
            {{ safeActivities.filter(a => a.type === 'registration').length }}
          </p>
        </div>
        <div class="bg-blue-50 rounded-lg p-3">
          <p class="text-sm text-blue-600 font-medium">Top Up</p>
          <p class="text-lg font-bold text-blue-800">
            {{ safeActivities.filter(a => a.type === 'topup').length }}
          </p>
        </div>
        <div class="bg-orange-50 rounded-lg p-3">
          <p class="text-sm text-orange-600 font-medium">Redeem</p>
          <p class="text-lg font-bold text-orange-800">
            {{ safeActivities.filter(a => a.type === 'redeem').length }}
          </p>
        </div>
        <div class="bg-gray-50 rounded-lg p-3">
          <p class="text-sm text-gray-600 font-medium">Total</p>
          <p class="text-lg font-bold text-gray-800">
            {{ safeActivities.length }}
          </p>
        </div>
      </div>
    </div>
  </div>
</template> 