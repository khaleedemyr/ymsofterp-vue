<template>
  <AppLayout>
    <div class="w-full py-8 px-0">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-plus-circle"></i> Buat Non Food Payment
        </h1>
        <button @click="goBack" class="bg-gray-500 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
          <i class="fa fa-arrow-left mr-1"></i> Kembali
        </button>
      </div>

      <!-- Step 1: Pilih Purchase Order -->
      <div v-if="!selectedPO" class="space-y-6">
        <!-- Available Purchase Orders -->
        <div v-if="mappedPOs.length > 0" class="bg-white rounded-2xl shadow-2xl p-6">
          <h2 class="text-xl font-bold text-gray-800 mb-4">Pilih Purchase Order untuk Dibayar</h2>
          <div class="space-y-4">
            <div v-for="po in mappedPOs" :key="po.id" 
                 class="border rounded-lg p-4 transition" 
                 :class="po.is_held ? 'border-red-300 bg-red-50 opacity-60 cursor-not-allowed' : 'border-gray-200 hover:bg-gray-50 cursor-pointer'"
                 @click="po.is_held ? null : selectPO(po)">
              <div class="flex items-center justify-between">
                <div class="flex-1">
                  <div class="flex items-center gap-2 mb-1">
                    <div class="font-semibold text-gray-900">{{ po.number }}</div>
                    <span v-if="po.is_held" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                      <i class="fas fa-lock mr-1"></i>
                      ON HOLD
                    </span>
                  </div>
                  <div class="text-sm text-gray-600">{{ po.supplier_name }}</div>
                  <div class="text-sm text-gray-500">
                    {{ formatDate(po.date) }} - {{ formatCurrency(po.grand_total) }}
                  </div>
                  <div v-if="po.pr_outlet_name" class="text-xs text-gray-600 mt-1">
                    <i class="fa fa-store mr-1"></i>Outlet: {{ po.pr_outlet_name }}
                  </div>
                  <div v-if="po.source_pr_number" class="text-xs text-blue-600 mt-1">
                    <i class="fa fa-link mr-1"></i>Source: {{ po.source_pr_number }}
                  </div>
                  <p v-if="po.is_held && po.hold_reason" class="text-sm text-red-600 mt-1">
                    <i class="fas fa-info-circle mr-1"></i>
                    {{ po.hold_reason }}
                  </p>
                </div>
                <div class="text-right">
                  <button type="button" 
                          :class="po.is_held ? 'bg-gray-400 text-white px-4 py-2 rounded-lg cursor-not-allowed' : 'bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition'"
                          :disabled="po.is_held">
                    <i class="fa fa-arrow-right mr-1"></i> Pilih
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Available Purchase Requisitions -->
        <div v-if="mappedPRs.length > 0" class="bg-white rounded-2xl shadow-2xl p-6">
          <h2 class="text-xl font-bold text-gray-800 mb-4">Available Purchase Requisitions</h2>
          <div class="space-y-4">
            <div v-for="pr in mappedPRs" :key="pr.id" 
                 class="border rounded-lg p-4 transition" 
                 :class="pr.is_held ? 'border-red-300 bg-red-50 opacity-60 cursor-not-allowed' : 'border-gray-200 hover:bg-gray-50 cursor-pointer'"
                 @click="pr.is_held ? null : selectPR(pr)">
              <div class="flex items-center justify-between">
                <div class="flex-1">
                  <div class="flex items-center gap-2 mb-1">
                    <div class="font-semibold text-gray-900">{{ pr.pr_number }}</div>
                    <span v-if="pr.is_held" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                      <i class="fas fa-lock mr-1"></i>
                      ON HOLD
                    </span>
                  </div>
                  <div class="text-sm text-gray-600">{{ pr.title }}</div>
                  <div class="text-sm text-gray-500">
                    {{ formatDate(pr.date) }} - {{ formatCurrency(pr.amount) }}
                  </div>
                  <p v-if="pr.is_held && pr.hold_reason" class="text-sm text-red-600 mt-1">
                    <i class="fas fa-info-circle mr-1"></i>
                    {{ pr.hold_reason }}
                  </p>
                </div>
                <div class="text-right">
                  <button type="button" 
                          :class="pr.is_held ? 'bg-gray-400 text-white px-4 py-2 rounded-lg cursor-not-allowed' : 'bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition'"
                          :disabled="pr.is_held">
                    <i class="fa fa-arrow-right mr-1"></i> Pilih
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div v-if="mappedPOs.length === 0 && mappedPRs.length === 0" class="bg-white rounded-2xl shadow-2xl p-6 text-center">
          <div class="text-gray-500">
            <i class="fa fa-inbox text-4xl mb-4"></i>
            <p>Tidak ada Purchase Order atau Purchase Requisition yang tersedia untuk dibayar.</p>
          </div>
        </div>
      </div>

      <!-- Step 2: Form Payment dengan Detail PO/PR -->
      <form v-if="selectedPO || selectedPR" @submit.prevent="submitForm" class="space-y-6">
        <!-- PO/PR Information -->
        <div class="bg-white rounded-2xl shadow-2xl p-6">
          <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold text-gray-800">{{ selectedPO ? 'Detail Purchase Order' : 'Detail Purchase Requisition' }}</h2>
            <button type="button" @click="resetSelection" class="text-gray-500 hover:text-gray-700">
              <i class="fa fa-times"></i>
            </button>
          </div>
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div>
              <label class="block text-sm font-medium text-gray-700">{{ selectedPO ? 'PO Number' : 'PR Number' }}</label>
              <p class="mt-1 text-lg font-semibold text-gray-900">{{ selectedPO ? selectedPO.number : selectedPR.pr_number }}</p>
            </div>
            <div v-if="selectedPO">
              <label class="block text-sm font-medium text-gray-700">Supplier</label>
              <p class="mt-1 text-gray-900">{{ selectedPO.supplier_name }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">{{ selectedPO ? 'PO Date' : 'PR Date' }}</label>
              <p class="mt-1 text-gray-900">{{ formatDate(selectedPO ? selectedPO.date : selectedPR.date) }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Total Amount</label>
              <p class="mt-1 text-lg font-bold text-green-600">{{ formatCurrency(selectedPO ? selectedPO.grand_total : selectedPR.amount) }}</p>
            </div>
            <div v-if="selectedPO && selectedPO.source_pr_number">
              <label class="block text-sm font-medium text-gray-700">Source PR</label>
              <p class="mt-1 text-gray-900">{{ selectedPO.source_pr_number }}</p>
            </div>
            <div v-if="selectedPR && selectedPR.division_name">
              <label class="block text-sm font-medium text-gray-700">Division</label>
              <p class="mt-1 text-gray-900">{{ selectedPR.division_name }}</p>
            </div>
          </div>

          <!-- PR Information Section (Title & Description) -->
          <div v-if="(selectedPO && (selectedPO.pr_title || selectedPO.pr_description)) || (selectedPR && (selectedPR.title || selectedPR.description))" class="bg-white rounded-2xl shadow-2xl p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">
              <i class="fa fa-shopping-cart mr-2 text-green-500"></i>
              Informasi Purchase Requisition
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div v-if="(selectedPO && selectedPO.pr_title) || (selectedPR && selectedPR.title)" class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                <p class="text-gray-900 font-medium">{{ selectedPO ? selectedPO.pr_title : selectedPR.title }}</p>
              </div>
              <div v-if="(selectedPO && selectedPO.pr_description) || (selectedPR && selectedPR.description)" class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                <p class="text-gray-900 whitespace-pre-wrap">{{ selectedPO ? selectedPO.pr_description : selectedPR.description }}</p>
              </div>
            </div>
          </div>

          <!-- Attachments Section -->
          <div v-if="(poAttachments && poAttachments.length > 0) || (prAttachments && prAttachments.length > 0)" class="bg-white rounded-2xl shadow-2xl p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Attachments</h3>
            
            <!-- PO Attachments -->
            <div v-if="selectedPO && poAttachments && poAttachments.length > 0" class="mb-6">
              <h4 class="text-md font-medium text-gray-700 mb-3">Purchase Order Attachments</h4>
              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div v-for="attachment in poAttachments" :key="`po-${attachment.id}`" class="border border-gray-200 rounded-lg p-3">
                  <!-- Image Thumbnail -->
                  <div v-if="isImageFile(attachment.file_name)" class="relative group cursor-pointer" @click="openLightbox(`/po-ops/attachments/${attachment.id}/view`, attachment.file_name)">
                    <div class="aspect-square bg-gray-100 border border-gray-200 rounded-lg overflow-hidden hover:shadow-lg transition-all duration-200">
                      <img
                        :src="`/po-ops/attachments/${attachment.id}/view`"
                        :alt="attachment.file_name"
                        class="w-full h-full object-cover hover:scale-105 transition-transform duration-200"
                        @click.stop="openLightbox(`/po-ops/attachments/${attachment.id}/view`, attachment.file_name)"
                      />
                    </div>
                    <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-30 transition-all duration-200 flex items-center justify-center">
                      <div class="opacity-0 group-hover:opacity-100 bg-white bg-opacity-90 text-gray-800 px-3 py-1 rounded-full text-sm transition-all duration-200 flex items-center gap-2">
                        <i class="fas fa-search-plus"></i>
                        <span>View</span>
                      </div>
                    </div>
                    <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black to-transparent text-white p-2">
                      <p class="text-xs truncate font-medium">{{ attachment.file_name }}</p>
                    </div>
                  </div>
                  
                  <!-- Non-Image Files -->
                  <div v-else class="flex items-center gap-3">
                    <div class="flex-shrink-0">
                      <i class="fa fa-file text-gray-500 text-xl"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                      <p class="text-sm font-medium text-gray-900 truncate">{{ attachment.file_name }}</p>
                      <p class="text-xs text-gray-500">{{ formatFileSize(attachment.file_size) }}</p>
                    </div>
                    <div class="flex-shrink-0">
                      <a 
                        :href="`/po-ops/attachments/${attachment.id}/download`" 
                        target="_blank" 
                        class="text-blue-600 hover:text-blue-800 text-sm"
                      >
                        <i class="fa fa-download"></i>
                      </a>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- PR Attachments -->
            <div v-if="selectedPR && prAttachments && prAttachments.length > 0" class="mb-6">
              <h4 class="text-md font-medium text-gray-700 mb-3">Purchase Requisition Attachments</h4>
              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div v-for="attachment in prAttachments" :key="`pr-${attachment.id}`" class="border border-gray-200 rounded-lg p-3">
                  <!-- Image Thumbnail -->
                  <div v-if="isImageFile(attachment.file_name)" class="relative group cursor-pointer" @click="openLightbox(`/purchase-requisitions/attachments/${attachment.id}/view`, attachment.file_name)">
                    <div class="aspect-square bg-gray-100 border border-gray-200 rounded-lg overflow-hidden hover:shadow-lg transition-all duration-200">
                      <img
                        :src="`/purchase-requisitions/attachments/${attachment.id}/view`"
                        :alt="attachment.file_name"
                        class="w-full h-full object-cover hover:scale-105 transition-transform duration-200"
                        @click.stop="openLightbox(`/purchase-requisitions/attachments/${attachment.id}/view`, attachment.file_name)"
                      />
                    </div>
                    <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-30 transition-all duration-200 flex items-center justify-center">
                      <div class="opacity-0 group-hover:opacity-100 bg-white bg-opacity-90 text-gray-800 px-3 py-1 rounded-full text-sm transition-all duration-200 flex items-center gap-2">
                        <i class="fas fa-search-plus"></i>
                        <span>View</span>
                      </div>
                    </div>
                    <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black to-transparent text-white p-2">
                      <p class="text-xs truncate font-medium">{{ attachment.file_name }}</p>
                    </div>
                  </div>
                  
                  <!-- Non-Image Files -->
                  <div v-else class="flex items-center gap-3">
                    <div class="flex-shrink-0">
                      <i class="fa fa-file text-gray-500 text-xl"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                      <p class="text-sm font-medium text-gray-900 truncate">{{ attachment.file_name }}</p>
                      <p class="text-xs text-gray-500">{{ formatFileSize(attachment.file_size) }}</p>
                    </div>
                    <div class="flex-shrink-0">
                      <a 
                        :href="`/purchase-requisitions/attachments/${attachment.id}/download`" 
                        target="_blank" 
                        class="text-blue-600 hover:text-blue-800 text-sm"
                      >
                        <i class="fa fa-download"></i>
                      </a>
                    </div>
                  </div>
                </div>
              </div>
            </div>

          </div>

          <!-- PO/PR Items Grouped by Outlet -->
          <div v-if="itemsByOutlet && Object.keys(itemsByOutlet).length > 0">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Items {{ selectedPO ? 'per Outlet' : (selectedPR ? 'per Outlet & Category' : '') }}</h3>
            
            <div v-for="(outletData, outletKey) in itemsByOutlet" :key="outletKey" class="mb-6">
              <!-- Outlet Header -->
              <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg p-4 mb-3">
                <div class="flex justify-between items-start">
                  <div>
                    <h4 class="text-lg font-semibold text-blue-800">{{ outletData.outlet_name || 'Global / All Outlets' }}</h4>
                    <div class="text-sm text-blue-600 mt-1">
                      <span v-if="outletData.category_name" class="inline-block bg-blue-100 text-blue-800 px-2 py-1 rounded-full text-xs mr-2">
                        Category: {{ outletData.category_name }}
                      </span>
                      <span v-if="outletData.category_division" class="inline-block bg-indigo-100 text-indigo-800 px-2 py-1 rounded-full text-xs mr-2">
                        {{ outletData.category_division }}
                      </span>
                      <span v-if="outletData.category_subcategory" class="inline-block bg-purple-100 text-purple-800 px-2 py-1 rounded-full text-xs mr-2">
                        {{ outletData.category_subcategory }}
                      </span>
                      <span v-if="outletData.category_budget_type" class="inline-block bg-green-100 text-green-800 px-2 py-1 rounded-full text-xs">
                        {{ outletData.category_budget_type === 'GLOBAL' ? 'Global Budget' : 'Per Outlet Budget' }}
                      </span>
                    </div>
                    <div class="text-xs text-gray-600 mt-1">
                      <span v-if="outletData.pr_number">PR: {{ outletData.pr_number }}</span>
                    </div>
                  </div>
                  <div class="text-right">
                    <div class="text-lg font-bold text-green-600">{{ formatCurrency(outletData.subtotal) }}</div>
                    <div class="text-xs text-gray-500">Subtotal</div>
                  </div>
                </div>
              </div>

              <!-- PR Title & Description for this outlet (separate from attachments) -->
              <div v-if="outletData.pr_title || outletData.pr_description" class="mt-4 bg-green-50 rounded-lg p-4 mb-4">
                <h5 class="text-sm font-semibold text-green-800 mb-3">
                  <i class="fa fa-shopping-cart mr-2"></i>
                  Informasi Purchase Requisition
                </h5>
                <div class="space-y-2">
                  <div v-if="outletData.pr_title">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Title</label>
                    <p class="text-sm text-gray-900 font-medium">{{ outletData.pr_title }}</p>
                  </div>
                  <div v-if="outletData.pr_description">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Description</label>
                    <p class="text-sm text-gray-900 whitespace-pre-wrap">{{ outletData.pr_description }}</p>
                  </div>
                </div>
              </div>

              <!-- Items Table -->
              <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                  <thead class="bg-gray-50">
                    <tr>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                    </tr>
                  </thead>
                  <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="item in outletData.items" :key="item.id">
                      <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ item.item_name }}</td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ item.quantity }}</td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ item.unit }}</td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ formatCurrency(item.price) }}</td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">{{ formatCurrency(item.total) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
              
              <!-- PR Attachments for this outlet -->
              <div v-if="outletData.pr_attachments && outletData.pr_attachments.length > 0" class="mt-4 bg-green-50 rounded-lg p-4">
                <h5 class="text-sm font-semibold text-green-800 mb-3">PR Attachments</h5>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                  <div v-for="attachment in outletData.pr_attachments" :key="`pr-${attachment.id}`" class="border border-green-200 rounded-lg p-3 bg-white">
                    <!-- Image Thumbnail -->
                    <div v-if="isImageFile(attachment.file_name)" class="relative group cursor-pointer" @click="openLightbox(`/purchase-requisitions/attachments/${attachment.id}/view`, attachment.file_name)">
                      <div class="aspect-square bg-gray-100 border border-gray-200 rounded-lg overflow-hidden hover:shadow-lg transition-all duration-200">
                        <img
                          :src="`/purchase-requisitions/attachments/${attachment.id}/view`"
                          :alt="attachment.file_name"
                          class="w-full h-full object-cover hover:scale-105 transition-transform duration-200"
                          @click.stop="openLightbox(`/purchase-requisitions/attachments/${attachment.id}/view`, attachment.file_name)"
                        />
                      </div>
                      <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-30 transition-all duration-200 flex items-center justify-center">
                        <div class="opacity-0 group-hover:opacity-100 bg-white bg-opacity-90 text-gray-800 px-3 py-1 rounded-full text-sm transition-all duration-200 flex items-center gap-2">
                          <i class="fas fa-search-plus"></i>
                          <span>View</span>
                        </div>
                      </div>
                      <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black to-transparent text-white p-2">
                        <p class="text-xs truncate font-medium">{{ attachment.file_name }}</p>
                      </div>
                    </div>
                    
                    <!-- Non-Image Files -->
                    <div v-else class="flex items-center gap-3">
                      <div class="flex-shrink-0">
                        <i class="fa fa-file text-gray-500 text-xl"></i>
                      </div>
                      <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900 truncate">{{ attachment.file_name }}</p>
                        <p class="text-xs text-gray-500">{{ formatFileSize(attachment.file_size) }}</p>
                      </div>
                      <div class="flex-shrink-0">
                        <a 
                          :href="`/purchase-requisitions/attachments/${attachment.id}/download`" 
                          target="_blank" 
                          class="text-green-600 hover:text-green-800 text-sm"
                        >
                          <i class="fa fa-download"></i>
                        </a>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Payment Information -->
        <div class="bg-white rounded-2xl shadow-2xl p-6">
          <h2 class="text-xl font-bold text-gray-800 mb-4">Informasi Payment</h2>
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Supplier -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Supplier *</label>
              <select 
                v-model="form.supplier_id" 
                required 
                :disabled="selectedPO"
                class="w-full px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition disabled:bg-gray-100"
              >
                <option value="">Pilih Supplier</option>
                <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">
                  {{ supplier.name }}
                </option>
              </select>
              <p v-if="selectedPO" class="mt-1 text-xs text-gray-500">
                Supplier diambil dari Purchase Order
              </p>
              <p v-if="selectedPR" class="mt-1 text-xs text-gray-500">
                Pilih supplier untuk payment ini
              </p>
            </div>

            <!-- Amount (Auto-filled from PO/PR, but editable) -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Amount * 
                <span class="text-xs font-normal text-gray-500">(Dapat diubah)</span>
              </label>
              <div class="relative">
                <input 
                  type="number" 
                  v-model="form.amount" 
                  step="0.01"
                  min="0"
                  required 
                  class="w-full px-4 py-2 pr-24 rounded-xl border border-gray-300 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
                  placeholder="0.00"
                />
                <button
                  v-if="selectedPO || selectedPR"
                  type="button"
                  @click="resetAmountToOriginal"
                  class="absolute right-2 top-1/2 -translate-y-1/2 px-3 py-1 text-xs bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 transition"
                  title="Reset ke nilai PO/PR"
                >
                  <i class="fa fa-undo mr-1"></i>
                  Reset
                </button>
              </div>
              <p class="mt-1 text-xs text-gray-500">
                <i class="fa fa-info-circle mr-1"></i>
                Nilai otomatis diisi dari {{ selectedPO ? 'PO' : 'PR' }}, namun dapat diubah sesuai kebutuhan
              </p>
              <p v-if="selectedPO || selectedPR" class="mt-1 text-xs text-gray-600">
                Nilai {{ selectedPO ? 'PO' : 'PR' }}: <strong>{{ formatCurrency(originalAmount || (selectedPO ? selectedPO.grand_total : selectedPR.amount)) }}</strong>
              </p>
            </div>

            <!-- Payment Method -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Payment Method *</label>
              <select v-model="form.payment_method" required class="w-full px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition">
                <option value="">Pilih Payment Method</option>
                <option value="cash">Cash</option>
                <option value="transfer">Transfer</option>
                <option value="check">Check</option>
              </select>
            </div>

            <!-- Payment Date -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Payment Date *</label>
              <input 
                type="date" 
                v-model="form.payment_date" 
                required 
                class="w-full px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
              />
            </div>

            <!-- Due Date -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Due Date</label>
              <input 
                type="date" 
                v-model="form.due_date" 
                class="w-full px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
              />
            </div>

            <!-- Reference Number -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Reference Number</label>
              <input 
                type="text" 
                v-model="form.reference_number" 
                class="w-full px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
                placeholder="Nomor referensi"
              />
            </div>
          </div>

          <!-- Description -->
          <div class="mt-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
            <textarea 
              v-model="form.description" 
              rows="3"
              class="w-full px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
              placeholder="Deskripsi payment"
            ></textarea>
          </div>

          <!-- Notes -->
          <div class="mt-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
            <textarea 
              v-model="form.notes" 
              rows="3"
              class="w-full px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
              placeholder="Catatan tambahan"
            ></textarea>
          </div>
        </div>

        <!-- Submit Button -->
        <div class="flex justify-end gap-4">
          <button type="button" @click="resetSelection" class="bg-gray-500 text-white px-6 py-3 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
            Kembali ke Pilih PO
          </button>
          <button type="submit" :disabled="isSubmitting" class="bg-gradient-to-r from-green-500 to-green-700 text-white px-6 py-3 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold disabled:opacity-50">
            <i v-if="isSubmitting" class="fa fa-spinner fa-spin mr-2"></i>
            {{ isSubmitting ? 'Menyimpan...' : 'Simpan Payment' }}
          </button>
        </div>
      </form>
    </div>

    <!-- Lightbox Modal -->
    <div v-if="lightboxVisible" class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50" @click="closeLightbox">
      <div class="relative max-w-4xl max-h-full p-4" @click.stop>
        <button @click="closeLightbox" class="absolute top-2 right-2 text-white text-2xl hover:text-gray-300 z-10">
          <i class="fa fa-times"></i>
        </button>
        <img 
          :src="lightboxImage?.path" 
          :alt="lightboxImage?.name"
          class="max-w-full max-h-full object-contain rounded-lg"
        />
        <div class="text-center text-white mt-2">
          <p class="text-sm">{{ lightboxImage?.name }}</p>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  suppliers: Array,
  availablePOs: Array,
  availablePRs: Array,
  filters: Object
});

// Map PO data to ensure is_held is boolean
const mappedPOs = computed(() => {
  return (props.availablePOs || []).map(po => ({
    ...po,
    is_held: po.is_held === true || po.is_held === 1 || po.is_held === '1' || po.is_held === 'true',
    hold_reason: po.hold_reason || null
  }));
});

// Map PR data to ensure is_held is boolean
const mappedPRs = computed(() => {
  return (props.availablePRs || []).map(pr => ({
    ...pr,
    is_held: pr.is_held === true || pr.is_held === 1 || pr.is_held === '1' || pr.is_held === 'true',
    hold_reason: pr.hold_reason || null
  }));
});

const isSubmitting = ref(false);
const selectedPO = ref(null);
const selectedPR = ref(null);
const poItems = ref([]);
const itemsByOutlet = ref({});
const loadingPOItems = ref(false);
const poAttachments = ref([]);
const prAttachments = ref([]);
const lightboxImage = ref(null);
const lightboxVisible = ref(false);
const originalAmount = ref(null);

const form = reactive({
  purchase_order_ops_id: null,
  purchase_requisition_id: null,
  supplier_id: '',
  amount: '',
  payment_method: '',
  payment_date: new Date().toISOString().split('T')[0],
  due_date: '',
  description: '',
  reference_number: '',
  notes: ''
});

function formatDate(date) {
  if (!date) return '-';
  const d = new Date(date);
  return d.toLocaleDateString('id-ID', { year: 'numeric', month: 'short', day: 'numeric' });
}

function formatCurrency(value) {
  if (value == null) return '-';
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(value);
}

function formatFileSize(bytes) {
  if (!bytes) return '0 B';
  const k = 1024;
  const sizes = ['B', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function isImageFile(filename) {
  if (!filename) return false;
  const imageExtensions = ['.jpg', '.jpeg', '.png', '.gif', '.bmp', '.webp', '.svg'];
  const ext = filename.toLowerCase().substring(filename.lastIndexOf('.'));
  return imageExtensions.includes(ext);
}

function openLightbox(imagePath, imageName) {
  lightboxImage.value = {
    path: imagePath,
    name: imageName
  };
  lightboxVisible.value = true;
}

function closeLightbox() {
  lightboxVisible.value = false;
  lightboxImage.value = null;
}

function resetSelection() {
  selectedPO.value = null;
  selectedPR.value = null;
  poItems.value = [];
  itemsByOutlet.value = {};
  poAttachments.value = [];
  prAttachments.value = [];
  form.purchase_order_ops_id = null;
  form.purchase_requisition_id = null;
  form.supplier_id = '';
  form.amount = '';
  originalAmount.value = null;
}

function resetAmountToOriginal() {
  if (originalAmount.value !== null) {
    form.amount = originalAmount.value;
  } else if (selectedPO.value) {
    form.amount = selectedPO.value.grand_total;
  } else if (selectedPR.value) {
    form.amount = selectedPR.value.amount;
  }
}

async function selectPO(po) {
  // Prevent selection if PO's source PR is on hold
  const isHeld = po.is_held === true || po.is_held === 1 || po.is_held === '1' || po.is_held === 'true';
  if (isHeld) {
    import('sweetalert2').then(({ default: Swal }) => {
      Swal.fire('Warning', 'Purchase Order tidak dapat dipilih karena PR source-nya sedang di-hold. Silakan release PR terlebih dahulu.', 'warning');
    });
    return;
  }
  
  selectedPO.value = po;
  selectedPR.value = null;
  form.purchase_order_ops_id = po.id;
  form.purchase_requisition_id = null;
  form.supplier_id = po.supplier_id;
  form.amount = po.grand_total;
  originalAmount.value = po.grand_total;
  
  // Load PO items grouped by outlet
  loadingPOItems.value = true;
  try {
    const response = await axios.get(`/non-food-payments/po-items/${po.id}`);
    poItems.value = response.data.items || [];
    itemsByOutlet.value = response.data.items_by_outlet || {};
    poAttachments.value = response.data.po_attachments || [];
    prAttachments.value = [];
    
    // Update amount with total from API if available, and save as original
    if (response.data.total_amount) {
      form.amount = response.data.total_amount;
      originalAmount.value = response.data.total_amount;
    }
  } catch (error) {
    console.error('Error loading PO items:', error);
    import('sweetalert2').then(({ default: Swal }) => {
      Swal.fire('Error', 'Gagal memuat detail Purchase Order', 'error');
    });
  } finally {
    loadingPOItems.value = false;
  }
}

async function selectPR(pr) {
  // Prevent selection if PR is on hold
  const isHeld = pr.is_held === true || pr.is_held === 1 || pr.is_held === '1' || pr.is_held === 'true';
  if (isHeld) {
    import('sweetalert2').then(({ default: Swal }) => {
      Swal.fire('Warning', 'Purchase Requisition tidak dapat dipilih karena sedang di-hold. Silakan release PR terlebih dahulu.', 'warning');
    });
    return;
  }
  
  selectedPR.value = pr;
  selectedPO.value = null;
  form.purchase_requisition_id = pr.id;
  form.purchase_order_ops_id = null;
  form.supplier_id = ''; // PR tidak punya supplier, user harus pilih manual
  form.amount = pr.amount;
  originalAmount.value = pr.amount;
  
  // Load PR items grouped by outlet
  loadingPOItems.value = true;
  try {
    const response = await axios.get(`/non-food-payments/pr-items/${pr.id}`);
    itemsByOutlet.value = response.data.items_by_outlet || {};
    prAttachments.value = response.data.pr_attachments || [];
    poAttachments.value = [];
    
    // Update amount with total from API if available, and save as original
    if (response.data.total_amount) {
      form.amount = response.data.total_amount;
      originalAmount.value = response.data.total_amount;
    }
    
    // Update selectedPR with full data
    if (response.data.pr) {
      selectedPR.value = { ...selectedPR.value, ...response.data.pr };
    }
  } catch (error) {
    console.error('Error loading PR items:', error);
    import('sweetalert2').then(({ default: Swal }) => {
      Swal.fire('Error', 'Gagal memuat detail Purchase Requisition', 'error');
    });
  } finally {
    loadingPOItems.value = false;
  }
}

function submitForm() {
  // Validate that at least one transaction is selected
  if (!form.purchase_order_ops_id && !form.purchase_requisition_id) {
    import('sweetalert2').then(({ default: Swal }) => {
      Swal.fire('Error', 'Pilih minimal satu transaksi (Purchase Order atau Purchase Requisition).', 'error');
    });
    return;
  }

  // Validate supplier_id is filled
  if (!form.supplier_id) {
    import('sweetalert2').then(({ default: Swal }) => {
      Swal.fire('Error', 'Supplier harus dipilih.', 'error');
    });
    return;
  }

  isSubmitting.value = true;

  router.post('/non-food-payments', form, {
    onSuccess: () => {
      import('sweetalert2').then(({ default: Swal }) => {
        Swal.fire('Berhasil', 'Non Food Payment berhasil dibuat!', 'success');
      });
    },
    onError: (errors) => {
      console.error('Validation errors:', errors);
      import('sweetalert2').then(({ default: Swal }) => {
        Swal.fire('Error', 'Gagal membuat Non Food Payment. Periksa data yang diinput.', 'error');
      });
    },
    onFinish: () => {
      isSubmitting.value = false;
    }
  });
}

function goBack() {
  router.get('/non-food-payments');
}
</script>
