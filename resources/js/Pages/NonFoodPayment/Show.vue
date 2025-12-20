<template>
  <AppLayout>
    <div class="w-full py-8 px-0">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-eye"></i> Detail Non Food Payment
        </h1>
        <div class="flex gap-2">
          <button @click="goBack" class="bg-gray-500 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
            <i class="fa fa-arrow-left mr-1"></i> Kembali
          </button>
          <button @click="printPayment" class="bg-blue-500 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
            <i class="fas fa-print mr-1"></i> Print
          </button>
          <button v-if="payment.status === 'pending'" @click="editPayment" class="bg-yellow-500 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
            <i class="fa fa-pencil-alt mr-1"></i> Edit
          </button>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Payment Information -->
        <div class="lg:col-span-2 space-y-6">
          <!-- Basic Information -->
          <div class="bg-white rounded-2xl shadow-2xl p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Informasi Payment</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700">Payment Number</label>
                <p class="mt-1 text-lg font-semibold text-gray-900">{{ payment.payment_number }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Status</label>
                <span :class="getStatusClass(payment.status)" class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold">
                  {{ getStatusText(payment.status) }}
                </span>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Supplier</label>
                <p class="mt-1 text-gray-900">{{ payment.supplier?.name || '-' }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Amount</label>
                <p class="mt-1 text-lg font-bold text-green-600">{{ formatCurrency(payment.amount) }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Payment Method</label>
                <span :class="getPaymentMethodClass(payment.payment_method)" class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold">
                  {{ getPaymentMethodText(payment.payment_method) }}
                </span>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Payment Date</label>
                <p class="mt-1 text-gray-900">{{ formatDate(payment.payment_date) }}</p>
              </div>
              <div v-if="payment.due_date">
                <label class="block text-sm font-medium text-gray-700">Due Date</label>
                <p class="mt-1 text-gray-900">{{ formatDate(payment.due_date) }}</p>
              </div>
              <div v-if="payment.reference_number">
                <label class="block text-sm font-medium text-gray-700">Reference Number</label>
                <p class="mt-1 text-gray-900">{{ payment.reference_number }}</p>
              </div>
            </div>
            
            <div v-if="payment.description" class="mt-4">
              <label class="block text-sm font-medium text-gray-700">Description</label>
              <p class="mt-1 text-gray-900">{{ payment.description }}</p>
            </div>
            
            <div v-if="payment.notes" class="mt-4">
              <label class="block text-sm font-medium text-gray-700">Notes</label>
              <p class="mt-1 text-gray-900">{{ payment.notes }}</p>
            </div>
          </div>

          <!-- Purchase Order Information -->
          <div v-if="payment.purchase_order_ops" class="bg-white rounded-2xl shadow-2xl p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Purchase Order Information</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700">PO Number</label>
                <p class="mt-1 text-lg font-semibold text-gray-900">{{ payment.purchase_order_ops.number }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">PO Date</label>
                <p class="mt-1 text-gray-900">{{ formatDate(payment.purchase_order_ops.date) }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">PO Status</label>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-blue-100 text-blue-800">
                  {{ payment.purchase_order_ops.status }}
                </span>
              </div>
              <div v-if="payment.purchase_order_ops.subtotal">
                <label class="block text-sm font-medium text-gray-700">Subtotal</label>
                <p class="mt-1 text-gray-900">{{ formatCurrency(payment.purchase_order_ops.subtotal) }}</p>
              </div>
              <div v-if="payment.purchase_order_ops.discount_total_percent > 0 || payment.purchase_order_ops.discount_total_amount > 0">
                <label class="block text-sm font-medium text-gray-700">Diskon Total PO</label>
                <p class="mt-1 text-red-600 font-semibold">
                  <span v-if="payment.purchase_order_ops.discount_total_percent > 0">{{ payment.purchase_order_ops.discount_total_percent }}%</span>
                  <span v-if="payment.purchase_order_ops.discount_total_percent > 0 && payment.purchase_order_ops.discount_total_amount > 0"> / </span>
                  <span v-if="payment.purchase_order_ops.discount_total_amount > 0">{{ formatCurrency(payment.purchase_order_ops.discount_total_amount) }}</span>
                </p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Grand Total</label>
                <p class="mt-1 text-lg font-bold text-blue-600">{{ formatCurrency(payment.purchase_order_ops.grand_total) }}</p>
              </div>
              <div v-if="payment.purchase_order_ops.payment_type">
                <label class="block text-sm font-medium text-gray-700">Metode Pembayaran</label>
                <div class="mt-1">
                  <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium"
                        :class="payment.purchase_order_ops.payment_type === 'lunas' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'">
                    <i :class="payment.purchase_order_ops.payment_type === 'lunas' ? 'fa fa-check-circle mr-1' : 'fa fa-calendar-alt mr-1'"></i>
                    {{ payment.purchase_order_ops.payment_type === 'lunas' ? 'Bayar Lunas' : 'Termin Bayar' }}
                  </span>
                </div>
                <p v-if="payment.purchase_order_ops.payment_type === 'termin' && payment.purchase_order_ops.payment_terms" class="mt-2 text-sm text-gray-700">
                  <strong>Detail Termin:</strong> {{ payment.purchase_order_ops.payment_terms }}
                </p>
              </div>
            </div>

            <!-- Payment Termin Progress (only for termin payment) -->
            <div v-if="payment.purchase_order_ops.payment_type === 'termin'" class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-xl">
              <div v-if="loadingPaymentInfo" class="text-center py-4">
                <i class="fas fa-spinner fa-spin text-blue-500"></i>
                <p class="text-sm text-gray-600 mt-2">Memuat informasi pembayaran...</p>
              </div>
              <div v-else-if="paymentInfo">
              <h3 class="text-lg font-semibold text-blue-800 mb-4 flex items-center">
                <i class="fa fa-chart-line mr-2"></i>
                Progress Pembayaran Termin
              </h3>
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div>
                  <label class="block text-xs font-medium text-gray-600 mb-1">Total PO</label>
                  <p class="text-lg font-bold text-gray-900">{{ formatCurrency(payment.purchase_order_ops.grand_total) }}</p>
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
              
              <!-- Progress Bar -->
              <div v-if="paymentInfo.remaining > 0" class="mb-4">
                <div class="w-full bg-gray-200 rounded-full h-3">
                  <div 
                    class="bg-blue-600 h-3 rounded-full transition-all duration-300" 
                    :style="{ width: `${(paymentInfo.total_paid / payment.purchase_order_ops.grand_total) * 100}%` }"
                  ></div>
                </div>
                <p class="text-xs text-gray-600 mt-1 text-center">
                  Progress: {{ ((paymentInfo.total_paid / payment.purchase_order_ops.grand_total) * 100).toFixed(1) }}%
                </p>
              </div>
              <div v-else class="mb-4 p-2 bg-green-100 border border-green-300 rounded-lg">
                <p class="text-sm text-green-800 text-center font-medium">
                  <i class="fa fa-check-circle mr-1"></i>
                  PO sudah lunas!
                </p>
              </div>

              <!-- Payment History -->
              <div v-if="paymentInfo.payment_history && paymentInfo.payment_history.length > 0" class="mt-4">
                <h4 class="text-sm font-semibold text-gray-700 mb-2">Riwayat Pembayaran</h4>
                <div class="space-y-2">
                  <div 
                    v-for="(hist, index) in paymentInfo.payment_history" 
                    :key="hist.id"
                    class="flex items-center justify-between p-2 bg-white rounded-lg border"
                    :class="hist.id === payment.id ? 'border-blue-500 bg-blue-50' : 'border-gray-200'"
                  >
                    <div class="flex items-center gap-3">
                      <span class="text-xs font-medium text-gray-500">#{{ hist.payment_sequence || (index + 1) }}</span>
                      <div>
                        <p class="text-sm font-medium text-gray-900">{{ hist.payment_number }}</p>
                        <p class="text-xs text-gray-500">{{ formatDate(hist.payment_date) }}</p>
                      </div>
                    </div>
                    <div class="text-right">
                      <p class="text-sm font-semibold text-gray-900">{{ formatCurrency(hist.amount) }}</p>
                      <span 
                        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                        :class="{
                          'bg-green-100 text-green-800': hist.status === 'paid',
                          'bg-yellow-100 text-yellow-800': hist.status === 'approved',
                          'bg-blue-100 text-blue-800': hist.status === 'pending'
                        }"
                      >
                        {{ hist.status }}
                      </span>
                    </div>
                  </div>
                </div>
              </div>
              </div>
              <div v-else class="text-center py-4 text-gray-500">
                <p class="text-sm">Tidak dapat memuat informasi pembayaran</p>
              </div>
            </div>

            <!-- PO Items -->
            <div v-if="payment.purchase_order_ops.items && payment.purchase_order_ops.items.length > 0" class="mt-6">
              <h3 class="text-lg font-semibold text-gray-800 mb-3">PO Items</h3>
              <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                  <thead class="bg-gray-50">
                    <tr>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Diskon</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                    </tr>
                  </thead>
                  <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="item in payment.purchase_order_ops.items" :key="item.id">
                      <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ item.item_name }}</td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ item.quantity }}</td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ item.unit }}</td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ formatCurrency(item.price) }}</td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-xs">
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
            </div>
          </div>

          <!-- Purchase Requisition Information -->
          <div v-if="payment.purchase_requisition || (payment.purchase_order_ops && payment.purchase_order_ops.source_pr)" class="bg-white rounded-2xl shadow-2xl p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Purchase Requisition Information</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700">PR Number</label>
                <p class="mt-1 text-lg font-semibold text-gray-900">{{ (payment.purchase_requisition || payment.purchase_order_ops?.source_pr)?.pr_number || '-' }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">PR Date</label>
                <p class="mt-1 text-gray-900">{{ formatDate((payment.purchase_requisition || payment.purchase_order_ops?.source_pr)?.date) }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Title</label>
                <p class="mt-1 text-gray-900">{{ (payment.purchase_requisition || payment.purchase_order_ops?.source_pr)?.title || '-' }}</p>
              </div>
              <div v-if="(payment.purchase_requisition?.outlet) || (payment.purchase_order_ops?.source_pr?.outlet)">
                <label class="block text-sm font-medium text-gray-700">Outlet</label>
                <p class="mt-1 text-gray-900">{{ (payment.purchase_requisition?.outlet || payment.purchase_order_ops?.source_pr?.outlet)?.nama_outlet || '-' }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Amount</label>
                <p class="mt-1 text-lg font-bold text-green-600">{{ formatCurrency((payment.purchase_requisition || payment.purchase_order_ops?.source_pr)?.amount || 0) }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Status</label>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-green-100 text-green-800">
                  {{ (payment.purchase_requisition || payment.purchase_order_ops?.source_pr)?.status || '-' }}
                </span>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Priority</label>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-yellow-100 text-yellow-800">
                  {{ (payment.purchase_requisition || payment.purchase_order_ops?.source_pr)?.priority || '-' }}
                </span>
              </div>
            </div>
            
            <div v-if="(payment.purchase_requisition || payment.purchase_order_ops?.source_pr)?.description" class="mt-4">
              <label class="block text-sm font-medium text-gray-700">Description</label>
              <p class="mt-1 text-gray-900">{{ (payment.purchase_requisition || payment.purchase_order_ops?.source_pr)?.description }}</p>
            </div>
          </div>

          <!-- Retail Non Food Information -->
          <div v-if="payment.retail_non_food" class="bg-white rounded-2xl shadow-2xl p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Retail Non Food Information</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700">Retail Number</label>
                <p class="mt-1 text-lg font-semibold text-gray-900">{{ payment.retail_non_food.retail_number || '-' }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Transaction Date</label>
                <p class="mt-1 text-gray-900">{{ formatDate(payment.retail_non_food.transaction_date) }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Total Amount</label>
                <p class="mt-1 text-lg font-bold text-green-600">{{ formatCurrency(payment.retail_non_food.total_amount || 0) }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Status</label>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-green-100 text-green-800">
                  {{ payment.retail_non_food.status || '-' }}
                </span>
              </div>
            </div>
            
            <div v-if="payment.retail_non_food.notes" class="mt-4">
              <label class="block text-sm font-medium text-gray-700">Notes</label>
              <p class="mt-1 text-gray-900">{{ payment.retail_non_food.notes }}</p>
            </div>
          </div>

          <!-- Attachments Section -->
          <div v-if="(po_attachments && po_attachments.length > 0) || (pr_attachments && pr_attachments.length > 0) || (retail_non_food_attachments && retail_non_food_attachments.length > 0)" class="bg-white rounded-2xl shadow-2xl p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Attachments</h2>
            
            <!-- PO Attachments -->
            <div v-if="po_attachments && po_attachments.length > 0" class="mb-6">
              <h3 class="text-lg font-semibold text-gray-700 mb-3">Purchase Order Attachments</h3>
              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div v-for="attachment in po_attachments" :key="`po-${attachment.id}`" class="border border-gray-200 rounded-lg p-3">
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
                        :href="attachment.file_path" 
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
            <div v-if="pr_attachments && pr_attachments.length > 0" class="mb-6">
              <h3 class="text-lg font-semibold text-gray-700 mb-3">Purchase Requisition Attachments</h3>
              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div v-for="attachment in pr_attachments" :key="`pr-${attachment.id}`" class="border border-gray-200 rounded-lg p-3">
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
                      <p v-if="attachment.pr_description" class="text-xs text-green-300">{{ attachment.pr_description }}</p>
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
                      <p v-if="attachment.pr_description" class="text-xs text-green-600 mt-1">{{ attachment.pr_description }}</p>
                    </div>
                    <div class="flex-shrink-0">
                      <a 
                        :href="attachment.file_path" 
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

            <!-- Retail Non Food Attachments -->
            <div v-if="retail_non_food_attachments && retail_non_food_attachments.length > 0">
              <h3 class="text-lg font-semibold text-gray-700 mb-3">Retail Non Food Attachments</h3>
              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div v-for="attachment in retail_non_food_attachments" :key="`rnf-${attachment.id}`" class="border border-gray-200 rounded-lg p-3">
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
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
          <!-- Actions -->
          <div class="bg-white rounded-2xl shadow-2xl p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Actions</h2>
            <div class="space-y-3">
              <button v-if="payment.status === 'pending'" @click="approvePayment" class="w-full bg-green-500 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
                <i class="fa fa-check mr-2"></i> Approve
              </button>
              <button v-if="payment.status === 'pending'" @click="rejectPayment" class="w-full bg-red-500 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
                <i class="fa fa-times mr-2"></i> Reject
              </button>
              <button v-if="payment.status === 'approved'" @click="markAsPaid" class="w-full bg-blue-500 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
                <i class="fa fa-money-bill-wave mr-2"></i> Mark as Paid
              </button>
              <button v-if="['pending', 'approved'].includes(payment.status)" @click="cancelPayment" class="w-full bg-gray-500 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
                <i class="fa fa-ban mr-2"></i> Cancel
              </button>
            </div>
          </div>

          <!-- Payment Details -->
          <div class="bg-white rounded-2xl shadow-2xl p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Payment Details</h2>
            <div class="space-y-3">
              <div>
                <label class="block text-sm font-medium text-gray-700">Created By</label>
                <p class="mt-1 text-gray-900">{{ payment.creator?.nama_lengkap || '-' }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Created At</label>
                <p class="mt-1 text-gray-900">{{ formatDateTime(payment.created_at) }}</p>
              </div>
              <div v-if="payment.status === 'rejected' && payment.approved_by">
                <label class="block text-sm font-medium text-gray-700">Rejected By</label>
                <p class="mt-1 text-gray-900">{{ payment.approver?.nama_lengkap || '-' }}</p>
              </div>
              <div v-if="payment.status === 'rejected' && payment.approved_at">
                <label class="block text-sm font-medium text-gray-700">Rejected At</label>
                <p class="mt-1 text-gray-900">{{ formatDateTime(payment.approved_at) }}</p>
              </div>
              <div v-if="payment.status !== 'rejected' && payment.approved_by">
                <label class="block text-sm font-medium text-gray-700">Approved By</label>
                <p class="mt-1 text-gray-900">{{ payment.approver?.nama_lengkap || '-' }}</p>
              </div>
              <div v-if="payment.status !== 'rejected' && payment.approved_at">
                <label class="block text-sm font-medium text-gray-700">Approved At</label>
                <p class="mt-1 text-gray-900">{{ formatDateTime(payment.approved_at) }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>
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
import { ref, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

const props = defineProps({
  payment: Object,
  po_attachments: Array,
  pr_attachments: Array,
  retail_non_food_attachments: Array
});

const payment = props.payment;
const lightboxImage = ref(null);
const lightboxVisible = ref(false);
const paymentInfo = ref(null);
const loadingPaymentInfo = ref(false);

function formatDate(date) {
  if (!date) return '-';
  const d = new Date(date);
  return d.toLocaleDateString('id-ID', { year: 'numeric', month: 'short', day: 'numeric' });
}

function formatDateTime(date) {
  if (!date) return '-';
  const d = new Date(date);
  return d.toLocaleString('id-ID', { 
    year: 'numeric', 
    month: 'short', 
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
}

function formatCurrency(value) {
  if (value == null) return '-';
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(value);
}

function getStatusClass(status) {
  return {
    pending: 'bg-yellow-100 text-yellow-800',
    approved: 'bg-green-100 text-green-800',
    paid: 'bg-blue-100 text-blue-800',
    rejected: 'bg-red-100 text-red-800',
    cancelled: 'bg-gray-100 text-gray-800'
  }[status] || 'bg-gray-100 text-gray-800';
}

function getStatusText(status) {
  return {
    pending: 'Pending',
    approved: 'Approved',
    paid: 'Paid',
    rejected: 'Rejected',
    cancelled: 'Cancelled'
  }[status] || status;
}

function getPaymentMethodClass(method) {
  return {
    cash: 'bg-green-100 text-green-800',
    transfer: 'bg-blue-100 text-blue-800',
    check: 'bg-purple-100 text-purple-800'
  }[method] || 'bg-gray-100 text-gray-800';
}

function getPaymentMethodText(method) {
  return {
    cash: 'Cash',
    transfer: 'Transfer',
    check: 'Check'
  }[method] || method;
}

function goBack() {
  router.get('/non-food-payments');
}

function editPayment() {
  router.visit(`/non-food-payments/${payment.id}/edit`);
}

function approvePayment() {
  import('sweetalert2').then(({ default: Swal }) => {
    Swal.fire({
      title: 'Approve Payment?',
      text: 'Apakah Anda yakin ingin menyetujui payment ini?',
      icon: 'question',
      showCancelButton: true,
      confirmButtonColor: '#3085d6',
      cancelButtonColor: '#d33',
      confirmButtonText: 'Ya, Approve!',
      cancelButtonText: 'Batal'
    }).then((result) => {
      if (result.isConfirmed) {
        router.post(`/non-food-payments/${payment.id}/approve`, {}, {
          onSuccess: () => {
            Swal.fire('Berhasil', 'Payment berhasil disetujui!', 'success');
          },
          onError: () => {
            Swal.fire('Gagal', 'Gagal menyetujui Payment', 'error');
          }
        });
      }
    });
  });
}

function rejectPayment() {
  import('sweetalert2').then(({ default: Swal }) => {
    Swal.fire({
      title: 'Reject Payment?',
      text: 'Apakah Anda yakin ingin menolak payment ini?',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#3085d6',
      confirmButtonText: 'Ya, Reject!',
      cancelButtonText: 'Batal'
    }).then((result) => {
      if (result.isConfirmed) {
        router.post(`/non-food-payments/${payment.id}/reject`, {}, {
          onSuccess: () => {
            Swal.fire('Berhasil', 'Payment berhasil ditolak!', 'success');
          },
          onError: () => {
            Swal.fire('Gagal', 'Gagal menolak Payment', 'error');
          }
        });
      }
    });
  });
}

function markAsPaid() {
  import('sweetalert2').then(({ default: Swal }) => {
    Swal.fire({
      title: 'Tandai sebagai Dibayar?',
      text: 'Apakah Anda yakin payment ini sudah dibayar?',
      icon: 'question',
      showCancelButton: true,
      confirmButtonColor: '#3085d6',
      cancelButtonColor: '#d33',
      confirmButtonText: 'Ya, Tandai!',
      cancelButtonText: 'Batal'
    }).then((result) => {
      if (result.isConfirmed) {
        router.post(`/non-food-payments/${payment.id}/mark-as-paid`, {}, {
          onSuccess: () => {
            Swal.fire('Berhasil', 'Payment berhasil ditandai sebagai dibayar!', 'success');
          },
          onError: () => {
            Swal.fire('Gagal', 'Gagal menandai Payment sebagai dibayar', 'error');
          }
        });
      }
    });
  });
}

function cancelPayment() {
  import('sweetalert2').then(({ default: Swal }) => {
    Swal.fire({
      title: 'Cancel Payment?',
      text: 'Apakah Anda yakin ingin membatalkan payment ini?',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#3085d6',
      confirmButtonText: 'Ya, Cancel!',
      cancelButtonText: 'Batal'
    }).then((result) => {
      if (result.isConfirmed) {
        router.post(`/non-food-payments/${payment.id}/cancel`, {}, {
          onSuccess: () => {
            Swal.fire('Berhasil', 'Payment berhasil dibatalkan!', 'success');
          },
          onError: () => {
            Swal.fire('Gagal', 'Gagal membatalkan Payment', 'error');
          }
        });
      }
    });
  });
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

function printPayment() {
  // Open print preview in new window
  const printUrl = `/non-food-payments/print-preview?ids=${payment.id}`;
  window.open(printUrl, '_blank', 'width=800,height=600,scrollbars=yes,resizable=yes');
}

// Fetch payment info for termin payment
async function fetchPaymentInfo() {
  if (payment.purchase_order_ops_id && payment.purchase_order_ops?.payment_type === 'termin') {
    loadingPaymentInfo.value = true;
    try {
      const response = await axios.get(`/api/non-food-payments/payment-info/${payment.purchase_order_ops_id}`);
      paymentInfo.value = response.data;
    } catch (error) {
      console.error('Error fetching payment info:', error);
      paymentInfo.value = {
        total_paid: 0,
        remaining: payment.purchase_order_ops?.grand_total || 0,
        payment_count: 0,
        payment_history: []
      };
    } finally {
      loadingPaymentInfo.value = false;
    }
  }
}

onMounted(() => {
  fetchPaymentInfo();
});
</script>
