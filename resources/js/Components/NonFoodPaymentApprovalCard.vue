<template>
    <div>
        <!-- Non Food Payment Approval Section -->
        <div v-if="approvalCount > 0" class="flex-shrink-0 mb-4">
            <div class="backdrop-blur-md rounded-2xl shadow-2xl border p-4 transition-all duration-500 animate-fade-in hover:shadow-3xl"
                :class="isNight ? 'bg-slate-800/90 border-slate-600/50' : 'bg-white/90 border-white/20'">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <div class="w-3 h-3 rounded-full bg-orange-500 animate-pulse"></div>
                        <h3 class="text-lg font-bold" :class="isNight ? 'text-white' : 'text-slate-800'">
                            <i class="fa fa-money-bill-wave mr-2 text-orange-500"></i>
                            Non Food Payment Approval
                        </h3>
                    </div>
                    <div class="flex items-center gap-2">
                        <button 
                            v-if="!isSelecting"
                            @click.stop="isSelecting = true"
                            class="text-xs bg-orange-500 text-white px-2 py-1 rounded hover:bg-orange-600 transition"
                        >
                            <i class="fa fa-check-square mr-1"></i>Multi Approve
                        </button>
                        <button 
                            v-else
                            @click.stop="isSelecting = false; selectedApprovals.clear()"
                            class="text-xs bg-gray-500 text-white px-2 py-1 rounded hover:bg-gray-600 transition"
                        >
                            <i class="fa fa-times mr-1"></i>Cancel
                        </button>
                        <div class="bg-orange-500 text-white text-xs font-bold px-2 py-1 rounded-full">
                            {{ approvalCount }}
                        </div>
                    </div>
                </div>
                
                <!-- Multi-approve actions -->
                <div v-if="isSelecting && selectedApprovals.size > 0" class="mb-3 p-2 bg-orange-100 dark:bg-orange-900/30 rounded-lg flex items-center justify-between">
                    <span class="text-sm font-medium text-orange-800 dark:text-orange-200">
                        {{ selectedApprovals.size }} item dipilih
                    </span>
                    <div class="flex gap-2">
                        <button 
                            @click="selectAllApprovals"
                            class="text-xs bg-blue-500 text-white px-2 py-1 rounded hover:bg-blue-600 transition"
                        >
                            <i class="fa fa-check-double mr-1"></i>Select All
                        </button>
                        <button 
                            @click="approveMultiple"
                            class="text-xs bg-orange-600 text-white px-2 py-1 rounded hover:bg-orange-700 transition"
                        >
                            <i class="fa fa-check mr-1"></i>Approve Selected
                        </button>
                    </div>
                </div>
                
                <div v-if="loading" class="text-center py-4">
                    <div class="inline-block animate-spin rounded-full h-6 w-6 border-b-2 border-orange-500"></div>
                    <p class="text-sm mt-2" :class="isNight ? 'text-slate-300' : 'text-slate-600'">Memuat data...</p>
                </div>
                
                <div v-else class="space-y-2">
                    <!-- Non Food Payment Approvals -->
                    <div v-for="nfp in pendingApprovals.slice(0, 3)" :key="'non-food-payment-approval-' + nfp.id"
                        @click="isSelecting ? toggleSelection(nfp.id) : showDetails(nfp.id)"
                        class="p-3 rounded-lg transition-all duration-200"
                        :class="[
                            isSelecting ? 'cursor-default' : 'cursor-pointer hover:scale-105',
                            isNight ? 'bg-slate-700/50 hover:bg-slate-600/50' : 'bg-orange-50 hover:bg-orange-100',
                            selectedApprovals.has(nfp.id) ? 'ring-2 ring-orange-500' : ''
                        ]">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2 flex-1">
                                <input 
                                    v-if="isSelecting"
                                    type="checkbox"
                                    :checked="selectedApprovals.has(nfp.id)"
                                    @click.stop="toggleSelection(nfp.id)"
                                    class="w-4 h-4 text-orange-600 rounded focus:ring-orange-500"
                                />
                                <div class="flex-1">
                                <div class="font-semibold text-sm" :class="isNight ? 'text-white' : 'text-slate-800'">
                                    {{ nfp.payment_number }}
                                </div>
                                <div class="text-xs" :class="isNight ? 'text-slate-300' : 'text-slate-600'">
                                    {{ nfp.supplier?.name || 'Unknown Supplier' }}
                                </div>
                                <div class="text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                                    <i class="fa fa-credit-card mr-1 text-orange-600"></i>
                                    {{ nfp.payment_method }}
                                </div>
                                <div class="text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                                    <i class="fa fa-file-invoice mr-1 text-orange-600"></i>
                                    {{ nfp.payment_type }}: {{ nfp.source_number || '-' }}
                                </div>
                                <div class="text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                                    Rp {{ new Intl.NumberFormat('id-ID').format(nfp.amount) }}
                                </div>
                                <div class="text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                                    <i class="fa fa-user mr-1 text-orange-500"></i>{{ nfp.creator?.nama_lengkap }}
                                </div>
                                <div class="text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                                    {{ formatDate(nfp.payment_date) }}
                                </div>
                                </div>
                            </div>
                            <div class="text-xs text-orange-500 font-medium">
                                <i class="fa fa-user-check mr-1"></i>{{ nfp.approver_name || (nfp.approval_level || 'Finance Manager') }}
                            </div>
                        </div>
                    </div>
                    
                    <!-- Show more button if there are more than 3 Non Food Payments -->
                    <div v-if="pendingApprovals.length > 3" class="text-center pt-2">
                        <button @click="openAllModal" class="text-sm text-orange-500 hover:text-orange-700 font-medium">
                            Lihat {{ pendingApprovals.length - 3 }} Non Food Payment lainnya...
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Non Food Payment Approval Detail Modal -->
        <Teleport to="body">
            <div v-if="showDetailModal && selectedPayment" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[9999]" @click="closeDetailModal">
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-full max-w-4xl mx-4 max-h-[90vh] overflow-y-auto" @click.stop>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                        <i class="fa fa-money-bill-wave mr-2 text-orange-500"></i>
                        Detail Non Food Payment Approval
                    </h3>
                    <button @click="closeDetailModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fa fa-times text-xl"></i>
                    </button>
                </div>

                <div v-if="loadingDetail" class="text-center py-8">
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-orange-500"></div>
                    <p class="text-sm mt-2 text-gray-600 dark:text-gray-400">Memuat detail...</p>
                </div>

                <div v-else class="space-y-6">
                    <!-- Basic Information -->
                    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                        <h4 class="text-md font-semibold text-gray-900 dark:text-white mb-3">Informasi Dasar</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Payment Number</label>
                                <p class="text-gray-900 dark:text-white font-semibold">{{ selectedPayment.payment_number }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Payment Date</label>
                                <p class="text-gray-900 dark:text-white">{{ new Date(selectedPayment.payment_date).toLocaleDateString('id-ID') }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Supplier</label>
                                <p class="text-gray-900 dark:text-white">{{ selectedPayment.supplier?.name || 'Unknown' }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Payment Method</label>
                                <p class="text-gray-900 dark:text-white">{{ selectedPayment.payment_method }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Amount</label>
                                <p class="text-gray-900 dark:text-white font-semibold text-lg">
                                    Rp {{ new Intl.NumberFormat('id-ID').format(selectedPayment.amount) }}
                                </p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Payment Type</label>
                                <p class="text-gray-900 dark:text-white">{{ selectedPayment.payment_type }}: {{ selectedPayment.source_info?.number || selectedPayment.source_info?.pr_number || '-' }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Created By</label>
                                <p class="text-gray-900 dark:text-white">{{ selectedPayment.creator?.nama_lengkap || 'Unknown' }}</p>
                            </div>
                            <div v-if="selectedPayment.due_date">
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Due Date</label>
                                <p class="text-gray-900 dark:text-white">{{ new Date(selectedPayment.due_date).toLocaleDateString('id-ID') }}</p>
                            </div>
                        </div>
                        <div v-if="selectedPayment.description" class="mt-4">
                            <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Description</label>
                            <p class="text-gray-900 dark:text-white">{{ selectedPayment.description }}</p>
                        </div>
                        <div v-if="selectedPayment.notes" class="mt-4">
                            <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Notes</label>
                            <p class="text-gray-900 dark:text-white">{{ selectedPayment.notes }}</p>
                        </div>
                    </div>

                    <!-- Source Information (PO or PR) -->
                    <div v-if="selectedPayment.source_info" class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg">
                        <h4 class="text-md font-semibold text-gray-900 dark:text-white mb-3">
                            <i class="fa fa-file-invoice mr-2 text-blue-500"></i>
                            Informasi {{ selectedPayment.source_info.type }}
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div v-if="selectedPayment.source_info.type === 'PO'">
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">PO Number</label>
                                <p class="text-gray-900 dark:text-white font-semibold">{{ selectedPayment.source_info.number }}</p>
                            </div>
                            <div v-if="selectedPayment.source_info.type === 'PR'">
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">PR Number</label>
                                <p class="text-gray-900 dark:text-white font-semibold">{{ selectedPayment.source_info.pr_number }}</p>
                            </div>
                            <div v-if="selectedPayment.source_info.date">
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Date</label>
                                <p class="text-gray-900 dark:text-white">{{ new Date(selectedPayment.source_info.date).toLocaleDateString('id-ID') }}</p>
                            </div>
                            <div v-if="selectedPayment.source_info.title">
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Title</label>
                                <p class="text-gray-900 dark:text-white">{{ selectedPayment.source_info.title }}</p>
                            </div>
                            <div v-if="selectedPayment.source_info.subtotal">
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Subtotal</label>
                                <p class="text-gray-900 dark:text-white font-semibold">
                                    Rp {{ new Intl.NumberFormat('id-ID').format(selectedPayment.source_info.subtotal) }}
                                </p>
                            </div>
                            <div v-if="selectedPayment.source_info.type === 'PO' && (selectedPayment.source_info.discount_total_percent > 0 || selectedPayment.source_info.discount_total_amount > 0)">
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Diskon Total PO</label>
                                <p class="text-gray-900 dark:text-white font-semibold text-red-600">
                                    <span v-if="selectedPayment.source_info.discount_total_percent > 0">{{ selectedPayment.source_info.discount_total_percent }}%</span>
                                    <span v-if="selectedPayment.source_info.discount_total_percent > 0 && selectedPayment.source_info.discount_total_amount > 0"> / </span>
                                    <span v-if="selectedPayment.source_info.discount_total_amount > 0">Rp {{ new Intl.NumberFormat('id-ID').format(selectedPayment.source_info.discount_total_amount) }}</span>
                                </p>
                            </div>
                            <div v-if="selectedPayment.source_info.grand_total">
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Grand Total</label>
                                <p class="text-gray-900 dark:text-white font-semibold">
                                    Rp {{ new Intl.NumberFormat('id-ID').format(selectedPayment.source_info.grand_total) }}
                                </p>
                            </div>
                            <div v-if="selectedPayment.source_info.amount">
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Amount</label>
                                <p class="text-gray-900 dark:text-white font-semibold">
                                    Rp {{ new Intl.NumberFormat('id-ID').format(selectedPayment.source_info.amount) }}
                                </p>
                            </div>
                            <div v-if="selectedPayment.source_info.division">
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Division</label>
                                <p class="text-gray-900 dark:text-white">{{ selectedPayment.source_info.division.nama_divisi }}</p>
                            </div>
                            <div v-if="selectedPayment.source_info.outlet">
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Outlet</label>
                                <p class="text-gray-900 dark:text-white">{{ selectedPayment.source_info.outlet.nama_outlet }}</p>
                            </div>
                            <div v-if="selectedPayment.source_info.type === 'PR' && (selectedPayment.source_info.creator || selectedPayment.source_info.created_by_name)">
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Created By</label>
                                <p class="text-gray-900 dark:text-white">
                                    <i class="fa fa-user mr-1 text-gray-400"></i>
                                    {{ selectedPayment.source_info.creator?.nama_lengkap || selectedPayment.source_info.created_by_name || '-' }}
                                </p>
                            </div>
                            <div v-if="selectedPayment.source_info.supplier">
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Supplier</label>
                                <p class="text-gray-900 dark:text-white">{{ selectedPayment.source_info.supplier.name }}</p>
                            </div>
                            <div v-if="selectedPayment.source_info.source_pr">
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Source PR</label>
                                <p class="text-gray-900 dark:text-white">{{ selectedPayment.source_info.source_pr.pr_number }} - {{ selectedPayment.source_info.source_pr.title }}</p>
                            </div>
                        </div>
                        <div v-if="selectedPayment.source_info.description" class="mt-4">
                            <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Description</label>
                            <p class="text-gray-900 dark:text-white">{{ selectedPayment.source_info.description }}</p>
                        </div>
                    </div>

                    <!-- Items by Outlet -->
                    <div v-if="selectedPayment.source_info && selectedPayment.source_info.items_by_outlet && selectedPayment.source_info.items_by_outlet.length > 0" 
                         class="space-y-4">
                        <div v-for="(outletGroup, outletIndex) in selectedPayment.source_info.items_by_outlet" 
                             :key="'outlet-' + outletIndex"
                             class="bg-green-50 dark:bg-green-900/20 p-4 rounded-lg">
                            <h4 class="text-md font-semibold text-gray-900 dark:text-white mb-3">
                                <i class="fa fa-store mr-2 text-green-500"></i>
                                {{ outletGroup.outlet_name }}
                                <span v-if="outletGroup.pr_number && selectedPayment.source_info && selectedPayment.source_info.type === 'PO'" class="text-sm font-normal text-gray-600 dark:text-gray-400">
                                    (PR: {{ outletGroup.pr_number }})
                                </span>
                            </h4>
                            
                            <!-- PR Title and Description - Only show if payment is from PO (multiple PRs) -->
                            <template v-if="selectedPayment.source_info && selectedPayment.source_info.type === 'PO'">
                                <div v-if="outletGroup.pr_title" class="mb-3">
                                    <label class="text-sm font-medium text-gray-600 dark:text-gray-400">PR Title</label>
                                    <p class="text-gray-900 dark:text-white">{{ outletGroup.pr_title }}</p>
                                </div>
                                <div v-if="outletGroup.pr_description" class="mb-3">
                                    <label class="text-sm font-medium text-gray-600 dark:text-gray-400">PR Description</label>
                                    <p class="text-gray-900 dark:text-white">{{ outletGroup.pr_description }}</p>
                                </div>
                            </template>
                            
                            <!-- Category Info -->
                            <div v-if="outletGroup.category_name" class="mb-3 grid grid-cols-1 md:grid-cols-2 gap-2 text-xs">
                                <div>
                                    <span class="text-gray-600 dark:text-gray-400">Category: </span>
                                    <span class="text-gray-900 dark:text-white font-medium">{{ outletGroup.category_name }}</span>
                                </div>
                                <div v-if="outletGroup.category_division">
                                    <span class="text-gray-600 dark:text-gray-400">Division: </span>
                                    <span class="text-gray-900 dark:text-white">{{ outletGroup.category_division }}</span>
                                </div>
                                <div v-if="outletGroup.category_subcategory">
                                    <span class="text-gray-600 dark:text-gray-400">Subcategory: </span>
                                    <span class="text-gray-900 dark:text-white">{{ outletGroup.category_subcategory }}</span>
                                </div>
                                <div v-if="outletGroup.category_budget_type">
                                    <span class="text-gray-600 dark:text-gray-400">Budget Type: </span>
                                    <span class="text-gray-900 dark:text-white">{{ outletGroup.category_budget_type }}</span>
                                </div>
                            </div>
                            
                            <!-- Items Table -->
                            <div class="overflow-x-auto mb-3">
                                <table class="min-w-full text-xs">
                                    <thead class="bg-gray-100 dark:bg-gray-700">
                                        <tr>
                                            <th class="px-2 py-1 text-left text-gray-700 dark:text-gray-300">Item</th>
                                            <th class="px-2 py-1 text-left text-gray-700 dark:text-gray-300">Qty</th>
                                            <th class="px-2 py-1 text-left text-gray-700 dark:text-gray-300">Unit</th>
                                            <th class="px-2 py-1 text-left text-gray-700 dark:text-gray-300">Price</th>
                                            <th v-if="selectedPayment.source_info && selectedPayment.source_info.type === 'PO'" class="px-2 py-1 text-left text-gray-700 dark:text-gray-300">Diskon</th>
                                            <th class="px-2 py-1 text-left text-gray-700 dark:text-gray-300">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="item in outletGroup.items" :key="'item-' + item.id" 
                                            class="border-t border-gray-200 dark:border-gray-600">
                                            <td class="px-2 py-1 text-gray-900 dark:text-white">
                                                {{ item.item_name }}
                                                <div v-if="item.others_notes" class="text-xs text-gray-500 italic mt-1">
                                                    Note: {{ item.others_notes }}
                                                </div>
                                            </td>
                                            <td class="px-2 py-1 text-gray-900 dark:text-white">{{ item.quantity || 0 }}</td>
                                            <td class="px-2 py-1 text-gray-900 dark:text-white">{{ item.unit || '-' }}</td>
                                            <td class="px-2 py-1 text-gray-900 dark:text-white">Rp {{ new Intl.NumberFormat('id-ID').format(item.price || 0) }}</td>
                                            <td v-if="selectedPayment.source_info && selectedPayment.source_info.type === 'PO'" class="px-2 py-1 text-xs">
                                                <div v-if="item.discount_percent > 0 || item.discount_amount > 0" class="text-red-600">
                                                    <div v-if="item.discount_percent > 0">{{ item.discount_percent }}%</div>
                                                    <div v-if="item.discount_amount > 0">Rp {{ new Intl.NumberFormat('id-ID').format(item.discount_amount || 0) }}</div>
                                                </div>
                                                <span v-else class="text-gray-400">-</span>
                                            </td>
                                            <td class="px-2 py-1 text-gray-900 dark:text-white font-semibold">Rp {{ new Intl.NumberFormat('id-ID').format(item.total || 0) }}</td>
                                        </tr>
                                    </tbody>
                                    <tfoot class="bg-gray-50 dark:bg-gray-800">
                                        <tr>
                                            <td :colspan="selectedPayment.source_info && selectedPayment.source_info.type === 'PO' ? 4 : 3" class="px-2 py-1 text-right font-medium text-gray-700 dark:text-gray-300">Subtotal:</td>
                                            <td v-if="selectedPayment.source_info && selectedPayment.source_info.type === 'PO'"></td>
                                            <td class="px-2 py-1 font-semibold text-gray-900 dark:text-white">
                                                Rp {{ new Intl.NumberFormat('id-ID').format(outletGroup.subtotal || 0) }}
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            
                            <!-- PR Attachments for this outlet -->
                            <div v-if="outletGroup.pr_attachments && outletGroup.pr_attachments.length > 0" class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-600">
                                <h5 class="text-sm font-semibold text-gray-900 dark:text-white mb-2">
                                    <i class="fa fa-paperclip mr-1 text-green-500"></i>
                                    PR Attachments ({{ outletGroup.pr_attachments.length }})
                                </h5>
                                <div class="flex flex-wrap gap-2">
                                    <!-- Image attachments with thumbnail -->
                                    <div v-for="attachment in outletGroup.pr_attachments" 
                                         :key="'pr-attachment-' + attachment.id"
                                         class="relative">
                                        <div v-if="isImageFile(attachment.file_name)" 
                                             @click="openLightbox(`/purchase-requisitions/attachments/${attachment.id}/download`, attachment.file_name)"
                                             class="cursor-pointer group">
                                            <img :src="`/purchase-requisitions/attachments/${attachment.id}/download`" 
                                                 :alt="attachment.file_name"
                                                 class="w-20 h-20 object-cover rounded border-2 border-green-200 dark:border-green-700 hover:border-green-400 dark:hover:border-green-500 transition-all hover:scale-105 shadow-md"
                                                 @error="handleImageError($event)" />
                                            <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-30 rounded transition-all flex items-center justify-center">
                                                <i class="fa fa-search-plus text-white opacity-0 group-hover:opacity-100 transition-opacity"></i>
                                            </div>
                                            <div class="text-xs text-gray-600 dark:text-gray-400 mt-1 truncate w-20" :title="attachment.file_name">
                                                {{ attachment.file_name }}
                                            </div>
                                        </div>
                                        <!-- PDF/DOC attachments - direct download -->
                                        <a v-else
                                           :href="`/purchase-requisitions/attachments/${attachment.id}/download`" 
                                           @click="handleFileDownload($event, attachment.file_name)"
                                           class="inline-flex items-center px-2 py-1 bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300 rounded text-xs hover:bg-green-200 dark:hover:bg-green-800 transition">
                                            <i :class="getFileIcon(attachment.file_name) + ' mr-1'"></i>
                                            {{ attachment.file_name }}
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- PO Attachments (if payment is for PO) -->
                    <div v-if="selectedPayment.po_attachments && selectedPayment.po_attachments.length > 0" 
                         class="bg-purple-50 dark:bg-purple-900/20 p-4 rounded-lg">
                        <h4 class="text-md font-semibold text-gray-900 dark:text-white mb-3">
                            <i class="fa fa-paperclip mr-2 text-purple-500"></i>
                            PO Attachments ({{ selectedPayment.po_attachments.length }})
                        </h4>
                        <div class="flex flex-wrap gap-2">
                            <!-- Image attachments with thumbnail -->
                            <div v-for="attachment in selectedPayment.po_attachments" 
                                 :key="'po-attachment-' + attachment.id"
                                 class="relative">
                                <div v-if="isImageFile(attachment.file_name)" 
                                     @click="openLightbox(`/po-ops/attachments/${attachment.id}/download`, attachment.file_name)"
                                     class="cursor-pointer group">
                                    <img :src="`/po-ops/attachments/${attachment.id}/download`" 
                                         :alt="attachment.file_name"
                                         class="w-24 h-24 object-cover rounded border-2 border-purple-200 dark:border-purple-700 hover:border-purple-400 dark:hover:border-purple-500 transition-all hover:scale-105 shadow-md"
                                         @error="handleImageError($event)" />
                                    <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-30 rounded transition-all flex items-center justify-center">
                                        <i class="fa fa-search-plus text-white opacity-0 group-hover:opacity-100 transition-opacity"></i>
                                    </div>
                                    <div class="text-xs text-gray-600 dark:text-gray-400 mt-1 truncate w-24" :title="attachment.file_name">
                                        {{ attachment.file_name }}
                                    </div>
                                </div>
                                <!-- PDF/DOC attachments - direct download -->
                                <a v-else
                                   :href="`/po-ops/attachments/${attachment.id}/download`" 
                                   @click="handleFileDownload($event, attachment.file_name)"
                                   class="inline-flex items-center px-3 py-2 bg-purple-100 dark:bg-purple-900 text-purple-700 dark:text-purple-300 rounded text-sm hover:bg-purple-200 dark:hover:bg-purple-800 transition">
                                    <i :class="getFileIcon(attachment.file_name) + ' mr-2'"></i>
                                    {{ attachment.file_name }}
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- PR Attachments (if payment is for PR directly) -->
                    <div v-if="selectedPayment.pr_attachments && selectedPayment.pr_attachments.length > 0 && selectedPayment.source_info && selectedPayment.source_info.type === 'PR'" 
                         class="bg-purple-50 dark:bg-purple-900/20 p-4 rounded-lg">
                        <h4 class="text-md font-semibold text-gray-900 dark:text-white mb-3">
                            <i class="fa fa-paperclip mr-2 text-purple-500"></i>
                            PR Attachments ({{ selectedPayment.pr_attachments.length }})
                        </h4>
                        <div class="flex flex-wrap gap-2">
                            <!-- Image attachments with thumbnail -->
                            <div v-for="attachment in selectedPayment.pr_attachments" 
                                 :key="'pr-attachment-' + attachment.id"
                                 class="relative">
                                <div v-if="isImageFile(attachment.file_name)" 
                                     @click="openLightbox(`/purchase-requisitions/attachments/${attachment.id}/download`, attachment.file_name)"
                                     class="cursor-pointer group">
                                    <img :src="`/purchase-requisitions/attachments/${attachment.id}/download`" 
                                         :alt="attachment.file_name"
                                         class="w-24 h-24 object-cover rounded border-2 border-purple-200 dark:border-purple-700 hover:border-purple-400 dark:hover:border-purple-500 transition-all hover:scale-105 shadow-md"
                                         @error="handleImageError($event)" />
                                    <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-30 rounded transition-all flex items-center justify-center">
                                        <i class="fa fa-search-plus text-white opacity-0 group-hover:opacity-100 transition-opacity"></i>
                                    </div>
                                    <div class="text-xs text-gray-600 dark:text-gray-400 mt-1 truncate w-24" :title="attachment.file_name">
                                        {{ attachment.file_name }}
                                    </div>
                                </div>
                                <!-- PDF/DOC attachments - direct download -->
                                <a v-else
                                   :href="`/purchase-requisitions/attachments/${attachment.id}/download`" 
                                   @click="handleFileDownload($event, attachment.file_name)"
                                   class="inline-flex items-center px-3 py-2 bg-purple-100 dark:bg-purple-900 text-purple-700 dark:text-purple-300 rounded text-sm hover:bg-purple-200 dark:hover:bg-purple-800 transition">
                                    <i :class="getFileIcon(attachment.file_name) + ' mr-2'"></i>
                                    {{ attachment.file_name }}
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Approval Actions -->
                    <div class="flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <button @click="showRejectModal" 
                                :disabled="isRejecting"
                                class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                            <i v-if="!isRejecting" class="fa fa-times mr-2"></i>
                            <i v-else class="fa fa-spinner fa-spin mr-2"></i>
                            {{ isRejecting ? 'Memproses...' : 'Tolak' }}
                        </button>
                        <button @click="approvePayment" 
                                :disabled="isApproving"
                                class="px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                            <i v-if="!isApproving" class="fa fa-check mr-2"></i>
                            <i v-else class="fa fa-spinner fa-spin mr-2"></i>
                            {{ isApproving ? 'Memproses...' : 'Setujui' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
        </Teleport>

        <!-- Lightbox Modal for Images -->
        <Teleport to="body">
            <div v-if="lightboxImage" 
                 class="fixed inset-0 bg-black bg-opacity-90 flex items-center justify-center z-[10000]" 
                 @click="closeLightbox">
            <div class="relative max-w-7xl max-h-[95vh] p-4" @click.stop>
                <button @click="closeLightbox" 
                        class="absolute top-2 right-2 text-white hover:text-gray-300 text-3xl font-bold z-10 bg-black bg-opacity-50 rounded-full w-10 h-10 flex items-center justify-center">
                    <i class="fa fa-times"></i>
                </button>
                <img :src="lightboxImage" 
                     :alt="lightboxImageName"
                     class="max-w-full max-h-[90vh] object-contain rounded-lg shadow-2xl" />
                <div class="text-center mt-4 text-white">
                    <p class="text-sm">{{ lightboxImageName }}</p>
                </div>
            </div>
        </div>
        </Teleport>

        <!-- All Non Food Payment Modal -->
        <Teleport to="body">
            <div v-if="showAllModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[9998]" @click="closeAllModal">
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-full max-w-5xl mx-4 max-h-[90vh] overflow-y-auto" @click.stop>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                        <i class="fa fa-list mr-2 text-orange-500"></i>
                        Semua Non Food Payment Pending
                    </h3>
                    <div class="flex items-center gap-2">
                        <button 
                            v-if="!isSelectingAll"
                            @click.stop="isSelectingAll = true"
                            class="text-xs bg-orange-500 text-white px-2 py-1 rounded hover:bg-orange-600 transition"
                        >
                            <i class="fa fa-check-square mr-1"></i>Multi Approve
                        </button>
                        <button 
                            v-else
                            @click.stop="isSelectingAll = false; selectedAllApprovals.clear()"
                            class="text-xs bg-gray-500 text-white px-2 py-1 rounded hover:bg-gray-600 transition"
                        >
                            <i class="fa fa-times mr-1"></i>Cancel
                        </button>
                        <button @click="closeAllModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                            <i class="fa fa-times text-xl"></i>
                        </button>
                    </div>
                </div>

                <!-- Multi-approve actions -->
                <div v-if="isSelectingAll && selectedAllApprovals.size > 0" class="mb-4 p-2 bg-orange-100 dark:bg-orange-900/30 rounded-lg flex items-center justify-between">
                    <span class="text-sm font-medium text-orange-800 dark:text-orange-200">
                        {{ selectedAllApprovals.size }} item dipilih
                    </span>
                    <div class="flex gap-2">
                        <button 
                            @click="selectAllAllApprovals"
                            class="text-xs bg-blue-500 text-white px-2 py-1 rounded hover:bg-blue-600 transition"
                        >
                            <i class="fa fa-check-double mr-1"></i>Select All
                        </button>
                        <button 
                            @click="approveMultipleAll"
                            class="text-xs bg-orange-600 text-white px-2 py-1 rounded hover:bg-orange-700 transition"
                        >
                            <i class="fa fa-check mr-1"></i>Approve Selected
                        </button>
                    </div>
                </div>

                <!-- Filters and Search -->
                <div class="mb-4 space-y-3">
                    <div class="flex flex-wrap gap-3">
                        <input v-model="searchQuery" type="text" placeholder="Cari nomor, supplier, creator..." 
                               class="flex-1 min-w-[200px] px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                        <select v-model="dateFilter" class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                            <option value="">Semua Tanggal</option>
                            <option value="today">Hari Ini</option>
                            <option value="week">Minggu Ini</option>
                            <option value="month">Bulan Ini</option>
                        </select>
                        <select v-model="sortBy" class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                            <option value="newest">Terbaru</option>
                            <option value="oldest">Terlama</option>
                            <option value="number">Nomor</option>
                            <option value="amount">Jumlah</option>
                        </select>
                    </div>
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-600 dark:text-gray-400">
                            Menampilkan {{ paginatedApprovals.length }} dari {{ filteredApprovals.length }} Non Food Payment
                        </div>
                        <select v-model="perPage" @change="currentPage = 1" 
                                class="px-3 py-1 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm">
                            <option :value="10">10 per halaman</option>
                            <option :value="25">25 per halaman</option>
                            <option :value="50">50 per halaman</option>
                            <option :value="100">100 per halaman</option>
                        </select>
                    </div>
                </div>

                <!-- List -->
                <div v-if="loadingAll" class="text-center py-8">
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-orange-500"></div>
                    <p class="text-sm mt-2 text-gray-600 dark:text-gray-400">Memuat data...</p>
                </div>

                <div v-else-if="paginatedApprovals.length === 0" class="text-center py-8 text-gray-500 dark:text-gray-400">
                    Tidak ada Non Food Payment yang ditemukan
                </div>

                <div v-else class="space-y-2">
                    <div v-for="nfp in paginatedApprovals" :key="'all-nfp-' + nfp.id"
                         @click="isSelectingAll ? toggleAllSelection(nfp.id) : showDetails(nfp.id)"
                         class="p-3 rounded-lg transition-all duration-200 border border-gray-200 dark:border-gray-700"
                         :class="[
                             isSelectingAll ? 'cursor-default' : 'cursor-pointer hover:scale-[1.02] hover:border-orange-500 dark:hover:border-orange-500',
                             isNight ? 'bg-slate-700/50 hover:bg-slate-600/50' : 'bg-gray-50 hover:bg-orange-50',
                             selectedAllApprovals.has(nfp.id) ? 'ring-2 ring-orange-500' : ''
                         ]">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2 flex-1">
                                <input 
                                    v-if="isSelectingAll"
                                    type="checkbox"
                                    :checked="selectedAllApprovals.has(nfp.id)"
                                    @click.stop="toggleAllSelection(nfp.id)"
                                    class="w-4 h-4 text-orange-600 rounded focus:ring-orange-500"
                                />
                                <div class="flex-1">
                                <div class="font-semibold text-sm" :class="isNight ? 'text-white' : 'text-slate-800'">
                                    {{ nfp.payment_number }}
                                </div>
                                <div class="text-xs" :class="isNight ? 'text-slate-300' : 'text-slate-600'">
                                    {{ nfp.supplier?.name || 'Unknown Supplier' }}
                                </div>
                                <div class="text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                                    <i class="fa fa-credit-card mr-1 text-orange-600"></i>
                                    {{ nfp.payment_method }}
                                </div>
                                <div class="text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                                    <i class="fa fa-file-invoice mr-1 text-orange-600"></i>
                                    {{ nfp.payment_type }}: {{ nfp.source_number || '-' }}
                                </div>
                                <div class="text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                                    Rp {{ new Intl.NumberFormat('id-ID').format(nfp.amount) }}
                                </div>
                                <div class="text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                                    <i class="fa fa-user mr-1 text-orange-500"></i>{{ nfp.creator?.nama_lengkap }}
                                </div>
                                <div class="text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                                    {{ formatDate(nfp.payment_date) }}
                                </div>
                                </div>
                            </div>
                            <div class="text-xs text-orange-500 font-medium">
                                <i class="fa fa-user-check mr-1"></i>{{ nfp.approver_name || (nfp.approval_level || 'Finance Manager') }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pagination -->
                <div v-if="totalPages > 1" class="mt-4 flex items-center justify-between">
                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        Halaman {{ currentPage }} dari {{ totalPages }}
                    </div>
                    <div class="flex gap-2">
                        <button @click="changePage(currentPage - 1)" :disabled="currentPage === 1"
                                class="px-3 py-1 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white disabled:opacity-50 disabled:cursor-not-allowed">
                            <i class="fa fa-chevron-left"></i>
                        </button>
                        <template v-for="page in pageRange" :key="'page-' + page">
                            <button v-if="page !== '...'" @click="changePage(page)"
                                    :class="['px-3 py-1 border rounded-lg', currentPage === page 
                                        ? 'bg-orange-500 text-white border-orange-500' 
                                        : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white']">
                                {{ page }}
                            </button>
                            <span v-else class="px-3 py-1 text-gray-500">...</span>
                        </template>
                        <button @click="changePage(currentPage + 1)" :disabled="currentPage === totalPages"
                                class="px-3 py-1 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white disabled:opacity-50 disabled:cursor-not-allowed">
                            <i class="fa fa-chevron-right"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        </Teleport>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';

const props = defineProps({
    isNight: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['approved', 'rejected']);

// State
const pendingApprovals = ref([]);
const loading = ref(false);
const isApproving = ref(false);
const isRejecting = ref(false);
const selectedApprovals = ref(new Set()); // For multi-select in card
const isSelecting = ref(false); // Toggle select mode in card
const isSelectingAll = ref(false); // Toggle select mode in modal
const selectedAllApprovals = ref(new Set()); // For multi-select in modal

// Computed
const approvalCount = computed(() => pendingApprovals.value.length);

// Methods
function formatDate(date) {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('id-ID', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

async function loadPendingApprovals() {
    loading.value = true;
    try {
        // Add timestamp to prevent caching
        const timestamp = new Date().getTime();
        const response = await axios.get(`/api/non-food-payment/pending-approvals?t=${timestamp}`);
        if (response.data.success) {
            pendingApprovals.value = response.data.non_food_payments || [];
        }
    } catch (error) {
        console.error('Error loading pending Non Food Payment approvals:', error);
    } finally {
        loading.value = false;
    }
}

const showDetailModal = ref(false);
const selectedPayment = ref(null);
const loadingDetail = ref(false);

async function showDetails(nfpId) {
    try {
        if (showAllModal.value) {
            showAllModal.value = false;
        }
        
        loadingDetail.value = true;
        showDetailModal.value = true;
        
        const response = await axios.get(`/api/non-food-payment/${nfpId}`);
        if (response.data && response.data.success && response.data.non_food_payment) {
            selectedPayment.value = response.data.non_food_payment;
        } else {
            Swal.fire('Error', response.data?.message || 'Gagal memuat detail Non Food Payment', 'error');
            closeDetailModal();
        }
    } catch (error) {
        console.error('Error loading Non Food Payment details:', error);
        const errorMessage = error.response?.data?.message || error.message || 'Gagal memuat detail Non Food Payment';
        Swal.fire('Error', errorMessage, 'error');
        closeDetailModal();
    } finally {
        loadingDetail.value = false;
    }
}

function closeDetailModal() {
    showDetailModal.value = false;
    selectedPayment.value = null;
}

// Multi-select functions for card
function toggleSelection(nfpId) {
    if (selectedApprovals.value.has(nfpId)) {
        selectedApprovals.value.delete(nfpId);
    } else {
        selectedApprovals.value.add(nfpId);
    }
}

function selectAllApprovals() {
    pendingApprovals.value.forEach(nfp => {
        selectedApprovals.value.add(nfp.id);
    });
}

async function approveMultiple() {
    if (selectedApprovals.value.size === 0) {
        Swal.fire('Warning', 'Pilih minimal satu Non Food Payment untuk di-approve', 'warning');
        return;
    }
    
    const result = await Swal.fire({
        title: 'Approve Multiple Non Food Payments?',
        text: `Apakah Anda yakin ingin approve ${selectedApprovals.value.size} Non Food Payment?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Approve',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#ea580c',
    });
    
    if (!result.isConfirmed) return;
    
    try {
        Swal.fire({
            title: 'Processing...',
            text: 'Sedang memproses approval...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        const nfpIds = Array.from(selectedApprovals.value);
        const promises = nfpIds.map(async (nfpId) => {
            try {
                await axios.post(`/non-food-payments/${nfpId}/approve`, {
                    note: ''
                });
                return { success: true, nfpId };
            } catch (err) {
                return { error: err, nfpId };
            }
        });
        
        const results = await Promise.all(promises);
        const success = results.filter(r => r.success).length;
        const failed = results.filter(r => r.error).length;
        const approvedIds = results.filter(r => r.success && r.nfpId).map(r => r.nfpId);
        
        selectedApprovals.value.clear();
        isSelecting.value = false;
        // Delay sedikit untuk memastikan database sudah ter-update
        await new Promise(resolve => setTimeout(resolve, 500));
        await loadPendingApprovals();
        
        if (failed === 0) {
            Swal.fire('Success', `${success} Non Food Payment berhasil disetujui`, 'success');
            // Emit approved event for each approved payment
            approvedIds.forEach(id => {
                if (id) {
                    emit('approved', id);
                }
            });
        } else {
            Swal.fire('Partial Success', `${success} berhasil, ${failed} gagal`, 'warning');
            // Emit approved event for successful approvals
            approvedIds.forEach(id => {
                if (id) {
                    emit('approved', id);
                }
            });
        }
    } catch (error) {
        console.error('Error approving multiple Non Food Payments:', error);
        Swal.fire('Error', 'Gagal menyetujui Non Food Payments', 'error');
    }
}

// Multi-select functions for All Modal
function toggleAllSelection(nfpId) {
    if (selectedAllApprovals.value.has(nfpId)) {
        selectedAllApprovals.value.delete(nfpId);
    } else {
        selectedAllApprovals.value.add(nfpId);
    }
}

function selectAllAllApprovals() {
    paginatedApprovals.value.forEach(nfp => {
        selectedAllApprovals.value.add(nfp.id);
    });
}

async function approveMultipleAll() {
    if (selectedAllApprovals.value.size === 0) {
        Swal.fire('Warning', 'Pilih minimal satu Non Food Payment untuk di-approve', 'warning');
        return;
    }
    
    const result = await Swal.fire({
        title: 'Approve Multiple Non Food Payments?',
        text: `Apakah Anda yakin ingin approve ${selectedAllApprovals.value.size} Non Food Payment?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Approve',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#ea580c',
    });
    
    if (!result.isConfirmed) return;
    
    try {
        Swal.fire({
            title: 'Processing...',
            text: 'Sedang memproses approval...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        const nfpIds = Array.from(selectedAllApprovals.value);
        const promises = nfpIds.map(async (nfpId) => {
            try {
                await axios.post(`/non-food-payments/${nfpId}/approve`, {
                    note: ''
                });
                return { success: true, nfpId };
            } catch (err) {
                return { error: err, nfpId };
            }
        });
        
        const results = await Promise.all(promises);
        const success = results.filter(r => r.success).length;
        const failed = results.filter(r => r.error).length;
        const approvedIds = results.filter(r => r.success && r.nfpId).map(r => r.nfpId);
        
        selectedAllApprovals.value.clear();
        isSelectingAll.value = false;
        // Delay sedikit untuk memastikan database sudah ter-update
        await new Promise(resolve => setTimeout(resolve, 500));
        await loadAllApprovals();
        await loadPendingApprovals();
        
        if (failed === 0) {
            Swal.fire('Success', `${success} Non Food Payment berhasil disetujui`, 'success');
            // Emit approved event for each approved payment
            approvedIds.forEach(id => {
                if (id) {
                    emit('approved', id);
                }
            });
        } else {
            Swal.fire('Partial Success', `${success} berhasil, ${failed} gagal`, 'warning');
            // Emit approved event for successful approvals
            approvedIds.forEach(id => {
                if (id) {
                    emit('approved', id);
                }
            });
        }
    } catch (error) {
        console.error('Error approving multiple Non Food Payments:', error);
        Swal.fire('Error', 'Gagal menyetujui Non Food Payments', 'error');
    }
}

async function approvePayment() {
    if (!selectedPayment.value || isApproving.value) return;
    
    isApproving.value = true;
    try {
        const response = await axios.post(`/non-food-payments/${selectedPayment.value.id}/approve`, {
            note: ''
        }, {
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
        });
        
        console.log('Approve response:', response.data);
        
        if (response.data && response.data.success) {
            // Save payment ID before closing modal
            const paymentId = selectedPayment.value?.id;
            
            Swal.fire('Success', response.data.message || 'Non Food Payment berhasil disetujui', 'success');
            closeDetailModal();
            // Delay sedikit untuk memastikan database sudah ter-update
            await new Promise(resolve => setTimeout(resolve, 500));
            // Force refresh pending approvals
            await loadPendingApprovals();
            
            // Emit event with payment ID if available
            if (paymentId) {
                emit('approved', paymentId);
            }
        } else {
            // If response doesn't have success: true, show error
            const errorMessage = response.data?.message || 'Gagal menyetujui Non Food Payment';
            console.error('Approve failed:', response.data);
            Swal.fire('Error', errorMessage, 'error');
        }
    } catch (error) {
        console.error('Error approving Non Food Payment:', error);
        console.error('Error response:', error.response);
        const errorMessage = error.response?.data?.message || error.message || 'Gagal menyetujui Non Food Payment';
        Swal.fire('Error', errorMessage, 'error');
    } finally {
        isApproving.value = false;
    }
}

function showRejectModal() {
    Swal.fire({
        title: 'Tolak Non Food Payment',
        input: 'textarea',
        inputLabel: 'Alasan Penolakan',
        inputPlaceholder: 'Masukkan alasan penolakan...',
        inputAttributes: {
            'aria-label': 'Masukkan alasan penolakan'
        },
        showCancelButton: true,
        confirmButtonText: 'Tolak',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#ef4444',
        inputValidator: (value) => {
            if (!value) {
                return 'Alasan penolakan harus diisi!';
            }
        }
    }).then(async (result) => {
        if (result.isConfirmed && selectedPayment.value) {
            isRejecting.value = true;
            try {
                const response = await axios.post(`/non-food-payments/${selectedPayment.value.id}/reject`, {
                    note: result.value
                });
                if (response.data.success) {
                    // Save payment ID before closing modal
                    const paymentId = selectedPayment.value?.id;
                    
                    Swal.fire('Success', 'Non Food Payment berhasil ditolak', 'success');
                    closeDetailModal();
                    // Delay sedikit untuk memastikan database sudah ter-update
                    await new Promise(resolve => setTimeout(resolve, 500));
                    // Force refresh pending approvals
                    await loadPendingApprovals();
                    
                    // Emit event with payment ID if available
                    if (paymentId) {
                        emit('rejected', paymentId);
                    }
                }
            } catch (error) {
                console.error('Error rejecting Non Food Payment:', error);
                Swal.fire('Error', error.response?.data?.message || 'Gagal menolak Non Food Payment', 'error');
            } finally {
                isRejecting.value = false;
            }
        }
    });
}

const showAllModal = ref(false);
const allApprovals = ref([]);
const loadingAll = ref(false);
const searchQuery = ref('');

// Lightbox state
const lightboxImage = ref(null);
const lightboxImageName = ref('');

// Helper functions for file handling
function isImageFile(fileName) {
    if (!fileName) return false;
    const imageExtensions = ['.jpg', '.jpeg', '.png', '.gif', '.bmp', '.webp', '.svg'];
    const lowerFileName = fileName.toLowerCase();
    return imageExtensions.some(ext => lowerFileName.endsWith(ext));
}

function getFileIcon(fileName) {
    if (!fileName) return 'fa fa-file';
    const lowerFileName = fileName.toLowerCase();
    if (lowerFileName.endsWith('.pdf')) return 'fa fa-file-pdf';
    if (lowerFileName.endsWith('.doc') || lowerFileName.endsWith('.docx')) return 'fa fa-file-word';
    if (lowerFileName.endsWith('.xls') || lowerFileName.endsWith('.xlsx')) return 'fa fa-file-excel';
    return 'fa fa-file';
}

function openLightbox(imageUrl, fileName) {
    lightboxImage.value = imageUrl;
    lightboxImageName.value = fileName;
}

function closeLightbox() {
    lightboxImage.value = null;
    lightboxImageName.value = '';
}

function handleFileDownload(event, fileName) {
    // Let the browser handle the download naturally
    // The href will trigger the download
}

function handleImageError(event) {
    // If image fails to load, show a placeholder
    event.target.src = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="100" height="100"%3E%3Crect fill="%23ddd" width="100" height="100"/%3E%3Ctext fill="%23999" font-family="sans-serif" font-size="12" x="50%25" y="50%25" text-anchor="middle" dy=".3em"%3EImage%3C/text%3E%3C/svg%3E';
}
const statusFilter = ref('');
const dateFilter = ref('');
const sortBy = ref('newest');
const currentPage = ref(1);
const perPage = ref(10);

async function loadAllApprovals() {
    loadingAll.value = true;
    try {
        const response = await axios.get('/api/non-food-payment/pending-approvals?limit=500');
        if (response.data.success) {
            allApprovals.value = response.data.non_food_payments || [];
            currentPage.value = 1;
        }
    } catch (error) {
        console.error('Error loading all Non Food Payment approvals:', error);
    } finally {
        loadingAll.value = false;
    }
}

function openAllModal() {
    showAllModal.value = true;
    loadAllApprovals();
    // Reset selection when opening modal
    isSelectingAll.value = false;
    selectedAllApprovals.value.clear();
}

function closeAllModal() {
    showAllModal.value = false;
}

// Computed properties for filtering and pagination
const filteredApprovals = computed(() => {
    let result = [...allApprovals.value];
    
    // Search
    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase();
        result = result.filter(nfp => {
            const number = (nfp.payment_number || '').toLowerCase();
            const supplier = (nfp.supplier?.name || '').toLowerCase();
            const creator = (nfp.creator?.nama_lengkap || '').toLowerCase();
            const sourceNumber = (nfp.source_number || '').toLowerCase();
            return number.includes(q) || supplier.includes(q) || creator.includes(q) || sourceNumber.includes(q);
        });
    }
    
    // Date filter
    if (dateFilter.value) {
        const now = new Date();
        const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        result = result.filter(nfp => {
            const d = new Date(nfp.payment_date);
            switch (dateFilter.value) {
                case 'today':
                    return d >= today;
                case 'week':
                    return d >= new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000);
                case 'month':
                    return d >= new Date(today.getTime() - 30 * 24 * 60 * 60 * 1000);
                default:
                    return true;
            }
        });
    }
    
    // Sort
    result.sort((a, b) => {
        switch (sortBy.value) {
            case 'oldest':
                return new Date(a.payment_date || a.created_at) - new Date(b.payment_date || b.created_at);
            case 'number':
                return (a.payment_number || '').localeCompare(b.payment_number || '');
            case 'amount':
                return (b.amount || 0) - (a.amount || 0);
            case 'newest':
            default:
                return new Date(b.payment_date || b.created_at) - new Date(a.payment_date || a.created_at);
        }
    });
    
    return result;
});

const totalPages = computed(() => {
    return Math.ceil(filteredApprovals.value.length / perPage.value);
});

const paginatedApprovals = computed(() => {
    const start = (currentPage.value - 1) * perPage.value;
    const end = start + perPage.value;
    return filteredApprovals.value.slice(start, end);
});

const pageRange = computed(() => {
    const total = totalPages.value;
    const current = currentPage.value;
    const range = [];
    
    if (total <= 7) {
        for (let i = 1; i <= total; i++) {
            range.push(i);
        }
    } else {
        if (current <= 3) {
            for (let i = 1; i <= 5; i++) {
                range.push(i);
            }
            range.push('...');
            range.push(total);
        } else if (current >= total - 2) {
            range.push(1);
            range.push('...');
            for (let i = total - 4; i <= total; i++) {
                range.push(i);
            }
        } else {
            range.push(1);
            range.push('...');
            for (let i = current - 1; i <= current + 1; i++) {
                range.push(i);
            }
            range.push('...');
            range.push(total);
        }
    }
    
    return range;
});

function changePage(page) {
    if (page >= 1 && page <= totalPages.value) {
        currentPage.value = page;
    }
}

// Watchers
watch([searchQuery, dateFilter, sortBy], () => {
    currentPage.value = 1;
});

// Lifecycle
onMounted(() => {
    loadPendingApprovals();
});

// Expose methods for parent component
defineExpose({
    loadPendingApprovals,
    refresh: loadPendingApprovals
});
</script>

