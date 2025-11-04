<template>
  <AppLayout title="Challenge Detail">
    <div class="w-full py-8 px-4">
      <div class="flex justify-between items-center mb-6">
        <div class="flex items-center gap-4">
          <button @click="goBack" class="text-gray-600 hover:text-gray-800">
            <i class="fa-solid fa-arrow-left text-xl"></i>
          </button>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-trophy text-purple-500"></i> Challenge Detail
          </h1>
        </div>
        <div class="flex gap-2">
          <button @click="editChallenge" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
            <i class="fa-solid fa-edit mr-2"></i>Edit
          </button>
        </div>
      </div>

      <div v-if="challenge" class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <!-- Header with Image -->
        <div v-if="challenge.image" class="w-full h-64 bg-gray-200 overflow-hidden">
          <img :src="getImageUrl(challenge.image)" :alt="challenge.title" class="w-full h-full object-cover">
        </div>

        <!-- Content -->
        <div class="p-6">
          <!-- Basic Information -->
          <div class="mb-6">
            <h2 class="text-2xl font-bold text-gray-900 mb-2">{{ challenge.title }}</h2>
            <div class="flex items-center gap-4 mb-4">
              <span :class="['px-3 py-1 rounded-full text-sm font-medium', challenge.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800']">
                {{ challenge.is_active ? 'Active' : 'Inactive' }}
              </span>
              <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800">
                <i class="fa-solid fa-coins mr-1"></i>
                {{ challenge.points_reward }} points
              </span>
            </div>
            <p v-if="challenge.description" class="text-gray-600 mb-4">{{ challenge.description }}</p>
          </div>

          <!-- Challenge Type & Rules -->
          <div class="mb-6 bg-gray-50 rounded-lg p-4">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Challenge Information</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Challenge Type</label>
                <p class="text-gray-900 font-semibold">{{ getChallengeTypeLabel(challenge.challenge_type_id) }}</p>
              </div>
              <div v-if="challenge.validity_period_days">
                <label class="block text-sm font-medium text-gray-700 mb-1">Validity Period</label>
                <p class="text-gray-900">{{ challenge.validity_period_days }} days</p>
              </div>
              <div v-if="challenge.start_date">
                <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                <p class="text-gray-900">{{ formatDate(challenge.start_date) }}</p>
              </div>
              <div v-if="challenge.end_date">
                <label class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                <p class="text-gray-900">{{ formatDate(challenge.end_date) }}</p>
              </div>
            </div>

            <!-- Rules Details -->
            <div v-if="challenge.rules && Object.keys(challenge.rules).length > 0" class="mt-4">
              <h4 class="text-md font-semibold text-gray-900 mb-3">Challenge Rules</h4>
              
              <!-- Spending-based Rules -->
              <div v-if="challenge.challenge_type_id === 'spending'" class="space-y-3">
                <div v-if="challenge.rules.min_amount">
                  <label class="block text-sm font-medium text-gray-700 mb-1">Minimum Spending</label>
                  <p class="text-gray-900 font-semibold">Rp {{ formatCurrency(challenge.rules.min_amount) }}</p>
                </div>
                <div v-if="challenge.rules.reward_type">
                  <label class="block text-sm font-medium text-gray-700 mb-1">Reward Type</label>
                  <p class="text-gray-900 font-semibold">{{ getRewardTypeLabel(challenge.rules.reward_type) }}</p>
                </div>
                <div v-if="challenge.rules.reward_type">
                  <label class="block text-sm font-medium text-gray-700 mb-1">Reward Value</label>
                  <div v-if="challenge.rules.reward_type === 'item' && challenge.reward_item" class="flex items-center gap-2">
                    <i class="fa-solid fa-box text-blue-600"></i>
                    <p class="text-gray-900 font-semibold">{{ challenge.reward_item.name }}</p>
                  </div>
                  <p v-else class="text-gray-900 font-semibold">{{ challenge.rules.reward_value || '-' }}</p>
                </div>
                <div v-if="challenge.rules.immediate !== undefined">
                  <label class="block text-sm font-medium text-gray-700 mb-1">Immediate Reward</label>
                  <span :class="['px-2 py-1 rounded text-sm', challenge.rules.immediate ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800']">
                    {{ challenge.rules.immediate ? 'Yes' : 'No' }}
                  </span>
                </div>
              </div>

              <!-- Product-based Rules -->
              <div v-else-if="challenge.challenge_type_id === 'product'" class="space-y-3">
                <div v-if="challenge.rules.product_category">
                  <label class="block text-sm font-medium text-gray-700 mb-1">Product Category</label>
                  <p class="text-gray-900 font-semibold">{{ challenge.rules.product_category }}</p>
                </div>
                <div v-if="challenge.rules.quantity_required">
                  <label class="block text-sm font-medium text-gray-700 mb-1">Quantity Required</label>
                  <p class="text-gray-900 font-semibold">{{ challenge.rules.quantity_required }}</p>
                </div>
                <div v-if="challenge.rules.reward_type">
                  <label class="block text-sm font-medium text-gray-700 mb-1">Reward Type</label>
                  <p class="text-gray-900 font-semibold">{{ getRewardTypeLabel(challenge.rules.reward_type) }}</p>
                </div>
                <div v-if="challenge.rules.reward_value">
                  <label class="block text-sm font-medium text-gray-700 mb-1">Reward Value</label>
                  <p class="text-gray-900 font-semibold">{{ challenge.rules.reward_value }}</p>
                </div>
              </div>

              <!-- Multi-condition Rules -->
              <div v-else-if="challenge.challenge_type_id === 'multi-condition'" class="space-y-3">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <div v-if="challenge.rules.min_spending">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Min Spending</label>
                    <p class="text-gray-900 font-semibold">Rp {{ formatCurrency(challenge.rules.min_spending) }}</p>
                  </div>
                  <div v-if="challenge.rules.min_transactions">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Min Transactions</label>
                    <p class="text-gray-900 font-semibold">{{ challenge.rules.min_transactions }}</p>
                  </div>
                  <div v-if="challenge.rules.min_visits">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Min Visits</label>
                    <p class="text-gray-900 font-semibold">{{ challenge.rules.min_visits }}</p>
                  </div>
                </div>
                <div v-if="challenge.rules.reward_type">
                  <label class="block text-sm font-medium text-gray-700 mb-1">Reward Type</label>
                  <p class="text-gray-900 font-semibold">{{ getRewardTypeLabel(challenge.rules.reward_type) }}</p>
                </div>
                <div v-if="challenge.rules.reward_value">
                  <label class="block text-sm font-medium text-gray-700 mb-1">Reward Value</label>
                  <p class="text-gray-900 font-semibold">{{ challenge.rules.reward_value }}</p>
                </div>
              </div>

              <!-- Custom Rules -->
              <div v-else-if="challenge.challenge_type_id === 'custom'">
                <label class="block text-sm font-medium text-gray-700 mb-1">Custom Rules</label>
                <p class="text-gray-900 whitespace-pre-wrap">{{ challenge.rules }}</p>
              </div>
            </div>
          </div>

          <!-- Timestamps -->
          <div class="mt-6 pt-6 border-t border-gray-200">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-gray-500">
              <div v-if="challenge.created_at">
                <span class="font-medium">Created:</span> {{ formatDateTime(challenge.created_at) }}
              </div>
              <div v-if="challenge.updated_at">
                <span class="font-medium">Last Updated:</span> {{ formatDateTime(challenge.updated_at) }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  challenge: Object
})

const getImageUrl = (path) => {
  if (!path) return null
  return `/storage/${path}`
}

const formatCurrency = (amount) => {
  if (!amount) return '0'
  return new Intl.NumberFormat('id-ID').format(amount)
}

const formatDate = (date) => {
  if (!date) return ''
  return new Date(date).toLocaleDateString('id-ID', {
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
}

const formatDateTime = (date) => {
  if (!date) return ''
  return new Date(date).toLocaleString('id-ID', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

const getChallengeTypeLabel = (type) => {
  const types = {
    'spending': 'Spending-based',
    'product': 'Product-based',
    'multi-condition': 'Multi-condition',
    'recurring': 'Recurring',
    'custom': 'Custom'
  }
  return types[type] || type || 'Unknown'
}

const getRewardTypeLabel = (type) => {
  const types = {
    'item': 'Item',
    'points': 'Points',
    'voucher': 'Voucher'
  }
  return types[type] || type || 'Unknown'
}

const goBack = () => {
  router.visit('/admin/member-apps-settings')
}

const editChallenge = () => {
  router.visit(`/admin/member-apps-settings`, {
    onSuccess: () => {
      // Trigger edit modal after navigation
      setTimeout(() => {
        window.dispatchEvent(new CustomEvent('edit-challenge', { detail: { challenge: props.challenge } }))
      }, 100)
    }
  })
}
</script>

