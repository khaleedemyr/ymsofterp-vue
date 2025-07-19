<script setup>
import { router } from '@inertiajs/vue3';

const props = defineProps({
  members: Array,
});

function viewMember(memberId) {
  router.visit(`/members/${memberId}`);
}

function getStatusBadgeClass(status) {
  if (status === 'Aktif') return 'bg-green-100 text-green-800';
  return 'bg-red-100 text-red-800';
}

function getExclusiveBadgeClass(exclusive) {
  return exclusive === 'Ya' ? 'bg-purple-100 text-purple-800' : 'bg-gray-100 text-gray-800';
}
</script>

<template>
  <div class="space-y-4">
    <div v-if="members.length === 0" class="text-center py-8 text-gray-500">
      <i class="fa-solid fa-users text-4xl mb-4"></i>
      <p>Tidak ada member terbaru</p>
    </div>
    
    <div v-else class="space-y-3">
      <div
        v-for="member in members"
        :key="member.id"
        class="bg-gray-50 rounded-lg p-4 hover:bg-gray-100 transition cursor-pointer"
        @click="viewMember(member.id)"
      >
        <div class="flex items-center justify-between">
          <div class="flex-1">
            <div class="flex items-center gap-3 mb-2">
              <div class="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center">
                <i class="fa-solid fa-user text-purple-600"></i>
              </div>
              <div>
                <h4 class="font-semibold text-gray-900">{{ member.name }}</h4>
                <p class="text-sm text-gray-600">{{ member.costumers_id }}</p>
              </div>
            </div>
            
            <div class="grid grid-cols-2 gap-4 text-sm">
              <div>
                <span class="text-gray-500">Email:</span>
                <span class="ml-1 text-gray-900">{{ member.email || '-' }}</span>
              </div>
              <div>
                <span class="text-gray-500">Telepon:</span>
                <span class="ml-1 text-gray-900">{{ member.telepon || '-' }}</span>
              </div>
              <div>
                <span class="text-gray-500">Jenis Kelamin:</span>
                <span class="ml-1 text-gray-900">{{ member.jenis_kelamin_text }}</span>
              </div>
              <div>
                <span class="text-gray-500">Register:</span>
                <span class="ml-1 text-gray-900">{{ member.tanggal_register_text }}</span>
              </div>
            </div>
          </div>
          
          <div class="flex flex-col gap-2 items-end">
            <span :class="['px-2 py-1 rounded-full text-xs font-medium', getStatusBadgeClass(member.status_aktif_text)]">
              {{ member.status_aktif_text }}
            </span>
            <span :class="['px-2 py-1 rounded-full text-xs font-medium', getExclusiveBadgeClass(member.exclusive_member_text)]">
              {{ member.exclusive_member_text }}
            </span>
          </div>
        </div>
        
        <div class="mt-3 pt-3 border-t border-gray-200">
          <div class="flex justify-between text-xs text-gray-500">
            <span>Valid Until: {{ member.valid_until_text }}</span>
            <span class="text-purple-600 hover:text-purple-700">
              Lihat Detail <i class="fa-solid fa-arrow-right ml-1"></i>
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template> 