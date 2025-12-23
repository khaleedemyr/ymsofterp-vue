<template>
  <AppLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Outlet Rejection Detail
      </h2>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6 text-gray-900">
            
            <!-- Header -->
            <div class="flex justify-between items-center mb-6">
              <div>
                <h3 class="text-lg font-semibold">{{ rejection.number }}</h3>
                <p class="text-gray-600">Outlet Rejection Details</p>
              </div>
              <div class="flex space-x-2">
                <Link
                  :href="route('outlet-rejections.index')"
                  class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded"
                >
                  <i class="fas fa-arrow-left mr-2"></i>
                  Back to List
                </Link>
                
                <!-- Edit Button (only for draft) -->
                <Link
                  v-if="rejection.status === 'draft'"
                  :href="route('outlet-rejections.edit', rejection.id)"
                  class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded"
                >
                  <i class="fas fa-edit mr-2"></i>
                  Edit
                </Link>
              </div>
            </div>

            <!-- Status Badge -->
            <div class="mb-6">
              <span :class="getStatusBadgeClass(rejection.status)" class="text-lg px-4 py-2">
                {{ getStatusLabel(rejection.status) }}
              </span>
            </div>

                         <!-- Header Information -->
             <div class="bg-gray-50 p-6 rounded-lg mb-6">
               <h4 class="text-lg font-semibold mb-4">Header Information</h4>
               
               <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                 <div>
                   <label class="block text-sm font-medium text-gray-700">Rejection Number</label>
                   <p class="text-lg font-semibold text-gray-900">{{ rejection.number }}</p>
                 </div>
                 
                 <div>
                   <label class="block text-sm font-medium text-gray-700">Rejection Date</label>
                   <p class="text-lg text-gray-900">{{ formatDate(rejection.rejection_date) }}</p>
                 </div>
                 
                 <div>
                   <label class="block text-sm font-medium text-gray-700">Status</label>
                   <span :class="getStatusBadgeClass(rejection.status)">
                     {{ getStatusLabel(rejection.status) }}
                   </span>
                 </div>
                 
                 <div>
                   <label class="block text-sm font-medium text-gray-700">Outlet</label>
                   <p class="text-lg text-gray-900">{{ rejection.outlet?.nama_outlet }}</p>
                 </div>
                 
                 <div>
                   <label class="block text-sm font-medium text-gray-700">Warehouse</label>
                   <p class="text-lg text-gray-900">{{ rejection.warehouse?.name }}</p>
                 </div>
                 
                 <div v-if="rejection.delivery_order">
                   <label class="block text-sm font-medium text-gray-700">Delivery Order</label>
                   <p class="text-lg text-gray-900">{{ rejection.delivery_order.number }}</p>
                 </div>
                 
                 <div v-if="rejection.notes" class="md:col-span-2">
                   <label class="block text-sm font-medium text-gray-700">Notes</label>
                   <p class="text-gray-900">{{ rejection.notes }}</p>
                 </div>
               </div>
             </div>

             <!-- Document Flow Information -->
             <div v-if="documentFlowInfo" class="bg-blue-50 p-6 rounded-lg mb-6">
               <h4 class="text-lg font-semibold mb-4">Document Flow Information</h4>
               
               <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                 <!-- Floor Order -->
                 <div v-if="documentFlowInfo.floor_order_number" class="bg-white p-4 rounded-lg border">
                   <h5 class="text-sm font-medium text-gray-700 mb-2">Floor Order</h5>
                   <div class="text-lg font-semibold text-indigo-600">{{ documentFlowInfo.floor_order_number }}</div>
                   <div class="text-sm text-gray-500">{{ formatDateTime(documentFlowInfo.floor_order_created_at) }}</div>
                   <div class="text-sm text-gray-600">{{ documentFlowInfo.floor_order_creator }}</div>
                   <div v-if="documentFlowInfo.floor_order_mode" class="text-xs text-gray-400 mt-1">
                     Mode: {{ documentFlowInfo.floor_order_mode }}
                   </div>
                 </div>
                 
                 <!-- Packing List -->
                 <div v-if="documentFlowInfo.packing_number" class="bg-white p-4 rounded-lg border">
                   <h5 class="text-sm font-medium text-gray-700 mb-2">Packing List</h5>
                   <div class="text-lg font-semibold text-blue-600">{{ documentFlowInfo.packing_number }}</div>
                   <div class="text-sm text-gray-500">{{ formatDateTime(documentFlowInfo.packing_list_created_at) }}</div>
                   <div class="text-sm text-gray-600">{{ documentFlowInfo.packing_list_creator }}</div>
                 </div>
                 
                 <!-- Delivery Order -->
                 <div v-if="documentFlowInfo.delivery_order_number" class="bg-white p-4 rounded-lg border">
                   <h5 class="text-sm font-medium text-gray-700 mb-2">Delivery Order</h5>
                   <div class="text-lg font-semibold text-orange-600">{{ documentFlowInfo.delivery_order_number }}</div>
                   <div class="text-sm text-gray-500">{{ formatDateTime(documentFlowInfo.delivery_order_created_at) }}</div>
                   <div class="text-sm text-gray-600">{{ documentFlowInfo.delivery_order_creator }}</div>
                 </div>
                 
                 <!-- Good Receive -->
                 <div v-if="documentFlowInfo.good_receive_number" class="bg-white p-4 rounded-lg border">
                   <h5 class="text-sm font-medium text-gray-700 mb-2">Good Receive</h5>
                   <div class="text-lg font-semibold text-green-600">{{ documentFlowInfo.good_receive_number }}</div>
                   <div class="text-sm text-gray-500">{{ formatDate(documentFlowInfo.good_receive_date) }}</div>
                   <div class="text-sm text-gray-600">{{ documentFlowInfo.good_receive_creator }}</div>
                 </div>
               </div>
             </div>

            <!-- Workflow Information -->
            <div class="bg-blue-50 p-6 rounded-lg mb-6">
              <h4 class="text-lg font-semibold mb-4">Workflow Information</h4>
              
              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div>
                  <label class="block text-sm font-medium text-gray-700">Created By</label>
                  <p class="text-gray-900">{{ rejection.approval_info?.created_by || rejection.createdBy?.nama_lengkap || '-' }}</p>
                  <p class="text-sm text-gray-500">{{ rejection.approval_info?.created_at || formatDateTime(rejection.created_at) }}</p>
                </div>
                
                <div v-if="rejection.approval_info?.assistant_ssd_manager || rejection.assistantSsdManager?.nama_lengkap">
                  <label class="block text-sm font-medium text-gray-700">Assistant SSD Manager</label>
                  <p class="text-gray-900">{{ rejection.approval_info?.assistant_ssd_manager || rejection.assistantSsdManager?.nama_lengkap }}</p>
                  <p class="text-sm text-gray-500">{{ rejection.approval_info?.assistant_ssd_manager_at || formatDateTime(rejection.assistant_ssd_manager_approved_at) }}</p>
                </div>
                
                <div v-if="rejection.approval_info?.ssd_manager || rejection.ssdManager?.nama_lengkap">
                  <label class="block text-sm font-medium text-gray-700">SSD Manager</label>
                  <p class="text-gray-900">{{ rejection.approval_info?.ssd_manager || rejection.ssdManager?.nama_lengkap }}</p>
                  <p class="text-sm text-gray-500">{{ rejection.approval_info?.ssd_manager_at || formatDateTime(rejection.ssd_manager_approved_at) }}</p>
                </div>
                
                <div v-if="rejection.approval_info?.completed_by || rejection.completedBy?.nama_lengkap">
                  <label class="block text-sm font-medium text-gray-700">Completed By</label>
                  <p class="text-gray-900">{{ rejection.approval_info?.completed_by || rejection.completedBy?.nama_lengkap }}</p>
                  <p class="text-sm text-gray-500">{{ rejection.approval_info?.completed_at || formatDateTime(rejection.completed_at) }}</p>
                </div>
              </div>
            </div>

            <!-- Items Table -->
            <div class="bg-gray-50 p-6 rounded-lg mb-6">
              <h4 class="text-lg font-semibold mb-4">Rejected Items</h4>
              
              <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                  <thead class="bg-gray-100">
                    <tr>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Item
                      </th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Unit
                      </th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Qty Rejected
                      </th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Qty Received
                      </th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Condition
                      </th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        MAC Cost
                      </th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Value
                      </th>
                    </tr>
                  </thead>
                  <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="item in rejection.items" :key="item.id">
                      <td class="px-6 py-4 whitespace-nowrap">
                        <div>
                          <div class="text-sm font-medium text-gray-900">{{ item.item?.name }}</div>
                          <div class="text-sm text-gray-500">{{ item.item?.sku }}</div>
                        </div>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-900">{{ item.unit?.name }}</div>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-900">{{ formatNumber(item.qty_rejected) }}</div>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-900">{{ formatNumber(item.qty_received) }}</div>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap">
                        <span :class="getConditionBadgeClass(item.item_condition)">
                          {{ getConditionLabel(item.item_condition) }}
                        </span>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-900">{{ formatCurrency(item.mac_cost) }}</div>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-900">{{ formatCurrency(item.qty_received * item.mac_cost) }}</div>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <!-- Summary -->
              <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-white p-4 rounded-lg border">
                  <h5 class="text-sm font-medium text-gray-700">Total Rejected</h5>
                  <p class="text-2xl font-bold text-gray-900">{{ formatNumber(getTotalRejected()) }}</p>
                </div>
                <div class="bg-white p-4 rounded-lg border">
                  <h5 class="text-sm font-medium text-gray-700">Total Received</h5>
                  <p class="text-2xl font-bold text-gray-900">{{ formatNumber(getTotalReceived()) }}</p>
                </div>
                <div class="bg-white p-4 rounded-lg border">
                  <h5 class="text-sm font-medium text-gray-700">Total Value</h5>
                  <p class="text-2xl font-bold text-gray-900">{{ formatCurrency(getTotalValue()) }}</p>
                </div>
              </div>
            </div>

                         <!-- Item Details (Expandable) -->
             <div class="bg-gray-50 p-6 rounded-lg mb-6">
               <h4 class="text-lg font-semibold mb-4">Item Details</h4>
               
               <div class="space-y-4">
                 <div
                   v-for="item in rejection.items"
                   :key="item.id"
                   class="bg-white p-4 rounded-lg border border-gray-200"
                 >
                   <div class="flex justify-between items-start">
                     <div>
                       <h5 class="font-medium text-lg">{{ item.item?.name }}</h5>
                       <p class="text-gray-600">{{ item.item?.sku }}</p>
                     </div>
                     <span :class="getConditionBadgeClass(item.item_condition)">
                       {{ getConditionLabel(item.item_condition) }}
                     </span>
                   </div>
                   
                   <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                     <div>
                       <label class="block text-sm font-medium text-gray-700">Rejection Reason</label>
                       <p class="text-gray-900">{{ item.rejection_reason || 'No reason provided' }}</p>
                     </div>
                     <div>
                       <label class="block text-sm font-medium text-gray-700">Condition Notes</label>
                       <p class="text-gray-900">{{ item.condition_notes || 'No notes provided' }}</p>
                     </div>
                   </div>

                   <!-- Quantity Flow Information -->
                   <div v-if="itemsWithQuantityFlow && itemsWithQuantityFlow[item.id]" class="mt-4 pt-4 border-t border-blue-200">
                     <h6 class="font-medium text-sm mb-2 text-blue-800">Quantity Flow:</h6>
                     <div class="grid grid-cols-2 md:grid-cols-5 gap-2 text-xs">
                       <div class="bg-blue-50 p-2 rounded border">
                         <div class="font-medium text-gray-700">Order Qty</div>
                         <div class="text-blue-600">{{ itemsWithQuantityFlow[item.id].qty_order || 0 }}</div>
                       </div>
                       <div class="bg-green-50 p-2 rounded border">
                         <div class="font-medium text-gray-700">PL Qty</div>
                         <div class="text-green-600">{{ itemsWithQuantityFlow[item.id].qty_packing_list || 0 }}</div>
                       </div>
                       <div class="bg-orange-50 p-2 rounded border">
                         <div class="font-medium text-gray-700">DO Qty</div>
                         <div class="text-orange-600">{{ itemsWithQuantityFlow[item.id].qty_do || 0 }}</div>
                       </div>
                       <div class="bg-purple-50 p-2 rounded border">
                         <div class="font-medium text-gray-700">GR Qty</div>
                         <div class="text-purple-600">{{ itemsWithQuantityFlow[item.id].qty_receive || 0 }}</div>
                       </div>
                       <div class="bg-red-50 p-2 rounded border">
                         <div class="font-medium text-gray-700">Remaining</div>
                         <div class="text-red-600 font-bold">{{ (itemsWithQuantityFlow[item.id].qty_do || 0) - (itemsWithQuantityFlow[item.id].qty_receive || 0) }}</div>
                       </div>
                     </div>
                   </div>
                 </div>
               </div>
             </div>

            <!-- Approval Section -->
            <div class="bg-white rounded-2xl shadow p-6 mb-6">
              <h2 class="text-lg font-bold mb-2">Approval</h2>
              
              <!-- Approval Asisten SSD Manager (hanya untuk rejection non-MK) -->
              <div v-if="!isMKWarehouse" class="mb-4">
                <b>Asisten SSD Manager:</b>
                <span v-if="rejection.assistant_ssd_manager_approved_at">
                  <span class="text-green-600 font-semibold">Approved</span>
                  oleh <b>{{ rejection.assistant_ssd_manager?.nama_lengkap || rejection.assistant_ssd_manager_approved_by }}</b>
                  pada {{ formatDateTime(rejection.assistant_ssd_manager_approved_at) }}
                  <span v-if="rejection.assistant_ssd_manager_note">- Note: {{ rejection.assistant_ssd_manager_note }}</span>
                </span>
                <span v-else class="text-gray-500">Belum di-approve</span>
                
                <div v-if="canApproveAssistantSSD" class="mt-2">
                  <button @click="approveAssistantSSD(true)" class="px-4 py-2 rounded bg-green-600 text-white font-semibold hover:bg-green-700 mr-2">Approve (Asisten SSD Manager)</button>
                  <button @click="approveAssistantSSD(false)" class="px-4 py-2 rounded bg-red-600 text-white font-semibold hover:bg-red-700">Reject</button>
                </div>
              </div>
              
              <!-- Approval SSD Manager / Sous Chef MK -->
              <div class="mb-2">
                <b>{{ getApproverTitle }}:</b>
                <span v-if="rejection.ssd_manager_approved_at">
                  <span class="text-green-600 font-semibold">Approved</span>
                  oleh <b>{{ rejection.ssd_manager?.nama_lengkap || rejection.ssd_manager_approved_by }}</b>
                  pada {{ formatDateTime(rejection.ssd_manager_approved_at) }}
                  <span v-if="rejection.ssd_manager_note">- Note: {{ rejection.ssd_manager_note }}</span>
                </span>
                <span v-else class="text-gray-500">Belum di-approve</span>
              </div>
              
              <div v-if="canApproveSSD">
                <button @click="approveSSD(true)" class="px-4 py-2 rounded bg-green-600 text-white font-semibold hover:bg-green-700 mr-2">Approve ({{ getApproverTitle }})</button>
                <button @click="approveSSD(false)" class="px-4 py-2 rounded bg-red-600 text-white font-semibold hover:bg-red-700">Reject</button>
              </div>
            </div>

            

          </div>
        </div>
      </div>
    </div>

    

  </AppLayout>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Swal from 'sweetalert2'

const props = defineProps({
  rejection: Object,
  documentFlowInfo: Object,
  itemsWithQuantityFlow: Object
})

const user = usePage().props.auth?.user || {};

const isSuperadmin = computed(() =>
  user?.id_role === '5af56935b011a' && user?.status === 'A'
);

// Check if warehouse is MK1 or MK2
const isMKWarehouse = computed(() => {
  const warehouseName = props.rejection.warehouse?.name;
  return warehouseName === 'MK1 Hot Kitchen' || warehouseName === 'MK2 Cold Kitchen';
});

// Determine who can approve Assistant SSD Manager
const canApproveAssistantSSD = computed(() => {
  // Hanya untuk rejection non-MK
  if (isMKWarehouse.value) return false;
  
  return ((user?.id_jabatan === 172 && user?.status === 'A') || isSuperadmin.value)
    && props.rejection.status === 'draft'
    && !props.rejection.assistant_ssd_manager_approved_at;
});

// Determine who can approve SSD Manager
const canApproveSSD = computed(() => {
  if (isMKWarehouse.value) {
    // For MK warehouses, Sous Chef MK (id_jabatan=179) can approve
    return ((user?.id_jabatan === 179 && user?.status === 'A') || isSuperadmin.value)
      && props.rejection.status === 'draft'
      && !props.rejection.ssd_manager_approved_at;
  } else {
    // For other warehouses, SSD Manager (id_jabatan=161) atau Asisten SSD Manager (id_jabatan=172) bisa approve
    // Tapi harus sudah di-approve asisten SSD manager terlebih dahulu
    return ((user?.id_jabatan === 161 || user?.id_jabatan === 172) && user?.status === 'A' || isSuperadmin.value)
      && props.rejection.status === 'draft'
      && props.rejection.assistant_ssd_manager_approved_at
      && !props.rejection.ssd_manager_approved_at;
  }
});

// Get approver title based on warehouse
const getApproverTitle = computed(() => {
  return isMKWarehouse.value ? 'Sous Chef MK' : 'SSD Manager';
});

const isProcessing = ref(false)

const formatDate = (date) => {
  return new Date(date).toLocaleDateString('id-ID', {
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
}

const formatDateTime = (date) => {
  return new Date(date).toLocaleString('id-ID', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

const formatNumber = (number) => {
  return new Intl.NumberFormat('id-ID').format(number)
}

const formatCurrency = (amount) => {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR'
  }).format(amount)
}

const getStatusLabel = (status) => {
  const labels = {
    draft: 'Draft',
    submitted: 'Submitted',
    approved: 'Approved',
    completed: 'Completed',
    cancelled: 'Cancelled'
  }
  return labels[status] || status
}

const getStatusBadgeClass = (status) => {
  const classes = {
    draft: 'bg-gray-100 text-gray-800',
    submitted: 'bg-yellow-100 text-yellow-800',
    approved: 'bg-blue-100 text-blue-800',
    completed: 'bg-green-100 text-green-800',
    cancelled: 'bg-red-100 text-red-800'
  }
  return `inline-flex px-2 py-1 text-xs font-semibold rounded-full ${classes[status] || 'bg-gray-100 text-gray-800'}`
}

const getConditionLabel = (condition) => {
  const labels = {
    good: 'Good',
    damaged: 'Damaged',
    expired: 'Expired',
    other: 'Other'
  }
  return labels[condition] || condition
}

const getConditionBadgeClass = (condition) => {
  const classes = {
    good: 'bg-green-100 text-green-800',
    damaged: 'bg-red-100 text-red-800',
    expired: 'bg-orange-100 text-orange-800',
    other: 'bg-gray-100 text-gray-800'
  }
  return `inline-flex px-2 py-1 text-xs font-semibold rounded-full ${classes[condition] || 'bg-gray-100 text-gray-800'}`
}

const getTotalRejected = () => {
  return props.rejection.items.reduce((sum, item) => sum + parseFloat(item.qty_rejected), 0)
}

const getTotalReceived = () => {
  return props.rejection.items.reduce((sum, item) => sum + parseFloat(item.qty_received), 0)
}

const getTotalValue = () => {
  return props.rejection.items.reduce((sum, item) => sum + (parseFloat(item.qty_received) * parseFloat(item.mac_cost)), 0)
}

async function approveAssistantSSD(approved) {
  const { value: note } = await Swal.fire({
    title: approved ? `Approve Outlet Rejection?` : `Reject Outlet Rejection?`,
    input: 'textarea',
    inputLabel: 'Catatan (opsional)',
    inputValue: '',
    showCancelButton: true,
    confirmButtonText: approved ? 'Approve' : 'Reject',
    cancelButtonText: 'Batal',
  });
  if (note !== undefined) {
    router.post(route('outlet-rejections.approve-assistant-ssd-manager', props.rejection.id), {
      approved,
      assistant_ssd_manager_note: note,
    });
  }
}

async function approveSSD(approved) {
  const approverTitle = getApproverTitle.value;
  
  if (approved) {
    // Jika approve, tampilkan form untuk input qty_received
    const { value: formValues } = await Swal.fire({
      title: `Approve Outlet Rejection?`,
      html: `
        <div class="text-left mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-2">Catatan (opsional):</label>
          <textarea id="note" class="w-full p-2 border border-gray-300 rounded-md" rows="3"></textarea>
        </div>
        <div class="text-left">
          <label class="block text-sm font-medium text-gray-700 mb-2">Qty Received untuk setiap item:</label>
          ${props.rejection.items.map(item => `
            <div class="mb-3 p-3 border border-gray-200 rounded">
              <div class="font-medium text-sm">${item.item?.name}</div>
              <div class="text-xs text-gray-600 mb-2">Rejected: ${item.qty_rejected} ${item.unit?.name}</div>
              <div class="flex items-center space-x-2">
                <label class="text-xs">Qty Received:</label>
                <input type="number" 
                       id="qty_${item.id}" 
                       class="w-20 p-1 border border-gray-300 rounded text-sm" 
                       min="0" 
                       max="${item.qty_rejected}" 
                       value="${item.qty_rejected}"
                       step="0.01">
                <span class="text-xs text-gray-500">${item.unit?.name}</span>
              </div>
            </div>
          `).join('')}
        </div>
      `,
      showCancelButton: true,
      confirmButtonText: 'Approve & Complete',
      cancelButtonText: 'Batal',
      preConfirm: () => {
        const note = document.getElementById('note').value;
        const items = props.rejection.items.map(item => ({
          id: item.id,
          qty_received: parseFloat(document.getElementById(`qty_${item.id}`).value) || 0
        }));
        
        // Validasi qty_received tidak boleh lebih dari qty_rejected
        for (let item of items) {
          const originalItem = props.rejection.items.find(i => i.id === item.id);
          if (item.qty_received > originalItem.qty_rejected) {
            Swal.showValidationMessage(`Qty Received untuk ${originalItem.item?.name} tidak boleh lebih dari ${originalItem.qty_rejected}`);
            return false;
          }
        }
        
        return { note, items };
      }
    });
    
    if (formValues) {
      router.post(route('outlet-rejections.approve-ssd-manager', props.rejection.id), {
        approved: true,
        ssd_manager_note: formValues.note,
        items: formValues.items
      });
    }
  } else {
    // Jika reject, hanya input note
    const { value: note } = await Swal.fire({
      title: `Reject Outlet Rejection?`,
      input: 'textarea',
      inputLabel: 'Catatan (opsional)',
      inputValue: '',
      showCancelButton: true,
      confirmButtonText: 'Reject',
      cancelButtonText: 'Batal',
    });
    
    if (note !== undefined) {
      router.post(route('outlet-rejections.approve-ssd-manager', props.rejection.id), {
        approved: false,
        ssd_manager_note: note,
      });
    }
  }
}



const cancelRejection = () => {
  if (confirm('Are you sure you want to cancel this rejection?')) {
    isProcessing.value = true
    router.post(route('outlet-rejections.cancel', props.rejection.id), {}, {
      onSuccess: () => {
        isProcessing.value = false
      },
      onError: () => {
        isProcessing.value = false
      }
    })
  }
}
</script>
