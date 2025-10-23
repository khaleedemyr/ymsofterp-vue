<template>
  <AppLayout>
    <div class="max-w-4xl mx-auto py-8 px-2">
      <!-- Header -->
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-trophy text-yellow-500"></i>
          {{ isEdit ? 'Edit Challenge' : 'Tambah Challenge' }}
        </h1>
        <button @click="goBack" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg">
          <i class="fa-solid fa-arrow-left mr-2"></i>Kembali
        </button>
      </div>

      <form @submit.prevent="submitForm" class="space-y-6">
        <!-- Basic Information -->
        <div class="bg-white rounded-lg shadow p-6">
          <h3 class="text-lg font-semibold text-gray-900 mb-4">Informasi Dasar</h3>
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Challenge Type *</label>
              <select v-model="form.challenge_type_id" @change="onTypeChange" class="form-select w-full rounded border px-3 py-2" required>
                <option value="">Pilih Challenge Type</option>
                <option v-for="type in challengeTypes" :key="type.id" :value="type.id">{{ type.name }}</option>
              </select>
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Nama Challenge *</label>
              <input v-model="form.name" type="text" class="form-input w-full rounded border px-3 py-2" placeholder="Masukkan nama challenge" required />
            </div>
          </div>
          
          <div class="mt-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">Deskripsi</label>
            <textarea v-model="form.description" class="form-textarea w-full rounded border px-3 py-2" rows="3" placeholder="Deskripsi challenge..."></textarea>
          </div>
        </div>

        <!-- Challenge Rules (Dynamic based on type) -->
        <div v-if="selectedTypeConfig" class="bg-white rounded-lg shadow p-6">
          <h3 class="text-lg font-semibold text-gray-900 mb-4">Challenge Rules</h3>
          
          <!-- Spending-based Rules -->
          <div v-if="selectedTypeConfig.name === 'Spending-based'" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Minimum Spending (IDR) *</label>
                <input v-model="form.rules.min_amount" type="number" class="form-input w-full rounded border px-3 py-2" placeholder="300000" required />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Reward Type *</label>
                <select v-model="form.rules.reward_type" class="form-select w-full rounded border px-3 py-2" required>
                  <option value="">Pilih Reward Type</option>
                  <option value="item">Item</option>
                  <option value="points">Points</option>
                  <option value="voucher">Voucher</option>
                </select>
              </div>
            </div>
            
            <div v-if="form.rules.reward_type === 'item'">
              <label class="block text-sm font-medium text-gray-700 mb-2">Free Item *</label>
              <select v-model="form.rules.reward_value" class="form-select w-full rounded border px-3 py-2" required>
                <option value="">Pilih Item</option>
                <option v-for="item in challengeItems" :key="item.id" :value="item.name">{{ item.name }}</option>
              </select>
            </div>
            
            <div v-else-if="form.rules.reward_type === 'points'">
              <label class="block text-sm font-medium text-gray-700 mb-2">Points Amount *</label>
              <input v-model="form.rules.reward_value" type="number" class="form-input w-full rounded border px-3 py-2" placeholder="100" required />
            </div>
            
            <div v-else-if="form.rules.reward_type === 'voucher'">
              <label class="block text-sm font-medium text-gray-700 mb-2">Voucher Amount (IDR) *</label>
              <input v-model="form.rules.reward_value" type="number" class="form-input w-full rounded border px-3 py-2" placeholder="100000" required />
            </div>
            
            <div class="flex items-center">
              <input v-model="form.rules.immediate" type="checkbox" class="form-checkbox h-4 w-4 text-blue-600" />
              <label class="ml-2 text-sm text-gray-700">Reward langsung diberikan</label>
            </div>
          </div>

          <!-- Product-based Rules -->
          <div v-else-if="selectedTypeConfig.name === 'Product-based'" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Product Category *</label>
                <input v-model="form.rules.product_category" type="text" class="form-input w-full rounded border px-3 py-2" placeholder="New Product Development" required />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Quantity Required *</label>
                <input v-model="form.rules.quantity_required" type="number" class="form-input w-full rounded border px-3 py-2" placeholder="2" required />
              </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Reward Type *</label>
                <select v-model="form.rules.reward_type" class="form-select w-full rounded border px-3 py-2" required>
                  <option value="">Pilih Reward Type</option>
                  <option value="points">Points</option>
                  <option value="item">Item</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Reward Value *</label>
                <input v-model="form.rules.reward_value" type="text" class="form-input w-full rounded border px-3 py-2" placeholder="100 points" required />
              </div>
            </div>
          </div>

          <!-- Multi-condition Rules -->
          <div v-else-if="selectedTypeConfig.name === 'Multi-condition'" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Min Spending (IDR) *</label>
                <input v-model="form.rules.min_spending" type="number" class="form-input w-full rounded border px-3 py-2" placeholder="1000000" required />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Min Transactions *</label>
                <input v-model="form.rules.min_transactions" type="number" class="form-input w-full rounded border px-3 py-2" placeholder="2" required />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Min Visits *</label>
                <input v-model="form.rules.min_visits" type="number" class="form-input w-full rounded border px-3 py-2" placeholder="2" required />
              </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Reward Type *</label>
                <select v-model="form.rules.reward_type" class="form-select w-full rounded border px-3 py-2" required>
                  <option value="">Pilih Reward Type</option>
                  <option value="voucher">Voucher</option>
                  <option value="item">Item</option>
                  <option value="points">Points</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Reward Value *</label>
                <input v-model="form.rules.reward_value" type="text" class="form-input w-full rounded border px-3 py-2" placeholder="IDR 100.000" required />
              </div>
            </div>
          </div>
        </div>

        <!-- Validity & Settings -->
        <div class="bg-white rounded-lg shadow p-6">
          <h3 class="text-lg font-semibold text-gray-900 mb-4">Pengaturan Challenge</h3>
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Validity Period (Days) *</label>
              <input v-model="form.validity_period_days" type="number" class="form-input w-full rounded border px-3 py-2" placeholder="30" required />
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Start Date</label>
              <input v-model="form.start_date" type="date" class="form-input w-full rounded border px-3 py-2" />
            </div>
          </div>
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">End Date</label>
              <input v-model="form.end_date" type="date" class="form-input w-full rounded border px-3 py-2" />
            </div>
            
            <div class="flex items-center">
              <input v-model="form.is_active" type="checkbox" class="form-checkbox h-4 w-4 text-blue-600" />
              <label class="ml-2 text-sm text-gray-700">Aktifkan challenge</label>
            </div>
          </div>
        </div>

        <!-- Outlet Scope -->
        <div class="bg-white rounded-lg shadow p-6">
          <h3 class="text-lg font-semibold text-gray-900 mb-4">Scope Outlet</h3>
          
          <div class="space-y-2">
            <label class="block text-sm font-medium text-gray-700">Pilih Outlet (kosongkan untuk semua outlet)</label>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-2 max-h-40 overflow-y-auto border rounded p-2">
              <label v-for="outlet in outlets" :key="outlet.id" class="flex items-center">
                <input v-model="form.outlet_ids" :value="outlet.id" type="checkbox" class="form-checkbox h-4 w-4 text-blue-600" />
                <span class="ml-2 text-sm text-gray-700">{{ outlet.name }}</span>
              </label>
            </div>
          </div>
        </div>

        <!-- Submit Buttons -->
        <div class="flex justify-end space-x-4">
          <button type="button" @click="goBack" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg">
            Batal
          </button>
          <button type="submit" :disabled="loading" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg disabled:opacity-50">
            <span v-if="loading"><i class="fa-solid fa-spinner fa-spin mr-2"></i>Menyimpan...</span>
            <span v-else>{{ isEdit ? 'Update' : 'Simpan' }} Challenge</span>
          </button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'
import axios from 'axios'

const props = defineProps({
  challengeTypes: Array,
  challengeItems: Array,
  outlets: Array,
  challenge: Object
})

const isEdit = computed(() => !!props.challenge)

const form = ref({
  challenge_type_id: props.challenge?.challenge_type_id || '',
  name: props.challenge?.name || '',
  description: props.challenge?.description || '',
  rules: props.challenge?.rules || {},
  validity_period_days: props.challenge?.validity_period_days || 30,
  start_date: props.challenge?.start_date || '',
  end_date: props.challenge?.end_date || '',
  is_active: props.challenge?.is_active ?? true,
  outlet_ids: props.challenge?.outlets?.map(o => o.id) || []
})

const loading = ref(false)
const selectedTypeConfig = ref(null)

function onTypeChange() {
  if (form.value.challenge_type_id) {
    const type = props.challengeTypes.find(t => t.id == form.value.challenge_type_id)
    selectedTypeConfig.value = type
    
    // Reset rules when type changes
    form.value.rules = {}
    
    // Load challenge items for the type
    loadChallengeItems()
  } else {
    selectedTypeConfig.value = null
  }
}

async function loadChallengeItems() {
  try {
    const response = await axios.get(route('api.challenge-items'))
    // Update challenge items if needed
  } catch (error) {
    console.error('Error loading challenge items:', error)
  }
}

function submitForm() {
  loading.value = true
  
  const url = isEdit.value 
    ? route('challenges.update', props.challenge.id)
    : route('challenges.store')
    
  const method = isEdit.value ? 'put' : 'post'
  
  router[method](url, form.value, {
    onSuccess: () => {
      loading.value = false
    },
    onError: () => {
      loading.value = false
    }
  })
}

function goBack() {
  router.visit(route('challenges.index'))
}

// Initialize form if editing
if (isEdit.value) {
  onTypeChange()
}
</script>
