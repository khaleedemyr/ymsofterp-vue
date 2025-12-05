<template>
  <AppLayout title="Payment Details">
    <div class="w-full py-8 px-4">
      <div class="flex justify-between items-center mb-6">
        <div class="flex items-center gap-3">
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-shopping-cart text-blue-500"></i> 
            Payment: {{ purchaseRequisition.pr_number }}
          </h1>
          <span v-if="purchaseRequisition.mode" 
                :class="['inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium', getModeBadgeClass(purchaseRequisition.mode)]">
            {{ getModeLabel(purchaseRequisition.mode) }}
          </span>
          <span v-else 
                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
            Legacy Data
          </span>
        </div>
        <div class="flex space-x-2">
          <Link
            v-if="purchaseRequisition.status === 'DRAFT' || purchaseRequisition.status === 'SUBMITTED'"
            :href="`/purchase-requisitions/${purchaseRequisition.id}/edit`"
            class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded"
          >
            <i class="fas fa-edit mr-2"></i>
            Edit
          </Link>
          <Link
            :href="'/purchase-requisitions'"
            class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded"
          >
            <i class="fas fa-arrow-left mr-2"></i>
            Back to List
          </Link>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-6">
          <!-- Basic Information -->
          <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Basic Information</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="text-sm font-medium text-gray-600">PR Number</label>
                <p class="text-lg font-semibold text-gray-900">{{ purchaseRequisition.pr_number }}</p>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-600">Status</label>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                      :class="getStatusColor(purchaseRequisition.status)">
                  {{ purchaseRequisition.status }}
                </span>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-600">Title</label>
                <p class="text-gray-900">{{ purchaseRequisition.title }}</p>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-600">Amount</label>
                <p class="text-lg font-semibold text-green-600">{{ formatCurrency(purchaseRequisition.amount) }}</p>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-600">Division</label>
                <p class="text-gray-900">{{ purchaseRequisition.division?.nama_divisi || '-' }}</p>
              </div>
              <!-- Category and Outlet: Show for legacy data (no mode) or non-multi-outlet modes -->
              <div v-if="!purchaseRequisition.mode || !['pr_ops', 'purchase_payment', 'travel_application'].includes(purchaseRequisition.mode)">
                <label class="text-sm font-medium text-gray-600">Category</label>
                <p class="text-gray-900">{{ purchaseRequisition.category?.name || '-' }}</p>
              </div>
              <div v-if="!purchaseRequisition.mode || !['pr_ops', 'purchase_payment', 'travel_application'].includes(purchaseRequisition.mode)">
                <label class="text-sm font-medium text-gray-600">Outlet</label>
                <p class="text-gray-900">{{ purchaseRequisition.outlet?.nama_outlet || '-' }}</p>
              </div>
              <!-- For kasbon, show category and outlet -->
              <div v-if="purchaseRequisition.mode === 'kasbon'">
                <label class="text-sm font-medium text-gray-600">Category</label>
                <p class="text-gray-900">{{ purchaseRequisition.category?.name || '-' }}</p>
              </div>
              <div v-if="purchaseRequisition.mode === 'kasbon'">
                <label class="text-sm font-medium text-gray-600">Outlet</label>
                <p class="text-gray-900">{{ purchaseRequisition.outlet?.nama_outlet || '-' }}</p>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-600">Priority</label>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                      :class="getPriorityColor(purchaseRequisition.priority)">
                  {{ purchaseRequisition.priority }}
                </span>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-600">Created Date</label>
                <p class="text-gray-900">{{ formatDate(purchaseRequisition.created_at) }}</p>
              </div>
            </div>
            
            <div v-if="purchaseRequisition.description" class="mt-4">
              <label class="text-sm font-medium text-gray-600">Description</label>
              <p class="text-gray-900 mt-1">{{ purchaseRequisition.description }}</p>
            </div>
          </div>

          <!-- Budget Information -->
          <div v-if="budgetInfo && purchaseRequisition.mode !== 'kasbon'" class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
              <i class="fa fa-chart-pie mr-2 text-green-500"></i>
              Budget Information
              <span class="ml-2 text-xs font-normal text-gray-500">
                ({{ budgetInfo.budget_type === 'PER_OUTLET' ? 'Per Outlet' : 'Global' }})
              </span>
            </h2>
            
            <!-- Global Budget Info -->
            <div v-if="budgetInfo.budget_type === 'GLOBAL'" class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
              <div>
                <p class="text-gray-600 mb-1">Category Budget</p>
                <p class="font-semibold text-gray-900">{{ formatCurrency(budgetInfo.category_budget) }}</p>
              </div>
              <div>
                <p class="text-gray-600 mb-1">Used Amount</p>
                <p class="font-semibold text-green-800">{{ formatCurrency(budgetInfo.category_used_amount) }}</p>
              </div>
              <div>
                <p class="text-gray-600 mb-1">Remaining Budget</p>
                <p class="font-semibold" :class="budgetInfo.category_remaining_amount < 0 ? 'text-red-800' : 'text-green-800'">
                  {{ formatCurrency(budgetInfo.category_remaining_amount) }}
                </p>
              </div>
              <div>
                <p class="text-gray-600 mb-1">Period</p>
                <p class="font-semibold text-gray-900">{{ getMonthName(budgetInfo.current_month) }} {{ budgetInfo.current_year }}</p>
              </div>
            </div>
            
            <!-- Per Outlet Budget Info -->
            <div v-else-if="budgetInfo.budget_type === 'PER_OUTLET'" class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
              <div>
                <p class="text-gray-600 mb-1">Outlet Budget</p>
                <p class="font-semibold text-gray-900">{{ formatCurrency(budgetInfo.outlet_budget) }}</p>
                <p class="text-xs text-gray-500 mt-1">{{ budgetInfo.outlet_info?.name || 'Unknown' }}</p>
              </div>
              <div>
                <p class="text-gray-600 mb-1">Used Amount</p>
                <p class="font-semibold text-green-800">{{ formatCurrency(budgetInfo.outlet_used_amount) }}</p>
              </div>
              <div>
                <p class="text-gray-600 mb-1">Remaining Budget</p>
                <p class="font-semibold" :class="budgetInfo.outlet_remaining_amount < 0 ? 'text-red-800' : 'text-green-800'">
                  {{ formatCurrency(budgetInfo.outlet_remaining_amount) }}
                </p>
              </div>
              <div>
                <p class="text-gray-600 mb-1">Period</p>
                <p class="font-semibold text-gray-900">{{ getMonthName(budgetInfo.current_month) }} {{ budgetInfo.current_year }}</p>
              </div>
            </div>
          </div>

          <!-- Travel Application Specific Fields -->
          <div v-if="purchaseRequisition.mode === 'travel_application' && modeSpecificData" class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
              <i class="fa fa-plane mr-2 text-purple-500"></i>
              Informasi Perjalanan Dinas
            </h2>
            <div class="space-y-4">
              <div v-if="modeSpecificData.travel_outlets && modeSpecificData.travel_outlets.length > 0">
                <label class="text-sm font-medium text-gray-600 mb-2 block">Outlet Tujuan Perjalanan Dinas</label>
                <div class="flex flex-wrap gap-2">
                  <span v-for="outlet in modeSpecificData.travel_outlets" :key="outlet.id"
                        class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-purple-100 text-purple-800">
                    <i class="fa fa-store mr-2"></i>
                    {{ outlet.name }}
                  </span>
                </div>
              </div>
              <div v-if="modeSpecificData.travel_agenda">
                <label class="text-sm font-medium text-gray-600 mb-2 block">Agenda Kerja</label>
                <div class="text-gray-900 bg-gray-50 p-3 rounded-lg border border-gray-200 whitespace-pre-wrap">{{ modeSpecificData.travel_agenda }}</div>
              </div>
              <div v-if="modeSpecificData.travel_notes">
                <label class="text-sm font-medium text-gray-600 mb-2 block">Notes</label>
                <div class="text-gray-900 bg-gray-50 p-3 rounded-lg border border-gray-200 whitespace-pre-wrap">{{ modeSpecificData.travel_notes }}</div>
              </div>
            </div>
          </div>

          <!-- Kasbon Specific Fields -->
          <div v-if="purchaseRequisition.mode === 'kasbon' && modeSpecificData" class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
              <i class="fa fa-money-bill-wave mr-2 text-orange-500"></i>
              Informasi Kasbon
            </h2>
            <div class="space-y-4">
              <div>
                <label class="text-sm font-medium text-gray-600 mb-2 block">Nilai Kasbon</label>
                <p class="text-lg font-semibold text-gray-900">
                  {{ formatCurrency(modeSpecificData.kasbon_amount || 0) }}
                </p>
              </div>
              <div v-if="modeSpecificData.kasbon_reason">
                <label class="text-sm font-medium text-gray-600 mb-2 block">Reason / Alasan Kasbon</label>
                <div class="text-gray-900 bg-gray-50 p-3 rounded-lg border border-gray-200 whitespace-pre-wrap">{{ modeSpecificData.kasbon_reason }}</div>
              </div>
            </div>
          </div>

          <!-- Items - Grouped by Outlet and Category for pr_ops and purchase_payment -->
          <div v-if="purchaseRequisition.items && purchaseRequisition.items.length > 0 && ['pr_ops', 'purchase_payment'].includes(purchaseRequisition.mode)" class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Items</h2>
            <div class="space-y-6">
              <div v-for="(outletGroup, outletId) in getGroupedItems(purchaseRequisition.items)" :key="outletId" class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                <h3 class="text-md font-semibold text-gray-900 mb-3 flex items-center">
                  <i class="fa fa-store mr-2 text-blue-500"></i>
                  Outlet: {{ outletGroup.outlet_name }}
                </h3>
                <div class="space-y-4">
                  <div v-for="(categoryGroup, categoryId) in outletGroup.categories" :key="categoryId" class="ml-4 border-l-2 border-gray-300 pl-4">
                    <h4 class="text-sm font-semibold text-gray-700 mb-2">
                      <i class="fa fa-tag mr-1"></i>
                      Category: {{ categoryGroup.category_name }}
                    </h4>
                    <div class="overflow-x-auto">
                      <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-100">
                          <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Item Name</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Qty</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Unit</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Unit Price</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Subtotal</th>
                          </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                          <tr v-for="item in categoryGroup.items" :key="item.id">
                            <td class="px-3 py-2 text-sm text-gray-900">
                              {{ item.item_name }}
                              <div v-if="item.item_type === 'allowance' && item.allowance_recipient_name" class="text-xs text-gray-500 mt-1">
                                <i class="fa fa-user mr-1"></i>
                                Penerima: {{ item.allowance_recipient_name }}
                                <span v-if="item.allowance_account_number"> | No. Rek: {{ item.allowance_account_number }}</span>
                              </div>
                              <div v-if="item.item_type === 'others' && item.others_notes" class="text-xs text-gray-500 mt-1">
                                <i class="fa fa-sticky-note mr-1"></i>
                                Notes: {{ item.others_notes }}
                              </div>
                            </td>
                            <td class="px-3 py-2 text-sm text-gray-900">{{ item.qty }}</td>
                            <td class="px-3 py-2 text-sm text-gray-900">{{ item.unit }}</td>
                            <td class="px-3 py-2 text-sm text-gray-900">{{ formatCurrency(item.unit_price) }}</td>
                            <td class="px-3 py-2 text-sm text-gray-900 text-right">{{ formatCurrency(item.subtotal) }}</td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="mt-4 pt-4 border-t border-gray-200">
              <div class="flex justify-end">
                <div class="text-right">
                  <p class="text-sm font-medium text-gray-600">Total Amount:</p>
                  <p class="text-lg font-bold text-gray-900">{{ formatCurrency(purchaseRequisition.amount) }}</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Items - Simple table for other modes (travel_application) or legacy data -->
          <div v-if="purchaseRequisition.items && purchaseRequisition.items.length > 0 && !['pr_ops', 'purchase_payment', 'kasbon'].includes(purchaseRequisition.mode)" class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Items</h2>
            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Qty</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit Price</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Subtotal</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <tr v-for="item in purchaseRequisition.items" :key="item.id">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                      {{ item.item_name }}
                      <div v-if="item.item_type === 'allowance' && item.allowance_recipient_name" class="text-xs text-gray-500 mt-1">
                        <i class="fa fa-user mr-1"></i>
                        Penerima: {{ item.allowance_recipient_name }}
                        <span v-if="item.allowance_account_number"> | No. Rek: {{ item.allowance_account_number }}</span>
                      </div>
                      <div v-if="item.item_type === 'others' && item.others_notes" class="text-xs text-gray-500 mt-1">
                        <i class="fa fa-sticky-note mr-1"></i>
                        Notes: {{ item.others_notes }}
                      </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ item.qty }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ item.unit }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ formatCurrency(item.unit_price) }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">{{ formatCurrency(item.subtotal) }}</td>
                  </tr>
                </tbody>
                <tfoot>
                  <tr>
                    <td colspan="4" class="px-6 py-4 text-right font-bold text-gray-900">Total Amount:</td>
                    <td class="px-6 py-4 text-right font-bold text-gray-900">{{ formatCurrency(purchaseRequisition.amount) }}</td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>

          <!-- Approval Flow -->
          <div v-if="purchaseRequisition.approval_flows && purchaseRequisition.approval_flows.length > 0" class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
              <i class="fa fa-users mr-2 text-blue-500"></i>
              Approval Flow
            </h2>
            <div class="space-y-4">
              <div
                v-for="(flow, index) in purchaseRequisition.approval_flows"
                :key="flow.id"
                class="flex items-center justify-between p-4 border border-gray-200 rounded-lg bg-gradient-to-r from-gray-50 to-white"
                :class="getApprovalFlowClass(flow.status)"
              >
                <div class="flex items-center space-x-4">
                  <div class="flex items-center space-x-3">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                      <i class="fa fa-layer-group mr-1"></i>
                      Level {{ flow.approval_level }}
                    </span>
                    <div class="w-10 h-10 rounded-full flex items-center justify-center shadow-md"
                         :class="getApprovalStatusIconClass(flow.status)">
                      <i :class="getApprovalStatusIcon(flow.status)"></i>
                    </div>
                  </div>
                  <div class="flex-1">
                    <div class="font-semibold text-gray-900 text-lg">{{ flow.approver?.nama_lengkap || flow.approver?.name }}</div>
                    <div class="text-sm text-gray-600 flex items-center mt-1">
                      <i class="fa fa-envelope mr-2"></i>{{ flow.approver?.email }}
                    </div>
                    <div v-if="flow.approver?.jabatan?.nama_jabatan" class="text-sm text-blue-600 font-medium mt-1 flex items-center">
                      <i class="fa fa-briefcase mr-2"></i>{{ flow.approver.jabatan.nama_jabatan }}
                    </div>
                    <div v-if="flow.comments" class="text-sm text-gray-600 mt-2 p-2 bg-gray-100 rounded border-l-4 border-blue-400">
                      <strong class="text-gray-800">Comments:</strong> {{ flow.comments }}
                    </div>
                  </div>
                </div>
                <div class="text-right">
                  <div class="text-sm font-medium px-3 py-1 rounded-full"
                       :class="getApprovalStatusTextClass(flow.status)">
                    <i class="fa fa-circle mr-1 text-xs"></i>{{ flow.status }}
                  </div>
                  <div v-if="flow.approved_at" class="text-xs text-gray-500 mt-1">
                    <i class="fa fa-check-circle mr-1 text-green-500"></i>Approved: {{ formatDate(flow.approved_at) }}
                  </div>
                  <div v-if="flow.rejected_at" class="text-xs text-gray-500 mt-1">
                    <i class="fa fa-times-circle mr-1 text-red-500"></i>Rejected: {{ formatDate(flow.rejected_at) }}
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Budget Information (hidden for kasbon) -->
          <div v-if="budgetInfo && purchaseRequisition.mode !== 'kasbon'" class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
              <i class="fa fa-chart-pie mr-2 text-green-500"></i>
              {{ budgetInfo.budget_type === 'PER_OUTLET' ? 'Outlet Budget Information' : 'Category Budget Information' }} - {{ getMonthName(budgetInfo.current_month) }} {{ budgetInfo.current_year }}
              <span class="ml-2 text-sm font-normal text-gray-600">
                ({{ budgetInfo.budget_type === 'PER_OUTLET' ? 'Per Outlet' : 'Global' }})
              </span>
            </h2>
            
            <!-- Outlet Info for PER_OUTLET -->
            <div v-if="budgetInfo.budget_type === 'PER_OUTLET' && budgetInfo.outlet_info" class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
              <p class="text-sm text-blue-600">
                <i class="fa fa-store mr-2"></i>
                <strong>Outlet:</strong> {{ budgetInfo.outlet_info.name }}
              </p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <div class="flex items-center justify-between">
                  <div>
                    <p class="text-sm font-medium text-blue-600">
                      {{ budgetInfo.budget_type === 'PER_OUTLET' ? 'Outlet Budget' : 'Total Budget' }}
                    </p>
                    <p class="text-2xl font-bold text-blue-800">
                      {{ formatCurrency(budgetInfo.budget_type === 'PER_OUTLET' ? budgetInfo.outlet_budget : budgetInfo.category_budget) }}
                    </p>
                    <p v-if="budgetInfo.budget_type === 'PER_OUTLET'" class="text-xs text-gray-500 mt-1">
                      Global: {{ formatCurrency(budgetInfo.category_budget) }}
                    </p>
                  </div>
                  <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                    <i class="fa fa-wallet text-blue-600 text-xl"></i>
                  </div>
                </div>
              </div>
              
              <div class="bg-orange-50 border border-orange-200 rounded-lg p-4">
                <div class="flex items-center justify-between">
                  <div>
                    <p class="text-sm font-medium text-orange-600">Used This Month</p>
                    <p class="text-2xl font-bold text-orange-800">
                      {{ formatCurrency(budgetInfo.budget_type === 'PER_OUTLET' ? budgetInfo.outlet_used_amount : budgetInfo.category_used_amount) }}
                    </p>
                  </div>
                  <div class="w-12 h-12 bg-orange-100 rounded-full flex items-center justify-center">
                    <i class="fa fa-chart-line text-orange-600 text-xl"></i>
                  </div>
                </div>
              </div>
              
              <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                <div class="flex items-center justify-between">
                  <div>
                    <p class="text-sm font-medium text-green-600">Remaining Budget</p>
                    <p class="text-2xl font-bold" :class="getRemainingAmount() < 0 ? 'text-red-800' : 'text-green-800'">
                      {{ formatCurrency(getRemainingAmount()) }}
                    </p>
                  </div>
                  <div class="w-12 h-12 rounded-full flex items-center justify-center" 
                       :class="getRemainingAmount() < 0 ? 'bg-red-100' : 'bg-green-100'">
                    <i class="fa fa-piggy-bank text-xl" 
                       :class="getRemainingAmount() < 0 ? 'text-red-600' : 'text-green-600'"></i>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- Progress Bar -->
            <div class="mt-4">
              <div class="flex justify-between text-sm text-gray-600 mb-2">
                <span>Budget Usage</span>
                <span>{{ Math.round(getUsagePercentage()) }}%</span>
              </div>
              <div class="w-full bg-gray-200 rounded-full h-3">
                <div class="h-3 rounded-full transition-all duration-300"
                     :class="getBudgetProgressColor(getUsedAmount(), getTotalBudget())"
                     :style="{ width: Math.min(getUsagePercentage(), 100) + '%' }">
                </div>
              </div>
            </div>
            
            <!-- Warning Messages -->
            <div v-if="getRemainingAmount() < 0" class="mt-4 p-3 bg-red-100 border border-red-300 rounded text-red-800 text-sm">
              <i class="fa fa-exclamation-triangle mr-2"></i>
              <strong>Budget Exceeded!</strong> 
              <span v-if="budgetInfo.budget_type === 'PER_OUTLET'">
                This outlet has exceeded its monthly budget limit.
              </span>
              <span v-else>
                This category has exceeded its monthly budget limit.
              </span>
            </div>
            <div v-else-if="getRemainingAmount() < (getTotalBudget() * 0.1)" class="mt-4 p-3 bg-yellow-100 border border-yellow-300 rounded text-yellow-800 text-sm">
              <i class="fa fa-exclamation-circle mr-2"></i>
              <strong>Budget Warning!</strong> Only {{ formatCurrency(getRemainingAmount()) }} remaining.
            </div>
          </div>

          <!-- Related Ticket (hidden for certain modes) -->
          <div v-if="purchaseRequisition.ticket && !['pr_ops', 'purchase_payment', 'travel_application', 'kasbon'].includes(purchaseRequisition.mode)" class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Related Ticket</h2>
            <div class="p-4 bg-blue-50 rounded-lg">
              <Link
                :href="`/tickets/${purchaseRequisition.ticket.id}`"
                class="text-blue-600 hover:text-blue-800 font-medium"
              >
                {{ purchaseRequisition.ticket.ticket_number }} - {{ purchaseRequisition.ticket.title }}
              </Link>
              <p class="text-sm text-gray-500 mt-1">
                {{ purchaseRequisition.ticket.outlet?.nama_outlet }} - {{ formatDate(purchaseRequisition.ticket.created_at) }}
              </p>
            </div>
          </div>

          <!-- Attachments - Grouped by Outlet for pr_ops and purchase_payment -->
          <div v-if="purchaseRequisition.attachments && purchaseRequisition.attachments.length > 0 && ['pr_ops', 'purchase_payment'].includes(purchaseRequisition.mode)" class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
              <i class="fa fa-paperclip mr-2 text-blue-500"></i>
              Attachments
              <span class="ml-2 bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded-full">
                {{ purchaseRequisition.attachments.length }}
              </span>
            </h2>
            
            <div class="space-y-4">
              <div v-for="(outletGroup, outletId) in getGroupedAttachments(purchaseRequisition.attachments)" :key="outletId" class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                <h3 v-if="outletId !== 'no-outlet'" class="text-sm font-semibold text-gray-700 mb-3 flex items-center">
                  <i class="fa fa-store mr-2 text-blue-500"></i>
                  Outlet: {{ outletGroup.outlet_name }}
                  <span class="ml-2 bg-gray-100 text-gray-800 text-xs font-medium px-2 py-0.5 rounded-full">
                    {{ outletGroup.attachments.length }}
                  </span>
                </h3>
                <div class="space-y-3">
                  <div
                    v-for="attachment in outletGroup.attachments"
                    :key="attachment.id"
                    class="flex items-center justify-between p-4 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
                  >
                    <div class="flex items-center space-x-3">
                      <!-- Image Thumbnail -->
                      <div v-if="isImageFile(attachment.file_name)" class="relative">
                        <img
                          :src="`/purchase-requisitions/attachments/${attachment.id}/view`"
                          :alt="attachment.file_name"
                          class="w-16 h-16 object-cover rounded-lg border border-gray-300 cursor-pointer hover:opacity-80 transition-opacity"
                          @click="openLightbox(attachment)"
                          @error="$event.target.style.display='none'; $event.target.nextElementSibling.style.display='block'"
                        />
                        <i :class="getFileIcon(attachment.file_name)" class="text-2xl absolute inset-0 flex items-center justify-center bg-gray-100 rounded-lg" style="display: none;"></i>
                      </div>
                      <!-- File Icon for non-images -->
                      <i v-else :class="getFileIcon(attachment.file_name)" class="text-2xl"></i>
                      
                      <div>
                        <p class="text-sm font-medium text-gray-900">{{ attachment.file_name }}</p>
                        <div class="flex items-center space-x-4 text-xs text-gray-500">
                          <span>{{ formatFileSize(attachment.file_size) }}</span>
                          <span>•</span>
                          <span>Uploaded by {{ attachment.uploader?.nama_lengkap || 'Unknown User' }}</span>
                          <span>•</span>
                          <span>{{ formatDate(attachment.created_at) }}</span>
                        </div>
                      </div>
                    </div>
                    <div class="flex items-center space-x-2">
                      <button
                        v-if="isImageFile(attachment.file_name)"
                        @click="openLightbox(attachment)"
                        class="p-2 text-green-600 hover:text-green-800 hover:bg-green-100 rounded-md transition-colors"
                        title="View Image"
                      >
                        <i class="fa fa-eye"></i>
                      </button>
                      <button
                        @click="downloadFile(attachment)"
                        class="p-2 text-blue-600 hover:text-blue-800 hover:bg-blue-100 rounded-md transition-colors"
                        title="Download"
                      >
                        <i class="fa fa-download"></i>
                      </button>
                      <button
                        v-if="canDeleteAttachment(attachment)"
                        @click="deleteAttachment(attachment)"
                        class="p-2 text-red-600 hover:text-red-800 hover:bg-red-100 rounded-md transition-colors"
                        title="Delete"
                      >
                        <i class="fa fa-trash"></i>
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Attachments - Simple list for other modes -->
          <div v-if="!['pr_ops', 'purchase_payment'].includes(purchaseRequisition.mode)" class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
              <i class="fa fa-paperclip mr-2 text-blue-500"></i>
              Attachments
              <span v-if="purchaseRequisition.attachments && purchaseRequisition.attachments.length > 0" 
                    class="ml-2 bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded-full">
                {{ purchaseRequisition.attachments.length }}
              </span>
            </h2>
            
            <div v-if="purchaseRequisition.attachments && purchaseRequisition.attachments.length > 0" class="space-y-3">
              <div
                v-for="attachment in purchaseRequisition.attachments"
                :key="attachment.id"
                class="flex items-center justify-between p-4 bg-gray-50 border border-gray-200 rounded-lg hover:bg-gray-100 transition-colors"
              >
                <div class="flex items-center space-x-3">
                  <!-- Image Thumbnail -->
                  <div v-if="isImageFile(attachment.file_name)" class="relative">
                    <img
                      :src="`/purchase-requisitions/attachments/${attachment.id}/view`"
                      :alt="attachment.file_name"
                      class="w-16 h-16 object-cover rounded-lg border border-gray-300 cursor-pointer hover:opacity-80 transition-opacity"
                      @click="openLightbox(attachment)"
                      @error="$event.target.style.display='none'; $event.target.nextElementSibling.style.display='block'"
                    />
                    <i :class="getFileIcon(attachment.file_name)" class="text-2xl absolute inset-0 flex items-center justify-center bg-gray-100 rounded-lg" style="display: none;"></i>
                  </div>
                  <!-- File Icon for non-images -->
                  <i v-else :class="getFileIcon(attachment.file_name)" class="text-2xl"></i>
                  
                  <div>
                    <p class="text-sm font-medium text-gray-900">{{ attachment.file_name }}</p>
                    <div class="flex items-center space-x-4 text-xs text-gray-500">
                      <span>{{ formatFileSize(attachment.file_size) }}</span>
                      <span>•</span>
                      <span>Uploaded by {{ attachment.uploader?.nama_lengkap || 'Unknown User' }}</span>
                      <span>•</span>
                      <span>{{ formatDate(attachment.created_at) }}</span>
                    </div>
                  </div>
                </div>
                <div class="flex items-center space-x-2">
                  <button
                    v-if="isImageFile(attachment.file_name)"
                    @click="openLightbox(attachment)"
                    class="p-2 text-green-600 hover:text-green-800 hover:bg-green-100 rounded-md transition-colors"
                    title="View Image"
                  >
                    <i class="fa fa-eye"></i>
                  </button>
                  <button
                    @click="downloadFile(attachment)"
                    class="p-2 text-blue-600 hover:text-blue-800 hover:bg-blue-100 rounded-md transition-colors"
                    title="Download"
                  >
                    <i class="fa fa-download"></i>
                  </button>
                  <button
                    v-if="canDeleteAttachment(attachment)"
                    @click="deleteAttachment(attachment)"
                    class="p-2 text-red-600 hover:text-red-800 hover:bg-red-100 rounded-md transition-colors"
                    title="Delete"
                  >
                    <i class="fa fa-trash"></i>
                  </button>
                </div>
              </div>
            </div>
            
            <div v-else class="text-center py-8 text-gray-500">
              <i class="fa fa-paperclip text-4xl mb-2"></i>
              <p>No attachments uploaded yet</p>
            </div>
          </div>

          <!-- Attachments - Empty state for pr_ops and purchase_payment if no attachments -->
          <div v-if="['pr_ops', 'purchase_payment'].includes(purchaseRequisition.mode) && (!purchaseRequisition.attachments || purchaseRequisition.attachments.length === 0)" class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
              <i class="fa fa-paperclip mr-2 text-blue-500"></i>
              Attachments
            </h2>
            <div class="text-center py-8 text-gray-500">
              <i class="fa fa-paperclip text-4xl mb-2"></i>
              <p>No attachments uploaded yet</p>
            </div>
          </div>

          <!-- Comments -->
          <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Comments</h2>
            
            <!-- Add Comment Form -->
            <div class="mb-4 p-4 bg-gray-50 rounded-lg">
              <textarea
                v-model="newComment"
                rows="3"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="Add a comment..."
              ></textarea>
              <div class="mt-2 flex items-center justify-between">
                <label class="flex items-center">
                  <input
                    v-model="isInternalComment"
                    type="checkbox"
                    class="mr-2"
                  />
                  <span class="text-sm text-gray-600">Internal comment</span>
                </label>
                <button
                  @click="addComment"
                  :disabled="!newComment.trim()"
                  class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50"
                >
                  Add Comment
                </button>
              </div>
            </div>

            <!-- Comments List -->
            <div v-if="purchaseRequisition.comments && purchaseRequisition.comments.length > 0" class="space-y-4">
              <div
                v-for="comment in purchaseRequisition.comments"
                :key="comment.id"
                class="p-4 border border-gray-200 rounded-lg"
              >
                <div class="flex items-center justify-between mb-2">
                  <div class="flex items-center space-x-2">
                    <span class="font-medium text-gray-900">{{ comment.user?.nama_lengkap || 'Unknown User' }}</span>
                    <span v-if="comment.is_internal" class="px-2 py-1 bg-yellow-100 text-yellow-800 text-xs rounded-full">
                      Internal
                    </span>
                  </div>
                  <span class="text-sm text-gray-500">{{ formatDate(comment.created_at) }}</span>
                </div>
                <p class="text-gray-700">{{ comment.comment }}</p>
              </div>
            </div>
            <div v-else class="text-center py-8 text-gray-500">
              No comments yet
            </div>
          </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
          <!-- Actions -->
          <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Actions</h2>
            <div class="space-y-2">
              <!-- Edit Button - Only for creator when status is DRAFT -->
              <button
                v-if="canEdit"
                @click="editRequisition"
                class="w-full px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700"
              >
                <i class="fa fa-edit mr-2"></i>Edit
              </button>
              
              <!-- Submit Button - Only for creator when status is DRAFT -->
              <button
                v-if="canSubmit"
                @click="submitRequisition"
                class="w-full px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
              >
                <i class="fa fa-paper-plane mr-2"></i>Submit for Approval
              </button>
              
              <!-- Approve Button - Only for current approver in line -->
              <button
                v-if="canApprove"
                @click="approveRequisition"
                class="w-full px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700"
              >
                <i class="fa fa-check mr-2"></i>Approve
              </button>
              
              <!-- Reject Button - Only for current approver in line -->
              <button
                v-if="canApprove"
                @click="showRejectModal = true"
                class="w-full px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700"
              >
                <i class="fa fa-times mr-2"></i>Reject
              </button>
              
              
              <!-- No Actions Available -->
              <div v-if="!canEdit && !canSubmit && !canApprove" 
                   class="text-center py-4 text-gray-500">
                <i class="fa fa-info-circle mr-2"></i>
                No actions available for you
              </div>
            </div>
          </div>

          <!-- Status Timeline -->
          <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Status Timeline</h2>
            <div class="space-y-4">
              <div class="flex items-center space-x-3">
                <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                <div>
                  <p class="text-sm font-medium text-gray-900">Created</p>
                  <p class="text-xs text-gray-500">{{ formatDate(purchaseRequisition.created_at) }}</p>
                </div>
              </div>
              <div v-if="purchaseRequisition.status !== 'DRAFT'" class="flex items-center space-x-3">
                <div class="w-3 h-3 bg-blue-500 rounded-full"></div>
                <div>
                  <p class="text-sm font-medium text-gray-900">Submitted</p>
                  <p class="text-xs text-gray-500">{{ formatDate(purchaseRequisition.updated_at) }}</p>
                </div>
              </div>
              <div v-if="['APPROVED', 'PROCESSED', 'COMPLETED'].includes(purchaseRequisition.status)" class="flex items-center space-x-3">
                <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                <div>
                  <p class="text-sm font-medium text-gray-900">Approved</p>
                  <p class="text-xs text-gray-500">{{ formatDate(purchaseRequisition.approved_ssd_at) }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Lightbox Modal -->
    <div v-if="showLightbox" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-75" @click="closeLightbox">
      <div class="relative max-w-4xl max-h-full p-4" @click.stop>
        <button
          @click="closeLightbox"
          class="absolute top-2 right-2 z-10 p-2 text-white bg-black bg-opacity-50 rounded-full hover:bg-opacity-75 transition-colors"
        >
          <i class="fa fa-times text-xl"></i>
        </button>
        <img
          v-if="lightboxImage"
          :src="`/purchase-requisitions/attachments/${lightboxImage.id}/view`"
          :alt="lightboxImage.file_name"
          class="max-w-full max-h-full object-contain rounded-lg"
        />
        <div class="absolute bottom-4 left-4 right-4 text-center">
          <p class="text-white bg-black bg-opacity-50 px-3 py-1 rounded-lg text-sm">
            {{ lightboxImage?.file_name }}
          </p>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link } from '@inertiajs/vue3'
import axios from 'axios'

const props = defineProps({
  purchaseRequisition: Object,
  budgetInfo: Object,
  modeSpecificData: Object,
  currentUser: Object,
})

const newComment = ref('')
const isInternalComment = ref(false)
const showRejectModal = ref(false)
const rejectionReason = ref('')
const showLightbox = ref(false)
const lightboxImage = ref(null)

// Computed properties for permissions
const canEdit = computed(() => {
  return (props.purchaseRequisition.status === 'DRAFT' || props.purchaseRequisition.status === 'SUBMITTED') && 
         props.purchaseRequisition.created_by === props.currentUser?.id
})

const canSubmit = computed(() => {
  return props.purchaseRequisition.status === 'DRAFT' && 
         props.purchaseRequisition.created_by === props.currentUser?.id
})

const canApprove = computed(() => {
  if (props.purchaseRequisition.status !== 'SUBMITTED') return false
  
  // Check if current user is in the approval flow
  const currentUserFlow = props.purchaseRequisition.approval_flows?.find(flow => 
    flow.approver_id === props.currentUser?.id && flow.status === 'PENDING'
  )
  
  if (!currentUserFlow) return false
  
  // Check if this is the next approver in line (lowest level that is still pending)
  const pendingFlows = props.purchaseRequisition.approval_flows?.filter(flow => flow.status === 'PENDING')
  if (!pendingFlows || pendingFlows.length === 0) return false
  
  const nextApprover = pendingFlows.reduce((min, flow) => 
    flow.approval_level < min.approval_level ? flow : min
  )
  
  return currentUserFlow.approval_level === nextApprover.approval_level
})


function getStatusColor(status) {
  return {
    'DRAFT': 'bg-gray-100 text-gray-800',
    'SUBMITTED': 'bg-yellow-100 text-yellow-800',
    'APPROVED': 'bg-green-100 text-green-800',
    'REJECTED': 'bg-red-100 text-red-800',
    'PROCESSED': 'bg-blue-100 text-blue-800',
    'COMPLETED': 'bg-purple-100 text-purple-800',
  }[status] || 'bg-gray-100 text-gray-800'
}

function getPriorityColor(priority) {
  return {
    'LOW': 'bg-green-100 text-green-800',
    'MEDIUM': 'bg-yellow-100 text-yellow-800',
    'HIGH': 'bg-orange-100 text-orange-800',
    'URGENT': 'bg-red-100 text-red-800',
  }[priority] || 'bg-gray-100 text-gray-800'
}

function formatCurrency(amount) {
  if (!amount) return '-'
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
  }).format(amount)
}

function formatDate(date) {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('id-ID', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

// File operations
function getFileIcon(fileName) {
  const extension = fileName.split('.').pop().toLowerCase()
  
  const iconMap = {
    'pdf': 'fa-file-pdf text-red-500',
    'doc': 'fa-file-word text-blue-500',
    'docx': 'fa-file-word text-blue-500',
    'xls': 'fa-file-excel text-green-500',
    'xlsx': 'fa-file-excel text-green-500',
    'ppt': 'fa-file-powerpoint text-orange-500',
    'pptx': 'fa-file-powerpoint text-orange-500',
    'jpg': 'fa-file-image text-purple-500',
    'jpeg': 'fa-file-image text-purple-500',
    'png': 'fa-file-image text-purple-500',
    'gif': 'fa-file-image text-purple-500',
    'webp': 'fa-file-image text-purple-500',
    'bmp': 'fa-file-image text-purple-500',
    'txt': 'fa-file-alt text-gray-500',
    'zip': 'fa-file-archive text-yellow-500',
    'rar': 'fa-file-archive text-yellow-500',
  }
  
  return iconMap[extension] || 'fa-file text-gray-500'
}

function isImageFile(fileName) {
  const imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp']
  const extension = fileName.split('.').pop().toLowerCase()
  return imageExtensions.includes(extension)
}

function formatFileSize(bytes) {
  if (bytes === 0) return '0 Bytes'
  
  const k = 1024
  const sizes = ['Bytes', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}

function downloadFile(attachment) {
  // Create download link
  const link = document.createElement('a')
  link.href = `/purchase-requisitions/attachments/${attachment.id}/download`
  link.download = attachment.file_name
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
}

function canDeleteAttachment(attachment) {
  // Only allow deletion if user is the uploader or admin
  return attachment.uploaded_by === window.authUser?.id || window.authUser?.roles?.includes('admin')
}

async function deleteAttachment(attachment) {
  if (!confirm('Are you sure you want to delete this file?')) {
    return
  }
  
  try {
    const response = await axios.delete(`/purchase-requisitions/attachments/${attachment.id}`)
    
    if (response.data.success) {
      // Remove from local array
      const index = props.purchaseRequisition.attachments.findIndex(a => a.id === attachment.id)
      if (index > -1) {
        props.purchaseRequisition.attachments.splice(index, 1)
      }
    }
  } catch (error) {
    console.error('Failed to delete attachment:', error)
    alert(`Failed to delete file: ${error.response?.data?.message || error.message}`)
  }
}

function openLightbox(attachment) {
  lightboxImage.value = attachment
  showLightbox.value = true
}

function closeLightbox() {
  showLightbox.value = false
  lightboxImage.value = null
}

// Approval Flow styling methods
function getApprovalFlowClass(status) {
  switch (status) {
    case 'APPROVED':
      return 'bg-green-50 border-green-200'
    case 'REJECTED':
      return 'bg-red-50 border-red-200'
    case 'PENDING':
      return 'bg-yellow-50 border-yellow-200'
    default:
      return 'bg-gray-50 border-gray-200'
  }
}

function getApprovalLevelClass(level) {
  const colors = [
    'bg-blue-100 text-blue-800',
    'bg-indigo-100 text-indigo-800',
    'bg-purple-100 text-purple-800',
    'bg-pink-100 text-pink-800',
    'bg-red-100 text-red-800'
  ]
  return colors[(level - 1) % colors.length]
}

function getApprovalStatusIconClass(status) {
  switch (status) {
    case 'APPROVED':
      return 'bg-green-100 text-green-600'
    case 'REJECTED':
      return 'bg-red-100 text-red-600'
    case 'PENDING':
      return 'bg-yellow-100 text-yellow-600'
    default:
      return 'bg-gray-100 text-gray-600'
  }
}

function getApprovalStatusIcon(status) {
  switch (status) {
    case 'APPROVED':
      return 'fa fa-check text-sm'
    case 'REJECTED':
      return 'fa fa-times text-sm'
    case 'PENDING':
      return 'fa fa-clock text-sm'
    default:
      return 'fa fa-question text-sm'
  }
}

function getApprovalStatusTextClass(status) {
  switch (status) {
    case 'APPROVED':
      return 'text-green-600'
    case 'REJECTED':
      return 'text-red-600'
    case 'PENDING':
      return 'text-yellow-600'
    default:
      return 'text-gray-600'
  }
}

function getMonthName(monthNumber) {
  const months = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December'
  ]
  return months[monthNumber - 1] || 'Unknown'
}

function getBudgetProgressColor(usedAmount, totalBudget) {
  const percentage = (usedAmount / totalBudget) * 100
  if (percentage >= 100) return 'bg-red-500'
  if (percentage >= 80) return 'bg-yellow-500'
  if (percentage >= 60) return 'bg-orange-500'
  return 'bg-green-500'
}

// Helper functions for budget calculations
function getTotalBudget() {
  if (!props.budgetInfo) return 0
  return props.budgetInfo.budget_type === 'PER_OUTLET' 
    ? props.budgetInfo.outlet_budget 
    : props.budgetInfo.category_budget
}

function getUsedAmount() {
  if (!props.budgetInfo) return 0
  return props.budgetInfo.budget_type === 'PER_OUTLET' 
    ? props.budgetInfo.outlet_used_amount 
    : props.budgetInfo.category_used_amount
}

function getRemainingAmount() {
  if (!props.budgetInfo) return 0
  return props.budgetInfo.budget_type === 'PER_OUTLET' 
    ? props.budgetInfo.outlet_remaining_amount 
    : props.budgetInfo.category_remaining_amount
}

function getUsagePercentage() {
  const used = getUsedAmount()
  const total = getTotalBudget()
  if (total === 0) return 0
  return (used / total) * 100
}

// Action methods
const editRequisition = () => {
  router.visit(`/purchase-requisitions/${props.purchaseRequisition.id}/edit`)
}

const addComment = () => {
  router.post(`/purchase-requisitions/${props.purchaseRequisition.id}/comments`, {
    comment: newComment.value,
    is_internal: isInternalComment.value,
  }, {
    onSuccess: () => {
      newComment.value = ''
      isInternalComment.value = false
    }
  })
}

const submitRequisition = () => {
  router.post(`/purchase-requisitions/${props.purchaseRequisition.id}/submit`)
}

const approveRequisition = () => {
  router.post(`/purchase-requisitions/${props.purchaseRequisition.id}/approve`)
}

const rejectRequisition = () => {
  router.post(`/purchase-requisitions/${props.purchaseRequisition.id}/reject`, {
    rejection_reason: rejectionReason.value,
  }, {
    onSuccess: () => {
      showRejectModal.value = false
      rejectionReason.value = ''
    }
  })
}

// Get mode label
function getModeLabel(mode) {
  const labels = {
    'pr_ops': 'Purchase Requisition',
    'purchase_payment': 'Payment Application',
    'travel_application': 'Travel Application',
    'kasbon': 'Kasbon'
  }
  return labels[mode] || mode
}

// Get mode badge class
function getModeBadgeClass(mode) {
  const classes = {
    'pr_ops': 'bg-blue-100 text-blue-800',
    'purchase_payment': 'bg-green-100 text-green-800',
    'travel_application': 'bg-purple-100 text-purple-800',
    'kasbon': 'bg-orange-100 text-orange-800'
  }
  return classes[mode] || 'bg-gray-100 text-gray-800'
}

// Group items by outlet and category for pr_ops and purchase_payment modes
function getGroupedItems(items) {
  if (!items || items.length === 0) return {}
  
  const grouped = {}
  items.forEach(item => {
    // For legacy data without outlet_id, use main PR outlet or 'General'
    const outletId = item.outlet_id || 'no-outlet'
    const outletName = item.outlet?.nama_outlet || (props.purchaseRequisition?.outlet?.nama_outlet || 'General')
    // For legacy data without category_id, use main PR category or 'General'
    const categoryId = item.category_id || 'no-category'
    const categoryName = item.category?.name || (props.purchaseRequisition?.category?.name || 'General')
    
    if (!grouped[outletId]) {
      grouped[outletId] = {
        outlet_id: outletId,
        outlet_name: outletName,
        categories: {}
      }
    }
    
    if (!grouped[outletId].categories[categoryId]) {
      grouped[outletId].categories[categoryId] = {
        category_id: categoryId,
        category_name: categoryName,
        items: []
      }
    }
    
    grouped[outletId].categories[categoryId].items.push(item)
  })
  
  return grouped
}

// Group attachments by outlet
function getGroupedAttachments(attachments) {
  if (!attachments || attachments.length === 0) return {}
  
  const grouped = {}
  attachments.forEach(attachment => {
    // For legacy data without outlet_id, use main PR outlet or 'General'
    const outletId = attachment.outlet_id || 'no-outlet'
    const outletName = attachment.outlet?.nama_outlet || (props.purchaseRequisition?.outlet?.nama_outlet || 'General')
    
    if (!grouped[outletId]) {
      grouped[outletId] = {
        outlet_id: outletId,
        outlet_name: outletName,
        attachments: []
      }
    }
    
    grouped[outletId].attachments.push(attachment)
  })
  
  return grouped
}

</script>