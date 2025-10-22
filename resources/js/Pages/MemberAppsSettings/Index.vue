<template>
  <AppLayout title="Member Apps Settings">
    <div class="w-full py-8 px-4">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-mobile-screen-button text-blue-500"></i> Member Apps Settings
        </h1>
      </div>

      <!-- Tab Navigation -->
      <div class="bg-white rounded-2xl shadow-lg overflow-hidden mb-6">
        <div class="border-b border-gray-200">
          <nav class="flex space-x-8 px-6" aria-label="Tabs">
            <button
              v-for="tab in tabs"
              :key="tab.id"
              @click="activeTab = tab.id"
              :class="[
                'py-4 px-1 border-b-2 font-medium text-sm transition-colors',
                activeTab === tab.id
                  ? 'border-blue-500 text-blue-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
              ]"
            >
              <i :class="tab.icon" class="mr-2"></i>
              {{ tab.name }}
            </button>
          </nav>
        </div>
      </div>

      <!-- Tab Content -->
      <div class="space-y-6">
        <!-- Banner Tab -->
        <div v-if="activeTab === 'banner'" class="bg-white rounded-2xl shadow-lg overflow-hidden">
          <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex justify-between items-center">
              <h3 class="text-lg font-semibold text-gray-700">Banner Management</h3>
              <button @click="openBannerModal" class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
                <i class="fa-solid fa-plus mr-2"></i>Add Banner
              </button>
            </div>
          </div>
          <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
              <div v-for="banner in banners" :key="banner.id" class="bg-gray-50 rounded-xl p-4 border border-gray-200 hover:shadow-lg transition-all">
                <div class="aspect-video bg-gray-200 rounded-lg mb-4 overflow-hidden">
                  <img v-if="banner.image" :src="getImageUrl(banner.image)" :alt="banner.title" class="w-full h-full object-cover">
                  <div v-else class="w-full h-full flex items-center justify-center text-gray-400">
                    <i class="fa-solid fa-image text-4xl"></i>
                  </div>
                </div>
                <h4 class="font-semibold text-gray-800 mb-2">{{ banner.title }}</h4>
                <p class="text-sm text-gray-600 mb-3">{{ banner.description }}</p>
                <div class="flex justify-between items-center">
                  <span :class="['px-2 py-1 rounded-full text-xs font-medium', banner.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800']">
                    {{ banner.is_active ? 'Active' : 'Inactive' }}
                  </span>
                  <div class="flex gap-2">
                    <button @click="editBanner(banner)" class="text-blue-600 hover:text-blue-800">
                      <i class="fa-solid fa-edit"></i>
                    </button>
                    <button @click="deleteBanner(banner.id)" class="text-red-600 hover:text-red-800">
                      <i class="fa-solid fa-trash"></i>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Reward Tab -->
        <div v-if="activeTab === 'reward'" class="bg-white rounded-2xl shadow-lg overflow-hidden">
          <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex justify-between items-center">
              <h3 class="text-lg font-semibold text-gray-700">Reward Management</h3>
              <button @click="openRewardModal" class="bg-gradient-to-r from-green-500 to-green-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
                <i class="fa-solid fa-plus mr-2"></i>Add Reward
              </button>
            </div>
          </div>
          <div class="p-6">
            <div class="overflow-x-auto">
              <table class="w-full">
                <thead class="bg-green-600 text-white">
                  <tr>
                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Item</th>
                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Points Required</th>
                    <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Status</th>
                    <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Actions</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <tr v-for="reward in rewards" :key="reward.id" class="hover:bg-gray-50">
                    <td class="px-4 py-4 whitespace-nowrap">
                      <div class="flex items-center">
                        <div class="flex-shrink-0 h-10 w-10">
                          <img v-if="reward.item?.image" :src="getImageUrl(reward.item.image)" :alt="reward.item.name" class="h-10 w-10 rounded-full object-cover">
                          <div v-else class="h-10 w-10 rounded-full bg-gray-200 flex items-center justify-center">
                            <i class="fa-solid fa-box text-gray-400"></i>
                          </div>
                        </div>
                        <div class="ml-4">
                          <div class="text-sm font-medium text-gray-900">{{ reward.item?.name }}</div>
                          <div class="text-sm text-gray-500">Rp {{ formatCurrency(reward.item?.price) }}</div>
                        </div>
                      </div>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                      <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                        <i class="fa-solid fa-coins mr-1"></i>
                        {{ reward.points_required }} points
                      </span>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap text-center">
                      <span :class="['inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium', reward.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800']">
                        {{ reward.is_active ? 'Active' : 'Inactive' }}
                      </span>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap text-center text-sm font-medium">
                      <div class="flex justify-center gap-2">
                        <button @click="editReward(reward)" class="text-blue-600 hover:text-blue-900">
                          <i class="fa-solid fa-edit"></i>
                        </button>
                        <button @click="deleteReward(reward.id)" class="text-red-600 hover:text-red-900">
                          <i class="fa-solid fa-trash"></i>
                        </button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Challenge Tab -->
        <div v-if="activeTab === 'challenge'" class="bg-white rounded-2xl shadow-lg overflow-hidden">
          <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex justify-between items-center">
              <h3 class="text-lg font-semibold text-gray-700">Challenge Management</h3>
              <button @click="openChallengeModal" class="bg-gradient-to-r from-purple-500 to-purple-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
                <i class="fa-solid fa-plus mr-2"></i>Add Challenge
              </button>
            </div>
          </div>
          <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
              <div v-for="challenge in challenges" :key="challenge.id" class="bg-gray-50 rounded-xl p-6 border border-gray-200 hover:shadow-lg transition-all">
                <div v-if="challenge.image" class="aspect-video bg-gray-200 rounded-lg mb-4 overflow-hidden">
                  <img :src="getImageUrl(challenge.image)" :alt="challenge.title" class="w-full h-full object-cover">
                </div>
                <h4 class="font-semibold text-gray-800 mb-2">{{ challenge.title }}</h4>
                <p class="text-sm text-gray-600 mb-3">{{ challenge.description }}</p>
                <div class="mb-3">
                  <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                    <i class="fa-solid fa-coins mr-1"></i>
                    {{ challenge.points_reward }} points
                  </span>
                </div>
                <div class="flex justify-between items-center mb-3">
                  <span :class="['px-2 py-1 rounded-full text-xs font-medium', challenge.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800']">
                    {{ challenge.is_active ? 'Active' : 'Inactive' }}
                  </span>
                  <div class="flex gap-2">
                    <button @click="editChallenge(challenge)" class="text-blue-600 hover:text-blue-800">
                      <i class="fa-solid fa-edit"></i>
                    </button>
                    <button @click="deleteChallenge(challenge.id)" class="text-red-600 hover:text-red-800">
                      <i class="fa-solid fa-trash"></i>
                    </button>
                  </div>
                </div>
                <div v-if="challenge.start_date || challenge.end_date" class="text-xs text-gray-500">
                  <div v-if="challenge.start_date">Start: {{ formatDate(challenge.start_date) }}</div>
                  <div v-if="challenge.end_date">End: {{ formatDate(challenge.end_date) }}</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Whats On Tab -->
        <div v-if="activeTab === 'whats-on'" class="bg-white rounded-2xl shadow-lg overflow-hidden">
          <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex justify-between items-center">
              <h3 class="text-lg font-semibold text-gray-700">Whats On Management</h3>
              <button @click="openWhatsOnModal" class="bg-gradient-to-r from-orange-500 to-orange-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
                <i class="fa-solid fa-plus mr-2"></i>Add News
              </button>
            </div>
          </div>
          <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
              <div v-for="news in whatsOn" :key="news.id" class="bg-gray-50 rounded-xl p-6 border border-gray-200 hover:shadow-lg transition-all">
                <div v-if="news.image" class="aspect-video bg-gray-200 rounded-lg mb-4 overflow-hidden">
                  <img :src="getImageUrl(news.image)" :alt="news.title" class="w-full h-full object-cover">
                </div>
                <h4 class="font-semibold text-gray-800 mb-2">{{ news.title }}</h4>
                <p class="text-sm text-gray-600 mb-3 line-clamp-3" v-html="news.content"></p>
                <div class="flex justify-between items-center mb-3">
                  <div class="flex gap-2">
                    <span :class="['px-2 py-1 rounded-full text-xs font-medium', news.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800']">
                      {{ news.is_active ? 'Active' : 'Inactive' }}
                    </span>
                    <span v-if="news.is_featured" class="px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                      Featured
                    </span>
                  </div>
                  <div class="flex gap-2">
                    <button @click="editWhatsOn(news)" class="text-blue-600 hover:text-blue-800">
                      <i class="fa-solid fa-edit"></i>
                    </button>
                    <button @click="deleteWhatsOn(news.id)" class="text-red-600 hover:text-red-800">
                      <i class="fa-solid fa-trash"></i>
                    </button>
                  </div>
                </div>
                <div v-if="news.published_at" class="text-xs text-gray-500">
                  Published: {{ formatDate(news.published_at) }}
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Brand Tab -->
        <div v-if="activeTab === 'brand'" class="bg-white rounded-2xl shadow-lg overflow-hidden">
          <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex justify-between items-center">
              <h3 class="text-lg font-semibold text-gray-700">Brand Management</h3>
              <button @click="openBrandModal" class="bg-gradient-to-r from-indigo-500 to-indigo-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
                <i class="fa-solid fa-plus mr-2"></i>Add Brand
              </button>
            </div>
          </div>
          <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
              <div v-for="brand in brands" :key="brand.id" class="bg-gray-50 rounded-xl p-6 border border-gray-200 hover:shadow-lg transition-all">
                <div v-if="brand.image" class="aspect-video bg-gray-200 rounded-lg mb-4 overflow-hidden">
                  <img :src="getImageUrl(brand.image)" :alt="brand.name" class="w-full h-full object-cover">
                </div>
                <h4 class="font-semibold text-gray-800 mb-2">{{ brand.name }}</h4>
                <p class="text-sm text-gray-600 mb-3">{{ brand.description }}</p>
                <div v-if="brand.website_url" class="mb-3">
                  <a :href="brand.website_url" target="_blank" class="text-blue-600 hover:text-blue-800 text-sm">
                    <i class="fa-solid fa-external-link-alt mr-1"></i>
                    {{ brand.website_url }}
                  </a>
                </div>
                <div class="flex justify-between items-center mb-3">
                  <span :class="['px-2 py-1 rounded-full text-xs font-medium', brand.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800']">
                    {{ brand.is_active ? 'Active' : 'Inactive' }}
                  </span>
                  <div class="flex gap-2">
                    <button @click="editBrand(brand)" class="text-blue-600 hover:text-blue-800">
                      <i class="fa-solid fa-edit"></i>
                    </button>
                    <button @click="deleteBrand(brand.id)" class="text-red-600 hover:text-red-800">
                      <i class="fa-solid fa-trash"></i>
                    </button>
                  </div>
                </div>
                <div v-if="brand.pdf_file" class="text-xs">
                  <a :href="getImageUrl(brand.pdf_file)" target="_blank" class="text-blue-600 hover:text-blue-800">
                    <i class="fa-solid fa-file-pdf mr-1"></i>
                    Download PDF
                  </a>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Banner Modal -->
    <div v-if="showBannerModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto">
        <div class="px-6 py-4 border-b border-gray-200">
          <div class="flex justify-between items-center">
            <h3 class="text-lg font-semibold text-gray-700">
              {{ editingBanner ? 'Edit Banner' : 'Add Banner' }}
            </h3>
            <button @click="closeBannerModal" class="text-gray-400 hover:text-gray-600">
              <i class="fa-solid fa-times text-xl"></i>
            </button>
          </div>
        </div>
        <form @submit.prevent="saveBanner" class="p-6">
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Title</label>
              <input v-model="bannerForm.title" type="text" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Image</label>
              <input @change="handleBannerImageChange" type="file" accept="image/*" :required="!editingBanner" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
              <div v-if="editingBanner && editingBanner.image" class="mt-2">
                <img :src="getImageUrl(editingBanner.image)" alt="Current image" class="w-32 h-20 object-cover rounded">
                <p class="text-sm text-gray-500 mt-1">Current image</p>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
              <textarea v-model="bannerForm.description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Sort Order</label>
              <input v-model.number="bannerForm.sort_order" type="number" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="flex items-center">
              <input v-model="bannerForm.is_active" type="checkbox" id="banner_active" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
              <label for="banner_active" class="ml-2 block text-sm text-gray-700">Active</label>
            </div>
          </div>
          <div class="flex justify-end gap-3 mt-6">
            <button type="button" @click="closeBannerModal" class="px-4 py-2 text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition">
              Cancel
            </button>
            <button type="submit" :disabled="savingBanner" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2">
              <i v-if="savingBanner" class="fa-solid fa-spinner fa-spin"></i>
              {{ savingBanner ? 'Saving...' : (editingBanner ? 'Update' : 'Create') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Reward Modal -->
    <div v-if="showRewardModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto">
        <div class="px-6 py-4 border-b border-gray-200">
          <div class="flex justify-between items-center">
            <h3 class="text-lg font-semibold text-gray-700">
              {{ editingReward ? 'Edit Reward' : 'Add Reward' }}
            </h3>
            <button @click="closeRewardModal" class="text-gray-400 hover:text-gray-600">
              <i class="fa-solid fa-times text-xl"></i>
            </button>
          </div>
        </div>
        <form @submit.prevent="saveReward" class="p-6">
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Item</label>
              <Multiselect
                v-model="rewardForm.item_id"
                :options="items"
                :searchable="true"
                :clear-on-select="false"
                :close-on-select="true"
                :show-labels="false"
                track-by="id"
                label="name"
                placeholder="Select Item"
                :allow-empty="false"
                required
              >
                <template #option="{ option }">
                  <div class="flex justify-between items-center">
                    <span>{{ option.name }}</span>
                  </div>
                </template>
                <template #singleLabel="{ value }">
                  <div class="flex justify-between items-center">
                    <span>{{ value.name }}</span>
                  </div>
                </template>
              </Multiselect>
              <div v-if="rewardForm.item_id" class="mt-2 p-2 bg-green-50 border border-green-200 rounded-lg">
                <div class="flex items-center gap-2">
                  <i class="fa-solid fa-check-circle text-green-600"></i>
                  <span class="text-sm text-green-800">
                    Selected: <strong>{{ rewardForm.item_id.name }}</strong>
                  </span>
                </div>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Points Required</label>
              <input v-model.number="rewardForm.points_required" type="number" min="1" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="flex items-center">
              <input v-model="rewardForm.is_active" type="checkbox" id="reward_active" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
              <label for="reward_active" class="ml-2 block text-sm text-gray-700">Active</label>
            </div>
          </div>
          <div class="flex justify-end gap-3 mt-6">
            <button type="button" @click="closeRewardModal" class="px-4 py-2 text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition">
              Cancel
            </button>
            <button type="submit" :disabled="savingReward" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2">
              <i v-if="savingReward" class="fa-solid fa-spinner fa-spin"></i>
              {{ savingReward ? 'Saving...' : (editingReward ? 'Update' : 'Create') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Challenge Modal -->
    <div v-if="showChallengeModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto">
        <div class="px-6 py-4 border-b border-gray-200">
          <div class="flex justify-between items-center">
            <h3 class="text-lg font-semibold text-gray-700">
              {{ editingChallenge ? 'Edit Challenge' : 'Add Challenge' }}
            </h3>
            <button @click="closeChallengeModal" class="text-gray-400 hover:text-gray-600">
              <i class="fa-solid fa-times text-xl"></i>
            </button>
          </div>
        </div>
        <form @submit.prevent="saveChallenge" class="p-6">
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Title</label>
              <input v-model="challengeForm.title" type="text" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
              <textarea v-model="challengeForm.description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Rules</label>
              <textarea v-model="challengeForm.rules" rows="4" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Image</label>
              <input @change="handleChallengeImageChange" type="file" accept="image/*" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
              <div v-if="editingChallenge && editingChallenge.image" class="mt-2">
                <img :src="getImageUrl(editingChallenge.image)" alt="Current image" class="w-32 h-20 object-cover rounded">
                <p class="text-sm text-gray-500 mt-1">Current image</p>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Points Reward</label>
              <input v-model.number="challengeForm.points_reward" type="number" min="0" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Start Date</label>
                <input v-model="challengeForm.start_date" type="date" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">End Date</label>
                <input v-model="challengeForm.end_date" type="date" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
              </div>
            </div>
            <div class="flex items-center">
              <input v-model="challengeForm.is_active" type="checkbox" id="challenge_active" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
              <label for="challenge_active" class="ml-2 block text-sm text-gray-700">Active</label>
            </div>
          </div>
          <div class="flex justify-end gap-3 mt-6">
            <button type="button" @click="closeChallengeModal" class="px-4 py-2 text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition">
              Cancel
            </button>
            <button type="submit" :disabled="savingChallenge" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2">
              <i v-if="savingChallenge" class="fa-solid fa-spinner fa-spin"></i>
              {{ savingChallenge ? 'Saving...' : (editingChallenge ? 'Update' : 'Create') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Whats On Modal -->
    <div v-if="showWhatsOnModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl mx-4 max-h-[90vh] overflow-y-auto">
        <div class="px-6 py-4 border-b border-gray-200">
          <div class="flex justify-between items-center">
            <h3 class="text-lg font-semibold text-gray-700">
              {{ editingWhatsOn ? 'Edit News' : 'Add News' }}
            </h3>
            <button @click="closeWhatsOnModal" class="text-gray-400 hover:text-gray-600">
              <i class="fa-solid fa-times text-xl"></i>
            </button>
          </div>
        </div>
        <form @submit.prevent="saveWhatsOn" class="p-6">
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Title</label>
              <input v-model="whatsOnForm.title" type="text" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Content</label>
              <textarea v-model="whatsOnForm.content" rows="8" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Image</label>
              <input @change="handleWhatsOnImageChange" type="file" accept="image/*" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
              <div v-if="editingWhatsOn && editingWhatsOn.image" class="mt-2">
                <img :src="getImageUrl(editingWhatsOn.image)" alt="Current image" class="w-32 h-20 object-cover rounded">
                <p class="text-sm text-gray-500 mt-1">Current image</p>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Published At</label>
              <input v-model="whatsOnForm.published_at" type="datetime-local" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="flex items-center space-x-4">
              <div class="flex items-center">
                <input v-model="whatsOnForm.is_active" type="checkbox" id="whats_on_active" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                <label for="whats_on_active" class="ml-2 block text-sm text-gray-700">Active</label>
              </div>
              <div class="flex items-center">
                <input v-model="whatsOnForm.is_featured" type="checkbox" id="whats_on_featured" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                <label for="whats_on_featured" class="ml-2 block text-sm text-gray-700">Featured</label>
              </div>
            </div>
          </div>
          <div class="flex justify-end gap-3 mt-6">
            <button type="button" @click="closeWhatsOnModal" class="px-4 py-2 text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition">
              Cancel
            </button>
            <button type="submit" :disabled="savingWhatsOn" class="px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2">
              <i v-if="savingWhatsOn" class="fa-solid fa-spinner fa-spin"></i>
              {{ savingWhatsOn ? 'Saving...' : (editingWhatsOn ? 'Update' : 'Create') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Brand Modal -->
    <div v-if="showBrandModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto">
        <div class="px-6 py-4 border-b border-gray-200">
          <div class="flex justify-between items-center">
            <h3 class="text-lg font-semibold text-gray-700">
              {{ editingBrand ? 'Edit Brand' : 'Add Brand' }}
            </h3>
            <button @click="closeBrandModal" class="text-gray-400 hover:text-gray-600">
              <i class="fa-solid fa-times text-xl"></i>
            </button>
          </div>
        </div>
        <form @submit.prevent="saveBrand" class="p-6">
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Name</label>
              <input v-model="brandForm.name" type="text" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
              <textarea v-model="brandForm.description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Website URL</label>
              <input v-model="brandForm.website_url" type="url" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Image</label>
              <input @change="handleBrandImageChange" type="file" accept="image/*" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
              <div v-if="editingBrand && editingBrand.image" class="mt-2">
                <img :src="getImageUrl(editingBrand.image)" alt="Current image" class="w-32 h-20 object-cover rounded">
                <p class="text-sm text-gray-500 mt-1">Current image</p>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">PDF File</label>
              <input @change="handleBrandPdfChange" type="file" accept=".pdf" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
              <div v-if="editingBrand && editingBrand.pdf_file" class="mt-2">
                <a :href="getImageUrl(editingBrand.pdf_file)" target="_blank" class="text-blue-600 hover:text-blue-800 text-sm">
                  <i class="fa-solid fa-file-pdf mr-1"></i>Current PDF
                </a>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Sort Order</label>
              <input v-model.number="brandForm.sort_order" type="number" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="flex items-center">
              <input v-model="brandForm.is_active" type="checkbox" id="brand_active" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
              <label for="brand_active" class="ml-2 block text-sm text-gray-700">Active</label>
            </div>
          </div>
          <div class="flex justify-end gap-3 mt-6">
            <button type="button" @click="closeBrandModal" class="px-4 py-2 text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition">
              Cancel
            </button>
            <button type="submit" :disabled="savingBrand" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2">
              <i v-if="savingBrand" class="fa-solid fa-spinner fa-spin"></i>
              {{ savingBrand ? 'Saving...' : (editingBrand ? 'Update' : 'Create') }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Swal from 'sweetalert2'
import Multiselect from 'vue-multiselect'
import 'vue-multiselect/dist/vue-multiselect.min.css'

const props = defineProps({
  banners: Array,
  rewards: Array,
  challenges: Array,
  whatsOn: Array,
  brands: Array
})

const activeTab = ref('banner')

const tabs = [
  { id: 'banner', name: 'Banner', icon: 'fa-solid fa-image' },
  { id: 'reward', name: 'Reward', icon: 'fa-solid fa-gift' },
  { id: 'challenge', name: 'Challenge', icon: 'fa-solid fa-trophy' },
  { id: 'whats-on', name: 'Whats On', icon: 'fa-solid fa-newspaper' },
  { id: 'brand', name: 'Brand', icon: 'fa-solid fa-building' }
]

// Modal states
const showBannerModal = ref(false)
const showRewardModal = ref(false)
const showChallengeModal = ref(false)
const showWhatsOnModal = ref(false)
const showBrandModal = ref(false)

// Editing states
const editingBanner = ref(null)
const editingReward = ref(null)
const editingChallenge = ref(null)
const editingWhatsOn = ref(null)
const editingBrand = ref(null)

// Form data
const bannerForm = ref({
  title: '',
  image: null,
  description: '',
  sort_order: 0,
  is_active: true
})

const rewardForm = ref({
  item_id: null,
  points_required: 1,
  is_active: true
})

const challengeForm = ref({
  title: '',
  description: '',
  rules: '',
  image: null,
  points_reward: 0,
  start_date: '',
  end_date: '',
  is_active: true
})

const whatsOnForm = ref({
  title: '',
  content: '',
  image: null,
  published_at: '',
  is_active: true,
  is_featured: false
})

const brandForm = ref({
  name: '',
  description: '',
  image: null,
  pdf_file: null,
  website_url: '',
  sort_order: 0,
  is_active: true
})

// Items for reward dropdown
const items = ref([])

// Loading states
const savingBanner = ref(false)
const savingReward = ref(false)
const savingChallenge = ref(false)
const savingWhatsOn = ref(false)
const savingBrand = ref(false)

// Helper functions
const getImageUrl = (path) => {
  if (!path) return null
  return `/storage/${path}`
}

const formatCurrency = (amount) => {
  if (!amount) return '0'
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0
  }).format(amount)
}

const formatDate = (date) => {
  if (!date) return ''
  return new Date(date).toLocaleDateString('id-ID', {
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
}

// Modal functions
const openBannerModal = () => {
  editingBanner.value = null
  bannerForm.value = {
    title: '',
    image: null,
    description: '',
    sort_order: 0,
    is_active: true
  }
  showBannerModal.value = true
}

const openRewardModal = () => {
  editingReward.value = null
  rewardForm.value = {
    item_id: null,
    points_required: 1,
    is_active: true
  }
  showRewardModal.value = true
}

const openChallengeModal = () => {
  editingChallenge.value = null
  challengeForm.value = {
    title: '',
    description: '',
    rules: '',
    image: null,
    points_reward: 0,
    start_date: '',
    end_date: '',
    is_active: true
  }
  showChallengeModal.value = true
}

const openWhatsOnModal = () => {
  editingWhatsOn.value = null
  whatsOnForm.value = {
    title: '',
    content: '',
    image: null,
    published_at: '',
    is_active: true,
    is_featured: false
  }
  showWhatsOnModal.value = true
}

const openBrandModal = () => {
  editingBrand.value = null
  brandForm.value = {
    name: '',
    description: '',
    image: null,
    pdf_file: null,
    website_url: '',
    sort_order: 0,
    is_active: true
  }
  showBrandModal.value = true
}

// Close modal functions
const closeBannerModal = () => {
  showBannerModal.value = false
  editingBanner.value = null
}

const closeRewardModal = () => {
  showRewardModal.value = false
  editingReward.value = null
}

const closeChallengeModal = () => {
  showChallengeModal.value = false
  editingChallenge.value = null
}

const closeWhatsOnModal = () => {
  showWhatsOnModal.value = false
  editingWhatsOn.value = null
}

const closeBrandModal = () => {
  showBrandModal.value = false
  editingBrand.value = null
}

// Edit functions
const editBanner = (banner) => {
  editingBanner.value = banner
  bannerForm.value = {
    title: banner.title,
    image: null,
    description: banner.description || '',
    sort_order: banner.sort_order || 0,
    is_active: banner.is_active
  }
  showBannerModal.value = true
}

const editReward = (reward) => {
  editingReward.value = reward
  // Find the exact item object from the 'items' array
  const selectedItem = items.value.find(item => item.id === reward.item.id)
  
  rewardForm.value = {
    item_id: selectedItem || null, // Set to the found object or null
    points_required: reward.points_required,
    is_active: reward.is_active
  }
  showRewardModal.value = true
}

const editChallenge = (challenge) => {
  editingChallenge.value = challenge
  challengeForm.value = {
    title: challenge.title,
    description: challenge.description || '',
    rules: challenge.rules,
    image: null,
    points_reward: challenge.points_reward,
    start_date: challenge.start_date || '',
    end_date: challenge.end_date || '',
    is_active: challenge.is_active
  }
  showChallengeModal.value = true
}

const editWhatsOn = (news) => {
  editingWhatsOn.value = news
  whatsOnForm.value = {
    title: news.title,
    content: news.content,
    image: null,
    published_at: news.published_at ? new Date(news.published_at).toISOString().slice(0, 16) : '',
    is_active: news.is_active,
    is_featured: news.is_featured
  }
  showWhatsOnModal.value = true
}

const editBrand = (brand) => {
  editingBrand.value = brand
  brandForm.value = {
    name: brand.name,
    description: brand.description || '',
    image: null,
    pdf_file: null,
    website_url: brand.website_url || '',
    sort_order: brand.sort_order || 0,
    is_active: brand.is_active
  }
  showBrandModal.value = true
}

// File handlers
const handleBannerImageChange = (event) => {
  bannerForm.value.image = event.target.files[0]
}

const handleChallengeImageChange = (event) => {
  challengeForm.value.image = event.target.files[0]
}

const handleWhatsOnImageChange = (event) => {
  whatsOnForm.value.image = event.target.files[0]
}

const handleBrandImageChange = (event) => {
  brandForm.value.image = event.target.files[0]
}

const handleBrandPdfChange = (event) => {
  brandForm.value.pdf_file = event.target.files[0]
}

// Save functions
const saveBanner = () => {
  if (savingBanner.value) return
  
  savingBanner.value = true
  
  const formData = new FormData()
  formData.append('title', bannerForm.value.title)
  formData.append('description', bannerForm.value.description)
  formData.append('sort_order', bannerForm.value.sort_order)
  formData.append('is_active', bannerForm.value.is_active)
  
  if (bannerForm.value.image) {
    formData.append('image', bannerForm.value.image)
  }

  const url = editingBanner.value 
    ? `/admin/member-apps-settings/banner/${editingBanner.value.id}`
    : '/admin/member-apps-settings/banner'
  
  const method = editingBanner.value ? 'put' : 'post'
  
  router[method](url, formData, {
    forceFormData: true,
    onSuccess: () => {
      savingBanner.value = false
      closeBannerModal()
      Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: editingBanner.value ? 'Banner updated successfully!' : 'Banner created successfully!',
        timer: 2000,
        showConfirmButton: false
      })
    },
    onError: () => {
      savingBanner.value = false
      Swal.fire({
        icon: 'error',
        title: 'Error!',
        text: 'Failed to save banner. Please try again.',
        confirmButtonText: 'OK'
      })
    }
  })
}

const saveReward = () => {
  if (savingReward.value) return
  
  savingReward.value = true
  
  const data = {
    item_id: rewardForm.value.item_id?.id || rewardForm.value.item_id,
    points_required: rewardForm.value.points_required,
    is_active: rewardForm.value.is_active
  }

  const url = editingReward.value 
    ? `/admin/member-apps-settings/reward/${editingReward.value.id}`
    : '/admin/member-apps-settings/reward'
  
  const method = editingReward.value ? 'put' : 'post'
  
  router[method](url, data, {
    onSuccess: () => {
      savingReward.value = false
      closeRewardModal()
      Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: editingReward.value ? 'Reward updated successfully!' : 'Reward created successfully!',
        timer: 2000,
        showConfirmButton: false
      })
    },
    onError: () => {
      savingReward.value = false
      Swal.fire({
        icon: 'error',
        title: 'Error!',
        text: 'Failed to save reward. Please try again.',
        confirmButtonText: 'OK'
      })
    }
  })
}

const saveChallenge = () => {
  if (savingChallenge.value) return
  
  savingChallenge.value = true
  
  const formData = new FormData()
  formData.append('title', challengeForm.value.title)
  formData.append('description', challengeForm.value.description)
  formData.append('rules', challengeForm.value.rules)
  formData.append('points_reward', challengeForm.value.points_reward)
  formData.append('start_date', challengeForm.value.start_date)
  formData.append('end_date', challengeForm.value.end_date)
  formData.append('is_active', challengeForm.value.is_active)
  
  if (challengeForm.value.image) {
    formData.append('image', challengeForm.value.image)
  }

  const url = editingChallenge.value 
    ? `/admin/member-apps-settings/challenge/${editingChallenge.value.id}`
    : '/admin/member-apps-settings/challenge'
  
  const method = editingChallenge.value ? 'put' : 'post'
  
  router[method](url, formData, {
    forceFormData: true,
    onSuccess: () => {
      savingChallenge.value = false
      closeChallengeModal()
      Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: editingChallenge.value ? 'Challenge updated successfully!' : 'Challenge created successfully!',
        timer: 2000,
        showConfirmButton: false
      })
    },
    onError: () => {
      savingChallenge.value = false
      Swal.fire({
        icon: 'error',
        title: 'Error!',
        text: 'Failed to save challenge. Please try again.',
        confirmButtonText: 'OK'
      })
    }
  })
}

const saveWhatsOn = () => {
  if (savingWhatsOn.value) return
  
  savingWhatsOn.value = true
  
  const formData = new FormData()
  formData.append('title', whatsOnForm.value.title)
  formData.append('content', whatsOnForm.value.content)
  formData.append('published_at', whatsOnForm.value.published_at)
  formData.append('is_active', whatsOnForm.value.is_active)
  formData.append('is_featured', whatsOnForm.value.is_featured)
  
  if (whatsOnForm.value.image) {
    formData.append('image', whatsOnForm.value.image)
  }

  const url = editingWhatsOn.value 
    ? `/admin/member-apps-settings/whats-on/${editingWhatsOn.value.id}`
    : '/admin/member-apps-settings/whats-on'
  
  const method = editingWhatsOn.value ? 'put' : 'post'
  
  router[method](url, formData, {
    forceFormData: true,
    onSuccess: () => {
      savingWhatsOn.value = false
      closeWhatsOnModal()
      Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: editingWhatsOn.value ? 'News updated successfully!' : 'News created successfully!',
        timer: 2000,
        showConfirmButton: false
      })
    },
    onError: () => {
      savingWhatsOn.value = false
      Swal.fire({
        icon: 'error',
        title: 'Error!',
        text: 'Failed to save news. Please try again.',
        confirmButtonText: 'OK'
      })
    }
  })
}

const saveBrand = () => {
  if (savingBrand.value) return
  
  savingBrand.value = true
  
  const formData = new FormData()
  formData.append('name', brandForm.value.name)
  formData.append('description', brandForm.value.description)
  formData.append('website_url', brandForm.value.website_url)
  formData.append('sort_order', brandForm.value.sort_order)
  formData.append('is_active', brandForm.value.is_active)
  
  if (brandForm.value.image) {
    formData.append('image', brandForm.value.image)
  }
  
  if (brandForm.value.pdf_file) {
    formData.append('pdf_file', brandForm.value.pdf_file)
  }

  const url = editingBrand.value 
    ? `/admin/member-apps-settings/brand/${editingBrand.value.id}`
    : '/admin/member-apps-settings/brand'
  
  const method = editingBrand.value ? 'put' : 'post'
  
  router[method](url, formData, {
    forceFormData: true,
    onSuccess: () => {
      savingBrand.value = false
      closeBrandModal()
      Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: editingBrand.value ? 'Brand updated successfully!' : 'Brand created successfully!',
        timer: 2000,
        showConfirmButton: false
      })
    },
    onError: () => {
      savingBrand.value = false
      Swal.fire({
        icon: 'error',
        title: 'Error!',
        text: 'Failed to save brand. Please try again.',
        confirmButtonText: 'OK'
      })
    }
  })
}

// Delete functions
const deleteBanner = (id) => {
  Swal.fire({
    title: 'Are you sure?',
    text: "You won't be able to revert this!",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Yes, delete it!'
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(`/admin/member-apps-settings/banner/${id}`, {
        onSuccess: () => {
          Swal.fire({
            icon: 'success',
            title: 'Deleted!',
            text: 'Banner has been deleted.',
            timer: 2000,
            showConfirmButton: false
          })
        },
        onError: () => {
          Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'Failed to delete banner.',
            confirmButtonText: 'OK'
          })
        }
      })
    }
  })
}

const deleteReward = (id) => {
  Swal.fire({
    title: 'Are you sure?',
    text: "You won't be able to revert this!",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Yes, delete it!'
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(`/admin/member-apps-settings/reward/${id}`, {
        onSuccess: () => {
          Swal.fire({
            icon: 'success',
            title: 'Deleted!',
            text: 'Reward has been deleted.',
            timer: 2000,
            showConfirmButton: false
          })
        },
        onError: () => {
          Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'Failed to delete reward.',
            confirmButtonText: 'OK'
          })
        }
      })
    }
  })
}

const deleteChallenge = (id) => {
  Swal.fire({
    title: 'Are you sure?',
    text: "You won't be able to revert this!",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Yes, delete it!'
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(`/admin/member-apps-settings/challenge/${id}`, {
        onSuccess: () => {
          Swal.fire({
            icon: 'success',
            title: 'Deleted!',
            text: 'Challenge has been deleted.',
            timer: 2000,
            showConfirmButton: false
          })
        },
        onError: () => {
          Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'Failed to delete challenge.',
            confirmButtonText: 'OK'
          })
        }
      })
    }
  })
}

const deleteWhatsOn = (id) => {
  Swal.fire({
    title: 'Are you sure?',
    text: "You won't be able to revert this!",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Yes, delete it!'
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(`/admin/member-apps-settings/whats-on/${id}`, {
        onSuccess: () => {
          Swal.fire({
            icon: 'success',
            title: 'Deleted!',
            text: 'News has been deleted.',
            timer: 2000,
            showConfirmButton: false
          })
        },
        onError: () => {
          Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'Failed to delete news.',
            confirmButtonText: 'OK'
          })
        }
      })
    }
  })
}

const deleteBrand = (id) => {
  Swal.fire({
    title: 'Are you sure?',
    text: "You won't be able to revert this!",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Yes, delete it!'
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(`/admin/member-apps-settings/brand/${id}`, {
        onSuccess: () => {
          Swal.fire({
            icon: 'success',
            title: 'Deleted!',
            text: 'Brand has been deleted.',
            timer: 2000,
            showConfirmButton: false
          })
        },
        onError: () => {
          Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'Failed to delete brand.',
            confirmButtonText: 'OK'
          })
        }
      })
    }
  })
}

// Load items for reward dropdown
const loadItems = async () => {
  try {
    const response = await fetch('/admin/member-apps-settings/items')
    const data = await response.json()
    items.value = data.map(item => ({
      id: item.id,
      name: item.name
    }))
  } catch (error) {
    console.error('Error loading items:', error)
    Swal.fire({
      icon: 'error',
      title: 'Error!',
      text: 'Failed to load items. Please refresh the page.',
      confirmButtonText: 'OK'
    })
  }
}

onMounted(() => {
  loadItems()
})
</script>

<script>
import Multiselect from 'vue-multiselect'

export default {
  components: {
    Multiselect
  }
}
</script>
