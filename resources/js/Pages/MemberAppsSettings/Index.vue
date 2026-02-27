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
                    <button @click="viewChallenge(challenge.id)" class="text-green-600 hover:text-green-800" title="View Detail">
                      <i class="fa-solid fa-eye"></i>
                    </button>
                    <button @click="editChallenge(challenge)" class="text-blue-600 hover:text-blue-800" title="Edit">
                      <i class="fa-solid fa-edit"></i>
                    </button>
                    <button @click="deleteChallenge(challenge.id)" class="text-red-600 hover:text-red-800" title="Delete">
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

        <!-- Outlet Tab (formerly Brand Tab) -->
        <div v-if="activeTab === 'outlet'" class="bg-white rounded-2xl shadow-lg overflow-hidden">
          <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex justify-between items-center">
              <h3 class="text-lg font-semibold text-gray-700">Outlet Management</h3>
              <button @click="openBrandModal" class="bg-gradient-to-r from-indigo-500 to-indigo-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
                <i class="fa-solid fa-plus mr-2"></i>Add Brand
              </button>
            </div>
          </div>
          <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
              <div v-for="brand in brands" :key="brand.id" class="bg-gray-50 rounded-xl p-6 border border-gray-200 hover:shadow-lg transition-all">
                <div v-if="brand.logo" class="aspect-video bg-gray-200 rounded-lg mb-4 overflow-hidden flex items-center justify-center">
                  <img :src="getImageUrl(brand.logo)" :alt="brand.name" class="w-full h-full object-contain">
                </div>
                <h4 class="font-semibold text-gray-800 mb-2">{{ brand.name }}</h4>
                <p v-if="brand.outlet_id" class="text-xs text-gray-500 mb-1">Outlet ID: {{ brand.outlet_id }}</p>
                <p class="text-sm text-gray-600 mb-3">{{ brand.description }}</p>
                <div v-if="brand.website_url" class="mb-3">
                  <a :href="brand.website_url" target="_blank" class="text-blue-600 hover:text-blue-800 text-sm">
                    <i class="fa-solid fa-external-link-alt mr-1"></i>
                    {{ brand.website_url }}
                  </a>
                </div>
                <div class="mb-3 space-y-1">
                  <div v-if="brand.pdf_menu" class="text-xs">
                    <a :href="getImageUrl(brand.pdf_menu)" target="_blank" class="text-blue-600 hover:text-blue-800">
                      <i class="fa-solid fa-file-pdf mr-1"></i>PDF Menu
                    </a>
                  </div>
                  <div v-if="brand.pdf_new_dining_experience" class="text-xs">
                    <a :href="getImageUrl(brand.pdf_new_dining_experience)" target="_blank" class="text-blue-600 hover:text-blue-800">
                      <i class="fa-solid fa-file-pdf mr-1"></i>PDF Monthly Promotion
                    </a>
                  </div>
                  <div v-if="brand.galleries && brand.galleries.length > 0" class="mt-2">
                    <p class="text-xs text-gray-600 mb-2">
                      <i class="fa-solid fa-images mr-1"></i>{{ brand.galleries.length }} Gallery Image(s)
                    </p>
                    <div class="grid grid-cols-3 gap-1">
                      <div 
                        v-for="(gallery, index) in brand.galleries" 
                        :key="gallery.id"
                        @click="openGalleryLightbox(brand.galleries, index)"
                        class="cursor-pointer hover:opacity-80 transition-opacity"
                      >
                        <img 
                          :src="getImageUrl(gallery.image)" 
                          :alt="`Gallery ${index + 1}`" 
                          class="w-full h-16 object-cover rounded border border-gray-200"
                        >
                      </div>
                    </div>
                  </div>
                </div>
                <div v-if="brand.facility && (Array.isArray(brand.facility) ? brand.facility.length > 0 : JSON.parse(brand.facility || '[]').length > 0)" class="mb-3">
                  <p class="text-xs font-medium text-gray-700 mb-1">
                    <i class="fa-solid fa-building mr-1"></i>Facilities:
                  </p>
                  <div class="flex flex-wrap gap-1">
                    <span 
                      v-for="(facility, index) in (Array.isArray(brand.facility) ? brand.facility : JSON.parse(brand.facility || '[]'))" 
                      :key="index"
                      class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs"
                    >
                      {{ getFacilityName(facility) }}
                    </span>
                  </div>
                </div>
                <div v-if="brand.tripadvisor_link" class="mb-3">
                  <p class="text-xs font-medium text-gray-700 mb-1">
                    <i class="fa-solid fa-star mr-1"></i>TripAdvisor:
                  </p>
                  <a :href="brand.tripadvisor_link" target="_blank" class="text-blue-600 hover:text-blue-800 text-xs break-all">
                    <i class="fa-solid fa-external-link-alt mr-1"></i>
                    {{ brand.tripadvisor_link }}
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
              </div>
            </div>
          </div>
        </div>

        <!-- Brands Tab (for brands table) -->
        <div v-if="activeTab === 'brands'" class="bg-white rounded-2xl shadow-lg overflow-hidden">
          <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex justify-between items-center">
              <h3 class="text-lg font-semibold text-gray-700">Brand Management</h3>
              <button @click="openBrandTableModal" class="bg-gradient-to-r from-indigo-500 to-indigo-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
                <i class="fa-solid fa-plus mr-2"></i>Add Brand
              </button>
            </div>
          </div>
          <div class="p-6">
            <div class="overflow-x-auto">
              <table class="w-full">
                <thead class="bg-indigo-600 text-white">
                  <tr>
                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">ID</th>
                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Brand Name</th>
                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Logo</th>
                    <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wider">Actions</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <tr v-for="brandTable in brandsTable" :key="brandTable.id" class="hover:bg-gray-50">
                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ brandTable.id }}</td>
                    <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ brandTable.brand }}</td>
                    <td class="px-4 py-4 whitespace-nowrap">
                      <div class="flex-shrink-0 h-10 w-10">
                        <img v-if="brandTable.logo" :src="getImageUrl(brandTable.logo)" :alt="brandTable.brand" class="h-10 w-10 rounded-full object-cover">
                        <div v-else class="h-10 w-10 rounded-full bg-gray-200 flex items-center justify-center">
                          <i class="fa-solid fa-image text-gray-400"></i>
                        </div>
                      </div>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap text-center text-sm font-medium">
                      <div class="flex justify-center gap-2">
                        <button @click="editBrandTable(brandTable)" class="text-blue-600 hover:text-blue-800">
                          <i class="fa-solid fa-edit"></i>
                        </button>
                        <button @click="deleteBrandTable(brandTable.id)" class="text-red-600 hover:text-red-800">
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

        <!-- FAQ Tab -->
        <div v-if="activeTab === 'faq'" class="bg-white rounded-2xl shadow-lg overflow-hidden">
          <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex justify-between items-center">
              <h3 class="text-lg font-semibold text-gray-700">FAQ Management</h3>
              <button @click="openFaqModal" class="bg-gradient-to-r from-purple-500 to-purple-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
                <i class="fa-solid fa-plus mr-2"></i>Add FAQ
              </button>
            </div>
          </div>
          <div class="p-6">
            <div class="space-y-4">
              <div v-for="faq in props.faqs" :key="faq.id" class="bg-gray-50 rounded-xl p-6 border border-gray-200 hover:shadow-lg transition-all">
                <div class="flex justify-between items-start mb-3">
                  <div class="flex-1">
                    <h4 class="font-semibold text-gray-800 mb-2 flex items-start gap-2">
                      <i class="fa-solid fa-question-circle text-purple-500 mt-1"></i>
                      <span>{{ faq.question }}</span>
                    </h4>
                    <div class="ml-7 text-sm text-gray-600 whitespace-pre-line">{{ faq.answer }}</div>
                  </div>
                  <div class="flex gap-2 ml-4">
                    <button @click="editFaq(faq)" class="text-blue-600 hover:text-blue-800">
                      <i class="fa-solid fa-edit"></i>
                    </button>
                    <button @click="deleteFaq(faq.id)" class="text-red-600 hover:text-red-800">
                      <i class="fa-solid fa-trash"></i>
                    </button>
                  </div>
                </div>
                <div class="flex justify-between items-center mt-4 pt-4 border-t border-gray-200">
                  <span :class="['px-2 py-1 rounded-full text-xs font-medium', faq.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800']">
                    {{ faq.is_active ? 'Active' : 'Inactive' }}
                  </span>
                </div>
              </div>
              <div v-if="!props.faqs || props.faqs.length === 0" class="text-center py-12 text-gray-500">
                <i class="fa-solid fa-question-circle text-4xl mb-4"></i>
                <p>No FAQs yet. Click "Add FAQ" to create one.</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Terms & Condition Tab -->
        <div v-if="activeTab === 'terms-condition'" class="bg-white rounded-2xl shadow-lg overflow-hidden">
          <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex justify-between items-center">
              <h3 class="text-lg font-semibold text-gray-700">Terms & Condition Management</h3>
              <button @click="openTermConditionModal" class="bg-gradient-to-r from-orange-500 to-orange-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
                <i class="fa-solid fa-plus mr-2"></i>Add Terms & Condition
              </button>
            </div>
          </div>
          <div class="p-6">
            <div class="space-y-4">
              <div v-for="termCondition in props.termsConditions" :key="termCondition.id" class="bg-gray-50 rounded-xl p-6 border border-gray-200 hover:shadow-lg transition-all">
                <div class="flex justify-between items-start mb-3">
                  <div class="flex-1">
                    <h4 class="font-semibold text-gray-800 mb-3 flex items-start gap-2">
                      <i class="fa-solid fa-file-contract text-orange-500 mt-1"></i>
                      <span>{{ termCondition.title }}</span>
                    </h4>
                    <div class="ml-7 text-sm text-gray-600 whitespace-pre-line max-h-48 overflow-y-auto">{{ termCondition.content }}</div>
                  </div>
                  <div class="flex gap-2 ml-4">
                    <button @click="editTermCondition(termCondition)" class="text-blue-600 hover:text-blue-800">
                      <i class="fa-solid fa-edit"></i>
                    </button>
                    <button @click="deleteTermCondition(termCondition.id)" class="text-red-600 hover:text-red-800">
                      <i class="fa-solid fa-trash"></i>
                    </button>
                  </div>
                </div>
                <div class="flex justify-between items-center mt-4 pt-4 border-t border-gray-200">
                  <span :class="['px-2 py-1 rounded-full text-xs font-medium', termCondition.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800']">
                    {{ termCondition.is_active ? 'Active' : 'Inactive' }}
                  </span>
                </div>
              </div>
              <div v-if="!props.termsConditions || props.termsConditions.length === 0" class="text-center py-12 text-gray-500">
                <i class="fa-solid fa-file-contract text-4xl mb-4"></i>
                <p>No Terms & Conditions yet. Click "Add Terms & Condition" to create one.</p>
              </div>
            </div>
          </div>
        </div>

        <!-- About Us Tab -->
        <div v-if="activeTab === 'about-us'" class="bg-white rounded-2xl shadow-lg overflow-hidden">
          <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex justify-between items-center">
              <h3 class="text-lg font-semibold text-gray-700">About Us Management</h3>
              <button @click="openAboutUsModal" class="bg-gradient-to-r from-teal-500 to-teal-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
                <i class="fa-solid fa-plus mr-2"></i>Add About Us
              </button>
            </div>
          </div>
          <div class="p-6">
            <div class="space-y-4">
              <div v-for="about in props.aboutUs" :key="about.id" class="bg-gray-50 rounded-xl p-6 border border-gray-200 hover:shadow-lg transition-all">
                <div class="flex justify-between items-start mb-3">
                  <div class="flex-1">
                    <h4 class="font-semibold text-gray-800 mb-3 flex items-start gap-2">
                      <i class="fa-solid fa-info-circle text-teal-500 mt-1"></i>
                      <span>{{ about.title }}</span>
                    </h4>
                    <div class="ml-7 text-sm text-gray-600 whitespace-pre-line max-h-48 overflow-y-auto">{{ about.content }}</div>
                  </div>
                  <div class="flex gap-2 ml-4">
                    <button @click="editAboutUs(about)" class="text-blue-600 hover:text-blue-800">
                      <i class="fa-solid fa-edit"></i>
                    </button>
                    <button @click="deleteAboutUs(about.id)" class="text-red-600 hover:text-red-800">
                      <i class="fa-solid fa-trash"></i>
                    </button>
                  </div>
                </div>
                <div class="flex justify-between items-center mt-4 pt-4 border-t border-gray-200">
                  <span :class="['px-2 py-1 rounded-full text-xs font-medium', about.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800']">
                    {{ about.is_active ? 'Active' : 'Inactive' }}
                  </span>
                </div>
              </div>
              <div v-if="!props.aboutUs || props.aboutUs.length === 0" class="text-center py-12 text-gray-500">
                <i class="fa-solid fa-info-circle text-4xl mb-4"></i>
                <p>No About Us yet. Click "Add About Us" to create one.</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Benefits Tab -->
        <div v-if="activeTab === 'benefits'" class="bg-white rounded-2xl shadow-lg overflow-hidden">
          <div class="p-6">
            <div class="flex justify-between items-center mb-6">
              <h3 class="text-lg font-semibold text-gray-700">Benefits Management</h3>
              <button @click="openBenefitsModal" class="bg-gradient-to-r from-teal-500 to-teal-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
                <i class="fa-solid fa-plus mr-2"></i>Add Benefits
              </button>
            </div>

            <div class="space-y-4">
              <div v-for="benefit in props.benefits" :key="benefit.id" class="bg-gray-50 rounded-xl p-6 border border-gray-200 hover:shadow-lg transition-all">
                <div class="flex justify-between items-start">
                  <div class="flex-1">
                    <h4 class="text-lg font-semibold text-gray-800 mb-2">{{ benefit.title }}</h4>
                    <p class="text-gray-600 text-sm mb-3" v-html="benefit.content.substring(0, 100) + (benefit.content.length > 100 ? '...' : '')"></p>
                    <span :class="benefit.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'" class="px-3 py-1 rounded-full text-xs font-semibold">
                      {{ benefit.is_active ? 'Active' : 'Inactive' }}
                    </span>
                  </div>
                  <div class="flex gap-2 ml-4">
                    <button @click="editBenefits(benefit)" class="text-blue-600 hover:text-blue-800">
                      <i class="fa-solid fa-edit"></i>
                    </button>
                    <button @click="deleteBenefits(benefit.id)" class="text-red-600 hover:text-red-800">
                      <i class="fa-solid fa-trash"></i>
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <div v-if="!props.benefits || props.benefits.length === 0" class="text-center py-12 text-gray-500">
              <i class="fa-solid fa-star text-4xl mb-4 text-gray-300"></i>
              <p>No benefits yet. Click "Add Benefits" to create one.</p>
            </div>
          </div>
        </div>

        <!-- Contact Us Tab -->
        <div v-if="activeTab === 'contact-us'" class="bg-white rounded-2xl shadow-lg overflow-hidden">
          <div class="p-6">
            <div class="flex justify-between items-center mb-6">
              <h3 class="text-lg font-semibold text-gray-700">Contact Us Management</h3>
              <button @click="openContactUsModal" class="bg-gradient-to-r from-teal-500 to-teal-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
                <i class="fa-solid fa-plus mr-2"></i>Add Contact Us
              </button>
            </div>

            <div class="space-y-4">
              <div v-for="contact in props.contactUs" :key="contact.id" class="bg-gray-50 rounded-xl p-6 border border-gray-200 hover:shadow-lg transition-all">
                <div class="flex justify-between items-start">
                  <div class="flex-1">
                    <h4 class="text-lg font-semibold text-gray-800 mb-2">{{ contact.title }}</h4>
                    <p class="text-gray-600 text-sm mb-3" v-html="contact.content.substring(0, 100) + (contact.content.length > 100 ? '...' : '')"></p>
                    <div v-if="contact.whatsapp_number" class="mb-2">
                      <span class="text-xs text-gray-500">WhatsApp: </span>
                      <span class="text-xs font-semibold text-green-600">{{ contact.whatsapp_number }}</span>
                    </div>
                    <span :class="contact.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'" class="px-3 py-1 rounded-full text-xs font-semibold">
                      {{ contact.is_active ? 'Active' : 'Inactive' }}
                    </span>
                  </div>
                  <div class="flex gap-2 ml-4">
                    <button @click="editContactUs(contact)" class="text-blue-600 hover:text-blue-800">
                      <i class="fa-solid fa-edit"></i>
                    </button>
                    <button @click="deleteContactUs(contact.id)" class="text-red-600 hover:text-red-800">
                      <i class="fa-solid fa-trash"></i>
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <div v-if="!props.contactUs || props.contactUs.length === 0" class="text-center py-12 text-gray-500">
              <i class="fa-solid fa-phone text-4xl mb-4 text-gray-300"></i>
              <p>No contact us yet. Click "Add Contact Us" to create one.</p>
            </div>
          </div>
        </div>

        <!-- Feedback Tab -->
        <div v-if="activeTab === 'feedback'" class="bg-white rounded-2xl shadow-lg overflow-hidden">
          <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex justify-between items-center">
              <h3 class="text-lg font-semibold text-gray-700">Feedback Management</h3>
              <div class="flex gap-2 items-center">
                <!-- Search -->
                <div class="relative flex-1 max-w-md">
                  <input
                    v-model="feedbackSearch"
                    @input="searchFeedbacks"
                    type="text"
                    placeholder="Search feedbacks..."
                    class="w-full px-4 py-2 pl-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  >
                  <i class="fa-solid fa-search absolute left-3 top-3 text-gray-400"></i>
                </div>
                <!-- Filter Status -->
                <select v-model="feedbackStatusFilter" @change="filterFeedbacks" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                  <option value="">All Status</option>
                  <option value="pending">Pending</option>
                  <option value="read">Read</option>
                  <option value="replied">Replied</option>
                  <option value="resolved">Resolved</option>
                </select>
                <!-- Per Page -->
                <select v-model="feedbackPerPage" @change="filterFeedbacks" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                  <option value="10">10 per page</option>
                  <option value="20">20 per page</option>
                  <option value="50">50 per page</option>
                  <option value="100">100 per page</option>
                </select>
                <!-- Refresh -->
                <button 
                  @click="refreshFeedbacks" 
                  :disabled="refreshingFeedbacks"
                  class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                  title="Refresh feedback data"
                >
                  <i :class="['fa-solid', refreshingFeedbacks ? 'fa-spinner fa-spin' : 'fa-arrows-rotate']"></i>
                  <span v-if="!refreshingFeedbacks">Refresh</span>
                  <span v-else>Refreshing...</span>
                </button>
              </div>
            </div>
          </div>
          <div class="p-6">
            <div class="space-y-4">
              <div v-for="feedback in filteredFeedbacks" :key="feedback.id" class="bg-gray-50 rounded-xl p-6 border border-gray-200 hover:shadow-lg transition-all">
                <div class="flex justify-between items-start mb-3">
                  <div class="flex-1">
                    <div class="flex items-center gap-3 mb-2">
                      <h4 class="font-semibold text-gray-800">{{ feedback.subject }}</h4>
                      <span :class="['px-2 py-1 rounded-full text-xs font-medium', getStatusClass(feedback.status)]">
                        {{ feedback.status.toUpperCase() }}
                      </span>
                      <span v-if="feedback.rating" class="flex items-center gap-1 text-yellow-500">
                        <i class="fa-solid fa-star"></i>
                        <span class="text-sm font-medium">{{ feedback.rating }}</span>
                      </span>
                    </div>
                    <div class="text-sm text-gray-600 mb-2">
                      <i class="fa-solid fa-user mr-2"></i>
                      <span class="font-medium">{{ feedback.member?.nama_lengkap || 'Unknown' }}</span>
                      <span class="mx-2">•</span>
                      <span>{{ feedback.member?.email || 'N/A' }}</span>
                    </div>
                    <div v-if="feedback.outlet_name" class="text-sm text-gray-600 mb-2">
                      <i class="fa-solid fa-store mr-2"></i>
                      <span>{{ feedback.outlet_name }}</span>
                    </div>
                    <div class="text-sm text-gray-500 mb-3">
                      <i class="fa-solid fa-clock mr-2"></i>
                      {{ formatFeedbackDate(feedback.created_at) }}
                    </div>
                    <div class="bg-white rounded-lg p-4 border border-gray-200 mb-3">
                      <p class="text-gray-700 whitespace-pre-line">{{ feedback.message }}</p>
                    </div>
                    <!-- Replies Thread with Collapse/Expand -->
                    <div v-if="feedback.replies && feedback.replies.length > 0" class="mt-3">
                      <button
                        @click="toggleFeedbackReplies(feedback.id)"
                        class="flex items-center gap-2 text-sm text-blue-600 hover:text-blue-800 mb-2"
                      >
                        <i :class="['fa-solid', expandedReplies.has(feedback.id) ? 'fa-chevron-up' : 'fa-chevron-down']"></i>
                        <span>{{ feedback.replies.length }} {{ feedback.replies.length === 1 ? 'Reply' : 'Replies' }}</span>
                      </button>
                      <div v-show="expandedReplies.has(feedback.id)" class="space-y-2">
                        <div 
                          v-for="reply in feedback.replies" 
                          :key="reply.id"
                          :class="[
                            'rounded-lg p-4 border',
                            reply.replied_by ? 'bg-blue-50 border-blue-200' : 'bg-gray-50 border-gray-200'
                          ]"
                        >
                          <div class="flex items-center gap-2 mb-2">
                            <i :class="[
                              'fa-solid',
                              reply.replied_by ? 'fa-user-shield text-blue-600' : 'fa-user text-gray-600'
                            ]"></i>
                            <span :class="[
                              'font-medium',
                              reply.replied_by ? 'text-blue-800' : 'text-gray-800'
                            ]">
                              {{ reply.replied_by ? 'Justus Group' : 'Member' }}
                            </span>
                            <span class="text-xs text-gray-500">{{ formatFeedbackDate(reply.created_at) }}</span>
                          </div>
                          <p class="text-gray-700 whitespace-pre-line">{{ reply.message }}</p>
                        </div>
                      </div>
                    </div>
                    <!-- Legacy admin_reply display (for backward compatibility) -->
                    <div v-else-if="feedback.admin_reply" class="bg-blue-50 rounded-lg p-4 border border-blue-200 mt-3">
                      <div class="flex items-center gap-2 mb-2">
                        <i class="fa-solid fa-reply text-blue-600"></i>
                        <span class="font-medium text-blue-800">Justus Group Reply</span>
                        <span class="text-xs text-gray-500">{{ formatFeedbackDate(feedback.replied_at) }}</span>
                      </div>
                      <p class="text-gray-700 whitespace-pre-line">{{ feedback.admin_reply }}</p>
                    </div>
                  </div>
                </div>
                <div class="flex gap-2 mt-4 pt-4 border-t border-gray-200">
                  <button @click="openReplyModal(feedback)" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                    <i class="fa-solid fa-reply mr-2"></i>Reply
                  </button>
                  <button v-if="feedback.status === 'pending'" @click="updateFeedbackStatus(feedback.id, 'read')" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition-colors">
                    <i class="fa-solid fa-check mr-2"></i>Mark as Read
                  </button>
                  <button v-if="feedback.status === 'replied'" @click="updateFeedbackStatus(feedback.id, 'resolved')" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors">
                    <i class="fa-solid fa-check-circle mr-2"></i>Mark as Resolved
                  </button>
                </div>
              </div>
              <div v-if="!filteredFeedbacks || filteredFeedbacks.length === 0" class="text-center py-12 text-gray-500">
                <i class="fa-solid fa-comment-dots text-4xl mb-4"></i>
                <p>No feedbacks yet.</p>
              </div>
            </div>
            <!-- Pagination -->
            <div v-if="props.feedbacks && props.feedbacks.links && props.feedbacks.links.length > 3" class="mt-6 flex justify-center items-center gap-4">
              <div class="text-sm text-gray-600">
                Showing {{ props.feedbacks.from || 0 }} to {{ props.feedbacks.to || 0 }} of {{ props.feedbacks.total || 0 }} feedbacks
              </div>
              <div class="flex gap-2">
                <button
                  v-for="(link, index) in props.feedbacks.links"
                  :key="index"
                  @click="loadFeedbacksPage(link.url)"
                  :disabled="!link.url"
                  :class="[
                    'px-4 py-2 rounded-lg border transition-colors',
                    link.active
                      ? 'bg-blue-600 text-white border-blue-600'
                      : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50',
                    !link.url ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'
                  ]"
                  v-html="link.label"
                ></button>
              </div>
            </div>
          </div>
        </div>

        <!-- Voucher Tab -->
        <div v-if="activeTab === 'voucher'" class="bg-white rounded-2xl shadow-lg overflow-hidden">
          <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex justify-between items-center">
              <h3 class="text-lg font-semibold text-gray-700">Voucher Management</h3>
              <button @click="openVoucherModal" class="bg-gradient-to-r from-yellow-500 to-yellow-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
                <i class="fa-solid fa-plus mr-2"></i>Create Voucher
              </button>
            </div>
          </div>
          <div class="p-6">
            <div class="space-y-4">
              <div v-for="voucher in props.vouchers" :key="voucher.id" class="bg-gray-50 rounded-xl p-6 border border-gray-200 hover:shadow-lg transition-all">
                <div class="flex justify-between items-start mb-3">
                  <div class="flex-1">
                    <div class="flex gap-4 mb-3">
                      <div v-if="voucher.image" class="flex-shrink-0">
                        <img 
                          :src="getImageUrl(voucher.image)" 
                          :alt="voucher.name" 
                          class="w-32 h-32 object-cover rounded-lg border border-gray-300"
                        >
                      </div>
                      <div class="flex-1">
                        <h4 class="font-semibold text-gray-800 mb-2 flex items-center gap-2">
                          <i class="fa-solid fa-ticket text-yellow-500"></i>
                          <span>{{ voucher.name }}</span>
                          <span :class="['px-2 py-1 rounded-full text-xs font-medium', voucher.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800']">
                            {{ voucher.is_active ? 'Active' : 'Inactive' }}
                          </span>
                        </h4>
                        <p class="text-sm text-gray-600 mb-2">{{ voucher.description }}</p>
                      </div>
                    </div>
                    <div class="text-sm text-gray-600 space-y-1">
                      <div><strong>Type:</strong> {{ formatVoucherType(voucher.voucher_type) }}</div>
                      <div v-if="voucher.is_for_sale" class="text-blue-600 font-semibold">
                        <i class="fa-solid fa-store mr-1"></i>Dijual dengan Point: {{ formatNumber(voucher.points_required) }} Points
                        <span v-if="voucher.one_time_purchase" class="ml-2 text-xs bg-orange-100 text-orange-800 px-2 py-1 rounded">
                          One Time Purchase
                        </span>
                      </div>
                      <div v-if="voucher.is_birthday_voucher" class="text-pink-600 font-semibold">
                        <i class="fa-solid fa-birthday-cake mr-1"></i>Voucher Khusus Birthday
                      </div>
                      <div v-if="voucher.discount_percentage"><strong>Discount:</strong> {{ voucher.discount_percentage }}%</div>
                      <div v-if="voucher.discount_amount"><strong>Discount:</strong> Rp {{ formatNumber(voucher.discount_amount) }}</div>
                      <div v-if="voucher.free_item_name"><strong>Free Item:</strong> {{ voucher.free_item_name }}</div>
                      <div><strong>Valid:</strong> {{ formatDate(voucher.valid_from) }} - {{ formatDate(voucher.valid_until) }}</div>
                      <div v-if="voucher.applicable_days && voucher.applicable_days.length > 0">
                        <strong>Applicable Days:</strong> {{ formatDays(voucher.applicable_days) }}
                      </div>
                      <div v-if="voucher.applicable_time_start && voucher.applicable_time_end">
                        <strong>Applicable Time:</strong> {{ formatTime(voucher.applicable_time_start) }} - {{ formatTime(voucher.applicable_time_end) }}
                      </div>
                      <div v-if="voucher.member_vouchers" class="flex items-center gap-2">
                        <strong>Distributed:</strong> {{ voucher.member_vouchers.length }} vouchers
                        <button 
                          @click="openVoucherMembersModal(voucher)" 
                          class="ml-2 text-blue-600 hover:text-blue-800 text-sm"
                          title="View Members"
                        >
                          <i class="fa-solid fa-users"></i>
                        </button>
                      </div>
                    </div>
                  </div>
                  <div class="flex gap-2 ml-4">
                    <button 
                      v-if="!voucher.is_for_sale && !voucher.is_birthday_voucher" 
                      @click="openDistributeVoucherModal(voucher)" 
                      class="text-green-600 hover:text-green-800" 
                      title="Distribute Voucher"
                    >
                      <i class="fa-solid fa-paper-plane"></i>
                    </button>
                    <button @click="deleteVoucher(voucher.id)" class="text-red-600 hover:text-red-800">
                      <i class="fa-solid fa-trash"></i>
                    </button>
                  </div>
                </div>
              </div>
              <div v-if="!props.vouchers || props.vouchers.length === 0" class="text-center py-12 text-gray-500">
                <i class="fa-solid fa-ticket text-4xl mb-4"></i>
                <p>No vouchers yet. Click "Create Voucher" to create one.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Voucher Modal -->
    <div v-if="showVoucherModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl mx-4 max-h-[90vh] overflow-y-auto">
        <div class="px-6 py-4 border-b border-gray-200">
          <div class="flex justify-between items-center">
            <h3 class="text-lg font-semibold text-gray-700">Create Voucher</h3>
            <button @click="closeVoucherModal" class="text-gray-400 hover:text-gray-600">
              <i class="fa-solid fa-times text-xl"></i>
            </button>
          </div>
        </div>
        <form @submit.prevent="saveVoucher" class="p-6">
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Voucher Name *</label>
              <input 
                v-model="voucherForm.name" 
                type="text" 
                required 
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"
                placeholder="Enter voucher name"
              >
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
              <textarea 
                v-model="voucherForm.description" 
                rows="2" 
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"
                placeholder="Enter description"
              ></textarea>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Voucher Image</label>
              <p class="text-xs text-gray-500 mb-2">Upload gambar voucher (JPG, PNG, GIF - Max 2MB)</p>
              <input 
                @change="handleVoucherImageChange"
                type="file" 
                accept="image/*"
                ref="voucherImageInput"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"
              >
              <div class="mt-2 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                <div class="flex items-start gap-2">
                  <i class="fa-solid fa-info-circle text-blue-600 mt-0.5"></i>
                  <div class="flex-1">
                    <p class="text-sm font-medium text-blue-800 mb-1">Rekomendasi Ukuran Gambar:</p>
                    <p class="text-xs text-blue-700">
                      <strong>Dimensi:</strong> 1080 x 400 pixels (atau rasio 2.7:1)<br>
                      <strong>Format:</strong> JPG, PNG<br>
                      <strong>Ukuran file:</strong> Maksimal 2MB<br>
                      <span class="text-blue-600">* Gambar akan ditampilkan full width dengan tinggi 200px di page My Voucher dengan BoxFit.cover</span>
                    </p>
                  </div>
                </div>
              </div>
              <div v-if="voucherForm.imagePreview" class="mt-3">
                <img :src="voucherForm.imagePreview" alt="Voucher preview" class="w-full max-w-md h-48 object-cover rounded-lg border border-gray-300">
                <button 
                  type="button"
                  @click="removeVoucherImage"
                  class="mt-2 text-sm text-red-600 hover:text-red-800"
                >
                  <i class="fa-solid fa-trash mr-1"></i>Remove Image
                </button>
              </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Voucher Type *</label>
                <select 
                  v-model="voucherForm.voucher_type" 
                  required 
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"
                >
                  <option value="">Select Type</option>
                  <option value="discount-percentage">Discount Percentage</option>
                  <option value="discount-fixed">Discount Fixed Amount</option>
                  <option value="free-item">Free Item</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Valid From *</label>
                <input 
                  v-model="voucherForm.valid_from" 
                  type="date" 
                  required 
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"
                >
              </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Valid Until *</label>
                <input 
                  v-model="voucherForm.valid_until" 
                  type="date" 
                  required 
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"
                >
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Min Purchase (Rp)</label>
                <input 
                  v-model.number="voucherForm.min_purchase" 
                  type="number" 
                  min="0" 
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"
                >
              </div>
            </div>
            
            <!-- Dynamic fields based on voucher type -->
            <div v-if="voucherForm.voucher_type === 'discount-percentage'" class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Discount Percentage (%) *</label>
                <input 
                  v-model.number="voucherForm.discount_percentage" 
                  type="number" 
                  min="0" 
                  max="100" 
                  step="0.01"
                  required
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"
                >
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Max Discount (Rp)</label>
                <input 
                  v-model.number="voucherForm.max_discount" 
                  type="number" 
                  min="0" 
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"
                >
              </div>
            </div>
            <div v-if="voucherForm.voucher_type === 'discount-fixed'">
              <label class="block text-sm font-medium text-gray-700 mb-2">Discount Amount (Rp) *</label>
              <input 
                v-model.number="voucherForm.discount_amount" 
                type="number" 
                min="0" 
                required
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"
              >
            </div>
            <div v-if="voucherForm.voucher_type === 'free-item'">
              <label class="block text-sm font-medium text-gray-700 mb-2">Pilih Items (Bisa Multiple) *</label>
              <Multiselect
                v-model="voucherForm.free_item_ids"
                :options="items || []"
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
              </Multiselect>
              <p class="mt-1 text-xs text-gray-500">Anda bisa memilih lebih dari satu item</p>
              
              <!-- Free Item Selection Mode (hanya muncul jika lebih dari 1 item dipilih) -->
              <div v-if="Array.isArray(voucherForm.free_item_ids) && voucherForm.free_item_ids.length > 1" class="mt-4 p-4 bg-yellow-50 rounded-lg border border-yellow-200">
                <label class="block text-sm font-medium text-gray-700 mb-3">Mode Pemberian Free Item *</label>
                <p class="text-xs text-gray-600 mb-3">Pilih bagaimana free item akan diberikan kepada member:</p>
                <div class="space-y-2">
                  <label class="flex items-center cursor-pointer">
                    <input
                      v-model="voucherForm.free_item_selection"
                      type="radio"
                      value="all"
                      class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300"
                    >
                    <div class="ml-3">
                      <span class="text-sm font-medium text-gray-700">Semua Item (All)</span>
                      <p class="text-xs text-gray-500">Member akan mendapatkan semua item yang dipilih</p>
                    </div>
                  </label>
                  <label class="flex items-center cursor-pointer">
                    <input
                      v-model="voucherForm.free_item_selection"
                      type="radio"
                      value="single"
                      class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300"
                    >
                    <div class="ml-3">
                      <span class="text-sm font-medium text-gray-700">Pilih Salah Satu (Single)</span>
                      <p class="text-xs text-gray-500">Member hanya bisa memilih salah satu item saat claim</p>
                    </div>
                  </label>
                </div>
              </div>
            </div>
            <div v-if="voucherForm.voucher_type === 'cashback'" class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Cashback Amount (Rp)</label>
                <input 
                  v-model.number="voucherForm.cashback_amount" 
                  type="number" 
                  min="0" 
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"
                >
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Cashback Percentage (%)</label>
                <input 
                  v-model.number="voucherForm.cashback_percentage" 
                  type="number" 
                  min="0" 
                  max="100"
                  step="0.01"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"
                >
              </div>
            </div>
            
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Usage Limit (per member)</label>
                <input 
                  v-model.number="voucherForm.usage_limit" 
                  type="number" 
                  min="1" 
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"
                  placeholder="Leave empty for unlimited"
                >
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Total Quantity</label>
                <input 
                  v-model.number="voucherForm.total_quantity" 
                  type="number" 
                  min="1" 
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"
                  placeholder="Leave empty for unlimited"
                >
              </div>
            </div>

            <!-- Applicable Days -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Applicable Days</label>
              <p class="text-xs text-gray-500 mb-3">Pilih hari yang bisa menggunakan voucher (kosongkan jika bisa digunakan semua hari)</p>
              <div class="grid grid-cols-7 gap-2">
                <label class="flex items-center space-x-2 cursor-pointer">
                  <input 
                    type="checkbox" 
                    :value="'monday'"
                    v-model="voucherForm.applicable_days"
                    class="h-4 w-4 text-yellow-600 focus:ring-yellow-500 border-gray-300 rounded"
                  >
                  <span class="text-sm text-gray-700">Senin</span>
                </label>
                <label class="flex items-center space-x-2 cursor-pointer">
                  <input 
                    type="checkbox" 
                    :value="'tuesday'"
                    v-model="voucherForm.applicable_days"
                    class="h-4 w-4 text-yellow-600 focus:ring-yellow-500 border-gray-300 rounded"
                  >
                  <span class="text-sm text-gray-700">Selasa</span>
                </label>
                <label class="flex items-center space-x-2 cursor-pointer">
                  <input 
                    type="checkbox" 
                    :value="'wednesday'"
                    v-model="voucherForm.applicable_days"
                    class="h-4 w-4 text-yellow-600 focus:ring-yellow-500 border-gray-300 rounded"
                  >
                  <span class="text-sm text-gray-700">Rabu</span>
                </label>
                <label class="flex items-center space-x-2 cursor-pointer">
                  <input 
                    type="checkbox" 
                    :value="'thursday'"
                    v-model="voucherForm.applicable_days"
                    class="h-4 w-4 text-yellow-600 focus:ring-yellow-500 border-gray-300 rounded"
                  >
                  <span class="text-sm text-gray-700">Kamis</span>
                </label>
                <label class="flex items-center space-x-2 cursor-pointer">
                  <input 
                    type="checkbox" 
                    :value="'friday'"
                    v-model="voucherForm.applicable_days"
                    class="h-4 w-4 text-yellow-600 focus:ring-yellow-500 border-gray-300 rounded"
                  >
                  <span class="text-sm text-gray-700">Jumat</span>
                </label>
                <label class="flex items-center space-x-2 cursor-pointer">
                  <input 
                    type="checkbox" 
                    :value="'saturday'"
                    v-model="voucherForm.applicable_days"
                    class="h-4 w-4 text-yellow-600 focus:ring-yellow-500 border-gray-300 rounded"
                  >
                  <span class="text-sm text-gray-700">Sabtu</span>
                </label>
                <label class="flex items-center space-x-2 cursor-pointer">
                  <input 
                    type="checkbox" 
                    :value="'sunday'"
                    v-model="voucherForm.applicable_days"
                    class="h-4 w-4 text-yellow-600 focus:ring-yellow-500 border-gray-300 rounded"
                  >
                  <span class="text-sm text-gray-700">Minggu</span>
                </label>
              </div>
            </div>

            <!-- Applicable Time -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Applicable Time</label>
              <p class="text-xs text-gray-500 mb-3">Pilih jam yang bisa menggunakan voucher (kosongkan jika bisa digunakan sepanjang hari)</p>
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs text-gray-600 mb-1">Time Start</label>
                  <input 
                    v-model="voucherForm.applicable_time_start" 
                    type="time" 
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"
                  >
                </div>
                <div>
                  <label class="block text-xs text-gray-600 mb-1">Time End</label>
                  <input 
                    v-model="voucherForm.applicable_time_end" 
                    type="time" 
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"
                  >
                </div>
              </div>
            </div>

            <!-- Voucher Bisa Ditukar Di -->
            <div class="mt-4 p-4 bg-gray-50 rounded-lg">
              <label class="block text-sm font-medium text-gray-700 mb-2">Voucher Bisa Ditukar Di *</label>
              <p class="text-xs text-gray-500 mb-3">Tentukan di outlet mana voucher ini bisa ditukar. Jika tidak dipilih, voucher bisa ditukar di semua outlet.</p>
              <div class="space-y-2">
                <label class="flex items-center">
                  <input 
                    type="checkbox" 
                    v-model="voucherForm.all_outlets"
                    @change="onVoucherAllOutletsChange"
                    class="h-4 w-4 text-yellow-600 focus:ring-yellow-500 border-gray-300 rounded"
                    id="voucher_all_outlets"
                  >
                  <label for="voucher_all_outlets" class="ml-2 text-sm text-gray-700">Semua Outlet</label>
                </label>
                <div v-if="!voucherForm.all_outlets">
                  <Multiselect
                    v-model="voucherForm.outlet_ids"
                    :options="outlets"
                    :searchable="true"
                    :clear-on-select="false"
                    :close-on-select="false"
                    :show-labels="false"
                    :multiple="true"
                    track-by="id"
                    label="name"
                    placeholder="Pilih Outlet untuk Voucher (kosongkan untuk semua outlet)"
                  >
                    <template #noOptions>
                      <span>Tidak ada outlet ditemukan</span>
                    </template>
                    <template #noResult>
                      <span>Tidak ada outlet ditemukan</span>
                    </template>
                  </Multiselect>
                  <p class="mt-1 text-xs text-gray-500">Voucher hanya bisa ditukar di outlet yang dipilih</p>
                </div>
              </div>
            </div>

            <div class="space-y-3">
              <div class="flex items-center">
                <input 
                  v-model="voucherForm.is_active" 
                  type="checkbox" 
                  id="voucher_active" 
                  class="h-4 w-4 text-yellow-600 focus:ring-yellow-500 border-gray-300 rounded"
                >
                <label for="voucher_active" class="ml-2 block text-sm text-gray-700">Active</label>
              </div>
              
              <!-- Voucher For Sale -->
              <div class="p-4 bg-blue-50 rounded-lg border border-blue-200">
                <div class="flex items-center mb-3">
                  <input 
                    v-model="voucherForm.is_for_sale" 
                    type="checkbox" 
                    id="voucher_for_sale" 
                    @change="onVoucherForSaleChange"
                    class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                  >
                  <label for="voucher_for_sale" class="ml-2 block text-sm font-medium text-gray-700">
                    Dijual dengan Point (Voucher Store)
                  </label>
                </div>
                <p class="text-xs text-gray-600 mb-3">
                  Jika dicentang, voucher ini akan muncul di Voucher Store dan member bisa membelinya dengan point. 
                  Voucher yang dijual tidak perlu di-distribute secara manual.
                </p>
                <div v-if="voucherForm.is_for_sale">
                  <label class="block text-sm font-medium text-gray-700 mb-2">Points Required *</label>
                  <input 
                    v-model.number="voucherForm.points_required" 
                    type="number" 
                    min="1"
                    required
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    placeholder="Masukkan jumlah point yang diperlukan"
                  >
                  <p class="mt-1 text-xs text-gray-500">Jumlah point yang diperlukan untuk membeli voucher ini</p>
                  
                  <div class="mt-4">
                    <label class="flex items-center">
                      <input 
                        v-model="voucherForm.one_time_purchase" 
                        type="checkbox" 
                        id="voucher_one_time_purchase" 
                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                      >
                      <label for="voucher_one_time_purchase" class="ml-2 block text-sm text-gray-700">
                        One Time Purchase
                      </label>
                    </label>
                    <p class="mt-1 text-xs text-gray-500 ml-6">
                      Jika dicentang, setiap member hanya bisa membeli voucher ini 1 kali. Jika tidak dicentang, member bisa membeli berkali-kali.
                    </p>
                  </div>
                </div>
              </div>
              
              <!-- Birthday Voucher -->
              <div class="p-4 bg-pink-50 rounded-lg border border-pink-200">
                <div class="flex items-center mb-3">
                  <input 
                    v-model="voucherForm.is_birthday_voucher" 
                    type="checkbox" 
                    id="voucher_birthday" 
                    @change="onVoucherBirthdayChange"
                    class="h-4 w-4 text-pink-600 focus:ring-pink-500 border-gray-300 rounded"
                  >
                  <label for="voucher_birthday" class="ml-2 block text-sm font-medium text-gray-700">
                    Voucher Khusus Birthday
                  </label>
                </div>
                <p class="text-xs text-gray-600 mb-3">
                  Jika dicentang, voucher ini akan otomatis diberikan ke member yang berulang tahun setiap hari. 
                  Voucher birthday tidak perlu di-distribute secara manual.
                </p>
              </div>
            </div>
          </div>
          <div class="flex justify-end gap-3 mt-6">
            <button 
              type="button" 
              @click="closeVoucherModal" 
              class="px-4 py-2 text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition"
            >
              Cancel
            </button>
            <button 
              type="submit" 
              :disabled="savingVoucher" 
              class="px-4 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
            >
              <i v-if="savingVoucher" class="fa-solid fa-spinner fa-spin"></i>
              {{ savingVoucher ? 'Saving...' : 'Create' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Distribute Voucher Modal -->
    <div v-if="showDistributeVoucherModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl mx-4 max-h-[90vh] overflow-y-auto">
        <div class="px-6 py-4 border-b border-gray-200">
          <div class="flex justify-between items-center">
            <h3 class="text-lg font-semibold text-gray-700">Distribute Voucher: {{ distributingVoucherData?.name }}</h3>
            <button @click="closeDistributeVoucherModal" class="text-gray-400 hover:text-gray-600">
              <i class="fa-solid fa-times text-xl"></i>
            </button>
          </div>
        </div>
        <form @submit.prevent="distributeVoucher" class="p-6">
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Distribution Type *</label>
              <select 
                v-model="distributionForm.distribution_type" 
                required 
                @change="onDistributionTypeChange"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"
              >
                <option value="">Select Distribution Type</option>
                <option value="all">All Members</option>
                <option value="specific">Specific Members</option>
                <option value="filter">Filter by Criteria</option>
              </select>
            </div>

            <!-- Specific Members -->
            <div v-if="distributionForm.distribution_type === 'specific'" class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Select Members *</label>
                <Multiselect
                  v-model="distributionForm.member_ids"
                  :options="memberOptions"
                  :multiple="true"
                  :searchable="true"
                  :loading="memberSearchLoading"
                  :internal-search="false"
                  @search-change="onMemberSearchChange"
                  placeholder="Type at least 2 characters to search members"
                  label="name"
                  track-by="id"
                  :close-on-select="false"
                ></Multiselect>
                <p class="text-xs text-gray-500 mt-1">Type member name, member ID, phone, or email</p>
              </div>
            </div>

            <!-- Filter Criteria -->
            <div v-if="distributionForm.distribution_type === 'filter'" class="space-y-4 border-t pt-4">
              <h4 class="font-semibold text-gray-700 mb-3">Filter Criteria</h4>
              
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Occupation</label>
                  <select 
                    v-model="distributionForm.filter_criteria.occupation_id" 
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"
                  >
                    <option value="">All Occupations</option>
                    <option v-for="occupation in props.occupations" :key="occupation.id" :value="occupation.id">
                      {{ occupation.name }}
                    </option>
                  </select>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Member Level</label>
                  <select 
                    v-model="distributionForm.filter_criteria.member_level" 
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"
                  >
                    <option value="">All Levels</option>
                    <option value="Silver">Silver</option>
                    <option value="Loyal">Loyal</option>
                    <option value="Elite">Elite</option>
                  </select>
                </div>
              </div>

              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                  <select 
                    v-model="distributionForm.filter_criteria.is_active" 
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"
                  >
                    <option value="">All Status</option>
                    <option :value="true">Active</option>
                    <option :value="false">Inactive</option>
                  </select>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Exclusive Member</label>
                  <select 
                    v-model="distributionForm.filter_criteria.is_exclusive_member" 
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"
                  >
                    <option value="">All</option>
                    <option :value="true">Yes</option>
                    <option :value="false">No</option>
                  </select>
                </div>
              </div>

              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Min Spending (Rp)</label>
                  <input 
                    v-model.number="distributionForm.filter_criteria.min_spending" 
                    type="number" 
                    min="0" 
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"
                  >
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Max Spending (Rp)</label>
                  <input 
                    v-model.number="distributionForm.filter_criteria.max_spending" 
                    type="number" 
                    min="0" 
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"
                  >
                </div>
              </div>

              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Min Points</label>
                  <input 
                    v-model.number="distributionForm.filter_criteria.min_points" 
                    type="number" 
                    min="0" 
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"
                  >
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Max Points</label>
                  <input 
                    v-model.number="distributionForm.filter_criteria.max_points" 
                    type="number" 
                    min="0" 
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"
                  >
                </div>
              </div>

              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Registered From</label>
                  <input 
                    v-model="distributionForm.filter_criteria.registered_from" 
                    type="date" 
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"
                  >
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Registered Until</label>
                  <input 
                    v-model="distributionForm.filter_criteria.registered_until" 
                    type="date" 
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"
                  >
                </div>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Gender</label>
                <select 
                  v-model="distributionForm.filter_criteria.jenis_kelamin" 
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"
                >
                  <option value="">All</option>
                  <option value="L">Laki-laki</option>
                  <option value="P">Perempuan</option>
                </select>
              </div>
            </div>
          </div>
          <div class="flex justify-end gap-3 mt-6">
            <button 
              type="button" 
              @click="closeDistributeVoucherModal" 
              class="px-4 py-2 text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition"
            >
              Cancel
            </button>
            <button 
              type="submit" 
              :disabled="distributingVoucher" 
              class="px-4 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
            >
              <i v-if="distributingVoucher" class="fa-solid fa-spinner fa-spin"></i>
              {{ distributingVoucher ? 'Distributing...' : 'Distribute' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Voucher Members Modal -->
    <div v-if="showVoucherMembersModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl mx-4 max-h-[90vh] overflow-y-auto">
        <div class="px-6 py-4 border-b border-gray-200">
          <div class="flex justify-between items-center">
            <h3 class="text-lg font-semibold text-gray-700">Members with Voucher: {{ viewingVoucherData?.name }}</h3>
            <button @click="closeVoucherMembersModal" class="text-gray-400 hover:text-gray-600">
              <i class="fa-solid fa-times text-xl"></i>
            </button>
          </div>
        </div>
        <div class="p-6">
          <!-- Search -->
          <div class="mb-4">
            <div class="relative">
              <input
                v-model="voucherMembersSearch"
                @input="searchVoucherMembers"
                type="text"
                placeholder="Search by member ID, name, phone, or email..."
                class="w-full px-4 py-2 pl-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              >
              <i class="fa-solid fa-search absolute left-3 top-3 text-gray-400"></i>
            </div>
          </div>

          <!-- Loading -->
          <div v-if="loadingVoucherMembers" class="text-center py-8">
            <i class="fa-solid fa-spinner fa-spin text-2xl text-blue-500"></i>
            <p class="mt-2 text-gray-600">Loading members...</p>
          </div>

          <!-- Members List -->
          <div v-else-if="voucherMembers.length > 0" class="space-y-3">
            <div class="text-sm text-gray-600 mb-4">
              Total: {{ voucherMembersTotal }} member(s)
            </div>
            <div class="overflow-x-auto">
              <table class="w-full">
                <thead class="bg-blue-600 text-white">
                  <tr>
                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Member ID</th>
                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Name</th>
                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Phone</th>
                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Email</th>
                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Serial Code</th>
                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Obtained At</th>
                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider">Used At</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <tr v-for="member in voucherMembers" :key="member.id" class="hover:bg-gray-50">
                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">{{ member.member_id || 'N/A' }}</td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">{{ member.nama_lengkap }}</td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">{{ member.mobile_phone }}</td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">{{ member.email }}</td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 font-mono">{{ member.serial_code || 'N/A' }}</td>
                    <td class="px-4 py-3 whitespace-nowrap">
                      <span :class="[
                        'px-2 py-1 rounded-full text-xs font-medium',
                        member.status === 'active' ? 'bg-green-100 text-green-800' : 
                        member.status === 'used' ? 'bg-blue-100 text-blue-800' : 
                        'bg-gray-100 text-gray-800'
                      ]">
                        {{ member.status === 'active' ? 'Active' : member.status === 'used' ? 'Used' : member.status }}
                      </span>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">{{ formatDateTime(member.created_at) }}</td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">{{ member.used_at ? formatDateTime(member.used_at) : '-' }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Empty State -->
          <div v-else class="text-center py-12 text-gray-500">
            <i class="fa-solid fa-users text-4xl mb-4"></i>
            <p>No members found{{ voucherMembersSearch ? ' matching your search' : '' }}.</p>
          </div>
        </div>
        <div class="px-6 py-4 border-t border-gray-200 flex justify-end">
          <button 
            @click="closeVoucherMembersModal" 
            class="px-4 py-2 text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition"
          >
            Close
          </button>
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
              <div class="mt-2 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                <div class="flex items-start gap-2">
                  <i class="fa-solid fa-info-circle text-blue-600 mt-0.5"></i>
                  <div class="flex-1">
                    <p class="text-sm font-medium text-blue-800 mb-1">Rekomendasi Ukuran Gambar:</p>
                    <p class="text-xs text-blue-700">
                      <strong>Dimensi:</strong> 1080 x 400 pixels (atau rasio 2.7:1)<br>
                      <strong>Format:</strong> JPG, PNG<br>
                      <strong>Ukuran file:</strong> Maksimal 2MB<br>
                      <span class="text-blue-600">* Gambar akan ditampilkan dengan tinggi 280px di home screen app</span>
                    </p>
                  </div>
                </div>
              </div>
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
                :multiple="false"
                :select-label="''"
                :selected-label="''"
                :deselect-label="''"
                :custom-label="(option) => option.name"
                required
              >
                <template #option="{ option }">
                  <div class="flex justify-between items-center">
                    <span>{{ option.name }}</span>
                  </div>
                </template>
                <template #singleLabel="{ value }">
                  <div class="flex justify-between items-center">
                    <span>{{ value?.name || '' }}</span>
                  </div>
                </template>
              </Multiselect>
              <div v-if="rewardForm.item_id && rewardForm.item_id.name" class="mt-2 p-2 bg-green-50 border border-green-200 rounded-lg">
                <div class="flex items-center gap-2">
                  <i class="fa-solid fa-check-circle text-green-600"></i>
                  <span class="text-sm text-green-800">
                    Selected: <strong>{{ rewardForm.item_id?.name || 'Unknown Item' }}</strong>
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
            
            <!-- Outlet Scope -->
            <div class="mt-6 pt-6 border-t border-gray-200">
              <h4 class="text-md font-semibold text-gray-900 mb-4">Scope Outlet</h4>
              <div class="space-y-2">
                <div class="flex items-center">
                  <input
                    v-model="rewardForm.all_outlets"
                    @change="onRewardAllOutletsChangeReward"
                    type="checkbox"
                    id="reward_all_outlets"
                    class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                  >
                  <label for="reward_all_outlets" class="ml-2 text-sm text-gray-700">Semua Outlet</label>
                </div>
                <div v-if="!rewardForm.all_outlets">
                  <label class="block text-sm font-medium text-gray-700 mb-2">Pilih Outlet (kosongkan untuk semua outlet)</label>
                  <Multiselect
                    v-model="rewardForm.outlet_ids"
                    :options="props.outlets"
                    :searchable="true"
                    :clear-on-select="false"
                    :close-on-select="false"
                    :show-labels="false"
                    :multiple="true"
                    placeholder="Pilih Outlet (bisa lebih dari satu)"
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
                </div>
              </div>
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
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl mx-4 max-h-[90vh] overflow-y-auto">
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
          <div class="space-y-6">
            <!-- Basic Information -->
            <div class="bg-gray-50 rounded-lg p-4">
              <h4 class="text-md font-semibold text-gray-900 mb-4">Informasi Dasar</h4>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Challenge Type *</label>
                  <select v-model="challengeForm.challenge_type_id" @change="onChallengeTypeChange" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                    <option value="">Pilih Challenge Type</option>
                    <option value="spending">Spending-based</option>
                    <option value="product">Product-based</option>
                  </select>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Title *</label>
                  <input v-model="challengeForm.title" type="text" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
              </div>
              <div class="mt-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                <textarea v-model="challengeForm.description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
              </div>
            </div>

            <!-- Challenge Rules (Dynamic based on type) -->
            <div v-if="challengeForm.challenge_type_id" class="bg-gray-50 rounded-lg p-4">
              <h4 class="text-md font-semibold text-gray-900 mb-4">Challenge Rules</h4>
              
              <!-- Spending-based Rules -->
              <div v-if="challengeForm.challenge_type_id === 'spending'" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Minimum Spending (IDR) *</label>
                    <input v-model="challengeForm.rules.min_amount" type="number" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="300000" required>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Reward Type *</label>
                    <select v-model="challengeForm.rules.reward_type" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                      <option value="">Pilih Reward Type</option>
                      <option value="item">Item</option>
                      <option value="points">Points</option>
                      <option value="voucher">Voucher</option>
                    </select>
                  </div>
                </div>
                <div v-if="challengeForm.rules.reward_type">
                  <!-- Item Multiselect (for item reward type) -->
                  <div v-if="challengeForm.rules.reward_type === 'item'">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Pilih Items (Bisa Multiple) *</label>
                    <Multiselect
                      v-model="challengeForm.rules.reward_value"
                      :options="items"
                      :searchable="true"
                      :clear-on-select="false"
                      :close-on-select="false"
                      :show-labels="false"
                      :multiple="true"
                      track-by="id"
                      label="name"
                      placeholder="Pilih Items (bisa lebih dari satu)"
                      required
                    >
                      <template #noOptions>
                        <span>Tidak ada item ditemukan</span>
                      </template>
                      <template #noResult>
                        <span>Tidak ada item ditemukan</span>
                      </template>
                    </Multiselect>
                    <p class="mt-1 text-xs text-gray-500">Anda bisa memilih lebih dari satu item</p>
                    
                    <!-- Reward Item Selection Mode (hanya muncul jika lebih dari 1 item dipilih) -->
                    <div v-if="shouldShowRewardSelectionMode" class="mt-4 p-4 bg-yellow-50 rounded-lg border border-yellow-200">
                      <label class="block text-sm font-medium text-gray-700 mb-3">Mode Pemberian Reward *</label>
                      <p class="text-xs text-gray-600 mb-3">Pilih bagaimana reward akan diberikan kepada member:</p>
                      <div class="space-y-2">
                        <label class="flex items-center cursor-pointer">
                          <input
                            v-model="challengeForm.rules.reward_item_selection"
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
                            v-model="challengeForm.rules.reward_item_selection"
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
                    
                    <div class="mt-4">
                      <label class="block text-sm font-medium text-gray-700 mb-2">Reward Bisa Ditukar Di *</label>
                      <div class="space-y-2">
                        <label class="flex items-center">
                          <input 
                            type="checkbox" 
                            v-model="challengeForm.rules.reward_all_outlets"
                            @change="onRewardAllOutletsChange('spending', 'item')"
                            class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                          >
                          <span class="ml-2 text-sm text-gray-700">Semua Outlet</span>
                        </label>
                        <div v-if="!challengeForm.rules.reward_all_outlets">
                          <Multiselect
                            v-model="challengeForm.rules.reward_outlet_ids"
                            :options="outlets"
                            :searchable="true"
                            :clear-on-select="false"
                            :close-on-select="false"
                            :show-labels="false"
                            :multiple="true"
                            track-by="id"
                            label="name"
                            placeholder="Pilih Outlet untuk Reward (kosongkan untuk semua outlet)"
                          >
                            <template #noOptions>
                              <span>Tidak ada outlet ditemukan</span>
                            </template>
                            <template #noResult>
                              <span>Tidak ada outlet ditemukan</span>
                            </template>
                          </Multiselect>
                        </div>
                      </div>
                    </div>
                  </div>
                  <!-- Points Input (for points reward type) -->
                  <div v-else-if="challengeForm.rules.reward_type === 'points'">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Points Amount *</label>
                    <input v-model="challengeForm.rules.reward_value" type="number" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="100" min="0" required>
                  </div>
                  <!-- Voucher Multiselect (for voucher reward type) -->
                  <div v-else-if="challengeForm.rules.reward_type === 'voucher'">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Pilih Vouchers (Bisa Multiple) *</label>
                    <Multiselect
                      v-model="challengeForm.rules.reward_value"
                      :options="vouchers || []"
                      :searchable="true"
                      :clear-on-select="false"
                      :close-on-select="false"
                      :show-labels="false"
                      :multiple="true"
                      track-by="id"
                      label="name"
                      placeholder="Pilih Vouchers (bisa lebih dari satu)"
                      required
                    >
                      <template #option="{ option }">
                        <div>
                          <strong>{{ option.name }}</strong>
                          <span v-if="option.code" class="text-gray-500 text-sm ml-2">({{ option.code }})</span>
                        </div>
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
                    </Multiselect>
                    <p class="mt-1 text-xs text-gray-500">Anda bisa memilih lebih dari satu voucher</p>
                    
                    <div class="mt-4">
                      <label class="block text-sm font-medium text-gray-700 mb-2">Reward Bisa Ditukar Di *</label>
                      <div class="space-y-2">
                        <label class="flex items-center">
                          <input 
                            type="checkbox" 
                            v-model="challengeForm.rules.reward_all_outlets"
                            @change="onRewardAllOutletsChange('spending', 'voucher')"
                            class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                          >
                          <span class="ml-2 text-sm text-gray-700">Semua Outlet</span>
                        </label>
                        <div v-if="!challengeForm.rules.reward_all_outlets">
                          <Multiselect
                            v-model="challengeForm.rules.reward_outlet_ids"
                            :options="outlets"
                            :searchable="true"
                            :clear-on-select="false"
                            :close-on-select="false"
                            :show-labels="false"
                            :multiple="true"
                            track-by="id"
                            label="name"
                            placeholder="Pilih Outlet untuk Reward (kosongkan untuk semua outlet)"
                          >
                            <template #noOptions>
                              <span>Tidak ada outlet ditemukan</span>
                            </template>
                            <template #noResult>
                              <span>Tidak ada outlet ditemukan</span>
                            </template>
                          </Multiselect>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                  <div class="flex items-center">
                    <input v-model="challengeForm.rules.immediate" type="checkbox" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                    <label class="ml-2 text-sm text-gray-700">Reward langsung diberikan</label>
                  </div>
                  
                  <!-- Challenge Outlet Scope (untuk menentukan di outlet mana challenge berlaku) -->
                  <div class="mt-4 p-4 bg-gray-100 rounded-lg">
                    <h5 class="text-sm font-semibold text-gray-900 mb-3">Challenge Outlet Scope</h5>
                    <p class="text-xs text-gray-600 mb-3">Tentukan di outlet mana challenge ini berlaku. Jika tidak dipilih, challenge berlaku di semua outlet.</p>
                    <div class="space-y-2">
                      <div class="flex items-center">
                        <input
                          v-model="challengeForm.rules.challenge_all_outlets"
                          @change="onChallengeAllOutletsChange"
                          type="checkbox"
                          id="challenge_all_outlets_spending_modal"
                          class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                        >
                        <label for="challenge_all_outlets_spending_modal" class="ml-2 text-sm text-gray-700">Semua Outlet</label>
                      </div>
                      <div v-if="!challengeForm.rules.challenge_all_outlets">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Pilih Outlet Challenge</label>
                        <Multiselect
                          v-model="challengeForm.rules.challenge_outlet_ids"
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
                </div>

              <!-- Product-based Rules -->
              <div v-else-if="challengeForm.challenge_type_id === 'product'" class="space-y-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Pilih Products (Bisa Multiple) *</label>
                  <Multiselect
                    v-model="challengeForm.rules.products"
                    :options="items"
                    :searchable="true"
                    :clear-on-select="false"
                    :close-on-select="false"
                    :show-labels="false"
                    :multiple="true"
                    track-by="id"
                    label="name"
                    placeholder="Pilih Products (bisa lebih dari satu)"
                    required
                  >
                    <template #noOptions>
                      <span>Tidak ada product ditemukan</span>
                    </template>
                    <template #noResult>
                      <span>Tidak ada product ditemukan</span>
                    </template>
                  </Multiselect>
                  <p class="mt-1 text-xs text-gray-500">Anda bisa memilih lebih dari satu product</p>
                </div>
                
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Quantity Required *</label>
                  <input v-model="challengeForm.rules.quantity_required" type="number" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="2" min="1" required>
                </div>
                
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Pilih Outlet *</label>
                  <div class="space-y-2">
                    <label class="flex items-center">
                      <input 
                        type="checkbox" 
                        v-model="challengeForm.rules.all_outlets"
                        @change="onAllOutletsChange('product')"
                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                      >
                      <span class="ml-2 text-sm text-gray-700">Semua Outlet</span>
                    </label>
                    <div v-if="!challengeForm.rules.all_outlets">
                      <Multiselect
                        v-model="challengeForm.rules.outlet_ids"
                        :options="outlets"
                        :searchable="true"
                        :clear-on-select="false"
                        :close-on-select="false"
                        :show-labels="false"
                        :multiple="true"
                        track-by="id"
                        label="name"
                        placeholder="Pilih Outlet (kosongkan untuk semua outlet)"
                      >
                        <template #noOptions>
                          <span>Tidak ada outlet ditemukan</span>
                        </template>
                        <template #noResult>
                          <span>Tidak ada outlet ditemukan</span>
                        </template>
                      </Multiselect>
                    </div>
                  </div>
                </div>
                
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Reward Type *</label>
                  <select v-model="challengeForm.rules.reward_type" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                    <option value="">Pilih Reward Type</option>
                    <option value="item">Item</option>
                    <option value="points">Points</option>
                    <option value="voucher">Voucher</option>
                  </select>
                </div>
                
                <div v-if="challengeForm.rules.reward_type === 'item'">
                  <label class="block text-sm font-medium text-gray-700 mb-2">Pilih Items (Bisa Multiple) *</label>
                  <Multiselect
                    v-model="challengeForm.rules.reward_value"
                    :options="items"
                    :searchable="true"
                    :clear-on-select="false"
                    :close-on-select="false"
                    :show-labels="false"
                    :multiple="true"
                    track-by="id"
                    label="name"
                    placeholder="Pilih Items (bisa lebih dari satu)"
                    required
                  >
                    <template #noOptions>
                      <span>Tidak ada item ditemukan</span>
                    </template>
                    <template #noResult>
                      <span>Tidak ada item ditemukan</span>
                    </template>
                  </Multiselect>
                  <p class="mt-1 text-xs text-gray-500">Anda bisa memilih lebih dari satu item</p>
                  
                  <!-- Reward Item Selection Mode (hanya muncul jika lebih dari 1 item dipilih) -->
                  <div v-if="shouldShowRewardSelectionMode" class="mt-4 p-4 bg-yellow-50 rounded-lg border border-yellow-200">
                    <label class="block text-sm font-medium text-gray-700 mb-3">Mode Pemberian Reward *</label>
                    <p class="text-xs text-gray-600 mb-3">Pilih bagaimana reward akan diberikan kepada member:</p>
                    <div class="space-y-2">
                      <label class="flex items-center cursor-pointer">
                        <input
                          v-model="challengeForm.rules.reward_item_selection"
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
                          v-model="challengeForm.rules.reward_item_selection"
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
                  
                  <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Reward Bisa Ditukar Di *</label>
                    <div class="space-y-2">
                      <label class="flex items-center">
                        <input 
                          type="checkbox" 
                          v-model="challengeForm.rules.reward_all_outlets"
                          @change="onRewardAllOutletsChange('product', 'item')"
                          class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                        >
                        <span class="ml-2 text-sm text-gray-700">Semua Outlet</span>
                      </label>
                      <div v-if="!challengeForm.rules.reward_all_outlets">
                        <Multiselect
                          v-model="challengeForm.rules.reward_outlet_ids"
                          :options="outlets"
                          :searchable="true"
                          :clear-on-select="false"
                          :close-on-select="false"
                          :show-labels="false"
                          :multiple="true"
                          track-by="id"
                          label="name"
                          placeholder="Pilih Outlet untuk Reward (kosongkan untuk semua outlet)"
                        >
                          <template #noOptions>
                            <span>Tidak ada outlet ditemukan</span>
                          </template>
                          <template #noResult>
                            <span>Tidak ada outlet ditemukan</span>
                          </template>
                        </Multiselect>
                      </div>
                    </div>
                  </div>
                </div>
                
                <div v-else-if="challengeForm.rules.reward_type === 'points'">
                  <label class="block text-sm font-medium text-gray-700 mb-2">Points Amount *</label>
                  <input v-model="challengeForm.rules.reward_value" type="number" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="100" min="0" required>
                </div>
                
                <div v-else-if="challengeForm.rules.reward_type === 'voucher'">
                  <label class="block text-sm font-medium text-gray-700 mb-2">Pilih Vouchers (Bisa Multiple) *</label>
                  <Multiselect
                    v-model="challengeForm.rules.reward_value"
                    :options="vouchers || []"
                    :searchable="true"
                    :clear-on-select="false"
                    :close-on-select="false"
                    :show-labels="false"
                    :multiple="true"
                    track-by="id"
                    label="name"
                    placeholder="Pilih Vouchers (bisa lebih dari satu)"
                    required
                  >
                    <template #option="{ option }">
                      <div>
                        <strong>{{ option.name }}</strong>
                        <span v-if="option.code" class="text-gray-500 text-sm ml-2">({{ option.code }})</span>
                      </div>
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
                  </Multiselect>
                  <p class="mt-1 text-xs text-gray-500">Anda bisa memilih lebih dari satu voucher</p>
                  
                  <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Reward Bisa Ditukar Di *</label>
                    <div class="space-y-2">
                      <label class="flex items-center">
                        <input 
                          type="checkbox" 
                          v-model="challengeForm.rules.reward_all_outlets"
                          @change="onRewardAllOutletsChange('product', 'voucher')"
                          class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                        >
                        <span class="ml-2 text-sm text-gray-700">Semua Outlet</span>
                      </label>
                      <div v-if="!challengeForm.rules.reward_all_outlets">
                        <Multiselect
                          v-model="challengeForm.rules.reward_outlet_ids"
                          :options="outlets"
                          :searchable="true"
                          :clear-on-select="false"
                          :close-on-select="false"
                          :show-labels="false"
                          :multiple="true"
                          track-by="id"
                          label="name"
                          placeholder="Pilih Outlet untuk Reward (kosongkan untuk semua outlet)"
                        >
                          <template #noOptions>
                            <span>Tidak ada outlet ditemukan</span>
                          </template>
                          <template #noResult>
                            <span>Tidak ada outlet ditemukan</span>
                          </template>
                        </Multiselect>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

            </div>
            <!-- Validity & Settings -->
            <div class="bg-gray-50 rounded-lg p-4">
              <h4 class="text-md font-semibold text-gray-900 mb-4">Pengaturan Challenge</h4>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Reward Validity Period (Days) *</label>
                  <input v-model="challengeForm.validity_period_days" type="number" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="30" required>
                  <p class="mt-1 text-xs text-gray-500">Masa berlaku reward setelah challenge selesai (dalam hari). Contoh: 30 = reward expire 30 hari setelah challenge complete.</p>
                </div>
              </div>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Start Date</label>
                  <input v-model="challengeForm.start_date" type="date" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">End Date</label>
                  <input v-model="challengeForm.end_date" type="date" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
              </div>
              <div class="mt-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Image</label>
                <input @change="handleChallengeImageChange" type="file" accept="image/*" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <div class="mt-2 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                  <div class="flex items-start gap-2">
                    <i class="fa-solid fa-info-circle text-blue-600 mt-0.5"></i>
                    <div class="flex-1">
                      <p class="text-sm font-medium text-blue-800 mb-1">Rekomendasi Ukuran Gambar:</p>
                      <p class="text-xs text-blue-700">
                        <strong>Dimensi:</strong> 1080 x 400 pixels (atau rasio 2.7:1)<br>
                        <strong>Format:</strong> JPG, PNG<br>
                        <strong>Ukuran file:</strong> Maksimal 2MB<br>
                        <span class="text-blue-600">* Gambar akan ditampilkan di slider challenge dengan tinggi 120px di home screen app</span>
                      </p>
                    </div>
                  </div>
                </div>
                <div v-if="editingChallenge && editingChallenge.image" class="mt-2">
                  <img :src="getImageUrl(editingChallenge.image)" alt="Current image" class="w-32 h-20 object-cover rounded">
                  <p class="text-sm text-gray-500 mt-1">Current image</p>
                </div>
              </div>
              <div class="flex items-center mt-4">
                <input v-model="challengeForm.is_active" type="checkbox" id="challenge_active" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                <label for="challenge_active" class="ml-2 block text-sm text-gray-700">Active</label>
              </div>
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
              <div class="mt-2 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                <div class="flex items-start gap-2">
                  <i class="fa-solid fa-info-circle text-blue-600 mt-0.5"></i>
                  <div class="flex-1">
                    <p class="text-sm font-medium text-blue-800 mb-1">Rekomendasi Ukuran Gambar:</p>
                    <p class="text-xs text-blue-700">
                      <strong>Dimensi:</strong> 800 x 1000 pixels (atau rasio 4:5)<br>
                      <strong>Format:</strong> JPG, PNG<br>
                      <strong>Ukuran file:</strong> Maksimal 2MB<br>
                      <span class="text-blue-600">* Featured items: gambar akan ditampilkan dengan ukuran 120x150px<br>
                      * Non-featured items: gambar akan ditampilkan dengan ukuran lebih kecil dalam grid 2 kolom</span>
                    </p>
                  </div>
                </div>
              </div>
              <div v-if="editingWhatsOn && editingWhatsOn.image" class="mt-2">
                <img :src="getImageUrl(editingWhatsOn.image)" alt="Current image" class="w-32 h-20 object-cover rounded">
                <p class="text-sm text-gray-500 mt-1">Current image</p>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
              <div class="flex gap-2">
                <select v-model="whatsOnForm.category_id" class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                  <option value="">No Category</option>
                  <option v-for="category in props.whatsOnCategories" :key="category.id" :value="category.id">
                    {{ category.name }}
                  </option>
                </select>
                <button 
                  type="button" 
                  @click="showAddCategoryModal = true"
                  class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition flex items-center gap-2"
                  title="Add New Category"
                >
                  <i class="fa-solid fa-plus"></i>
                  <span class="hidden sm:inline">Add</span>
                </button>
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
              <label class="block text-sm font-medium text-gray-700 mb-2">Outlet/Brand *</label>
              <select 
                v-model="brandForm.outlet_id" 
                :disabled="editingBrand" 
                required 
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              >
                <option value="">Pilih Outlet</option>
                <option v-for="outlet in props.outlets" :key="outlet.id" :value="outlet.id">
                  {{ outlet.name }}
                </option>
              </select>
              <p v-if="editingBrand" class="text-xs text-gray-500 mt-1">Outlet tidak dapat diubah setelah dibuat</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Brand *</label>
              <select 
                v-model="brandForm.id_brand" 
                required 
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              >
                <option value="">Pilih Brand</option>
                <option v-for="brand in props.brandsTable" :key="brand.id" :value="brand.id">
                  {{ brand.brand }}
                </option>
              </select>
              <p class="text-xs text-gray-500 mt-1">Pilih brand yang akan dikaitkan dengan outlet ini</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
              <textarea v-model="brandForm.description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">WhatsApp Number</label>
              <input 
                v-model="brandForm.whatsapp_number" 
                type="text" 
                placeholder="Contoh: 6281234567890"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              >
              <p class="text-xs text-gray-500 mt-1">Format: 62xxxxxxxxxxx (tanpa + dan spasi)</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Brand Logo</label>
              <input @change="handleBrandLogoChange" type="file" accept="image/*" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
              <div class="mt-2 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                <div class="flex items-start gap-2">
                  <i class="fa-solid fa-info-circle text-blue-600 mt-0.5"></i>
                  <div class="flex-1">
                    <p class="text-sm font-medium text-blue-800 mb-1">Rekomendasi Ukuran Logo:</p>
                    <p class="text-xs text-blue-700">
                      <strong>Dimensi:</strong> 500 x 500 pixels (persegi 1:1) atau 1000 x 500 pixels (landscape 2:1)<br>
                      <strong>Format:</strong> PNG dengan transparan (disarankan), JPG<br>
                      <strong>Ukuran file:</strong> Maksimal 2MB<br>
                      <span class="text-blue-600">* Logo akan ditampilkan dengan ukuran 100x100px di list brand dan 80x80px di detail brand dengan BoxFit.contain</span>
                    </p>
                  </div>
                </div>
              </div>
              <div v-if="editingBrand && editingBrand.logo" class="mt-2">
                <img :src="getImageUrl(editingBrand.logo)" alt="Current logo" class="w-32 h-20 object-cover rounded">
                <p class="text-sm text-gray-500 mt-1">Current logo</p>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">PDF Menu</label>
              <input @change="handleBrandPdfMenuChange" type="file" accept=".pdf" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
              <p class="text-xs text-gray-500 mt-1">Max size: 50MB. Format: PDF</p>
              <div v-if="editingBrand && editingBrand.pdf_menu" class="mt-2">
                <a :href="getImageUrl(editingBrand.pdf_menu)" target="_blank" class="text-blue-600 hover:text-blue-800 text-sm">
                  <i class="fa-solid fa-file-pdf mr-1"></i>Current PDF Menu
                </a>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">PDF Monthly Promotion</label>
              <input @change="handleBrandPdfNewDiningChange" type="file" accept=".pdf" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
              <p class="text-xs text-gray-500 mt-1">Max size: 50MB. Format: PDF</p>
              <div v-if="editingBrand && editingBrand.pdf_new_dining_experience" class="mt-2">
                <a :href="getImageUrl(editingBrand.pdf_new_dining_experience)" target="_blank" class="text-blue-600 hover:text-blue-800 text-sm">
                  <i class="fa-solid fa-file-pdf mr-1"></i>Current PDF Monthly Promotion
                </a>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Facilities</label>
              <div class="space-y-2">
                <label class="flex items-center space-x-2 cursor-pointer">
                  <input type="checkbox" v-model="brandForm.facility" value="wifi" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                  <span class="text-sm text-gray-700">Speed Wi-fi</span>
                </label>
                <label class="flex items-center space-x-2 cursor-pointer">
                  <input type="checkbox" v-model="brandForm.facility" value="smoking_area" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                  <span class="text-sm text-gray-700">Smoking Area</span>
                </label>
                <label class="flex items-center space-x-2 cursor-pointer">
                  <input type="checkbox" v-model="brandForm.facility" value="mushola" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                  <span class="text-sm text-gray-700">Mushola</span>
                </label>
                <label class="flex items-center space-x-2 cursor-pointer">
                  <input type="checkbox" v-model="brandForm.facility" value="meeting_room" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                  <span class="text-sm text-gray-700">Meeting Room</span>
                </label>
                <label class="flex items-center space-x-2 cursor-pointer">
                  <input type="checkbox" v-model="brandForm.facility" value="valet_parking" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                  <span class="text-sm text-gray-700">Free Valet Parking</span>
                </label>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">TripAdvisor Link</label>
              <input 
                v-model="brandForm.tripadvisor_link" 
                type="url" 
                placeholder="https://www.tripadvisor.com/..."
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              >
              <p class="text-xs text-gray-500 mt-1">Masukkan link TripAdvisor untuk brand ini</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Foto Gallery</label>
              <input @change="handleBrandGalleryChange" type="file" accept="image/*" multiple class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
              <div class="mt-2 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                <div class="flex items-start gap-2">
                  <i class="fa-solid fa-info-circle text-blue-600 mt-0.5"></i>
                  <div class="flex-1">
                    <p class="text-sm font-medium text-blue-800 mb-1">Rekomendasi Ukuran Gallery:</p>
                    <p class="text-xs text-blue-700">
                      <strong>Dimensi:</strong> 1200 x 1500 pixels (atau rasio 4:5)<br>
                      <strong>Format:</strong> JPG, PNG<br>
                      <strong>Ukuran file:</strong> Maksimal 2MB per gambar<br>
                      <span class="text-blue-600">* Gallery akan ditampilkan dalam grid 2 kolom dengan aspect ratio 0.8 (sedikit lebih tinggi) untuk efek brick layout</span>
                    </p>
                  </div>
                </div>
              </div>
              <p class="text-xs text-gray-500 mt-1">Pilih multiple images untuk gallery</p>
              
              <!-- Existing Gallery Images -->
              <div v-if="editingBrand && editingBrand.galleries && editingBrand.galleries.length > 0" class="mt-4">
                <p class="text-sm font-medium text-gray-700 mb-2">Existing Gallery Images:</p>
                <div class="grid grid-cols-3 gap-2">
                  <div v-for="gallery in editingBrand.galleries" :key="gallery.id" class="relative">
                    <img :src="getImageUrl(gallery.image)" alt="Gallery" class="w-full h-24 object-cover rounded border">
                    <button 
                      type="button"
                      @click="removeGalleryImage(gallery.id)"
                      class="absolute top-1 right-1 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs hover:bg-red-600"
                    >
                      <i class="fa-solid fa-times"></i>
                    </button>
                  </div>
                </div>
              </div>
              
              <!-- Preview New Gallery Images -->
              <div v-if="brandGalleryImages.length > 0" class="mt-4">
                <p class="text-sm font-medium text-gray-700 mb-2">New Gallery Images:</p>
                <div class="grid grid-cols-3 gap-2">
                  <div v-for="(image, index) in brandGalleryImages" :key="index" class="relative">
                    <img :src="getImagePreview(image)" alt="Preview" class="w-full h-24 object-cover rounded border">
                    <button 
                      type="button"
                      @click="removeNewGalleryImage(index)"
                      class="absolute top-1 right-1 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs hover:bg-red-600"
                    >
                      <i class="fa-solid fa-times"></i>
                    </button>
                  </div>
                </div>
              </div>
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

    <!-- Add Category Modal -->
    <div v-if="showAddCategoryModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4">
        <div class="px-6 py-4 border-b border-gray-200">
          <div class="flex justify-between items-center">
            <h3 class="text-lg font-semibold text-gray-700">Add New Category</h3>
            <button @click="showAddCategoryModal = false" class="text-gray-400 hover:text-gray-600">
              <i class="fa-solid fa-times text-xl"></i>
            </button>
          </div>
        </div>
        <form @submit.prevent="saveCategory" class="p-6">
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Category Name *</label>
              <input 
                v-model="newCategoryName" 
                type="text" 
                required 
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                placeholder="Enter category name"
              >
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
              <textarea 
                v-model="newCategoryDescription" 
                rows="3" 
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                placeholder="Enter category description (optional)"
              ></textarea>
            </div>
          </div>
          <div class="flex justify-end gap-3 mt-6">
            <button 
              type="button" 
              @click="showAddCategoryModal = false" 
              class="px-4 py-2 text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition"
            >
              Cancel
            </button>
            <button 
              type="submit" 
              :disabled="addingCategory" 
              class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
            >
              <i v-if="addingCategory" class="fa-solid fa-spinner fa-spin"></i>
              {{ addingCategory ? 'Adding...' : 'Add Category' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Terms & Condition Modal -->
    <div v-if="showTermConditionModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl mx-4 max-h-[90vh] overflow-y-auto">
        <div class="px-6 py-4 border-b border-gray-200">
          <div class="flex justify-between items-center">
            <h3 class="text-lg font-semibold text-gray-700">
              {{ editingTermCondition ? 'Edit Terms & Condition' : 'Add Terms & Condition' }}
            </h3>
            <button @click="closeTermConditionModal" class="text-gray-400 hover:text-gray-600">
              <i class="fa-solid fa-times text-xl"></i>
            </button>
          </div>
        </div>
        <form @submit.prevent="saveTermCondition" class="p-6">
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Title *</label>
              <input 
                v-model="termConditionForm.title" 
                type="text" 
                required 
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                placeholder="Enter title"
              >
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Content *</label>
              <textarea 
                v-model="termConditionForm.content" 
                rows="12" 
                required 
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                placeholder="Enter terms & condition content"
              ></textarea>
            </div>
            <div class="flex items-center">
              <input 
                v-model="termConditionForm.is_active" 
                type="checkbox" 
                id="term_condition_active" 
                class="h-4 w-4 text-orange-600 focus:ring-orange-500 border-gray-300 rounded"
              >
              <label for="term_condition_active" class="ml-2 block text-sm text-gray-700">Active</label>
            </div>
          </div>
          <div class="flex justify-end gap-3 mt-6">
            <button 
              type="button" 
              @click="closeTermConditionModal" 
              class="px-4 py-2 text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition"
            >
              Cancel
            </button>
            <button 
              type="submit" 
              :disabled="savingTermCondition" 
              class="px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
            >
              <i v-if="savingTermCondition" class="fa-solid fa-spinner fa-spin"></i>
              {{ savingTermCondition ? 'Saving...' : (editingTermCondition ? 'Update' : 'Create') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- About Us Modal -->
    <div v-if="showAboutUsModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl mx-4 max-h-[90vh] overflow-y-auto">
        <div class="px-6 py-4 border-b border-gray-200">
          <div class="flex justify-between items-center">
            <h3 class="text-lg font-semibold text-gray-700">
              {{ editingAboutUs ? 'Edit About Us' : 'Add About Us' }}
            </h3>
            <button @click="closeAboutUsModal" class="text-gray-400 hover:text-gray-600">
              <i class="fa-solid fa-times text-xl"></i>
            </button>
          </div>
        </div>
        <form @submit.prevent="saveAboutUs" class="p-6">
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Title *</label>
              <input 
                v-model="aboutUsForm.title" 
                type="text" 
                required 
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500"
                placeholder="Enter title"
              >
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Content *</label>
              <textarea 
                v-model="aboutUsForm.content" 
                rows="12" 
                required 
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500"
                placeholder="Enter about us content"
              ></textarea>
            </div>
            <div class="flex items-center">
              <input 
                v-model="aboutUsForm.is_active" 
                type="checkbox" 
                id="about_us_active" 
                class="h-4 w-4 text-teal-600 focus:ring-teal-500 border-gray-300 rounded"
              >
              <label for="about_us_active" class="ml-2 block text-sm text-gray-700">Active</label>
            </div>
          </div>
          <div class="flex justify-end gap-3 mt-6">
            <button 
              type="button" 
              @click="closeAboutUsModal" 
              class="px-4 py-2 text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition"
            >
              Cancel
            </button>
            <button 
              type="submit" 
              :disabled="savingAboutUs" 
              class="px-4 py-2 bg-teal-600 text-white rounded-lg hover:bg-teal-700 transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
            >
              <i v-if="savingAboutUs" class="fa-solid fa-spinner fa-spin"></i>
              {{ savingAboutUs ? 'Saving...' : (editingAboutUs ? 'Update' : 'Create') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Benefits Modal -->
    <div v-if="showBenefitsModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl mx-4 max-h-[90vh] overflow-y-auto">
        <div class="px-6 py-4 border-b border-gray-200">
          <div class="flex justify-between items-center">
            <h3 class="text-lg font-semibold text-gray-700">
              {{ editingBenefits ? 'Edit Benefits' : 'Add Benefits' }}
            </h3>
            <button @click="closeBenefitsModal" class="text-gray-400 hover:text-gray-600">
              <i class="fa-solid fa-times text-xl"></i>
            </button>
          </div>
        </div>
        <form @submit.prevent="saveBenefits" class="p-6">
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Title *</label>
              <input 
                v-model="benefitsForm.title" 
                type="text" 
                required 
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500"
                placeholder="Enter title"
              >
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Content *</label>
              <textarea 
                v-model="benefitsForm.content" 
                rows="12" 
                required 
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500"
                placeholder="Enter benefits content"
              ></textarea>
            </div>
            <div class="flex items-center">
              <input 
                v-model="benefitsForm.is_active" 
                type="checkbox" 
                id="benefits_active" 
                class="h-4 w-4 text-teal-600 focus:ring-teal-500 border-gray-300 rounded"
              >
              <label for="benefits_active" class="ml-2 block text-sm text-gray-700">Active</label>
            </div>
          </div>
          <div class="flex justify-end gap-3 mt-6">
            <button 
              type="button" 
              @click="closeBenefitsModal" 
              class="px-4 py-2 text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition"
            >
              Cancel
            </button>
            <button 
              type="submit" 
              :disabled="savingBenefits" 
              class="px-4 py-2 bg-teal-600 text-white rounded-lg hover:bg-teal-700 transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
            >
              <i v-if="savingBenefits" class="fa-solid fa-spinner fa-spin"></i>
              {{ savingBenefits ? 'Saving...' : (editingBenefits ? 'Update' : 'Create') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Contact Us Modal -->
    <div v-if="showContactUsModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl mx-4 max-h-[90vh] overflow-y-auto">
        <div class="px-6 py-4 border-b border-gray-200">
          <div class="flex justify-between items-center">
            <h3 class="text-lg font-semibold text-gray-700">
              {{ editingContactUs ? 'Edit Contact Us' : 'Add Contact Us' }}
            </h3>
            <button @click="closeContactUsModal" class="text-gray-400 hover:text-gray-600">
              <i class="fa-solid fa-times text-xl"></i>
            </button>
          </div>
        </div>
        <form @submit.prevent="saveContactUs" class="p-6">
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Title *</label>
              <input 
                v-model="contactUsForm.title" 
                type="text" 
                required 
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500"
                placeholder="Enter title"
              >
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Content *</label>
              <textarea 
                v-model="contactUsForm.content" 
                rows="12" 
                required 
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500"
                placeholder="Enter contact us content"
              ></textarea>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">WhatsApp Number</label>
              <input 
                v-model="contactUsForm.whatsapp_number" 
                type="text" 
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500"
                placeholder="e.g., +6281234567890"
              >
              <p class="text-xs text-gray-500 mt-1">Format: +62xxxxxxxxxxx (dengan kode negara)</p>
            </div>
            <div class="flex items-center">
              <input 
                v-model="contactUsForm.is_active" 
                type="checkbox" 
                id="contact_us_active" 
                class="h-4 w-4 text-teal-600 focus:ring-teal-500 border-gray-300 rounded"
              >
              <label for="contact_us_active" class="ml-2 block text-sm text-gray-700">Active</label>
            </div>
          </div>
          <div class="flex justify-end gap-3 mt-6">
            <button 
              type="button" 
              @click="closeContactUsModal" 
              class="px-4 py-2 text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition"
            >
              Cancel
            </button>
            <button 
              type="submit" 
              :disabled="savingContactUs" 
              class="px-4 py-2 bg-teal-600 text-white rounded-lg hover:bg-teal-700 transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
            >
              <i v-if="savingContactUs" class="fa-solid fa-spinner fa-spin"></i>
              {{ savingContactUs ? 'Saving...' : (editingContactUs ? 'Update' : 'Create') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Reply Feedback Modal -->
    <div v-if="showReplyModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto">
        <div class="px-6 py-4 border-b border-gray-200">
          <div class="flex justify-between items-center">
            <h3 class="text-lg font-semibold text-gray-700">
              Reply to Feedback
            </h3>
            <button @click="closeReplyModal" class="text-gray-400 hover:text-gray-600">
              <i class="fa-solid fa-times text-xl"></i>
            </button>
          </div>
        </div>
        <div class="p-6">
          <div class="space-y-4 mb-6">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Subject</label>
              <input 
                :value="selectedFeedback?.subject" 
                type="text" 
                disabled
                class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-100"
              >
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Member</label>
              <input 
                :value="selectedFeedback?.member?.nama_lengkap + ' (' + selectedFeedback?.member?.email + ')'" 
                type="text" 
                disabled
                class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-100"
              >
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Original Message</label>
              <div class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 min-h-[100px]">
                <p class="text-gray-700 whitespace-pre-line">{{ selectedFeedback?.message }}</p>
              </div>
            </div>
            <!-- Show existing replies thread -->
            <div v-if="selectedFeedback?.replies && selectedFeedback.replies.length > 0" class="space-y-2">
              <label class="block text-sm font-medium text-gray-700 mb-2">Conversation Thread</label>
              <div 
                v-for="reply in selectedFeedback.replies" 
                :key="reply.id"
                :class="[
                  'rounded-lg p-3 border',
                  reply.replied_by ? 'bg-blue-50 border-blue-200' : 'bg-gray-50 border-gray-200'
                ]"
              >
                <div class="flex items-center gap-2 mb-1">
                  <i :class="[
                    'fa-solid text-xs',
                    reply.replied_by ? 'fa-user-shield text-blue-600' : 'fa-user text-gray-600'
                  ]"></i>
                  <span :class="[
                    'text-xs font-medium',
                    reply.replied_by ? 'text-blue-800' : 'text-gray-800'
                  ]">
                    {{ reply.replied_by ? 'Justus Group' : 'Member' }}
                  </span>
                  <span class="text-xs text-gray-500">{{ formatFeedbackDate(reply.created_at) }}</span>
                </div>
                <p class="text-sm text-gray-700 whitespace-pre-line">{{ reply.message }}</p>
              </div>
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Your Reply *</label>
            <textarea 
              v-model="replyMessage" 
              rows="6" 
              required 
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              placeholder="Enter your reply message"
            ></textarea>
          </div>
          <div class="flex justify-end gap-3 mt-6">
            <button 
              type="button" 
              @click="closeReplyModal" 
              class="px-4 py-2 text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition"
            >
              Cancel
            </button>
            <button 
              type="button" 
              @click="submitReply" 
              class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition flex items-center gap-2"
            >
              <i class="fa-solid fa-paper-plane"></i>
              Send Reply
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- FAQ Modal -->
    <div v-if="showFaqModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto">
        <div class="px-6 py-4 border-b border-gray-200">
          <div class="flex justify-between items-center">
            <h3 class="text-lg font-semibold text-gray-700">
              {{ editingFaq ? 'Edit FAQ' : 'Add FAQ' }}
            </h3>
            <button @click="closeFaqModal" class="text-gray-400 hover:text-gray-600">
              <i class="fa-solid fa-times text-xl"></i>
            </button>
          </div>
        </div>
        <form @submit.prevent="saveFaq" class="p-6">
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Question *</label>
              <textarea 
                v-model="faqForm.question" 
                rows="3" 
                required 
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                placeholder="Enter your question"
              ></textarea>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Answer *</label>
              <textarea 
                v-model="faqForm.answer" 
                rows="6" 
                required 
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                placeholder="Enter the answer"
              ></textarea>
            </div>
            <div class="flex items-center">
              <input 
                v-model="faqForm.is_active" 
                type="checkbox" 
                id="faq_active" 
                class="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300 rounded"
              >
              <label for="faq_active" class="ml-2 block text-sm text-gray-700">Active</label>
            </div>
          </div>
          <div class="flex justify-end gap-3 mt-6">
            <button 
              type="button" 
              @click="closeFaqModal" 
              class="px-4 py-2 text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition"
            >
              Cancel
            </button>
            <button 
              type="submit" 
              :disabled="savingFaq" 
              class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
            >
              <i v-if="savingFaq" class="fa-solid fa-spinner fa-spin"></i>
              {{ savingFaq ? 'Saving...' : (editingFaq ? 'Update' : 'Create') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Brand Table Modal -->
    <div v-if="showBrandTableModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
        <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
          <h3 class="text-lg font-semibold text-gray-700">
            {{ editingBrandTable ? 'Edit Brand' : 'Add Brand' }}
          </h3>
          <button @click="closeBrandTableModal" class="text-gray-400 hover:text-gray-600">
            <i class="fa-solid fa-times text-xl"></i>
          </button>
        </div>
        <form @submit.prevent="saveBrandTable" class="p-6">
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Brand Name *</label>
              <input 
                v-model="brandTableForm.brand" 
                type="text" 
                required 
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                placeholder="Enter brand name"
              >
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Logo</label>
              <input 
                type="file" 
                @change="handleBrandTableLogoChange"
                accept="image/*"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
              >
              <p class="text-xs text-gray-500 mt-1">Max size: 2MB. Formats: JPEG, PNG, JPG, GIF</p>
              <div v-if="editingBrandTable && editingBrandTable.logo" class="mt-2">
                <img :src="getImageUrl(editingBrandTable.logo)" alt="Current logo" class="w-32 h-20 object-cover rounded">
              </div>
            </div>
          </div>
          <div class="flex justify-end gap-3 mt-6">
            <button 
              type="button" 
              @click="closeBrandTableModal" 
              class="px-4 py-2 text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition"
            >
              Cancel
            </button>
            <button 
              type="submit" 
              :disabled="savingBrandTable" 
              class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
            >
              <i v-if="savingBrandTable" class="fa-solid fa-spinner fa-spin"></i>
              {{ savingBrandTable ? 'Saving...' : (editingBrandTable ? 'Update' : 'Create') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Gallery Lightbox -->
    <VueEasyLightbox
      :visible="lightboxVisible"
      :imgs="lightboxImages"
      :index="lightboxIndex"
      @hide="lightboxVisible = false"
    />
  </AppLayout>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Swal from 'sweetalert2'
import Multiselect from 'vue-multiselect'
import 'vue-multiselect/dist/vue-multiselect.min.css'
import VueEasyLightbox from 'vue-easy-lightbox'
import axios from 'axios'

const props = defineProps({
  banners: Array,
  rewards: Array,
  challenges: Array,
  whatsOn: Array,
  whatsOnCategories: Array,
  brands: Array,
  brandsTable: Array,
  outlets: Array,
  faqs: Array,
  termsConditions: Array,
  aboutUs: Array,
  benefits: Array,
  contactUs: Array,
  vouchers: Array,
  members: Object,
  occupations: Array,
  feedbacks: Object
})

const activeTab = ref('banner')

const tabs = [
  { id: 'banner', name: 'Banner', icon: 'fa-solid fa-image' },
  { id: 'reward', name: 'Reward', icon: 'fa-solid fa-gift' },
  { id: 'challenge', name: 'Challenge', icon: 'fa-solid fa-trophy' },
  { id: 'whats-on', name: 'Whats On', icon: 'fa-solid fa-newspaper' },
  { id: 'outlet', name: 'Outlets', icon: 'fa-solid fa-building' },
  { id: 'brands', name: 'Brands', icon: 'fa-solid fa-store' },
  { id: 'faq', name: 'FAQ', icon: 'fa-solid fa-question-circle' },
  { id: 'terms-condition', name: 'Terms & Condition', icon: 'fa-solid fa-file-contract' },
  { id: 'about-us', name: 'About Us', icon: 'fa-solid fa-info-circle' },
  { id: 'benefits', name: 'Benefits', icon: 'fa-solid fa-star' },
  { id: 'contact-us', name: 'Contact Us', icon: 'fa-solid fa-phone' },
  { id: 'voucher', name: 'Voucher', icon: 'fa-solid fa-ticket' },
  { id: 'feedback', name: 'Feedback', icon: 'fa-solid fa-comment-dots' }
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
  is_active: true,
  all_outlets: false,
  outlet_ids: []
})

const challengeForm = ref({
  challenge_type_id: '',
  title: '',
  description: '',
  rules: {},
  validity_period_days: 30,
  image: null,
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
  is_featured: false,
  category_id: ''
})

// Category management
const showAddCategoryModal = ref(false)
const newCategoryName = ref('')
const newCategoryDescription = ref('')
const addingCategory = ref(false)

const brandForm = ref({
  outlet_id: '',
  id_brand: '',
  description: '',
  whatsapp_number: '',
  facility: [],
  tripadvisor_link: '',
  logo: null,
  pdf_menu: null,
  pdf_new_dining_experience: null,
  gallery_images: []
})

// Brands Table management (for brands table)
const brandsTable = ref(props.brandsTable || [])
const showBrandTableModal = ref(false)
const editingBrandTable = ref(null)
const savingBrandTable = ref(false)
const brandTableForm = ref({
  brand: '',
  logo: null
})

// Gallery management
const brandGalleryImages = ref([])
const deleteGalleryIds = ref([])

// Lightbox for gallery
const lightboxVisible = ref(false)
const lightboxImages = ref([])
const lightboxIndex = ref(0)

// Open gallery lightbox
const openGalleryLightbox = (galleries, index) => {
  if (!galleries || galleries.length === 0) return
  
  lightboxImages.value = galleries.map(gallery => getImageUrl(gallery.image))
  lightboxIndex.value = index
  lightboxVisible.value = true
}

// FAQ management
const showFaqModal = ref(false)
const editingFaq = ref(null)
const savingFaq = ref(false)
const faqForm = ref({
  question: '',
  answer: '',
  is_active: true
})

// Terms & Condition management
const showTermConditionModal = ref(false)
const editingTermCondition = ref(null)
const savingTermCondition = ref(false)
const termConditionForm = ref({
  title: '',
  content: '',
  is_active: true
})

// About Us management
const showAboutUsModal = ref(false)
const editingAboutUs = ref(null)

const showBenefitsModal = ref(false)
const editingBenefits = ref(null)
const savingBenefits = ref(false)
const benefitsForm = ref({
  title: '',
  content: '',
  is_active: true
})

const showContactUsModal = ref(false)
const editingContactUs = ref(null)
const savingContactUs = ref(false)
const contactUsForm = ref({
  title: '',
  content: '',
  whatsapp_number: '',
  is_active: true
})
const savingAboutUs = ref(false)
const aboutUsForm = ref({
  title: '',
  content: '',
  is_active: true
})

// Voucher management
const showVoucherModal = ref(false)
const savingVoucher = ref(false)
const voucherImageInput = ref(null)
const voucherForm = ref({
  name: '',
  description: '',
  voucher_type: '',
  discount_percentage: null,
  discount_amount: null,
  min_purchase: null,
  max_discount: null,
  free_item_name: '',
  free_item_ids: [],
  free_item_selection: 'all',
  cashback_amount: null,
  cashback_percentage: null,
  valid_from: '',
  valid_until: '',
  usage_limit: null,
  total_quantity: null,
  applicable_channels: [],
  applicable_days: [],
  applicable_time_start: null,
  applicable_time_end: null,
  exclude_items: [],
  exclude_categories: [],
  image: null,
  imagePreview: null,
  is_active: true,
  is_for_sale: false,
  points_required: null,
  one_time_purchase: false,
  is_birthday_voucher: false,
  all_outlets: true,
  outlet_ids: []
})

// Distribute Voucher
const showDistributeVoucherModal = ref(false)
const distributingVoucher = ref(false)
const distributingVoucherData = ref(null)
const distributionForm = ref({
  distribution_type: '',
  member_ids: [],
  filter_criteria: {
    occupation_id: '',
    member_level: '',
    is_active: '',
    is_exclusive_member: '',
    min_spending: null,
    max_spending: null,
    min_points: null,
    max_points: null,
    registered_from: '',
    registered_until: '',
    jenis_kelamin: ''
  }
})

// Member options for multiselect
const memberOptions = ref([])
const memberSearchLoading = ref(false)
let memberSearchTimeout = null
let memberSearchRequestToken = 0

// Voucher Members Modal
const showVoucherMembersModal = ref(false)
const viewingVoucherData = ref(null)
const voucherMembers = ref([])
const voucherMembersTotal = ref(0)
const loadingVoucherMembers = ref(false)
const voucherMembersSearch = ref('')
let voucherMembersSearchTimeout = null

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

const formatNumber = (number) => {
  if (!number && number !== 0) return '0'
  return new Intl.NumberFormat('id-ID').format(number)
}

const formatDate = (date) => {
  if (!date) return ''
  return new Date(date).toLocaleDateString('id-ID', {
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
}

// Helper function to get facility display name
const getFacilityName = (facilityKey) => {
  const facilityNames = {
    'wifi': 'Speed Wi-fi',
    'smoking_area': 'Smoking Area',
    'mushola': 'Mushola',
    'meeting_room': 'Meeting Room',
    'valet_parking': 'Free Valet Parking'
  }
  return facilityNames[facilityKey] || facilityKey
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
    is_active: true,
    all_outlets: false,
    outlet_ids: []
  }
  showRewardModal.value = true
}

const onRewardAllOutletsChangeReward = () => {
  if (rewardForm.value.all_outlets) {
    rewardForm.value.outlet_ids = []
  }
}

const openChallengeModal = () => {
  editingChallenge.value = null
  challengeForm.value = {
    challenge_type_id: '',
    title: '',
    description: '',
    rules: {
      challenge_all_outlets: true,
      challenge_outlet_ids: [],
      reward_all_outlets: true,
      reward_outlet_ids: [],
      reward_item_selection: 'all' // Default: all
    },
    validity_period_days: 30,
    image: null,
    start_date: '',
    end_date: '',
    is_active: true
  }
  showChallengeModal.value = true
}

const onChallengeTypeChange = () => {
  // Reset rules when type changes
  challengeForm.value.rules = {}
}

// Computed property to check if should show reward selection mode
const shouldShowRewardSelectionMode = computed(() => {
  if (!challengeForm.value.rules || challengeForm.value.rules.reward_type !== 'item') {
    return false
  }
  
  const rewardValue = challengeForm.value.rules.reward_value
  if (!rewardValue) {
    return false
  }
  
  // Check if reward_value is array and has more than 1 item
  if (Array.isArray(rewardValue)) {
    return rewardValue.length > 1
  }
  
  return false
})

// Watch reward_type to initialize reward_value as array when type is 'item'
watch(() => challengeForm.value.rules?.reward_type, (newType, oldType) => {
  if (newType !== oldType) {
    if (newType === 'item' || newType === 'voucher') {
      // Initialize as empty array if not already an array
      if (!Array.isArray(challengeForm.value.rules.reward_value)) {
        challengeForm.value.rules.reward_value = []
      }
      // Initialize reward_item_selection if not set
      if (!challengeForm.value.rules.reward_item_selection) {
        challengeForm.value.rules.reward_item_selection = 'all'
      }
    } else if (newType === 'points') {
      challengeForm.value.rules.reward_value = 0
    }
  }
})

// Watch reward_value to ensure reward_item_selection is initialized when items are selected
watch(() => challengeForm.value.rules?.reward_value, (newValue) => {
  if (challengeForm.value.rules?.reward_type === 'item') {
    // Ensure reward_value is always an array
    if (!Array.isArray(newValue)) {
      challengeForm.value.rules.reward_value = []
    }
    // Initialize reward_item_selection if not set and we have items
    if (!challengeForm.value.rules.reward_item_selection && Array.isArray(newValue) && newValue.length > 0) {
      challengeForm.value.rules.reward_item_selection = 'all'
    }
  }
}, { deep: true })

const onAllOutletsChange = (type) => {
  if (challengeForm.value.rules.all_outlets) {
    challengeForm.value.rules.outlet_ids = []
  }
}

const onRewardAllOutletsChange = (challengeType, rewardType) => {
  if (challengeForm.value.rules.reward_all_outlets) {
    challengeForm.value.rules.reward_outlet_ids = []
  }
}

// Handler for challenge all outlets change
const onChallengeAllOutletsChange = () => {
  if (challengeForm.value.rules.challenge_all_outlets) {
    challengeForm.value.rules.challenge_outlet_ids = []
  }
}

// Handler for voucher all outlets change
const onVoucherAllOutletsChange = () => {
  if (voucherForm.value.all_outlets) {
    voucherForm.value.outlet_ids = []
  }
}

// Handler for voucher for sale change
const onVoucherForSaleChange = () => {
  if (!voucherForm.value.is_for_sale) {
    voucherForm.value.points_required = null
    voucherForm.value.one_time_purchase = false
  }
}

const onVoucherBirthdayChange = () => {
  // Birthday voucher cannot be for sale
  if (voucherForm.value.is_birthday_voucher) {
    voucherForm.value.is_for_sale = false
    voucherForm.value.points_required = null
    voucherForm.value.one_time_purchase = false
  }
}

const openWhatsOnModal = () => {
  editingWhatsOn.value = null
  whatsOnForm.value = {
    title: '',
    content: '',
    image: null,
    published_at: '',
    is_active: true,
    is_featured: false,
    category_id: ''
  }
  showWhatsOnModal.value = true
}

const openBrandModal = () => {
  editingBrand.value = null
  brandForm.value = {
    outlet_id: '',
    id_brand: '',
    description: '',
    whatsapp_number: '',
    facility: [],
    tripadvisor_link: '',
    logo: null,
    pdf_menu: null,
    pdf_new_dining_experience: null,
    gallery_images: []
  }
  brandGalleryImages.value = []
  deleteGalleryIds.value = []
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
  brandForm.value = {
    outlet_id: '',
    id_brand: '',
    description: '',
    whatsapp_number: '',
    facility: [],
    tripadvisor_link: '',
    logo: null,
    pdf_menu: null,
    pdf_new_dining_experience: null,
    gallery_images: []
  }
  brandGalleryImages.value = []
  deleteGalleryIds.value = []
}

const openFaqModal = () => {
  editingFaq.value = null
  faqForm.value = {
    question: '',
    answer: '',
    is_active: true
  }
  showFaqModal.value = true
}

const closeFaqModal = () => {
  showFaqModal.value = false
  editingFaq.value = null
}

const editFaq = (faq) => {
  editingFaq.value = faq
  faqForm.value = {
    question: faq.question || '',
    answer: faq.answer || '',
    is_active: faq.is_active
  }
  showFaqModal.value = true
}

const openTermConditionModal = () => {
  editingTermCondition.value = null
  termConditionForm.value = {
    title: '',
    content: '',
    is_active: true
  }
  showTermConditionModal.value = true
}

const closeTermConditionModal = () => {
  showTermConditionModal.value = false
  editingTermCondition.value = null
}

const editTermCondition = (termCondition) => {
  editingTermCondition.value = termCondition
  termConditionForm.value = {
    title: termCondition.title || '',
    content: termCondition.content || '',
    is_active: termCondition.is_active
  }
  showTermConditionModal.value = true
}

const openAboutUsModal = () => {
  editingAboutUs.value = null
  aboutUsForm.value = {
    title: '',
    content: '',
    is_active: true
  }
  showAboutUsModal.value = true
}

const closeAboutUsModal = () => {
  showAboutUsModal.value = false
  editingAboutUs.value = null
}

const editAboutUs = (about) => {
  editingAboutUs.value = about
  aboutUsForm.value = {
    title: about.title || '',
    content: about.content || '',
    is_active: about.is_active
  }
  showAboutUsModal.value = true
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
  
  // Load outlet_ids if reward has outlets
  let outletIds = []
  let allOutlets = false
  if (reward.outlets && reward.outlets.length > 0) {
    // Map outlet objects to match multiselect format
    outletIds = reward.outlets.map(outlet => {
      const outletObj = props.outlets.find(o => o.id === outlet.id || o.id === outlet.id_outlet)
      return outletObj || { id: outlet.id || outlet.id_outlet, name: outlet.name || outlet.nama_outlet }
    })
  } else {
    // If no outlets, assume all outlets
    allOutlets = true
  }
  
  rewardForm.value = {
    item_id: selectedItem || null, // Set to the found object or null
    points_required: reward.points_required,
    is_active: reward.is_active,
    all_outlets: allOutlets,
    outlet_ids: outletIds
  }
  showRewardModal.value = true
}

const editChallenge = (challenge) => {
  editingChallenge.value = challenge
  
  // Parse rules JSON if it's a string
  let rules = challenge.rules || {}
  if (typeof rules === 'string') {
    try {
      rules = JSON.parse(rules)
    } catch (e) {
      console.error('Error parsing rules JSON:', e)
      rules = {}
    }
  }
  
  // Initialize products for product-based challenge
  if (challenge.challenge_type_id === 'product' && rules.products) {
    if (Array.isArray(rules.products)) {
      rules.products = rules.products.map(id => {
        const item = items.value.find(i => i.id == id)
        return item || { id: id, name: 'Unknown Product' }
      }).filter(Boolean)
    } else {
      rules.products = []
    }
  }
  
  // Initialize outlet_ids for product-based challenge
  if (challenge.challenge_type_id === 'product') {
    if (rules.all_outlets) {
      rules.all_outlets = true
      rules.outlet_ids = []
    } else if (rules.outlet_ids) {
      if (Array.isArray(rules.outlet_ids)) {
        rules.outlet_ids = rules.outlet_ids.map(id => {
          const outlet = props.outlets.find(o => o.id == id)
          return outlet || { id: id, name: 'Unknown Outlet' }
        }).filter(Boolean)
      }
    } else {
      rules.all_outlets = false
      rules.outlet_ids = []
    }
  }
  
  // Initialize challenge outlet scope (from challenge.outlets relationship)
  if (challenge.outlets && challenge.outlets.length > 0) {
    rules.challenge_all_outlets = false
    rules.challenge_outlet_ids = challenge.outlets.map(outlet => {
      const outletObj = props.outlets.find(o => o.id == outlet.id_outlet || o.id == outlet.id)
      return outletObj || { id: outlet.id_outlet || outlet.id, name: outlet.nama_outlet || outlet.name || 'Unknown Outlet' }
    }).filter(Boolean)
  } else {
    // If no outlets, challenge applies to all outlets
    rules.challenge_all_outlets = true
    rules.challenge_outlet_ids = []
  }
  
  // Initialize reward_value and reward outlet_ids based on reward_type
  if (rules.reward_type === 'item' && rules.reward_value) {
    // For items, convert IDs to array of item objects
    if (Array.isArray(rules.reward_value)) {
      rules.reward_value = rules.reward_value.map(id => {
        const item = items.value.find(i => i.id == id)
        return item || { id: id, name: 'Unknown Item' }
      }).filter(Boolean)
    } else if (rules.item_id) {
      // Backward compatibility: if item_id exists, convert to array
      const selectedItem = items.value.find(item => item.id === rules.item_id)
      rules.reward_value = selectedItem ? [selectedItem] : []
    } else {
      rules.reward_value = []
    }
    
    // Initialize reward_item_selection (default: all)
    if (!rules.reward_item_selection) {
      rules.reward_item_selection = 'all'
    }
    
    // Initialize reward outlet_ids for item reward
    if (rules.reward_all_outlets) {
      rules.reward_all_outlets = true
      rules.reward_outlet_ids = []
    } else if (rules.reward_outlet_ids) {
      if (Array.isArray(rules.reward_outlet_ids)) {
        rules.reward_outlet_ids = rules.reward_outlet_ids.map(id => {
          const outlet = props.outlets.find(o => o.id == id)
          return outlet || { id: id, name: 'Unknown Outlet' }
        }).filter(Boolean)
      }
    } else {
      rules.reward_all_outlets = false
      rules.reward_outlet_ids = []
    }
  } else if (rules.reward_type === 'voucher' && rules.reward_value) {
    // For vouchers, convert IDs to array of voucher objects
    if (Array.isArray(rules.reward_value)) {
      rules.reward_value = rules.reward_value.map(id => {
        const voucher = props.vouchers.find(v => v.id == id)
        return voucher || { id: id, name: 'Unknown Voucher' }
      }).filter(Boolean)
    } else {
      rules.reward_value = []
    }
    
    // Initialize reward outlet_ids for voucher reward
    if (rules.reward_all_outlets) {
      rules.reward_all_outlets = true
      rules.reward_outlet_ids = []
    } else if (rules.reward_outlet_ids) {
      if (Array.isArray(rules.reward_outlet_ids)) {
        rules.reward_outlet_ids = rules.reward_outlet_ids.map(id => {
          const outlet = props.outlets.find(o => o.id == id)
          return outlet || { id: id, name: 'Unknown Outlet' }
        }).filter(Boolean)
      }
    } else {
      rules.reward_all_outlets = false
      rules.reward_outlet_ids = []
    }
  } else if (rules.reward_type === 'points') {
    // For points, use reward_value from rules, or fallback to points_reward from challenge
    if (rules.reward_value) {
      rules.reward_value = parseInt(rules.reward_value) || 0
    } else if (challenge.points_reward) {
      rules.reward_value = parseInt(challenge.points_reward) || 0
    } else {
      rules.reward_value = 0
    }
  } else {
    // If no reward_type is set but points_reward exists, set reward_type to points
    if (!rules.reward_type && challenge.points_reward) {
      rules.reward_type = 'points'
      rules.reward_value = parseInt(challenge.points_reward) || 0
    }
  }
  
  // Format dates for input fields (YYYY-MM-DD)
  let startDate = ''
  let endDate = ''
  if (challenge.start_date) {
    const start = new Date(challenge.start_date)
    startDate = start.toISOString().split('T')[0]
  }
  if (challenge.end_date) {
    const end = new Date(challenge.end_date)
    endDate = end.toISOString().split('T')[0]
  }
  
  challengeForm.value = {
    challenge_type_id: challenge.challenge_type_id || '',
    title: challenge.title || '',
    description: challenge.description || '',
    rules: rules,
    validity_period_days: challenge.validity_period_days || 30,
    image: null,
    start_date: startDate,
    end_date: endDate,
    is_active: challenge.is_active !== undefined ? challenge.is_active : true
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
  // Parse facility - handle both array and JSON string
  let facilityArray = []
  if (brand.facility) {
    if (Array.isArray(brand.facility)) {
      facilityArray = brand.facility
    } else if (typeof brand.facility === 'string') {
      try {
        facilityArray = JSON.parse(brand.facility)
        if (!Array.isArray(facilityArray)) {
          facilityArray = []
        }
      } catch (e) {
        console.error('Error parsing facility:', e)
        facilityArray = []
      }
    }
  }
  
  console.log('=== EDIT BRAND DEBUG ===')
  console.log('brand.facility:', brand.facility)
  console.log('facilityArray:', facilityArray)
  console.log('brand.tripadvisor_link:', brand.tripadvisor_link)
  console.log('brand.description:', brand.description)
  console.log('brand.whatsapp_number:', brand.whatsapp_number)
  
  // Get id_brand from outlet
  let idBrand = ''
  if (brand.outlet_id) {
    const outlet = props.outlets.find(o => o.id == brand.outlet_id)
    if (outlet && outlet.id_brand) {
      idBrand = outlet.id_brand
    }
  }
  
  brandForm.value = {
    outlet_id: brand.outlet_id || '',
    id_brand: idBrand,
    description: brand.description || '',
    whatsapp_number: brand.whatsapp_number || '',
    facility: facilityArray,
    tripadvisor_link: brand.tripadvisor_link || '',
    logo: null,
    pdf_menu: null,
    pdf_new_dining_experience: null,
    gallery_images: []
  }
  
  console.log('brandForm.value after setting:', JSON.parse(JSON.stringify(brandForm.value)))
  
  brandGalleryImages.value = []
  deleteGalleryIds.value = []
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

const handleBrandLogoChange = (event) => {
  brandForm.value.logo = event.target.files[0]
}

const handleBrandPdfMenuChange = (event) => {
  const file = event.target.files[0]
  if (file) {
    if (file.size > 50 * 1024 * 1024) {
      Swal.fire({
        icon: 'error',
        title: 'File too large',
        text: 'PDF Menu size must be less than 50MB',
        confirmButtonText: 'OK'
      })
      event.target.value = ''
      return
    }
    brandForm.value.pdf_menu = file
  }
}

const handleBrandPdfNewDiningChange = (event) => {
  const file = event.target.files[0]
  if (file) {
    if (file.size > 50 * 1024 * 1024) {
      Swal.fire({
        icon: 'error',
        title: 'File too large',
        text: 'PDF Monthly Promotion size must be less than 50MB',
        confirmButtonText: 'OK'
      })
      event.target.value = ''
      return
    }
    brandForm.value.pdf_new_dining_experience = file
  }
}

const handleBrandGalleryChange = (event) => {
  const files = Array.from(event.target.files)
  brandGalleryImages.value = [...brandGalleryImages.value, ...files]
  // Reset input
  event.target.value = ''
}

const removeGalleryImage = (galleryId) => {
  deleteGalleryIds.value.push(galleryId)
  if (editingBrand.value && editingBrand.value.galleries) {
    editingBrand.value.galleries = editingBrand.value.galleries.filter(g => g.id !== galleryId)
  }
}

const removeNewGalleryImage = (index) => {
  brandGalleryImages.value.splice(index, 1)
}

const getImagePreview = (file) => {
  return URL.createObjectURL(file)
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
  
  // Format outlet_ids
  let outletIds = []
  if (!rewardForm.value.all_outlets && rewardForm.value.outlet_ids && rewardForm.value.outlet_ids.length > 0) {
    outletIds = rewardForm.value.outlet_ids.map(outlet => 
      typeof outlet === 'object' ? outlet.id : outlet
    )
  }
  
  const data = {
    item_id: rewardForm.value.item_id?.id || rewardForm.value.item_id,
    points_required: rewardForm.value.points_required,
    is_active: rewardForm.value.is_active,
    outlet_ids: outletIds,
    all_outlets: rewardForm.value.all_outlets
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
  
  // Prepare rules: format all fields based on type
  const rules = { ...challengeForm.value.rules }
  
  // Format products for product-based challenge
  if (challengeForm.value.challenge_type_id === 'product' && rules.products) {
    if (Array.isArray(rules.products)) {
      rules.products = rules.products.map(product => 
        typeof product === 'object' ? product.id : product
      )
    }
  }
  
  // Format outlet_ids for product-based challenge
  if (challengeForm.value.challenge_type_id === 'product') {
    if (rules.all_outlets) {
      rules.outlet_ids = []
    } else if (rules.outlet_ids && Array.isArray(rules.outlet_ids)) {
      rules.outlet_ids = rules.outlet_ids.map(outlet => 
        typeof outlet === 'object' ? outlet.id : outlet
      )
    }
  }
  
  // Format challenge outlet_ids for spending-based challenge
  let challengeOutletIds = []
  if (challengeForm.value.challenge_type_id === 'spending') {
    if (rules.challenge_all_outlets) {
      challengeOutletIds = []
    } else if (rules.challenge_outlet_ids && Array.isArray(rules.challenge_outlet_ids)) {
      challengeOutletIds = rules.challenge_outlet_ids.map(outlet => 
        typeof outlet === 'object' ? outlet.id : outlet
      )
    }
  }
  
  // Format reward_value based on reward_type
  if (rules.reward_type === 'item' && rules.reward_value) {
    // For items, convert array of objects to array of IDs
    if (Array.isArray(rules.reward_value)) {
      rules.reward_value = rules.reward_value.map(item => 
        typeof item === 'object' ? item.id : item
      )
      
      // Set default reward_item_selection if not set and multiple items selected
      if (rules.reward_value.length > 1 && !rules.reward_item_selection) {
        rules.reward_item_selection = 'all' // Default to 'all' if not specified
      }
    }
    
    // Format reward outlet_ids for item reward
    if (rules.reward_all_outlets) {
      rules.reward_outlet_ids = []
    } else if (rules.reward_outlet_ids && Array.isArray(rules.reward_outlet_ids)) {
      rules.reward_outlet_ids = rules.reward_outlet_ids.map(outlet => 
        typeof outlet === 'object' ? outlet.id : outlet
      )
    }
  } else if (rules.reward_type === 'voucher' && rules.reward_value) {
    // For vouchers, convert array of objects to array of IDs
    if (Array.isArray(rules.reward_value)) {
      rules.reward_value = rules.reward_value.map(voucher => 
        typeof voucher === 'object' ? voucher.id : voucher
      )
    }
    
    // Format reward outlet_ids for voucher reward
    if (rules.reward_all_outlets) {
      rules.reward_outlet_ids = []
    } else if (rules.reward_outlet_ids && Array.isArray(rules.reward_outlet_ids)) {
      rules.reward_outlet_ids = rules.reward_outlet_ids.map(outlet => 
        typeof outlet === 'object' ? outlet.id : outlet
      )
    }
  } else if (rules.reward_type === 'points' && rules.reward_value) {
    // For points, ensure it's a number
    rules.reward_value = parseInt(rules.reward_value) || 0
  }
  
  const formData = new FormData()
  formData.append('title', challengeForm.value.title)
  formData.append('description', challengeForm.value.description)
  formData.append('rules', JSON.stringify(rules))
  formData.append('start_date', challengeForm.value.start_date)
  formData.append('end_date', challengeForm.value.end_date)
  formData.append('is_active', challengeForm.value.is_active ? '1' : '0')
  
  // Extract points_reward from rules if reward_type is 'points', otherwise use 0 as default
  let pointsReward = 0
  if (rules.reward_type === 'points' && rules.reward_value) {
    pointsReward = parseInt(rules.reward_value) || 0
  }
  formData.append('points_reward', pointsReward.toString())
  
  // Always send challenge_type_id if it exists (even if empty string)
  if (challengeForm.value.challenge_type_id !== undefined && challengeForm.value.challenge_type_id !== null) {
    formData.append('challenge_type_id', challengeForm.value.challenge_type_id || '')
  }
  // Always send validity_period_days if it exists
  if (challengeForm.value.validity_period_days !== undefined && challengeForm.value.validity_period_days !== null) {
    formData.append('validity_period_days', challengeForm.value.validity_period_days)
  }
  
  // Send challenge outlet_ids separately for database storage (for spending-based challenge)
  if (challengeForm.value.challenge_type_id === 'spending') {
    if (rules.challenge_all_outlets) {
      // Empty array means all outlets
      formData.append('challenge_outlet_ids[]', '')
    } else if (challengeOutletIds && challengeOutletIds.length > 0) {
      challengeOutletIds.forEach(id => {
        formData.append('challenge_outlet_ids[]', id)
      })
    }
  }
  
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
  if (whatsOnForm.value.category_id) {
    formData.append('category_id', whatsOnForm.value.category_id)
  }
  
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
  
  // Debug: Log form data before sending
  console.log('=== SAVE BRAND DEBUG ===')
  console.log('editingBrand:', editingBrand.value)
  console.log('brandForm.value:', JSON.parse(JSON.stringify(brandForm.value)))
  console.log('facility:', brandForm.value.facility)
  console.log('tripadvisor_link:', brandForm.value.tripadvisor_link)
  console.log('description:', brandForm.value.description)
  console.log('whatsapp_number:', brandForm.value.whatsapp_number)
  
  const formData = new FormData()
  
  if (!editingBrand.value) {
    formData.append('outlet_id', brandForm.value.outlet_id)
  }
  
  // Always append id_brand (for both create and update)
  if (brandForm.value.id_brand) {
    formData.append('id_brand', brandForm.value.id_brand)
  }
  
  // Always append description, even if empty
  formData.append('description', brandForm.value.description || '')
  
  // Always append whatsapp_number, even if empty
  formData.append('whatsapp_number', brandForm.value.whatsapp_number || '')
  
  // Append facility as array (FormData will handle it correctly)
  console.log('Saving brand - facility type:', typeof brandForm.value.facility)
  console.log('Saving brand - facility:', brandForm.value.facility)
  console.log('Saving brand - facility isArray:', Array.isArray(brandForm.value.facility))
  console.log('Saving brand - tripadvisor_link:', brandForm.value.tripadvisor_link)
  
  if (brandForm.value.facility && Array.isArray(brandForm.value.facility) && brandForm.value.facility.length > 0) {
    // Send as array - FormData will convert to facility[]
    brandForm.value.facility.forEach((facility) => {
      formData.append('facility[]', facility)
      console.log('Appended facility:', facility)
    })
    console.log('Sending facility as array:', brandForm.value.facility)
  } else if (editingBrand.value) {
    // For update, if facility is empty, we need to clear it
    // Send a special marker to indicate empty
    formData.append('facility_clear', '1')
    console.log('Sending facility clear marker for update')
  }
  
  // Append tripadvisor_link (always send, even if empty)
  const tripadvisorLink = brandForm.value.tripadvisor_link || ''
  formData.append('tripadvisor_link', tripadvisorLink)
  console.log('Appended tripadvisor_link:', tripadvisorLink)
  
  // Debug: Log all FormData entries
  console.log('=== FormData entries ===')
  for (let pair of formData.entries()) {
    console.log(pair[0] + ': ' + pair[1])
  }
  
  if (brandForm.value.logo) {
    formData.append('logo', brandForm.value.logo)
  }
  
  if (brandForm.value.pdf_menu) {
    formData.append('pdf_menu', brandForm.value.pdf_menu)
  }
  
  if (brandForm.value.pdf_new_dining_experience) {
    formData.append('pdf_new_dining_experience', brandForm.value.pdf_new_dining_experience)
  }
  
  // Append gallery images
  brandGalleryImages.value.forEach((image, index) => {
    formData.append(`gallery_images[${index}]`, image)
  })
  
  // Append delete gallery IDs for update
  if (editingBrand.value && deleteGalleryIds.value.length > 0) {
    deleteGalleryIds.value.forEach((id, index) => {
      formData.append(`delete_gallery_ids[${index}]`, id)
    })
  }

  const url = editingBrand.value 
    ? `/admin/member-apps-settings/brand/${editingBrand.value.id}`
    : '/admin/member-apps-settings/brand'
  
  const method = editingBrand.value ? 'put' : 'post'
  
  // Use axios for file uploads to get better error handling
  console.log('=== SENDING REQUEST ===')
  console.log('Method:', method)
  console.log('URL:', url)
  
  // Get CSRF token from meta tag
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
  if (csrfToken) {
    formData.append('_token', csrfToken)
  }
  
  // For PUT request, Laravel needs _method
  if (method === 'put') {
    formData.append('_method', 'PUT')
  }
  
  axios({
    method: 'post', // Always use POST, Laravel will handle PUT via _method
    url: url,
    data: formData,
    headers: {
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest'
      // Don't set Content-Type for FormData, let browser set it automatically with boundary
    }
  })
  .then((response) => {
    savingBrand.value = false
    closeBrandModal()
    Swal.fire({
      icon: 'success',
      title: 'Success!',
      text: editingBrand.value ? 'Brand updated successfully!' : 'Brand created successfully!',
      timer: 2000,
      showConfirmButton: false
    })
    // Reload page to refresh data
    window.location.reload()
  })
  .catch((error) => {
    savingBrand.value = false
    console.error('Error saving brand:', error)
    
    let errorMessage = 'Failed to save brand. Please try again.'
    
    if (error.response) {
      // Server responded with error
      const responseData = error.response.data
      
      if (responseData.message) {
        errorMessage = responseData.message
      } else if (responseData.errors) {
        // Check for general error first
        if (responseData.errors.general) {
          errorMessage = Array.isArray(responseData.errors.general) 
            ? responseData.errors.general[0] 
            : responseData.errors.general
        } else {
          // Get first error message
          const errorKeys = Object.keys(responseData.errors)
          if (errorKeys.length > 0) {
            const firstError = responseData.errors[errorKeys[0]]
            errorMessage = Array.isArray(firstError) ? firstError[0] : firstError
          }
        }
      } else if (responseData.error) {
        errorMessage = responseData.error
      }
    } else if (error.message) {
      errorMessage = error.message
    }
    
    Swal.fire({
      icon: 'error',
      title: 'Error!',
      text: errorMessage,
      confirmButtonText: 'OK'
    })
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

const viewChallenge = (id) => {
  router.visit(`/admin/member-apps-settings/challenge/${id}`)
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

// Brands Table functions
const openBrandTableModal = () => {
  editingBrandTable.value = null
  brandTableForm.value = {
    brand: '',
    logo: null
  }
  showBrandTableModal.value = true
}

const editBrandTable = (brand) => {
  editingBrandTable.value = brand
  brandTableForm.value = {
    brand: brand.brand || '',
    logo: null
  }
  showBrandTableModal.value = true
}

const closeBrandTableModal = () => {
  showBrandTableModal.value = false
  editingBrandTable.value = null
  brandTableForm.value = {
    brand: '',
    logo: null
  }
}

const handleBrandTableLogoChange = (event) => {
  brandTableForm.value.logo = event.target.files[0]
}

const saveBrandTable = () => {
  if (savingBrandTable.value) return
  
  savingBrandTable.value = true
  
  const formData = new FormData()
  formData.append('brand', brandTableForm.value.brand)
  
  if (brandTableForm.value.logo) {
    formData.append('logo', brandTableForm.value.logo)
  }
  
  const url = editingBrandTable.value 
    ? `/admin/member-apps-settings/brand-table/${editingBrandTable.value.id}`
    : '/admin/member-apps-settings/brand-table'
  
  const method = editingBrandTable.value ? 'put' : 'post'
  
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
  if (csrfToken) {
    formData.append('_token', csrfToken)
  }
  
  if (method === 'put') {
    formData.append('_method', 'PUT')
  }
  
  axios({
    method: 'post',
    url: url,
    data: formData,
    headers: {
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest'
    }
  })
  .then((response) => {
    savingBrandTable.value = false
    closeBrandTableModal()
    Swal.fire({
      icon: 'success',
      title: 'Success!',
      text: editingBrandTable.value ? 'Brand updated successfully!' : 'Brand created successfully!',
      timer: 2000,
      showConfirmButton: false
    })
    window.location.reload()
  })
  .catch((error) => {
    savingBrandTable.value = false
    console.error('Error saving brand table:', error)
    
    let errorMessage = 'Failed to save brand. Please try again.'
    
    if (error.response) {
      const responseData = error.response.data
      if (responseData.message) {
        errorMessage = responseData.message
      } else if (responseData.errors) {
        const errorKeys = Object.keys(responseData.errors)
        if (errorKeys.length > 0) {
          const firstError = responseData.errors[errorKeys[0]]
          errorMessage = Array.isArray(firstError) ? firstError[0] : firstError
        }
      }
    }
    
    Swal.fire({
      icon: 'error',
      title: 'Error!',
      text: errorMessage,
      confirmButtonText: 'OK'
    })
  })
}

const deleteBrandTable = (id) => {
  Swal.fire({
    title: 'Are you sure?',
    text: "You won't be able to revert this!",
    icon: 'warning',
    showCancelButton: true,
    confirmColor: '#d33',
    cancelColor: '#3085d6',
    confirmButtonText: 'Yes, delete it!'
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(`/admin/member-apps-settings/brand-table/${id}`, {
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

// FAQ functions
const saveFaq = () => {
  if (savingFaq.value) return
  
  savingFaq.value = true
  
  const formData = {
    question: faqForm.value.question,
    answer: faqForm.value.answer,
    is_active: faqForm.value.is_active
  }

  const url = editingFaq.value 
    ? `/admin/member-apps-settings/faq/${editingFaq.value.id}`
    : '/admin/member-apps-settings/faq'
  
  const method = editingFaq.value ? 'put' : 'post'
  
  router[method](url, formData, {
    onSuccess: () => {
      savingFaq.value = false
      closeFaqModal()
      Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: editingFaq.value ? 'FAQ updated successfully!' : 'FAQ created successfully!',
        timer: 2000,
        showConfirmButton: false
      })
    },
    onError: () => {
      savingFaq.value = false
      Swal.fire({
        icon: 'error',
        title: 'Error!',
        text: 'Failed to save FAQ. Please try again.',
        confirmButtonText: 'OK'
      })
    }
  })
}

const deleteFaq = (id) => {
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
      router.delete(`/admin/member-apps-settings/faq/${id}`, {
        onSuccess: () => {
          Swal.fire({
            icon: 'success',
            title: 'Deleted!',
            text: 'FAQ has been deleted.',
            timer: 2000,
            showConfirmButton: false
          })
        },
        onError: () => {
          Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'Failed to delete FAQ.',
            confirmButtonText: 'OK'
          })
        }
      })
    }
  })
}

// Terms & Condition functions
const saveTermCondition = () => {
  if (savingTermCondition.value) return
  
  savingTermCondition.value = true
  
  const formData = {
    title: termConditionForm.value.title,
    content: termConditionForm.value.content,
    is_active: termConditionForm.value.is_active
  }

  const url = editingTermCondition.value 
    ? `/admin/member-apps-settings/term-condition/${editingTermCondition.value.id}`
    : '/admin/member-apps-settings/term-condition'
  
  const method = editingTermCondition.value ? 'put' : 'post'
  
  router[method](url, formData, {
    onSuccess: () => {
      savingTermCondition.value = false
      closeTermConditionModal()
      Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: editingTermCondition.value ? 'Terms & Condition updated successfully!' : 'Terms & Condition created successfully!',
        timer: 2000,
        showConfirmButton: false
      })
    },
    onError: () => {
      savingTermCondition.value = false
      Swal.fire({
        icon: 'error',
        title: 'Error!',
        text: 'Failed to save Terms & Condition. Please try again.',
        confirmButtonText: 'OK'
      })
    }
  })
}

const deleteTermCondition = (id) => {
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
      router.delete(`/admin/member-apps-settings/term-condition/${id}`, {
        onSuccess: () => {
          Swal.fire({
            icon: 'success',
            title: 'Deleted!',
            text: 'Terms & Condition has been deleted.',
            timer: 2000,
            showConfirmButton: false
          })
        },
        onError: () => {
          Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'Failed to delete Terms & Condition.',
            confirmButtonText: 'OK'
          })
        }
      })
    }
  })
}

// About Us functions
const saveAboutUs = () => {
  if (savingAboutUs.value) return
  
  savingAboutUs.value = true
  
  const formData = {
    title: aboutUsForm.value.title,
    content: aboutUsForm.value.content,
    is_active: aboutUsForm.value.is_active
  }

  const url = editingAboutUs.value 
    ? `/admin/member-apps-settings/about-us/${editingAboutUs.value.id}`
    : '/admin/member-apps-settings/about-us'
  
  const method = editingAboutUs.value ? 'put' : 'post'
  
  router[method](url, formData, {
    onSuccess: () => {
      savingAboutUs.value = false
      closeAboutUsModal()
      Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: editingAboutUs.value ? 'About Us updated successfully!' : 'About Us created successfully!',
        timer: 2000,
        showConfirmButton: false
      })
    },
    onError: () => {
      savingAboutUs.value = false
      Swal.fire({
        icon: 'error',
        title: 'Error!',
        text: 'Failed to save About Us. Please try again.',
        confirmButtonText: 'OK'
      })
    }
  })
}

const openBenefitsModal = () => {
  editingBenefits.value = null
  benefitsForm.value = {
    title: '',
    content: '',
    is_active: true
  }
  showBenefitsModal.value = true
}

const closeBenefitsModal = () => {
  showBenefitsModal.value = false
  editingBenefits.value = null
}

const editBenefits = (benefit) => {
  editingBenefits.value = benefit
  benefitsForm.value = {
    title: benefit.title || '',
    content: benefit.content || '',
    is_active: benefit.is_active
  }
  showBenefitsModal.value = true
}

const saveBenefits = () => {
  if (savingBenefits.value) return
  
  savingBenefits.value = true
  
  const url = editingBenefits.value 
    ? `/admin/member-apps-settings/benefits/${editingBenefits.value.id}`
    : '/admin/member-apps-settings/benefits'
  
  const method = editingBenefits.value ? 'put' : 'post'
  
  router[method](url, benefitsForm.value, {
    onSuccess: () => {
      savingBenefits.value = false
      closeBenefitsModal()
      Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: editingBenefits.value ? 'Benefits updated successfully!' : 'Benefits created successfully!',
        timer: 2000,
        showConfirmButton: false
      })
    },
    onError: () => {
      savingBenefits.value = false
      Swal.fire({
        icon: 'error',
        title: 'Error!',
        text: 'Failed to save benefits. Please try again.',
        confirmButtonText: 'OK'
      })
    }
  })
}

const deleteBenefits = (id) => {
  Swal.fire({
    title: 'Are you sure?',
    text: "You won't be able to revert this!",
    icon: 'warning',
    showCancelButton: true,
    confirmColor: '#d33',
    cancelColor: '#3085d6',
    confirmText: 'Yes, delete it!'
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(`/admin/member-apps-settings/benefits/${id}`, {
        onSuccess: () => {
          Swal.fire({
            icon: 'success',
            title: 'Deleted!',
            text: 'Benefits has been deleted.',
            timer: 2000,
            showConfirmButton: false
          })
        },
        onError: () => {
          Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'Failed to delete benefits.',
            confirmButtonText: 'OK'
          })
        }
      })
    }
  })
}

const openContactUsModal = () => {
  editingContactUs.value = null
  contactUsForm.value = {
    title: '',
    content: '',
    whatsapp_number: '',
    is_active: true
  }
  showContactUsModal.value = true
}

const closeContactUsModal = () => {
  showContactUsModal.value = false
  editingContactUs.value = null
}

const editContactUs = (contact) => {
  editingContactUs.value = contact
  contactUsForm.value = {
    title: contact.title || '',
    content: contact.content || '',
    whatsapp_number: contact.whatsapp_number || '',
    is_active: contact.is_active
  }
  showContactUsModal.value = true
}

const saveContactUs = () => {
  if (savingContactUs.value) return
  
  savingContactUs.value = true
  
  const url = editingContactUs.value 
    ? `/admin/member-apps-settings/contact-us/${editingContactUs.value.id}`
    : '/admin/member-apps-settings/contact-us'
  
  const method = editingContactUs.value ? 'put' : 'post'
  
  router[method](url, contactUsForm.value, {
    onSuccess: () => {
      savingContactUs.value = false
      closeContactUsModal()
      Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: editingContactUs.value ? 'Contact Us updated successfully!' : 'Contact Us created successfully!',
        timer: 2000,
        showConfirmButton: false
      })
    },
    onError: () => {
      savingContactUs.value = false
      Swal.fire({
        icon: 'error',
        title: 'Error!',
        text: 'Failed to save contact us. Please try again.',
        confirmButtonText: 'OK'
      })
    }
  })
}

const deleteContactUs = (id) => {
  Swal.fire({
    title: 'Are you sure?',
    text: "You won't be able to revert this!",
    icon: 'warning',
    showCancelButton: true,
    confirmColor: '#d33',
    cancelColor: '#3085d6',
    confirmText: 'Yes, delete it!'
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(`/admin/member-apps-settings/contact-us/${id}`, {
        onSuccess: () => {
          Swal.fire({
            icon: 'success',
            title: 'Deleted!',
            text: 'Contact Us has been deleted.',
            timer: 2000,
            showConfirmButton: false
          })
        },
        onError: () => {
          Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'Failed to delete contact us.',
            confirmButtonText: 'OK'
          })
        }
      })
    }
  })
}

const deleteAboutUs = (id) => {
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
      router.delete(`/admin/member-apps-settings/about-us/${id}`, {
        onSuccess: () => {
          Swal.fire({
            icon: 'success',
            title: 'Deleted!',
            text: 'About Us has been deleted.',
            timer: 2000,
            showConfirmButton: false
          })
        },
        onError: () => {
          Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'Failed to delete About Us.',
            confirmButtonText: 'OK'
          })
        }
      })
    }
  })
}

// Voucher functions
const openVoucherModal = () => {
  voucherForm.value = {
    name: '',
    description: '',
    voucher_type: '',
    discount_percentage: null,
    discount_amount: null,
    min_purchase: null,
    max_discount: null,
    free_item_name: '',
    free_item_ids: [],
    free_item_selection: 'all',
    cashback_amount: null,
    cashback_percentage: null,
    valid_from: '',
    valid_until: '',
    usage_limit: null,
    total_quantity: null,
    applicable_channels: [],
    applicable_days: [],
    applicable_time_start: null,
    applicable_time_end: null,
    exclude_items: [],
    exclude_categories: [],
    image: null,
    imagePreview: null,
    is_active: true,
    is_for_sale: false,
    points_required: null,
    one_time_purchase: false,
    is_birthday_voucher: false,
    all_outlets: true,
    outlet_ids: []
  }
  if (voucherImageInput.value) {
    voucherImageInput.value.value = ''
  }
  showVoucherModal.value = true
}

const closeVoucherModal = () => {
  showVoucherModal.value = false
}

const handleVoucherImageChange = (event) => {
  const file = event.target.files[0]
  if (file) {
    if (file.size > 2 * 1024 * 1024) {
      Swal.fire({
        icon: 'error',
        title: 'File too large',
        text: 'Image size must be less than 2MB',
        confirmButtonText: 'OK'
      })
      event.target.value = ''
      return
    }
    voucherForm.value.image = file
    const reader = new FileReader()
    reader.onload = (e) => {
      voucherForm.value.imagePreview = e.target.result
    }
    reader.readAsDataURL(file)
  }
}

const removeVoucherImage = () => {
  voucherForm.value.image = null
  voucherForm.value.imagePreview = null
  if (voucherImageInput.value) {
    voucherImageInput.value.value = ''
  }
}

const openDistributeVoucherModal = async (voucher) => {
  distributingVoucherData.value = voucher
  distributionForm.value = {
    distribution_type: '',
    member_ids: [],
    filter_criteria: {
      occupation_id: '',
      member_level: '',
      is_active: '',
      is_exclusive_member: '',
      min_spending: null,
      max_spending: null,
      min_points: null,
      max_points: null,
      registered_from: '',
      registered_until: '',
      jenis_kelamin: ''
    }
  }
  memberOptions.value = []
  memberSearchLoading.value = false
  if (memberSearchTimeout) {
    clearTimeout(memberSearchTimeout)
    memberSearchTimeout = null
  }
  memberSearchRequestToken = 0
  
  showDistributeVoucherModal.value = true
}

const closeDistributeVoucherModal = () => {
  showDistributeVoucherModal.value = false
  distributingVoucherData.value = null
  memberOptions.value = []
  memberSearchLoading.value = false
  if (memberSearchTimeout) {
    clearTimeout(memberSearchTimeout)
    memberSearchTimeout = null
  }
}

const onDistributionTypeChange = () => {
  // Reset form when type changes
  if (distributionForm.value.distribution_type !== 'specific') {
    distributionForm.value.member_ids = []
    memberOptions.value = []
    if (memberSearchTimeout) {
      clearTimeout(memberSearchTimeout)
      memberSearchTimeout = null
    }
  }
  if (distributionForm.value.distribution_type !== 'filter') {
    distributionForm.value.filter_criteria = {
      occupation_id: '',
      member_level: '',
      is_active: '',
      is_exclusive_member: '',
      min_spending: null,
      max_spending: null,
      min_points: null,
      max_points: null,
      registered_from: '',
      registered_until: '',
      jenis_kelamin: ''
    }
  }
}

const formatMemberOption = (member) => {
  const identity = member.member_id ? `${member.member_id} • ` : ''
  const phoneOrEmail = member.mobile_phone || member.email || '-'

  return {
    id: member.id,
    name: `${identity}${member.nama_lengkap} (${phoneOrEmail})`
  }
}

const searchMembersForDistribution = async (keyword = '') => {
  const searchKeyword = (keyword || '').trim()

  if (searchKeyword.length < 2) {
    memberOptions.value = [...(distributionForm.value.member_ids || [])]
    memberSearchLoading.value = false
    return
  }

  const requestToken = ++memberSearchRequestToken
  memberSearchLoading.value = true

  try {
    const response = await axios.get('/admin/member-apps-settings/members/search', {
      params: {
        q: searchKeyword,
        limit: 30
      }
    })

    if (requestToken !== memberSearchRequestToken) return

    const fetchedOptions = (response.data?.data || []).map(formatMemberOption)
    const selectedOptions = distributionForm.value.member_ids || []
    const mergedOptions = [...selectedOptions]
    const existingIds = new Set(selectedOptions.map(option => option?.id).filter(Boolean))

    fetchedOptions.forEach(option => {
      if (!existingIds.has(option.id)) {
        mergedOptions.push(option)
      }
    })

    memberOptions.value = mergedOptions
  } catch (error) {
    if (requestToken !== memberSearchRequestToken) return
    console.error('Error searching members for voucher distribution:', error)
  } finally {
    if (requestToken === memberSearchRequestToken) {
      memberSearchLoading.value = false
    }
  }
}

const onMemberSearchChange = (keyword) => {
  if (memberSearchTimeout) {
    clearTimeout(memberSearchTimeout)
  }

  memberSearchTimeout = setTimeout(() => {
    searchMembersForDistribution(keyword)
  }, 350)
}

const saveVoucher = () => {
  if (savingVoucher.value) return
  
  savingVoucher.value = true
  
  // Create FormData for file upload
  const formData = new FormData()
  
  // Add all form fields
  formData.append('name', voucherForm.value.name)
  if (voucherForm.value.description) formData.append('description', voucherForm.value.description)
  formData.append('voucher_type', voucherForm.value.voucher_type)
  formData.append('valid_from', voucherForm.value.valid_from)
  formData.append('valid_until', voucherForm.value.valid_until)
  formData.append('is_active', voucherForm.value.is_active ? '1' : '0')
  formData.append('is_for_sale', voucherForm.value.is_for_sale ? '1' : '0')
  if (voucherForm.value.is_for_sale && voucherForm.value.points_required !== null) {
    formData.append('points_required', voucherForm.value.points_required)
    formData.append('one_time_purchase', voucherForm.value.one_time_purchase ? '1' : '0')
  }
  formData.append('is_birthday_voucher', voucherForm.value.is_birthday_voucher ? '1' : '0')
  
  // Add optional fields
  if (voucherForm.value.discount_percentage !== null) formData.append('discount_percentage', voucherForm.value.discount_percentage)
  if (voucherForm.value.discount_amount !== null) formData.append('discount_amount', voucherForm.value.discount_amount)
  if (voucherForm.value.min_purchase !== null) formData.append('min_purchase', voucherForm.value.min_purchase)
  if (voucherForm.value.max_discount !== null) formData.append('max_discount', voucherForm.value.max_discount)
  // Handle free item - support both old format (free_item_name) and new format (free_item_ids)
  if (voucherForm.value.voucher_type === 'free-item') {
    if (Array.isArray(voucherForm.value.free_item_ids) && voucherForm.value.free_item_ids.length > 0) {
      // New format: multiple items with selection mode
      voucherForm.value.free_item_ids.forEach((item, index) => {
        const itemId = typeof item === 'object' ? item.id : item
        formData.append(`free_item_ids[${index}]`, itemId)
      })
      if (voucherForm.value.free_item_selection) {
        formData.append('free_item_selection', voucherForm.value.free_item_selection)
      }
      // For backward compatibility, also set free_item_name from first item
      const firstItem = voucherForm.value.free_item_ids[0]
      const itemName = typeof firstItem === 'object' ? firstItem.name : items.value.find(i => i.id === firstItem)?.name || ''
      if (itemName) formData.append('free_item_name', itemName)
    } else if (voucherForm.value.free_item_name) {
      // Old format: single item name (backward compatibility)
      formData.append('free_item_name', voucherForm.value.free_item_name)
    }
  }
  if (voucherForm.value.cashback_amount !== null) formData.append('cashback_amount', voucherForm.value.cashback_amount)
  if (voucherForm.value.cashback_percentage !== null) formData.append('cashback_percentage', voucherForm.value.cashback_percentage)
  if (voucherForm.value.usage_limit !== null) formData.append('usage_limit', voucherForm.value.usage_limit)
  if (voucherForm.value.total_quantity !== null) formData.append('total_quantity', voucherForm.value.total_quantity)
  if (voucherForm.value.applicable_time_start) formData.append('applicable_time_start', voucherForm.value.applicable_time_start)
  if (voucherForm.value.applicable_time_end) formData.append('applicable_time_end', voucherForm.value.applicable_time_end)
  
  // Add arrays
  if (voucherForm.value.applicable_days && voucherForm.value.applicable_days.length > 0) {
    voucherForm.value.applicable_days.forEach((day, index) => {
      formData.append(`applicable_days[${index}]`, day)
    })
  }
  
  // Add outlet selection
  formData.append('all_outlets', voucherForm.value.all_outlets ? '1' : '0')
  if (voucherForm.value.outlet_ids && voucherForm.value.outlet_ids.length > 0) {
    voucherForm.value.outlet_ids.forEach((outlet, index) => {
      const outletId = typeof outlet === 'object' ? outlet.id : outlet
      formData.append(`outlet_ids[${index}]`, outletId)
    })
  }
  
  // Add image if exists
  if (voucherForm.value.image) {
    formData.append('image', voucherForm.value.image)
  }

  router.post('/admin/member-apps-settings/voucher', formData, {
    forceFormData: true,
    onSuccess: () => {
      savingVoucher.value = false
      closeVoucherModal()
      Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: 'Voucher created successfully!',
        timer: 2000,
        showConfirmButton: false
      })
    },
    onError: () => {
      savingVoucher.value = false
      Swal.fire({
        icon: 'error',
        title: 'Error!',
        text: 'Failed to create voucher. Please try again.',
        confirmButtonText: 'OK'
      })
    }
  })
}

const distributeVoucher = () => {
  if (distributingVoucher.value || !distributingVoucherData.value) return
  
  distributingVoucher.value = true
  
  const formData = { ...distributionForm.value }
  
  // Convert member_ids from objects to IDs if needed
  if (formData.member_ids && Array.isArray(formData.member_ids)) {
    formData.member_ids = formData.member_ids.map(m => typeof m === 'object' ? m.id : m)
  }
  
  // Clean up filter_criteria - remove empty values
  if (formData.filter_criteria) {
    Object.keys(formData.filter_criteria).forEach(key => {
      if (formData.filter_criteria[key] === null || formData.filter_criteria[key] === '' || 
          (Array.isArray(formData.filter_criteria[key]) && formData.filter_criteria[key].length === 0)) {
        delete formData.filter_criteria[key]
      }
    })
    
    // If filter_criteria is empty, remove it
    if (Object.keys(formData.filter_criteria).length === 0) {
      delete formData.filter_criteria
    }
  }

  router.post(`/admin/member-apps-settings/voucher/${distributingVoucherData.value.id}/distribute`, formData, {
    onSuccess: (page) => {
      distributingVoucher.value = false
      closeDistributeVoucherModal()
      Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: page.props.flash?.success || 'Voucher distributed successfully!',
        timer: 2000,
        showConfirmButton: false
      })
    },
    onError: (errors) => {
      distributingVoucher.value = false
      Swal.fire({
        icon: 'error',
        title: 'Error!',
        text: errors.message || 'Failed to distribute voucher. Please try again.',
        confirmButtonText: 'OK'
      })
    }
  })
}

const deleteVoucher = (id) => {
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
      router.delete(`/admin/member-apps-settings/voucher/${id}`, {
        onSuccess: () => {
          Swal.fire({
            icon: 'success',
            title: 'Deleted!',
            text: 'Voucher has been deleted.',
            timer: 2000,
            showConfirmButton: false
          })
        },
        onError: () => {
          Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'Failed to delete voucher.',
            confirmButtonText: 'OK'
          })
        }
      })
    }
  })
}

const formatVoucherType = (type) => {
  const types = {
    'discount-percentage': 'Discount Percentage',
    'discount-fixed': 'Discount Fixed',
    'free-item': 'Free Item',
    'cashback': 'Cashback'
  }
  return types[type] || type
}

const formatDays = (days) => {
  if (!days || !Array.isArray(days) || days.length === 0) return 'All Days'
  
  const dayMap = {
    'monday': 'Senin',
    'tuesday': 'Selasa',
    'wednesday': 'Rabu',
    'thursday': 'Kamis',
    'friday': 'Jumat',
    'saturday': 'Sabtu',
    'sunday': 'Minggu'
  }
  
  return days.map(day => dayMap[day] || day).join(', ')
}

const formatTime = (time) => {
  if (!time) return ''
  // Format time from HH:mm:ss to HH:mm
  return time.substring(0, 5)
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

const openVoucherMembersModal = async (voucher) => {
  viewingVoucherData.value = voucher
  voucherMembersSearch.value = ''
  voucherMembers.value = []
  voucherMembersTotal.value = 0
  showVoucherMembersModal.value = true
  await loadVoucherMembers()
}

const closeVoucherMembersModal = () => {
  showVoucherMembersModal.value = false
  viewingVoucherData.value = null
  voucherMembers.value = []
  voucherMembersTotal.value = 0
  voucherMembersSearch.value = ''
  if (voucherMembersSearchTimeout) {
    clearTimeout(voucherMembersSearchTimeout)
    voucherMembersSearchTimeout = null
  }
}

const loadVoucherMembers = async () => {
  if (!viewingVoucherData.value) return
  
  loadingVoucherMembers.value = true
  try {
    const response = await axios.get(`/admin/member-apps-settings/voucher/${viewingVoucherData.value.id}/members`, {
      params: {
        search: voucherMembersSearch.value
      }
    })
    
    if (response.data.success) {
      voucherMembers.value = response.data.data
      voucherMembersTotal.value = response.data.total
    }
  } catch (error) {
    console.error('Error loading voucher members:', error)
    Swal.fire({
      icon: 'error',
      title: 'Error!',
      text: 'Failed to load voucher members. Please try again.',
      confirmButtonText: 'OK'
    })
  } finally {
    loadingVoucherMembers.value = false
  }
}

const searchVoucherMembers = () => {
  if (voucherMembersSearchTimeout) {
    clearTimeout(voucherMembersSearchTimeout)
  }
  
  voucherMembersSearchTimeout = setTimeout(() => {
    loadVoucherMembers()
  }, 500) // Debounce 500ms
}

// Save new category
const saveCategory = async () => {
  if (addingCategory.value || !newCategoryName.value.trim()) return
  
  addingCategory.value = true
  
  try {
    const response = await fetch('/admin/member-apps-settings/whats-on-category', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
      },
      body: JSON.stringify({
        name: newCategoryName.value.trim(),
        description: newCategoryDescription.value.trim(),
        is_active: true
      })
    })
    
    const data = await response.json()
    
    if (data.success) {
      Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: data.message,
        timer: 2000,
        showConfirmButton: false
      })
      
      // Reset form
      newCategoryName.value = ''
      newCategoryDescription.value = ''
      showAddCategoryModal.value = false
      
      // Reload page to get updated categories
      router.reload({ only: ['whatsOnCategories'] })
    } else {
      Swal.fire({
        icon: 'error',
        title: 'Error!',
        text: data.message || 'Failed to add category',
        confirmButtonText: 'OK'
      })
    }
  } catch (error) {
    console.error('Error saving category:', error)
    Swal.fire({
      icon: 'error',
      title: 'Error!',
      text: 'Failed to add category. Please try again.',
      confirmButtonText: 'OK'
    })
  } finally {
    addingCategory.value = false
  }
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


// Feedback management
const feedbackStatusFilter = ref('')
const feedbackSearch = ref('')
const feedbackPerPage = ref(10)
const showReplyModal = ref(false)
const selectedFeedback = ref(null)
const replyMessage = ref('')
const refreshingFeedbacks = ref(false)
const expandedReplies = ref(new Set())
let feedbackSearchTimeout = null

const filteredFeedbacks = computed(() => {
  // Filtering is now done on the server side
  if (!props.feedbacks || !props.feedbacks.data) return []
  return props.feedbacks.data
})

const toggleFeedbackReplies = (feedbackId) => {
  if (expandedReplies.value.has(feedbackId)) {
    expandedReplies.value.delete(feedbackId)
  } else {
    expandedReplies.value.add(feedbackId)
  }
}

const getStatusClass = (status) => {
  const classes = {
    pending: 'bg-yellow-100 text-yellow-800',
    read: 'bg-blue-100 text-blue-800',
    replied: 'bg-purple-100 text-purple-800',
    resolved: 'bg-green-100 text-green-800'
  }
  return classes[status] || 'bg-gray-100 text-gray-800'
}

const formatFeedbackDate = (dateString) => {
  if (!dateString) return 'N/A'
  const date = new Date(dateString)
  return date.toLocaleString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

const openReplyModal = (feedback) => {
  selectedFeedback.value = feedback
  replyMessage.value = ''
  showReplyModal.value = true
}

const closeReplyModal = () => {
  showReplyModal.value = false
  selectedFeedback.value = null
  replyMessage.value = ''
}

const submitReply = async () => {
  if (!replyMessage.value.trim()) {
    Swal.fire({
      icon: 'warning',
      title: 'Warning!',
      text: 'Please enter a reply message',
      confirmButtonText: 'OK'
    })
    return
  }

  try {
    const response = await axios.post(`/admin/member-apps-settings/feedback/${selectedFeedback.value.id}/reply`, {
      admin_reply: replyMessage.value.trim()
    })

    const data = response.data
    if (data.success) {
      Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: 'Reply sent successfully',
        timer: 2000,
        showConfirmButton: false
      })
      closeReplyModal()
      router.reload({ only: ['feedbacks'] })
    } else {
      Swal.fire({
        icon: 'error',
        title: 'Error!',
        text: data.message || 'Failed to send reply',
        confirmButtonText: 'OK'
      })
    }
  } catch (error) {
    console.error('Error submitting reply:', error)
    let errorMessage = 'Failed to send reply. Please try again.'
    
    if (error.response) {
      // Server responded with error
      const errorData = error.response.data
      if (errorData.errors) {
        // Validation errors
        const firstError = Object.values(errorData.errors)[0]
        errorMessage = Array.isArray(firstError) ? firstError[0] : firstError
      } else if (errorData.message) {
        errorMessage = errorData.message
      } else if (errorData.error) {
        errorMessage = errorData.error
      }
    } else if (error.message) {
      errorMessage = error.message
    }
    
    Swal.fire({
      icon: 'error',
      title: 'Error!',
      text: errorMessage,
      confirmButtonText: 'OK'
    })
  }
}

const updateFeedbackStatus = async (feedbackId, status) => {
  try {
    const response = await axios.put(`/admin/member-apps-settings/feedback/${feedbackId}/status`, {
      status: status
    })

    const data = response.data
    if (data.success) {
      Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: 'Status updated successfully',
        timer: 2000,
        showConfirmButton: false
      })
      router.reload({ only: ['feedbacks'] })
    } else {
      Swal.fire({
        icon: 'error',
        title: 'Error!',
        text: data.message || 'Failed to update status',
        confirmButtonText: 'OK'
      })
    }
  } catch (error) {
    console.error('Error updating status:', error)
    Swal.fire({
      icon: 'error',
      title: 'Error!',
      text: 'Failed to update status. Please try again.',
      confirmButtonText: 'OK'
    })
  }
}

const loadFeedbacksPage = async (url) => {
  if (!url) return
  try {
    // Extract query parameters from URL
    const urlObj = new URL(url)
    const status = urlObj.searchParams.get('status') || feedbackStatusFilter.value
    const search = urlObj.searchParams.get('search') || feedbackSearch.value
    const perPage = urlObj.searchParams.get('per_page') || feedbackPerPage.value
    
    // Update filters
    feedbackStatusFilter.value = status
    feedbackSearch.value = search
    feedbackPerPage.value = perPage
    
    // Build new URL with current filters
    const params = new URLSearchParams()
    if (status) params.append('status', status)
    if (search) params.append('search', search)
    if (perPage) params.append('per_page', perPage)
    
    // Extract page number from original URL
    const page = urlObj.searchParams.get('page')
    if (page) params.append('page', page)
    
    const queryString = params.toString()
    const newUrl = queryString ? `/admin/member-apps-settings?${queryString}` : '/admin/member-apps-settings'
    
    await router.visit(newUrl, {
      only: ['feedbacks'],
      preserveState: true,
      preserveScroll: true
    })
  } catch (error) {
    console.error('Error loading feedback page:', error)
  }
}

const searchFeedbacks = () => {
  if (feedbackSearchTimeout) {
    clearTimeout(feedbackSearchTimeout)
  }
  
  feedbackSearchTimeout = setTimeout(() => {
    loadFeedbacksWithFilters()
  }, 500) // Debounce 500ms
}

const loadFeedbacksWithFilters = async () => {
  try {
    const params = new URLSearchParams()
    if (feedbackStatusFilter.value) {
      params.append('status', feedbackStatusFilter.value)
    }
    if (feedbackSearch.value) {
      params.append('search', feedbackSearch.value)
    }
    if (feedbackPerPage.value) {
      params.append('per_page', feedbackPerPage.value)
    }
    
    const queryString = params.toString()
    const url = queryString ? `/admin/member-apps-settings?${queryString}` : '/admin/member-apps-settings'
    
    await router.visit(url, {
      only: ['feedbacks'],
      preserveState: true,
      preserveScroll: true
    })
  } catch (error) {
    console.error('Error loading feedbacks:', error)
  }
}

const filterFeedbacks = async () => {
  // Reload feedbacks with filters
  await loadFeedbacksWithFilters()
}

// Initialize filters from URL query parameters
onMounted(() => {
  const urlParams = new URLSearchParams(window.location.search)
  const status = urlParams.get('status')
  const search = urlParams.get('search')
  const perPage = urlParams.get('per_page')
  
  if (status) feedbackStatusFilter.value = status
  if (search) feedbackSearch.value = search
  if (perPage) feedbackPerPage.value = parseInt(perPage) || 10
})

const refreshFeedbacks = async () => {
  if (refreshingFeedbacks.value) return
  
  refreshingFeedbacks.value = true
  try {
    // Reload feedbacks data from server
    await router.reload({ only: ['feedbacks'] })
    
    Swal.fire({
      icon: 'success',
      title: 'Success!',
      text: 'Feedback data refreshed',
      timer: 1500,
      showConfirmButton: false
    })
  } catch (error) {
    console.error('Error refreshing feedbacks:', error)
    Swal.fire({
      icon: 'error',
      title: 'Error!',
      text: 'Failed to refresh feedback data',
      confirmButtonText: 'OK'
    })
  } finally {
    refreshingFeedbacks.value = false
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
