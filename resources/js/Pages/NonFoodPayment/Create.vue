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
          <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold text-gray-800">Available Purchase Requisitions</h2>
            <div class="flex items-center gap-2">
              <div class="relative">
                <input
                  type="text"
                  v-model="searchPR"
                  placeholder="Cari PR number, title, atau mode..."
                  class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-400 focus:border-blue-400 w-64"
                />
                <i class="fa fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
              </div>
            </div>
          </div>
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

        <!-- Available Retail Non Food (Contra Bon) -->
        <div v-if="mappedRetailNonFoods.length > 0" class="bg-white rounded-2xl shadow-2xl p-6">
          <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold text-gray-800">Available Retail Non Food (Contra Bon)</h2>
            <div class="flex items-center gap-2">
              <div class="relative">
                <input
                  type="text"
                  v-model="searchRetailNonFood"
                  placeholder="Cari retail number, supplier, atau outlet..."
                  class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-400 focus:border-blue-400 w-64"
                />
                <i class="fa fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
              </div>
            </div>
          </div>
          <div class="space-y-4">
            <div v-for="rnf in mappedRetailNonFoods" :key="rnf.id" 
                 class="border rounded-lg p-4 transition border-gray-200 hover:bg-gray-50 cursor-pointer"
                 @click="selectRetailNonFood(rnf)">
              <div class="flex items-center justify-between">
                <div class="flex-1">
                  <div class="flex items-center gap-2 mb-1">
                    <div class="font-semibold text-gray-900">{{ rnf.retail_number }}</div>
                  </div>
                  <div class="text-sm text-gray-600">{{ rnf.supplier_name }}</div>
                  <div class="text-sm text-gray-500">
                    {{ formatDate(rnf.transaction_date) }} - {{ formatCurrency(rnf.total_amount) }}
                  </div>
                  <div v-if="rnf.outlet_name" class="text-xs text-gray-600 mt-1">
                    <i class="fa fa-store mr-1"></i>Outlet: {{ rnf.outlet_name }}
                  </div>
                </div>
                <div class="text-right">
                  <button type="button" 
                          class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition">
                    <i class="fa fa-arrow-right mr-1"></i> Pilih
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div v-if="mappedPOs.length === 0 && mappedPRs.length === 0 && (props.availableRetailNonFoods || []).length === 0" class="bg-white rounded-2xl shadow-2xl p-6 text-center">
          <div class="text-gray-500">
            <i class="fa fa-inbox text-4xl mb-4"></i>
            <p>Tidak ada Purchase Order, Purchase Requisition, atau Retail Non Food yang tersedia untuk dibayar.</p>
          </div>
        </div>
        
        <!-- Show message if search returns no results for Purchase Requisitions -->
        <div v-if="(props.availablePRs || []).length > 0 && mappedPRs.length === 0" class="bg-white rounded-2xl shadow-2xl p-6 text-center">
          <div class="text-gray-500">
            <i class="fa fa-search text-4xl mb-4"></i>
            <p>Tidak ada Purchase Requisition yang ditemukan untuk pencarian "{{ searchPR }}".</p>
            <button @click="searchPR = ''" class="mt-4 text-blue-600 hover:text-blue-800 underline">
              Hapus filter pencarian
            </button>
          </div>
        </div>
        
        <!-- Show message if search returns no results for Retail Non Food -->
        <div v-if="(props.availableRetailNonFoods || []).length > 0 && mappedRetailNonFoods.length === 0" class="bg-white rounded-2xl shadow-2xl p-6 text-center">
          <div class="text-gray-500">
            <i class="fa fa-search text-4xl mb-4"></i>
            <p>Tidak ada Retail Non Food yang ditemukan untuk pencarian "{{ searchRetailNonFood }}".</p>
            <button @click="searchRetailNonFood = ''" class="mt-4 text-blue-600 hover:text-blue-800 underline">
              Hapus filter pencarian
            </button>
          </div>
        </div>
      </div>

      <!-- Step 2: Form Payment dengan Detail PO/PR/Retail Non Food -->
      <form v-if="selectedPO || selectedPR || selectedRetailNonFood" @submit.prevent="submitForm" class="space-y-6">
        <!-- PO/PR/Retail Non Food Information -->
        <div class="bg-white rounded-2xl shadow-2xl p-6">
          <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold text-gray-800">{{ selectedPO ? 'Detail Purchase Order' : selectedPR ? 'Detail Purchase Requisition' : 'Detail Retail Non Food' }}</h2>
            <div class="flex items-center gap-2">
              <button
                v-if="selectedPO"
                type="button"
                @click="showTutorial = true"
                class="text-blue-600 hover:text-blue-800 text-sm font-medium flex items-center gap-1 px-3 py-1 bg-blue-50 rounded-lg border border-blue-200"
              >
                <i class="fa fa-question-circle"></i>
                <span class="text-red-600 font-semibold">Tutorial Payment Type</span>
              </button>
              <button type="button" @click="resetSelection" class="text-gray-500 hover:text-gray-700">
                <i class="fa fa-times"></i>
              </button>
            </div>
          </div>
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div>
              <label class="block text-sm font-medium text-gray-700">{{ selectedPO ? 'PO Number' : selectedPR ? 'PR Number' : 'Retail Number' }}</label>
              <p class="mt-1 text-lg font-semibold text-gray-900">{{ selectedPO ? selectedPO.number : selectedPR ? selectedPR.pr_number : selectedRetailNonFood.retail_number }}</p>
            </div>
            <div v-if="selectedPO || selectedRetailNonFood">
              <label class="block text-sm font-medium text-gray-700">Supplier</label>
              <p class="mt-1 text-gray-900">{{ selectedPO ? selectedPO.supplier_name : selectedRetailNonFood.supplier_name }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">{{ selectedPO ? 'PO Date' : selectedPR ? 'PR Date' : 'Transaction Date' }}</label>
              <p class="mt-1 text-gray-900">{{ formatDate(selectedPO ? selectedPO.date : selectedPR ? selectedPR.date : selectedRetailNonFood.transaction_date) }}</p>
            </div>
            <div v-if="selectedPO && selectedPO.po_discount_info?.subtotal">
              <label class="block text-sm font-medium text-gray-700">Subtotal</label>
              <p class="mt-1 text-gray-900">{{ formatCurrency(selectedPO.po_discount_info.subtotal) }}</p>
            </div>
            <div v-if="selectedPO && selectedPO.po_discount_info && (selectedPO.po_discount_info.discount_total_percent > 0 || selectedPO.po_discount_info.discount_total_amount > 0)">
              <label class="block text-sm font-medium text-gray-700">Diskon Total PO</label>
              <p class="mt-1 text-red-600 font-semibold">
                <span v-if="selectedPO.po_discount_info.discount_total_percent > 0">{{ selectedPO.po_discount_info.discount_total_percent }}%</span>
                <span v-if="selectedPO.po_discount_info.discount_total_percent > 0 && selectedPO.po_discount_info.discount_total_amount > 0"> / </span>
                <span v-if="selectedPO.po_discount_info.discount_total_amount > 0">{{ formatCurrency(selectedPO.po_discount_info.discount_total_amount) }}</span>
              </p>
            </div>
            <div v-if="selectedPO && selectedPO.po_discount_info && selectedPO.po_discount_info.ppn_enabled">
              <label class="block text-sm font-medium text-gray-700">PPN (11%)</label>
              <p class="mt-1 text-blue-600 font-semibold">{{ formatCurrency(selectedPO.po_discount_info.ppn_amount || 0) }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Grand Total (Setelah Discount{{ selectedPO && selectedPO.po_discount_info && selectedPO.po_discount_info.ppn_enabled ? ' + PPN' : '' }})</label>
              <p class="mt-1 text-lg font-bold text-green-600">
                {{ formatCurrency(selectedPO ? (selectedPO.po_discount_info?.grand_total || selectedPO.grand_total) : selectedPR ? selectedPR.amount : selectedRetailNonFood.total_amount) }}
              </p>
              <p v-if="selectedPO && selectedPO.po_discount_info" class="mt-1 text-xs text-gray-500">
                <span v-if="selectedPO.po_discount_info.discount_total_percent > 0 || selectedPO.po_discount_info.discount_total_amount > 0">
                  (Subtotal: {{ formatCurrency(selectedPO.po_discount_info.subtotal) }} - Discount: {{ formatCurrency(selectedPO.po_discount_info.discount_total_amount) }}{{ selectedPO.po_discount_info.ppn_enabled ? ' + PPN: ' + formatCurrency(selectedPO.po_discount_info.ppn_amount || 0) : '' }})
                </span>
              </p>
            </div>
            <div v-if="selectedPO && selectedPO.source_pr_number">
              <label class="block text-sm font-medium text-gray-700">Source PR</label>
              <p class="mt-1 text-gray-900">{{ selectedPO.source_pr_number }}</p>
            </div>
            <div v-if="selectedPR && selectedPR.division_name">
              <label class="block text-sm font-medium text-gray-700">Division</label>
              <p class="mt-1 text-gray-900">{{ selectedPR.division_name }}</p>
            </div>
            <!-- Payment Type Information (always show if PO selected) -->
            <div v-if="selectedPO" class="md:col-span-2">
              <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <div class="flex items-center justify-between mb-2">
                  <label class="block text-sm font-medium text-blue-800">Metode Pembayaran</label>
                  <button
                    type="button"
                    @click="showTutorial = true"
                    class="text-blue-600 hover:text-blue-800 text-xs font-medium flex items-center gap-1"
                  >
                    <i class="fa fa-question-circle"></i>
                    <span class="text-red-600 font-semibold">Tutorial</span>
                  </button>
                </div>
                <div v-if="selectedPO.payment_type" class="mt-2">
                  <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium"
                        :class="selectedPO.payment_type === 'lunas' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'">
                    <i :class="selectedPO.payment_type === 'lunas' ? 'fa fa-check-circle mr-1' : 'fa fa-calendar-alt mr-1'"></i>
                    {{ selectedPO.payment_type === 'lunas' ? 'Bayar Lunas' : 'Termin Bayar' }}
                  </span>
                  <p v-if="selectedPO.payment_type === 'termin' && selectedPO.payment_terms" class="mt-2 text-sm text-gray-700">
                    <strong>Detail Termin:</strong> {{ selectedPO.payment_terms }}
                  </p>
                  <p v-else-if="selectedPO.payment_type === 'lunas'" class="mt-2 text-sm text-gray-600">
                    <i class="fa fa-info-circle mr-1"></i>
                    Pembayaran harus dilakukan sekaligus (lunas) dengan amount = Grand Total PO
                  </p>
                </div>
                <div v-else class="mt-2 text-sm text-gray-500 italic">
                  <i class="fa fa-info-circle mr-1"></i>
                  Metode pembayaran belum ditentukan di PO
                </div>
              </div>
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
          <div v-if="(poAttachments && poAttachments.length > 0) || (prAttachments && prAttachments.length > 0) || (retailNonFoodAttachments && retailNonFoodAttachments.length > 0)" class="bg-white rounded-2xl shadow-2xl p-6">
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

            <!-- Retail Non Food Attachments -->
            <div v-if="selectedRetailNonFood && retailNonFoodAttachments && retailNonFoodAttachments.length > 0" class="mb-6">
              <h4 class="text-md font-medium text-gray-700 mb-3">Retail Non Food Attachments</h4>
              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div v-for="attachment in retailNonFoodAttachments" :key="`rnf-${attachment.id}`" class="border border-gray-200 rounded-lg p-3">
                  <!-- Image Thumbnail -->
                  <div v-if="isImageFile(attachment.file_name)" class="relative group cursor-pointer" @click="openLightbox(`/storage/${attachment.file_path}`, attachment.file_name)">
                    <div class="aspect-square bg-gray-100 border border-gray-200 rounded-lg overflow-hidden hover:shadow-lg transition-all duration-200">
                      <img
                        :src="`/storage/${attachment.file_path}`"
                        :alt="attachment.file_name"
                        class="w-full h-full object-cover hover:scale-105 transition-transform duration-200"
                        @click.stop="openLightbox(`/storage/${attachment.file_path}`, attachment.file_name)"
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
                        :href="`/storage/${attachment.file_path}`" 
                        target="_blank" 
                        class="text-blue-600 hover:text-blue-800 text-sm"
                        download
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
                      <th v-if="selectedPO" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Diskon</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                    </tr>
                  </thead>
                  <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="item in outletData.items" :key="item.id">
                      <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ item.item_name }}</td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ item.quantity }}</td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ item.unit }}</td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ formatCurrency(item.price) }}</td>
                      <td v-if="selectedPO" class="px-6 py-4 whitespace-nowrap text-sm text-xs">
                        <div v-if="item.discount_percent > 0 || item.discount_amount > 0" class="text-red-600">
                          <div v-if="item.discount_percent > 0">{{ item.discount_percent }}%</div>
                          <div v-if="item.discount_amount > 0">{{ formatCurrency(item.discount_amount) }}</div>
                        </div>
                        <span v-else class="text-gray-400">-</span>
                      </td>
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
            <div v-if="shouldShowSupplier">
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Supplier 
                <span v-if="isSupplierRequired">*</span>
              </label>
              <multiselect
                v-model="selectedSupplier"
                :options="suppliers"
                :searchable="true"
                :close-on-select="true"
                :show-labels="false"
                :disabled="selectedPO || selectedRetailNonFood"
                placeholder="Pilih Supplier"
                label="name"
                track-by="id"
                @select="onSupplierChange"
                @remove="onSupplierRemove"
                class="mt-1"
                :required="isSupplierRequired"
              >
                <template #noOptions>
                  <span>Tidak ada supplier ditemukan</span>
                </template>
                <template #noResult>
                  <span>Tidak ada supplier ditemukan</span>
                </template>
              </multiselect>
              <p v-if="selectedPO" class="mt-1 text-xs text-gray-500">
                Supplier diambil dari Purchase Order
              </p>
              <p v-if="selectedRetailNonFood" class="mt-1 text-xs text-gray-500">
                Supplier diambil dari Retail Non Food
              </p>
              <p v-if="selectedPR && isSupplierRequired" class="mt-1 text-xs text-gray-500">
                Pilih supplier untuk payment ini (wajib)
              </p>
              <p v-if="selectedPR && !isSupplierRequired" class="mt-1 text-xs text-gray-500">
                Pilih supplier untuk payment ini (opsional)
              </p>
            </div>

            <!-- Payment Termin Info (only for PO with termin payment) -->
            <div v-if="selectedPO && selectedPO.payment_type === 'termin'" class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-4">
              <div class="flex items-center justify-between mb-3">
                <h4 class="text-sm font-semibold text-blue-800 flex items-center">
                  <i class="fa fa-calendar-alt mr-2"></i>
                  Informasi Pembayaran Termin
                </h4>
                <button
                  type="button"
                  @click="showTutorial = true"
                  class="text-blue-600 hover:text-blue-800 text-xs font-medium flex items-center gap-1"
                >
                  <i class="fa fa-question-circle"></i>
                  <span class="text-red-600 font-semibold">Tutorial</span>
                </button>
              </div>
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                <div>
                  <label class="block text-xs font-medium text-gray-600 mb-1">Total PO (Setelah Discount{{ selectedPO.po_discount_info && selectedPO.po_discount_info.ppn_enabled ? ' + PPN' : '' }})</label>
                  <p class="text-lg font-bold text-gray-900">{{ formatCurrency(selectedPO.po_discount_info?.grand_total || selectedPO.grand_total) }}</p>
                  <p v-if="selectedPO.po_discount_info" class="text-xs text-gray-500 mt-1">
                    <span v-if="selectedPO.po_discount_info.discount_total_percent > 0 || selectedPO.po_discount_info.discount_total_amount > 0">
                      Subtotal: {{ formatCurrency(selectedPO.po_discount_info.subtotal) }} - Discount: {{ formatCurrency(selectedPO.po_discount_info.discount_total_amount) }}
                    </span>
                    <span v-if="selectedPO.po_discount_info.ppn_enabled" class="block mt-1">
                      PPN (11%): {{ formatCurrency(selectedPO.po_discount_info.ppn_amount || 0) }}
                    </span>
                  </p>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-600 mb-1">Sudah Dibayar</label>
                  <p class="text-lg font-bold text-green-600">{{ formatCurrency(paymentInfo.total_paid) }}</p>
                  <p class="text-xs text-gray-500 mt-1">{{ paymentInfo.payment_count }} pembayaran</p>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-600 mb-1">Sisa Pembayaran</label>
                  <p class="text-lg font-bold" :class="paymentInfo.remaining > 0 ? 'text-red-600' : 'text-green-600'">
                    {{ formatCurrency(paymentInfo.remaining) }}
                  </p>
                </div>
              </div>
              <div v-if="paymentInfo.remaining > 0" class="mt-3">
                <div class="w-full bg-gray-200 rounded-full h-2.5">
                  <div 
                    class="bg-blue-600 h-2.5 rounded-full transition-all duration-300" 
                    :style="{ width: `${(paymentInfo.total_paid / (selectedPO.po_discount_info?.grand_total || selectedPO.grand_total)) * 100}%` }"
                  ></div>
                </div>
                <p class="text-xs text-gray-600 mt-1 text-center">
                  Progress: {{ ((paymentInfo.total_paid / (selectedPO.po_discount_info?.grand_total || selectedPO.grand_total)) * 100).toFixed(1) }}%
                </p>
              </div>
              <div v-else class="mt-3 p-2 bg-green-100 border border-green-300 rounded-lg">
                <p class="text-sm text-green-800 text-center font-medium">
                  <i class="fa fa-check-circle mr-1"></i>
                  PO sudah lunas!
                </p>
              </div>
            </div>

            <!-- Amount (Auto-filled from PO/PR, but editable) -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Amount * 
                <span class="text-xs font-normal text-gray-500">(Dapat diubah)</span>
                <span v-if="selectedPO && selectedPO.payment_type === 'termin'" class="text-xs font-normal text-blue-600 ml-2">
                  (Maks: {{ formatCurrency(paymentInfo.remaining) }})
                </span>
              </label>
              <div class="relative">
                <input 
                  type="number" 
                  v-model="form.amount" 
                  step="0.01"
                  :min="0"
                  :max="selectedPO && selectedPO.payment_type === 'termin' ? paymentInfo.remaining : undefined"
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
                Nilai {{ selectedPO ? 'PO' : 'PR' }}: <strong>{{ formatCurrency(originalAmount || (selectedPO ? (selectedPO.po_discount_info?.grand_total || selectedPO.grand_total) : selectedPR.amount)) }}</strong>
              </p>
              <p v-if="selectedPO && selectedPO.po_discount_info && (selectedPO.po_discount_info.discount_total_percent > 0 || selectedPO.po_discount_info.discount_total_amount > 0)" class="mt-1 text-xs text-blue-600">
                <i class="fa fa-info-circle mr-1"></i>
                Nilai ini sudah termasuk discount total PO ({{ selectedPO.po_discount_info.discount_total_percent > 0 ? selectedPO.po_discount_info.discount_total_percent + '%' : '' }}{{ selectedPO.po_discount_info.discount_total_percent > 0 && selectedPO.po_discount_info.discount_total_amount > 0 ? ' / ' : '' }}{{ selectedPO.po_discount_info.discount_total_amount > 0 ? formatCurrency(selectedPO.po_discount_info.discount_total_amount) : '' }})
              </p>
              <p v-if="selectedPO && selectedPO.payment_type === 'termin' && paymentInfo.remaining < (selectedPO.po_discount_info?.grand_total || selectedPO.grand_total)" class="mt-1 text-xs text-blue-600">
                <i class="fa fa-info-circle mr-1"></i>
                Ini adalah pembayaran termin. Sisa yang harus dibayar: <strong>{{ formatCurrency(paymentInfo.remaining) }}</strong>
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

    <!-- Tutorial Modal -->
    <div v-if="showTutorial" class="fixed inset-0 z-50 overflow-y-auto" @click.self="showTutorial = false">
      <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <!-- Background overlay -->
        <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" @click="showTutorial = false"></div>

        <!-- Modal panel -->
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-5xl sm:w-full">
          <!-- Header -->
          <div class="bg-blue-600 px-6 py-4 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-white">
              <i class="fa fa-graduation-cap mr-2"></i>
              Tutorial: Payment Type & Pembayaran Termin
            </h3>
            <button
              @click="showTutorial = false"
              class="text-white hover:text-gray-200 focus:outline-none"
            >
              <i class="fa fa-times text-xl"></i>
            </button>
          </div>

          <!-- Content -->
          <div class="px-6 py-6 max-h-[70vh] overflow-y-auto">
            <div class="space-y-6">
              <!-- Introduction -->
              <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded">
                <h4 class="font-semibold text-blue-800 mb-2">
                  <i class="fa fa-info-circle mr-2"></i>
                  Payment Type di Purchase Order
                </h4>
                <p class="text-sm text-blue-700">
                  Setiap PO memiliki <strong>Payment Type</strong> yang ditentukan saat membuat PO di menu Purchase Order Ops.
                  Ada dua jenis: <strong>Bayar Lunas</strong> (pembayaran penuh sekaligus) dan <strong>Termin Bayar</strong> (pembayaran bertahap).
                  Payment type ini akan mempengaruhi cara pembayaran di menu Non Food Payment.
                </p>
              </div>

              <!-- Payment Type Overview -->
              <div class="border border-gray-200 rounded-lg p-4">
                <div class="flex items-start">
                  <div class="flex-shrink-0 w-8 h-8 bg-purple-600 text-white rounded-full flex items-center justify-center font-bold">
                    <i class="fa fa-info-circle text-sm"></i>
                  </div>
                  <div class="ml-4 flex-1">
                    <h4 class="font-semibold text-gray-900 mb-2">Perbedaan Payment Type</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-2">
                      <div class="bg-green-50 p-3 rounded border border-green-200">
                        <p class="text-xs font-semibold text-green-800 mb-1">
                          <i class="fa fa-check-circle mr-1"></i>
                          Bayar Lunas:
                        </p>
                        <ul class="text-xs text-gray-700 space-y-1 list-disc list-inside">
                          <li>Pembayaran penuh sekaligus</li>
                          <li>Hanya bisa 1x payment</li>
                          <li>Amount harus = Grand Total PO</li>
                          <li>Tidak bisa buat payment baru jika sudah ada payment</li>
                        </ul>
                      </div>
                      <div class="bg-blue-50 p-3 rounded border border-blue-200">
                        <p class="text-xs font-semibold text-blue-800 mb-1">
                          <i class="fa fa-calendar-alt mr-1"></i>
                          Termin Bayar:
                        </p>
                        <ul class="text-xs text-gray-700 space-y-1 list-disc list-inside">
                          <li>Pembayaran bertahap</li>
                          <li>Bisa multiple payments</li>
                          <li>Amount bisa ≤ Sisa Pembayaran</li>
                          <li>Bisa buat payment baru sampai lunas</li>
                        </ul>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Step 1: Pilih PO dan Lihat Payment Type -->
              <div class="border border-gray-200 rounded-lg p-4">
                <div class="flex items-start">
                  <div class="flex-shrink-0 w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center font-bold">
                    1
                  </div>
                  <div class="ml-4 flex-1">
                    <h4 class="font-semibold text-gray-900 mb-2">Pilih PO dan Lihat Payment Type</h4>
                    <ul class="text-sm text-gray-700 space-y-1 list-disc list-inside">
                      <li>Di form Create Payment, pilih PO yang sudah di-approve</li>
                      <li>Di section "Detail Purchase Order", akan muncul informasi <strong>Metode Pembayaran</strong></li>
                      <li>PO dengan payment_type = 'lunas' akan menampilkan badge <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Bayar Lunas</span></li>
                      <li>PO dengan payment_type = 'termin' akan menampilkan badge <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">Termin Bayar</span></li>
                      <li>Jika termin, akan muncul detail termin yang diinput saat create PO</li>
                    </ul>
                  </div>
                </div>
              </div>

              <!-- Step 2: Lihat Informasi Pembayaran -->
              <div class="border border-gray-200 rounded-lg p-4">
                <div class="flex items-start">
                  <div class="flex-shrink-0 w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center font-bold">
                    2
                  </div>
                  <div class="ml-4 flex-1">
                    <h4 class="font-semibold text-gray-900 mb-2">Lihat Informasi Pembayaran Termin</h4>
                    <p class="text-sm text-gray-700 mb-2">
                      Setelah memilih PO dengan termin, akan muncul box informasi:
                    </p>
                    <div class="bg-gray-50 p-3 rounded border border-gray-200">
                      <ul class="text-xs text-gray-700 space-y-1">
                        <li><strong>Total PO:</strong> Grand total dari PO</li>
                        <li><strong>Sudah Dibayar:</strong> Total semua payment yang sudah dibuat (approved/paid)</li>
                        <li><strong>Sisa Pembayaran:</strong> Total PO - Sudah Dibayar</li>
                        <li><strong>Progress Bar:</strong> Visual progress pembayaran (persentase)</li>
                        <li><strong>Detail Termin:</strong> Catatan termin dari PO (contoh: "50% di muka, 50% setelah barang diterima")</li>
                      </ul>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Step 3: Input Amount -->
              <div class="border border-gray-200 rounded-lg p-4">
                <div class="flex items-start">
                  <div class="flex-shrink-0 w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center font-bold">
                    3
                  </div>
                  <div class="ml-4 flex-1">
                    <h4 class="font-semibold text-gray-900 mb-2">Input Amount Pembayaran</h4>
                    <p class="text-sm text-gray-700 mb-2">
                      Field Amount akan otomatis terisi dengan <strong>Sisa Pembayaran</strong>. Anda punya 2 opsi:
                    </p>
                    <div class="space-y-2">
                      <div class="bg-green-50 border-l-4 border-green-500 p-3 rounded">
                        <p class="text-xs font-medium text-green-800 mb-1">
                          <i class="fa fa-check-circle mr-1"></i>
                          Opsi A: Bayar Sisa Penuh (Lunas)
                        </p>
                        <ul class="text-xs text-green-700 space-y-1 list-disc list-inside ml-4">
                          <li>Biarkan amount = Sisa Pembayaran</li>
                          <li>Atau klik tombol <strong>Reset</strong> untuk set ke sisa</li>
                          <li>Setelah payment ini, PO akan <strong>LUNAS</strong> ✅</li>
                        </ul>
                      </div>
                      <div class="bg-blue-50 border-l-4 border-blue-500 p-3 rounded">
                        <p class="text-xs font-medium text-blue-800 mb-1">
                          <i class="fa fa-calendar-alt mr-1"></i>
                          Opsi B: Bayar Sebagian (Partial)
                        </p>
                        <ul class="text-xs text-blue-700 space-y-1 list-disc list-inside ml-4">
                          <li>Ubah amount menjadi lebih kecil dari sisa</li>
                          <li>Contoh: Sisa Rp 5.000.000 → Input Rp 3.000.000</li>
                          <li>Masih ada sisa, bisa buat payment lagi nanti</li>
                        </ul>
                      </div>
                    </div>
                    <div class="bg-amber-50 border-l-4 border-amber-500 p-3 rounded mt-2">
                      <p class="text-xs text-amber-800">
                        <i class="fa fa-exclamation-triangle mr-1"></i>
                        <strong>Validasi:</strong> Amount tidak boleh melebihi Sisa Pembayaran. Sistem akan menolak jika amount > sisa.
                      </p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Step 4: Cara Bayar Sisa Termin -->
              <div class="border border-gray-200 rounded-lg p-4">
                <div class="flex items-start">
                  <div class="flex-shrink-0 w-8 h-8 bg-purple-600 text-white rounded-full flex items-center justify-center font-bold">
                    4
                  </div>
                  <div class="ml-4 flex-1">
                    <h4 class="font-semibold text-gray-900 mb-2">Cara Membayar Sisa Termin (Payment Berikutnya)</h4>
                    <p class="text-sm text-gray-700 mb-2">
                      Jika sudah ada payment sebelumnya dan masih ada sisa:
                    </p>
                    <ol class="text-sm text-gray-700 space-y-2 list-decimal list-inside">
                      <li>Buka menu <strong>Non Food Payment → Create</strong></li>
                      <li>Pilih <strong>PO yang sama</strong> (yang sudah punya payment sebelumnya)</li>
                      <li>Lihat info: <strong>Sisa Pembayaran</strong> akan otomatis ter-update</li>
                      <li>Input amount untuk pembayaran berikutnya (≤ Sisa Pembayaran)</li>
                      <li>Submit dan approve payment</li>
                      <li>Ulangi sampai PO lunas (Sisa = 0)</li>
                    </ol>
                    <div class="bg-purple-50 border-l-4 border-purple-500 p-3 rounded mt-2">
                      <p class="text-xs text-purple-800">
                        <i class="fa fa-info-circle mr-1"></i>
                        <strong>Catatan:</strong> Setiap payment akan punya sequence number (#1, #2, #3, dst) dan bisa dilihat di Show page.
                      </p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Step 5: Perbedaan Lunas vs Termin -->
              <div class="border border-gray-200 rounded-lg p-4">
                <div class="flex items-start">
                  <div class="flex-shrink-0 w-8 h-8 bg-green-600 text-white rounded-full flex items-center justify-center font-bold">
                    5
                  </div>
                  <div class="ml-4 flex-1">
                    <h4 class="font-semibold text-gray-900 mb-2">Perbedaan: PO Lunas vs PO Termin</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-2">
                      <div class="bg-green-50 p-3 rounded border border-green-200">
                        <p class="text-xs font-semibold text-green-800 mb-1">PO dengan Bayar Lunas:</p>
                        <ul class="text-xs text-green-700 space-y-1 list-disc list-inside">
                          <li>Hanya 1x payment</li>
                          <li>Amount = Grand Total PO</li>
                          <li>Tidak ada info box termin</li>
                          <li>Tidak bisa buat payment baru jika sudah ada payment</li>
                        </ul>
                      </div>
                      <div class="bg-blue-50 p-3 rounded border border-blue-200">
                        <p class="text-xs font-semibold text-blue-800 mb-1">PO dengan Termin Bayar:</p>
                        <ul class="text-xs text-blue-700 space-y-1 list-disc list-inside">
                          <li>Multiple payments</li>
                          <li>Amount ≤ Sisa Pembayaran</li>
                          <li>Ada info box dengan progress</li>
                          <li>Bisa buat payment baru sampai lunas</li>
                        </ul>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Tips -->
              <div class="bg-amber-50 border-l-4 border-amber-500 p-4 rounded">
                <h4 class="font-semibold text-amber-800 mb-2">
                  <i class="fa fa-lightbulb mr-2"></i>
                  Tips Penting
                </h4>
                <ul class="text-sm text-amber-700 space-y-1 list-disc list-inside">
                  <li>Selalu cek <strong>Sisa Pembayaran</strong> sebelum input amount</li>
                  <li>Gunakan field <strong>Description</strong> untuk tracking (contoh: "Pembayaran termin pertama (50% di muka)")</li>
                  <li>Cek progress di Show page setelah approve payment</li>
                  <li>Sistem otomatis mencegah overpayment (amount > sisa)</li>
                  <li>PO yang sudah lunas tidak akan muncul di list Available PO</li>
                </ul>
              </div>
            </div>
          </div>

          <!-- Footer -->
          <div class="bg-gray-50 px-6 py-4 flex justify-end">
            <button
              @click="showTutorial = false"
              class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
            >
              Tutup
            </button>
          </div>
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
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.min.css';

const props = defineProps({
  suppliers: Array,
  availablePOs: Array,
  availablePRs: Array,
  availableRetailNonFoods: Array,
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

// Search for Purchase Requisitions
const searchPR = ref('');

// Map PR data to ensure is_held is boolean with search filter
const mappedPRs = computed(() => {
  const prs = (props.availablePRs || []).map(pr => ({
    ...pr,
    is_held: pr.is_held === true || pr.is_held === 1 || pr.is_held === '1' || pr.is_held === 'true',
    hold_reason: pr.hold_reason || null
  }));
  
  if (!searchPR.value) {
    return prs;
  }
  
  const searchTerm = searchPR.value.toLowerCase();
  return prs.filter(pr => {
    const prNumber = (pr.pr_number || '').toLowerCase();
    const title = (pr.title || '').toLowerCase();
    const mode = (pr.mode || '').toLowerCase();
    
    return prNumber.includes(searchTerm) || 
           title.includes(searchTerm) || 
           mode.includes(searchTerm);
  });
});

// Search for Retail Non Food
const searchRetailNonFood = ref('');

// Map Retail Non Food data with search filter
const mappedRetailNonFoods = computed(() => {
  const retailNonFoods = props.availableRetailNonFoods || [];
  
  if (!searchRetailNonFood.value) {
    return retailNonFoods;
  }
  
  const searchTerm = searchRetailNonFood.value.toLowerCase();
  return retailNonFoods.filter(rnf => {
    const retailNumber = (rnf.retail_number || '').toLowerCase();
    const supplierName = (rnf.supplier_name || '').toLowerCase();
    const outletName = (rnf.outlet_name || '').toLowerCase();
    
    return retailNumber.includes(searchTerm) || 
           supplierName.includes(searchTerm) || 
           outletName.includes(searchTerm);
  });
});

const isSubmitting = ref(false);
const selectedPO = ref(null);
const selectedPR = ref(null);
const selectedRetailNonFood = ref(null);
const selectedSupplier = ref(null);
const poItems = ref([]);
const itemsByOutlet = ref({});
const loadingPOItems = ref(false);
const poAttachments = ref([]);
const prAttachments = ref([]);
const retailNonFoodAttachments = ref([]);
const lightboxImage = ref(null);
const lightboxVisible = ref(false);
const originalAmount = ref(null);
const showTutorial = ref(false);
const paymentInfo = ref({
  total_paid: 0,
  remaining: 0,
  payment_count: 0
});

const form = reactive({
  purchase_order_ops_id: null,
  purchase_requisition_id: null,
  retail_non_food_id: null,
  supplier_id: '',
  amount: '',
  payment_method: '',
  payment_date: new Date().toISOString().split('T')[0],
  due_date: '',
  description: '',
  reference_number: '',
  notes: '',
  is_partial_payment: false
});

// Computed properties for supplier field visibility and requirement
const shouldShowSupplier = computed(() => {
  // If PO is selected, always show supplier (taken from PO)
  if (selectedPO.value) {
    return true;
  }
  
  // If Retail Non Food is selected, always show supplier (taken from Retail Non Food)
  if (selectedRetailNonFood.value) {
    return true;
  }
  
  // If PR is selected, check mode
  if (selectedPR.value) {
    const mode = selectedPR.value.mode;
    // Hide supplier for travel_application and kasbon
    if (mode === 'travel_application' || mode === 'kasbon') {
      return false;
    }
    // Show supplier for pr_ops and purchase_payment
    return true;
  }
  
  // Default: show supplier
  return true;
});

const isSupplierRequired = computed(() => {
  // If PO is selected, supplier is always required (from PO)
  if (selectedPO.value) {
    return true;
  }
  
  // If Retail Non Food is selected, supplier is always required (from Retail Non Food)
  if (selectedRetailNonFood.value) {
    return true;
  }
  
  // If PR is selected, check mode
  if (selectedPR.value) {
    const mode = selectedPR.value.mode;
    // Required for pr_ops
    if (mode === 'pr_ops') {
      return true;
    }
    // Optional for purchase_payment
    if (mode === 'purchase_payment') {
      return false;
    }
    // Not needed for travel_application and kasbon (field is hidden)
    return false;
  }
  
  // Default: required
  return true;
});

function onSupplierChange(supplier) {
  if (supplier && supplier.id) {
    form.supplier_id = supplier.id;
  }
}

function onSupplierRemove() {
  selectedSupplier.value = null;
  form.supplier_id = '';
}

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
  selectedRetailNonFood.value = null;
  selectedSupplier.value = null;
  poItems.value = [];
  itemsByOutlet.value = {};
  poAttachments.value = [];
  prAttachments.value = [];
  retailNonFoodAttachments.value = [];
  form.purchase_order_ops_id = null;
  form.purchase_requisition_id = null;
  form.retail_non_food_id = null;
  form.supplier_id = '';
  form.amount = '';
  form.is_partial_payment = false;
  originalAmount.value = null;
  paymentInfo.value = {
    total_paid: 0,
    remaining: 0,
    payment_count: 0
  };
}

function resetAmountToOriginal() {
  if (selectedPO.value && selectedPO.value.payment_type === 'termin') {
    // For termin, reset to remaining amount
    form.amount = paymentInfo.value.remaining > 0 ? paymentInfo.value.remaining : 0;
  } else if (originalAmount.value !== null) {
    form.amount = originalAmount.value;
  } else if (selectedPO.value) {
    form.amount = selectedPO.value.grand_total;
  } else if (selectedPR.value) {
    form.amount = selectedPR.value.amount;
  } else if (selectedRetailNonFood.value) {
    form.amount = selectedRetailNonFood.value.total_amount;
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
  
  // Set selected supplier from PO
  if (po.supplier_id && props.suppliers) {
    const supplier = props.suppliers.find(s => s.id == po.supplier_id);
    if (supplier) {
      selectedSupplier.value = supplier;
    }
  }
  
  // Load PO items grouped by outlet first to get discount info
  loadingPOItems.value = true;
  try {
    const response = await axios.get(`/non-food-payments/po-items/${po.id}`);
    poItems.value = response.data.items || [];
    itemsByOutlet.value = response.data.items_by_outlet || {};
    poAttachments.value = response.data.po_attachments || [];
    prAttachments.value = [];
    retailNonFoodAttachments.value = [];
    
    // Update PO with discount info from API
    if (response.data.po_discount_info) {
      selectedPO.value.po_discount_info = response.data.po_discount_info;
      selectedPO.value.subtotal = response.data.po_discount_info.subtotal;
      selectedPO.value.grand_total = response.data.po_discount_info.grand_total;
    }
    
    // Get grand_total after discount (this is the correct value to use)
    const grandTotalAfterDiscount = response.data.po_discount_info?.grand_total || po.grand_total;
    
    // Load payment info for termin payment (use grand_total after discount)
    if (po.payment_type === 'termin') {
      try {
        const paymentResponse = await axios.get(`/api/non-food-payments/payment-info/${po.id}`);
        paymentInfo.value = {
          total_paid: paymentResponse.data.total_paid || 0,
          remaining: paymentResponse.data.remaining || grandTotalAfterDiscount,
          payment_count: paymentResponse.data.payment_count || 0
        };
        
        // Set default amount to remaining if termin
        form.amount = paymentInfo.value.remaining > 0 ? paymentInfo.value.remaining : 0;
        originalAmount.value = paymentInfo.value.remaining;
        form.is_partial_payment = paymentInfo.value.total_paid > 0;
      } catch (error) {
        console.error('Error loading payment info:', error);
        paymentInfo.value = {
          total_paid: 0,
          remaining: grandTotalAfterDiscount,
          payment_count: 0
        };
        form.amount = grandTotalAfterDiscount;
        originalAmount.value = grandTotalAfterDiscount;
        form.is_partial_payment = false;
      }
    } else {
      // For lunas payment, use grand_total after discount
      form.amount = grandTotalAfterDiscount;
      originalAmount.value = grandTotalAfterDiscount;
      form.is_partial_payment = false;
      paymentInfo.value = {
        total_paid: 0,
        remaining: grandTotalAfterDiscount,
        payment_count: 0
      };
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
  
  // Set supplier_id based on mode
  const mode = pr.mode;
  if (mode === 'travel_application' || mode === 'kasbon') {
    // No supplier needed for these modes
    form.supplier_id = '';
    selectedSupplier.value = null;
  } else if (mode === 'pr_ops') {
    // Supplier required for pr_ops, user must select
    form.supplier_id = '';
    selectedSupplier.value = null;
  } else {
    // purchase_payment: supplier optional
    form.supplier_id = '';
    selectedSupplier.value = null;
  }
  
  form.amount = pr.amount;
  originalAmount.value = pr.amount;
  
  // Load PR items grouped by outlet
  loadingPOItems.value = true;
  try {
    const response = await axios.get(`/non-food-payments/pr-items/${pr.id}`);
    itemsByOutlet.value = response.data.items_by_outlet || {};
    prAttachments.value = response.data.pr_attachments || [];
    poAttachments.value = [];
    retailNonFoodAttachments.value = [];
    
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

async function selectRetailNonFood(rnf) {
  selectedRetailNonFood.value = rnf;
  selectedPO.value = null;
  selectedPR.value = null;
  form.retail_non_food_id = rnf.id;
  form.purchase_order_ops_id = null;
  form.purchase_requisition_id = null;
  
  // Set supplier_id from retail non food
  if (rnf.supplier_id && props.suppliers) {
    const supplier = props.suppliers.find(s => s.id == rnf.supplier_id);
    if (supplier) {
      selectedSupplier.value = supplier;
      form.supplier_id = rnf.supplier_id;
    }
  }
  
  form.amount = rnf.total_amount;
  originalAmount.value = rnf.total_amount;
  
  // Load Retail Non Food items
  loadingPOItems.value = true;
  try {
    console.log('Loading Retail Non Food items for ID:', rnf.id);
    const response = await axios.get(`/non-food-payments/retail-non-food-items/${rnf.id}`);
    console.log('Response received:', response.data);
    console.log('Attachments in response:', response.data.retail_non_food_attachments);
    itemsByOutlet.value = response.data.items_by_outlet || {};
    retailNonFoodAttachments.value = response.data.retail_non_food_attachments || [];
    console.log('retailNonFoodAttachments.value set to:', retailNonFoodAttachments.value);
    prAttachments.value = [];
    poAttachments.value = [];
    
    // Update amount with total from API if available, and save as original
    if (response.data.total_amount) {
      form.amount = response.data.total_amount;
      originalAmount.value = response.data.total_amount;
    }
    
    // Update selectedRetailNonFood with full data
    if (response.data.retail_non_food) {
      selectedRetailNonFood.value = { ...selectedRetailNonFood.value, ...response.data.retail_non_food };
    }
  } catch (error) {
    console.error('Error loading Retail Non Food items:', error);
    import('sweetalert2').then(({ default: Swal }) => {
      Swal.fire('Error', 'Gagal memuat detail Retail Non Food', 'error');
    });
  } finally {
    loadingPOItems.value = false;
  }
}

function submitForm() {
  // Validate that at least one transaction is selected
  if (!form.purchase_order_ops_id && !form.purchase_requisition_id && !form.retail_non_food_id) {
    import('sweetalert2').then(({ default: Swal }) => {
      Swal.fire('Error', 'Pilih minimal satu transaksi (Purchase Order, Purchase Requisition, atau Retail Non Food).', 'error');
    });
    return;
  }

  // Validate supplier_id based on mode
  if (shouldShowSupplier.value && isSupplierRequired.value && !form.supplier_id) {
    import('sweetalert2').then(({ default: Swal }) => {
      Swal.fire('Error', 'Supplier harus dipilih.', 'error');
    });
    return;
  }
  
  // For travel_application and kasbon, set supplier_id to null
  if (!shouldShowSupplier.value) {
    form.supplier_id = '';
  }

  // Validate amount for termin payment
  if (selectedPO.value && selectedPO.value.payment_type === 'termin') {
    if (parseFloat(form.amount) > paymentInfo.value.remaining) {
      import('sweetalert2').then(({ default: Swal }) => {
        Swal.fire('Error', `Jumlah pembayaran melebihi sisa yang harus dibayar. Sisa: ${formatCurrency(paymentInfo.value.remaining)}`, 'error');
      });
      return;
    }
    if (parseFloat(form.amount) <= 0) {
      import('sweetalert2').then(({ default: Swal }) => {
        Swal.fire('Error', 'Jumlah pembayaran harus lebih dari 0.', 'error');
      });
      return;
    }
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

<script>
export default { 
  components: {
    Multiselect
  }
}
</script>

<style scoped>
/* Custom multiselect styling */
.multiselect {
  min-height: 42px;
}

.multiselect :deep(.multiselect__tags) {
  border: 1px solid #d1d5db;
  border-radius: 0.75rem;
  box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
  min-height: 42px;
  padding: 8px 12px;
}

.multiselect :deep(.multiselect__placeholder) {
  color: #6b7280;
  padding-top: 8px;
  padding-bottom: 8px;
}

.multiselect :deep(.multiselect__single) {
  color: #111827;
  padding-top: 8px;
  padding-bottom: 8px;
}

.multiselect :deep(.multiselect__input) {
  border: none;
  padding: 0;
  margin: 0;
  min-height: auto;
}

.multiselect :deep(.multiselect__input:focus) {
  outline: none;
}

.multiselect :deep(.multiselect__content-wrapper) {
  border: 1px solid #d1d5db;
  border-radius: 0.75rem;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.multiselect :deep(.multiselect__option--highlight) {
  background: #3b82f6;
  color: white;
}

.multiselect :deep(.multiselect__option--selected) {
  background: #eff6ff;
  color: #1e40af;
  font-weight: 600;
}

.multiselect :deep(.multiselect__option--selected.multiselect__option--highlight) {
  background: #3b82f6;
  color: white;
}

.multiselect :deep(.multiselect--disabled) {
  background: #f3f4f6;
  opacity: 1;
}

.multiselect :deep(.multiselect--disabled .multiselect__tags) {
  background: #f3f4f6;
  cursor: not-allowed;
}
</style>
