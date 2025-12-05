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
              <label class="block text-sm font-medium text-gray-700 mb-2">Pilih Items (Bisa Multiple) *</label>
              <multiselect
                v-model="form.rules.reward_value"
                :options="challengeItems || []"
                :multiple="true"
                :searchable="true"
                :close-on-select="false"
                :show-labels="false"
                placeholder="Pilih Items (bisa lebih dari satu)"
                label="name"
                track-by="id"
                class="mt-1"
                required
              >
                <template #noOptions>
                  <span>Tidak ada item ditemukan</span>
                </template>
                <template #noResult>
                  <span>Tidak ada item ditemukan</span>
                </template>
              </multiselect>
              <p class="mt-1 text-xs text-gray-500">Anda bisa memilih lebih dari satu item</p>
              
              <!-- Reward Item Selection Mode (hanya muncul jika lebih dari 1 item dipilih) -->
              <div v-if="Array.isArray(form.rules.reward_value) && form.rules.reward_value.length > 1" class="mt-4 p-4 bg-yellow-50 rounded-lg border border-yellow-200">
                <label class="block text-sm font-medium text-gray-700 mb-3">Mode Pemberian Reward *</label>
                <p class="text-xs text-gray-600 mb-3">Pilih bagaimana reward akan diberikan kepada member:</p>
                <div class="space-y-2">
                  <label class="flex items-center cursor-pointer">
                    <input
                      v-model="form.rules.reward_item_selection"
                      type="radio"
                      value="all"
                      class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300"
                    >
                    <div class="ml-3">
                      <span class="text-sm font-medium text-gray-700">Semua Item (All)</span>
                      <p class="text-xs text-gray-500">Member akan mendapatkan semua item reward yang dipilih</p>
                    </div>
                  </label>
                  <label class="flex items-center cursor-pointer">
                    <input
                      v-model="form.rules.reward_item_selection"
                      type="radio"
                      value="single"
                      class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300"
                    >
                    <div class="ml-3">
                      <span class="text-sm font-medium text-gray-700">Pilih Salah Satu (Single)</span>
                      <p class="text-xs text-gray-500">Member hanya bisa memilih salah satu item reward saat claim</p>
                    </div>
                  </label>
                </div>
              </div>
            </div>
            
            <div v-else-if="form.rules.reward_type === 'points'">
              <label class="block text-sm font-medium text-gray-700 mb-2">Points Amount *</label>
              <input v-model="form.rules.reward_value" type="number" class="form-input w-full rounded border px-3 py-2" placeholder="100" min="0" required />
            </div>
            
            <div v-else-if="form.rules.reward_type === 'voucher'">
              <label class="block text-sm font-medium text-gray-700 mb-2">Pilih Vouchers (Bisa Multiple) *</label>
              <multiselect
                v-model="form.rules.reward_value"
                :options="vouchers || []"
                :multiple="true"
                :searchable="true"
                :close-on-select="false"
                :show-labels="false"
                placeholder="Pilih Vouchers (bisa lebih dari satu)"
                label="name"
                track-by="id"
                class="mt-1"
                required
              >
                <template #option="{ option }">
                  <div>
                    <strong>{{ option.name }}</strong>
                    <span v-if="option.code" class="text-gray-500 text-sm ml-2">({{ option.code }})</span>
                  </div>
                </template>
                <template #singleLabel="{ option }">
                  <strong>{{ option.name }}</strong>
                  <span v-if="option.code" class="text-gray-500 text-sm ml-2">({{ option.code }})</span>
                </template>
                <template #tag="{ option, remove }">
                  <span class="multiselect__tag">
                    <span>{{ option.name }}</span>
                    <i class="multiselect__tag-icon" @click.prevent="remove(option)"></i>
                  </span>
                </template>
                <template #noOptions>
                  <span>Tidak ada voucher ditemukan</span>
                </template>
                <template #noResult>
                  <span>Tidak ada voucher ditemukan</span>
                </template>
              </multiselect>
            </div>
            
            <div class="flex items-center">
              <input v-model="form.rules.immediate" type="checkbox" class="form-checkbox h-4 w-4 text-blue-600" />
              <label class="ml-2 text-sm text-gray-700">Reward langsung diberikan</label>
            </div>
            
            <!-- Challenge Outlet Scope (untuk menentukan di outlet mana challenge berlaku) -->
            <div class="mt-4 p-4 bg-gray-50 rounded-lg">
              <h4 class="text-md font-semibold text-gray-900 mb-3">Challenge Outlet Scope</h4>
              <p class="text-sm text-gray-600 mb-3">Tentukan di outlet mana challenge ini berlaku. Jika tidak dipilih, challenge berlaku di semua outlet.</p>
              <div class="space-y-2">
                <div class="flex items-center">
                  <input
                    v-model="form.rules.challenge_all_outlets"
                    @change="onChallengeAllOutletsChange"
                    type="checkbox"
                    id="challenge_all_outlets_spending"
                    class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                  >
                  <label for="challenge_all_outlets_spending" class="ml-2 text-sm text-gray-700">Semua Outlet</label>
                </div>
                <div v-if="!form.rules.challenge_all_outlets">
                  <label class="block text-sm font-medium text-gray-700 mb-2">Pilih Outlet Challenge</label>
                  <Multiselect
                    v-model="form.rules.challenge_outlet_ids"
                    :options="outlets"
                    :searchable="true"
                    :clear-on-select="false"
                    :close-on-select="false"
                    :multiple="true"
                    :show-labels="false"
                    placeholder="Pilih outlet (kosongkan untuk semua outlet)"
                    label="name"
                    track-by="id"
                    class="mt-1"
                  >
                    <template #noOptions>
                      <span>Tidak ada outlet ditemukan</span>
                    </template>
                    <template #noResult>
                      <span>Tidak ada outlet ditemukan</span>
                    </template>
                  </Multiselect>
                  <p class="mt-1 text-xs text-gray-500">Challenge hanya akan berlaku di outlet yang dipilih</p>
                </div>
              </div>
            </div>
            
            <!-- Reward Outlet Scope (untuk menentukan di outlet mana reward bisa ditebus) -->
            <div v-if="form.rules.reward_type === 'item' || form.rules.reward_type === 'voucher'" class="mt-4 p-4 bg-blue-50 rounded-lg">
              <h4 class="text-md font-semibold text-gray-900 mb-3">Reward Outlet Scope</h4>
              <p class="text-sm text-gray-600 mb-3">Tentukan di outlet mana reward bisa ditebus. Bisa berbeda dengan challenge outlet scope.</p>
              <div class="space-y-2">
                <div class="flex items-center">
                  <input
                    v-model="form.rules.reward_all_outlets"
                    @change="onRewardAllOutletsChange('spending', form.rules.reward_type)"
                    type="checkbox"
                    id="reward_all_outlets_spending"
                    class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                  >
                  <label for="reward_all_outlets_spending" class="ml-2 text-sm text-gray-700">Semua Outlet</label>
                </div>
                <div v-if="!form.rules.reward_all_outlets">
                  <label class="block text-sm font-medium text-gray-700 mb-2">Pilih Outlet Reward</label>
                  <Multiselect
                    v-model="form.rules.reward_outlet_ids"
                    :options="outlets"
                    :searchable="true"
                    :clear-on-select="false"
                    :close-on-select="false"
                    :multiple="true"
                    :show-labels="false"
                    placeholder="Pilih outlet (kosongkan untuk semua outlet)"
                    label="name"
                    track-by="id"
                    class="mt-1"
                  >
                    <template #noOptions>
                      <span>Tidak ada outlet ditemukan</span>
                    </template>
                    <template #noResult>
                      <span>Tidak ada outlet ditemukan</span>
                    </template>
                  </Multiselect>
                  <p class="mt-1 text-xs text-gray-500">Reward hanya bisa ditebus di outlet yang dipilih</p>
                </div>
              </div>
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
            
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Reward Type *</label>
              <select v-model="form.rules.reward_type" class="form-select w-full rounded border px-3 py-2" required>
                <option value="">Pilih Reward Type</option>
                <option value="points">Points</option>
                <option value="item">Item</option>
              </select>
            </div>
            
            <div v-if="form.rules.reward_type === 'item'">
              <label class="block text-sm font-medium text-gray-700 mb-2">Pilih Items (Bisa Multiple) *</label>
              <multiselect
                v-model="form.rules.reward_value"
                :options="challengeItems || []"
                :multiple="true"
                :searchable="true"
                :close-on-select="false"
                :show-labels="false"
                placeholder="Pilih Items (bisa lebih dari satu)"
                label="name"
                track-by="id"
                class="mt-1"
                required
              >
                <template #noOptions>
                  <span>Tidak ada item ditemukan</span>
                </template>
                <template #noResult>
                  <span>Tidak ada item ditemukan</span>
                </template>
              </multiselect>
              <p class="mt-1 text-xs text-gray-500">Anda bisa memilih lebih dari satu item</p>
              
              <!-- Reward Item Selection Mode (hanya muncul jika lebih dari 1 item dipilih) -->
              <div v-if="Array.isArray(form.rules.reward_value) && form.rules.reward_value.length > 1" class="mt-4 p-4 bg-yellow-50 rounded-lg border border-yellow-200">
                <label class="block text-sm font-medium text-gray-700 mb-3">Mode Pemberian Reward *</label>
                <p class="text-xs text-gray-600 mb-3">Pilih bagaimana reward akan diberikan kepada member:</p>
                <div class="space-y-2">
                  <label class="flex items-center cursor-pointer">
                    <input
                      v-model="form.rules.reward_item_selection"
                      type="radio"
                      value="all"
                      class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300"
                    >
                    <div class="ml-3">
                      <span class="text-sm font-medium text-gray-700">Semua Item (All)</span>
                      <p class="text-xs text-gray-500">Member akan mendapatkan semua item reward yang dipilih</p>
                    </div>
                  </label>
                  <label class="flex items-center cursor-pointer">
                    <input
                      v-model="form.rules.reward_item_selection"
                      type="radio"
                      value="single"
                      class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300"
                    >
                    <div class="ml-3">
                      <span class="text-sm font-medium text-gray-700">Pilih Salah Satu (Single)</span>
                      <p class="text-xs text-gray-500">Member hanya bisa memilih salah satu item reward saat claim</p>
                    </div>
                  </label>
                </div>
              </div>
            </div>
            
            <div v-else-if="form.rules.reward_type === 'points'">
              <label class="block text-sm font-medium text-gray-700 mb-2">Points Amount *</label>
              <input v-model="form.rules.reward_value" type="number" class="form-input w-full rounded border px-3 py-2" placeholder="100" min="0" required />
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
            
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Reward Type *</label>
              <select v-model="form.rules.reward_type" class="form-select w-full rounded border px-3 py-2" required>
                <option value="">Pilih Reward Type</option>
                <option value="voucher">Voucher</option>
                <option value="item">Item</option>
                <option value="points">Points</option>
              </select>
            </div>
            
            <div v-if="form.rules.reward_type === 'item'">
              <label class="block text-sm font-medium text-gray-700 mb-2">Pilih Items (Bisa Multiple) *</label>
              <multiselect
                v-model="form.rules.reward_value"
                :options="challengeItems || []"
                :multiple="true"
                :searchable="true"
                :close-on-select="false"
                :show-labels="false"
                placeholder="Pilih Items (bisa lebih dari satu)"
                label="name"
                track-by="id"
                class="mt-1"
                required
              >
                <template #noOptions>
                  <span>Tidak ada item ditemukan</span>
                </template>
                <template #noResult>
                  <span>Tidak ada item ditemukan</span>
                </template>
              </multiselect>
              <p class="mt-1 text-xs text-gray-500">Anda bisa memilih lebih dari satu item</p>
              
              <!-- Reward Item Selection Mode (hanya muncul jika lebih dari 1 item dipilih) -->
              <div v-if="Array.isArray(form.rules.reward_value) && form.rules.reward_value.length > 1" class="mt-4 p-4 bg-yellow-50 rounded-lg border border-yellow-200">
                <label class="block text-sm font-medium text-gray-700 mb-3">Mode Pemberian Reward *</label>
                <p class="text-xs text-gray-600 mb-3">Pilih bagaimana reward akan diberikan kepada member:</p>
                <div class="space-y-2">
                  <label class="flex items-center cursor-pointer">
                    <input
                      v-model="form.rules.reward_item_selection"
                      type="radio"
                      value="all"
                      class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300"
                    >
                    <div class="ml-3">
                      <span class="text-sm font-medium text-gray-700">Semua Item (All)</span>
                      <p class="text-xs text-gray-500">Member akan mendapatkan semua item reward yang dipilih</p>
                    </div>
                  </label>
                  <label class="flex items-center cursor-pointer">
                    <input
                      v-model="form.rules.reward_item_selection"
                      type="radio"
                      value="single"
                      class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300"
                    >
                    <div class="ml-3">
                      <span class="text-sm font-medium text-gray-700">Pilih Salah Satu (Single)</span>
                      <p class="text-xs text-gray-500">Member hanya bisa memilih salah satu item reward saat claim</p>
                    </div>
                  </label>
                </div>
              </div>
            </div>
            
            <div v-else-if="form.rules.reward_type === 'points'">
              <label class="block text-sm font-medium text-gray-700 mb-2">Points Amount *</label>
              <input v-model="form.rules.reward_value" type="number" class="form-input w-full rounded border px-3 py-2" placeholder="100" min="0" required />
            </div>
            
            <div v-else-if="form.rules.reward_type === 'voucher'">
              <label class="block text-sm font-medium text-gray-700 mb-2">Pilih Vouchers (Bisa Multiple) *</label>
              <multiselect
                v-model="form.rules.reward_value"
                :options="vouchers || []"
                :multiple="true"
                :searchable="true"
                :close-on-select="false"
                :show-labels="false"
                placeholder="Pilih Vouchers (bisa lebih dari satu)"
                label="name"
                track-by="id"
                class="mt-1"
                required
              >
                <template #option="{ option }">
                  <div>
                    <strong>{{ option.name }}</strong>
                    <span v-if="option.code" class="text-gray-500 text-sm ml-2">({{ option.code }})</span>
                  </div>
                </template>
                <template #singleLabel="{ option }">
                  <strong>{{ option.name }}</strong>
                  <span v-if="option.code" class="text-gray-500 text-sm ml-2">({{ option.code }})</span>
                </template>
                <template #tag="{ option, remove }">
                  <span class="multiselect__tag">
                    <span>{{ option.name }}</span>
                    <i class="multiselect__tag-icon" @click.prevent="remove(option)"></i>
                  </span>
                </template>
                <template #noOptions>
                  <span>Tidak ada voucher ditemukan</span>
                </template>
                <template #noResult>
                  <span>Tidak ada voucher ditemukan</span>
                </template>
              </multiselect>
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
import Multiselect from 'vue-multiselect'
import 'vue-multiselect/dist/vue-multiselect.min.css'

const props = defineProps({
  challengeTypes: {
    type: Array,
    default: () => []
  },
  challengeItems: {
    type: Array,
    default: () => []
  },
  vouchers: {
    type: Array,
    default: () => []
  },
  outlets: {
    type: Array,
    default: () => []
  },
  challenge: {
    type: Object,
    default: null
  }
})

const isEdit = computed(() => !!props.challenge)

// Initialize reward_value based on reward_type
const initializeRewardValue = (rules) => {
  if (!rules || !rules.reward_type) return null
  
  if (rules.reward_type === 'points') {
    // For points, reward_value should be a number
    return typeof rules.reward_value === 'number' ? rules.reward_value : (parseInt(rules.reward_value) || 0)
  } else if (rules.reward_type === 'item' || rules.reward_type === 'voucher') {
    // For items and vouchers, reward_value should be an array
    if (Array.isArray(rules.reward_value)) {
      return rules.reward_value
    } else if (rules.reward_value) {
      // If it's a string or single value, try to find the item/voucher and convert to array
      if (rules.reward_type === 'item') {
        const item = props.challengeItems.find(i => i.id == rules.reward_value || i.name === rules.reward_value)
        return item ? [item] : []
      } else if (rules.reward_type === 'voucher') {
        const voucher = props.vouchers.find(v => v.id == rules.reward_value || v.name === rules.reward_value)
        return voucher ? [voucher] : []
      }
    }
    return []
  }
  return null
}

const form = ref({
  challenge_type_id: props.challenge?.challenge_type_id || '',
  name: props.challenge?.name || '',
  description: props.challenge?.description || '',
  rules: props.challenge?.rules ? {
    ...props.challenge.rules,
    reward_value: initializeRewardValue(props.challenge.rules),
    // Initialize challenge outlet scope
    challenge_all_outlets: props.challenge?.rules?.challenge_all_outlets ?? true,
    challenge_outlet_ids: props.challenge?.rules?.challenge_outlet_ids || [],
    // Initialize reward outlet scope (if exists)
    reward_all_outlets: props.challenge?.rules?.reward_all_outlets ?? true,
    reward_outlet_ids: props.challenge?.rules?.reward_outlet_ids || [],
    // Initialize reward item selection (default: all)
    reward_item_selection: props.challenge?.rules?.reward_item_selection || 'all'
  } : {
    challenge_all_outlets: true,
    challenge_outlet_ids: [],
    reward_all_outlets: true,
    reward_outlet_ids: [],
    reward_item_selection: 'all'
  },
  validity_period_days: props.challenge?.validity_period_days || 30,
  start_date: props.challenge?.start_date || '',
  end_date: props.challenge?.end_date || '',
  is_active: props.challenge?.is_active ?? true,
  outlet_ids: props.challenge?.outlets?.map(o => o.id) || [] // Keep for backward compatibility
})

const loading = ref(false)
const selectedTypeConfig = ref(null)

function onTypeChange() {
  if (form.value.challenge_type_id) {
    const type = props.challengeTypes.find(t => t.id == form.value.challenge_type_id)
    selectedTypeConfig.value = type
    
    // Reset rules when type changes (but preserve reward_value if editing)
    if (!isEdit.value) {
      form.value.rules = {}
    } else {
      // Keep existing rules but reset reward_value format
      form.value.rules = {
        ...form.value.rules,
        reward_value: initializeRewardValue(form.value.rules)
      }
    }
    
    // Load challenge items for the type
    loadChallengeItems()
  } else {
    selectedTypeConfig.value = null
  }
}

// Watch reward_type changes to reset reward_value format
watch(() => form.value.rules?.reward_type, (newType, oldType) => {
  if (newType !== oldType) {
    if (newType === 'points') {
      form.value.rules.reward_value = 0
    } else if (newType === 'item' || newType === 'voucher') {
      form.value.rules.reward_value = []
    }
  }
})

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
  
  // Format reward_value before submitting
  const formData = { ...form.value }
  
  if (formData.rules?.reward_type && formData.rules?.reward_value) {
    if (formData.rules.reward_type === 'item' || formData.rules.reward_type === 'voucher') {
      // For items and vouchers, send array of IDs
      if (Array.isArray(formData.rules.reward_value)) {
        formData.rules.reward_value = formData.rules.reward_value.map(item => 
          typeof item === 'object' ? item.id : item
        )
      }
    } else if (formData.rules.reward_type === 'points') {
      // For points, ensure it's a number
      formData.rules.reward_value = parseInt(formData.rules.reward_value) || 0
    }
  }
  
  // Format challenge outlet_ids and save challenge_all_outlets before converting to JSON
  let challengeOutletIds = []
  const challengeAllOutlets = formData.rules?.challenge_all_outlets ?? true
  if (formData.rules?.challenge_outlet_ids && Array.isArray(formData.rules.challenge_outlet_ids)) {
    challengeOutletIds = formData.rules.challenge_outlet_ids.map(outlet => 
      typeof outlet === 'object' ? outlet.id : outlet
    )
    formData.rules.challenge_outlet_ids = challengeOutletIds
  }
  
  // Format reward outlet_ids (for item and voucher rewards)
  if (formData.rules?.reward_outlet_ids && Array.isArray(formData.rules.reward_outlet_ids)) {
    formData.rules.reward_outlet_ids = formData.rules.reward_outlet_ids.map(outlet => 
      typeof outlet === 'object' ? outlet.id : outlet
    )
  }
  
  // Set default reward_item_selection if not set and multiple items selected
  if (formData.rules?.reward_type === 'item' && 
      Array.isArray(formData.rules.reward_value) && 
      formData.rules.reward_value.length > 1 &&
      !formData.rules.reward_item_selection) {
    formData.rules.reward_item_selection = 'all' // Default to 'all' if not specified
  }
  
  // Convert rules to JSON string for backend
  if (formData.rules) {
    formData.rules = JSON.stringify(formData.rules)
  }
  
  // Include challenge outlet_ids separately for database storage
  // If challenge_all_outlets is true, set empty array, otherwise use challenge_outlet_ids
  if (challengeAllOutlets) {
    formData.challenge_outlet_ids = []
  } else {
    formData.challenge_outlet_ids = challengeOutletIds
  }
  
  const url = isEdit.value 
    ? route('challenges.update', props.challenge.id)
    : route('challenges.store')
    
  const method = isEdit.value ? 'put' : 'post'
  
  router[method](url, formData, {
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

// Handler for challenge all outlets change
function onChallengeAllOutletsChange() {
  if (form.value.rules.challenge_all_outlets) {
    form.value.rules.challenge_outlet_ids = []
  }
}

// Handler for reward all outlets change
function onRewardAllOutletsChange(challengeType, rewardType) {
  if (form.value.rules.reward_all_outlets) {
    form.value.rules.reward_outlet_ids = []
  }
}

// Initialize form if editing
if (isEdit.value) {
  onTypeChange()
}
</script>
