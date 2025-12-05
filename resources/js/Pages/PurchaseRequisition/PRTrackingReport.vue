<script setup>
import { ref, onMounted, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
  user: Object,
});

const loading = ref(true);
const prList = ref([]);
const expandedPRs = ref({});
const filters = ref({
  search: '',
  status: '',
  division: '',
  dateFrom: '',
  dateTo: '',
  per_page: 15,
  page: 1
});

const divisions = ref([]);
const pagination = ref({
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 0,
  from: null,
  to: null,
  links: []
});

// Fetch PR tracking data
const fetchPRTrackingData = async (page = 1) => {
  try {
    loading.value = true;
    const params = {
      ...filters.value,
      page: page,
      per_page: filters.value.per_page
    };
    
    const response = await axios.get('/api/pr-tracking-report', {
      params: params
    });
    
    if (response.data.success) {
      prList.value = response.data.data;
      if (response.data.pagination) {
        pagination.value = response.data.pagination;
        filters.value.page = response.data.pagination.current_page;
      }
    }
  } catch (error) {
    console.error('Error fetching PR tracking data:', error);
  } finally {
    loading.value = false;
  }
};

// Fetch divisions
const fetchDivisions = async () => {
  try {
    const response = await axios.get('/api/divisions');
    divisions.value = response.data;
  } catch (error) {
    console.error('Error fetching divisions:', error);
  }
};

// Toggle expand/collapse PR
const togglePR = (prId) => {
  expandedPRs.value[prId] = !expandedPRs.value[prId];
};

// Format currency
const formatCurrency = (amount) => {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
  }).format(amount);
};

// Format date
const formatDate = (date) => {
  return new Date(date).toLocaleDateString('id-ID');
};

// Get status color
const getStatusColor = (status) => {
  const colors = {
    'DRAFT': 'bg-gray-100 text-gray-800',
    'SUBMITTED': 'bg-yellow-100 text-yellow-800',
    'PENDING': 'bg-orange-100 text-orange-800',
    'APPROVED': 'bg-green-100 text-green-800',
    'REJECTED': 'bg-red-100 text-red-800',
    'PROCESSED': 'bg-blue-100 text-blue-800',
  };
  return colors[status] || 'bg-gray-100 text-gray-800';
};

// Get mode label
const getModeLabel = (mode) => {
  if (!mode) return '-';
  const labels = {
    'pr_ops': 'Purchase Requisition',
    'purchase_payment': 'Payment Application',
  };
  return labels[mode] || mode;
};

// Get mode badge class
const getModeBadgeClass = (mode) => {
  if (!mode) return 'bg-gray-100 text-gray-800';
  const classes = {
    'pr_ops': 'bg-blue-100 text-blue-800',
    'purchase_payment': 'bg-green-100 text-green-800',
  };
  return classes[mode] || 'bg-gray-100 text-gray-800';
};

// Get timeline status color
const getTimelineStatusColor = (status) => {
  const colors = {
    'completed': 'bg-green-500',
    'current': 'bg-blue-500',
    'pending': 'bg-gray-300',
    'rejected': 'bg-red-500',
  };
  return colors[status] || 'bg-gray-300';
};

// Get timeline status text
const getTimelineStatusText = (status) => {
  const texts = {
    'completed': 'Completed',
    'current': 'Current',
    'pending': 'Pending',
    'rejected': 'Rejected',
  };
  return texts[status] || 'Unknown';
};

// Get appropriate icon for timeline status
const getTimelineIcon = (type, status) => {
  const iconMap = {
    'created': 'fas fa-plus-circle',
    'approval': {
      'completed': 'fas fa-check-circle',
      'current': 'fas fa-clock',
      'pending': 'fas fa-hourglass-half',
      'rejected': 'fas fa-times-circle'
    },
    'po_generated': 'fas fa-shopping-cart',
    'po_approval': {
      'completed': 'fas fa-check-circle',
      'current': 'fas fa-clock',
      'pending': 'fas fa-hourglass-half',
      'rejected': 'fas fa-times-circle'
    },
    'po_status': 'fas fa-info-circle',
    'payment_created': 'fas fa-credit-card',
    'payment_status': 'fas fa-info-circle',
    'payment_ready': 'fas fa-credit-card'
  };
  
  if (type === 'approval' || type === 'po_approval') {
    return iconMap[type][status] || 'fas fa-question-circle';
  }
  
  return iconMap[type] || 'fas fa-circle';
};

// Get PR timeline data
const getPRTimeline = (pr) => {
  const timeline = [];
  
  // Created
  timeline.push({
    id: 'created',
    title: 'PR Created',
    description: `Created by ${pr.creator?.nama_lengkap || 'Unknown'}`,
    date: pr.created_at,
    status: 'completed',
    icon: getTimelineIcon('created', 'completed')
  });

  // Approval flows
  if (pr.approval_flows && pr.approval_flows.length > 0) {
    pr.approval_flows.forEach((flow, index) => {
      const isLastApproval = index === pr.approval_flows.length - 1;
      const isCurrentApproval = flow.status === 'PENDING' && isLastApproval;
      const isCompleted = flow.status === 'APPROVED';
      const isRejected = flow.status === 'REJECTED';
      
      let description = `${flow.approver?.nama_lengkap || 'Unknown'} - ${flow.status}`;
      if (isCurrentApproval) {
        description = `⏳ Pending approval from: ${flow.approver?.nama_lengkap || 'Unknown'}`;
      } else if (isRejected) {
        description = `❌ Rejected by: ${flow.approver?.nama_lengkap || 'Unknown'}`;
      } else if (isCompleted) {
        description = `✅ Approved by: ${flow.approver?.nama_lengkap || 'Unknown'}`;
      }
      
      const approvalStatus = isCompleted ? 'completed' : (isCurrentApproval ? 'current' : (isRejected ? 'rejected' : 'pending'));
      timeline.push({
        id: `approval_${flow.id}`,
        title: `Approval Level ${flow.approval_level}`,
        description: description,
        date: flow.approved_at || flow.created_at,
        status: approvalStatus,
        icon: getTimelineIcon('approval', approvalStatus)
      });
    });
  }

  // PO Generation
  if (pr.purchase_orders && pr.purchase_orders.length > 0) {
    pr.purchase_orders.forEach((po, index) => {
      // PO Generated
      timeline.push({
        id: `po_generated_${po.id}`,
        title: `Purchase Order Generated`,
        description: `PO Number: ${po.number}`,
        date: po.created_at,
        status: 'completed',
        icon: getTimelineIcon('po_generated', 'completed')
      });

      // PO Approval Flows
      if (po.approval_flows && po.approval_flows.length > 0) {
        po.approval_flows.forEach((flow, flowIndex) => {
          const isLastPOApproval = flowIndex === po.approval_flows.length - 1;
          const isCurrentPOApproval = flow.status === 'PENDING' && isLastPOApproval;
          const isCompletedPO = flow.status === 'APPROVED';
          const isRejectedPO = flow.status === 'REJECTED';
          
          let poDescription = `${flow.approver?.nama_lengkap || 'Unknown'} - ${flow.status}`;
          if (isCurrentPOApproval) {
            poDescription = `⏳ PO Pending approval from: ${flow.approver?.nama_lengkap || 'Unknown'}`;
          } else if (isRejectedPO) {
            poDescription = `❌ PO Rejected by: ${flow.approver?.nama_lengkap || 'Unknown'}`;
          } else if (isCompletedPO) {
            poDescription = `✅ PO Approved by: ${flow.approver?.nama_lengkap || 'Unknown'}`;
          }
          
          const poApprovalStatus = isCompletedPO ? 'completed' : (isCurrentPOApproval ? 'current' : (isRejectedPO ? 'rejected' : 'pending'));
          timeline.push({
            id: `po_approval_${flow.id}`,
            title: `PO Approval Level ${flow.approval_level}`,
            description: poDescription,
            date: flow.approved_at || flow.created_at,
            status: poApprovalStatus,
            icon: getTimelineIcon('po_approval', poApprovalStatus)
          });
        });
      } else {
        // PO without approval flows
        let poStatus = 'current';
        let poDescription = `PO Status: ${po.status}`;
        
        if (po.status === 'approved') {
          poStatus = 'completed';
          poDescription = `✅ PO Approved: ${po.number}`;
        } else if (po.status === 'rejected') {
          poStatus = 'rejected';
          poDescription = `❌ PO Rejected: ${po.number}`;
        } else if (po.status === 'submitted' || po.status === 'pending') {
          poStatus = 'current';
          poDescription = `⏳ PO Pending Approval: ${po.number}`;
        }
        
        timeline.push({
          id: `po_status_${po.id}`,
          title: `PO Status`,
          description: poDescription,
          date: po.updated_at || po.created_at,
          status: poStatus,
          icon: getTimelineIcon('po_status', poStatus)
        });
      }
    });
  } else if (pr.status === 'APPROVED') {
    timeline.push({
      id: 'po_pending',
      title: 'Purchase Order Generation',
      description: 'Ready to generate PO',
      date: null,
      status: 'pending',
      icon: getTimelineIcon('po_generated', 'pending')
    });
  }

  // Payment Status
  if (pr.payments && pr.payments.length > 0) {
    pr.payments.forEach((payment, index) => {
      // Payment Created
      timeline.push({
        id: `payment_created_${payment.id}`,
        title: `Payment Created`,
        description: `Payment Number: ${payment.payment_number} - Amount: ${formatCurrency(payment.amount)}`,
        date: payment.created_at,
        status: 'completed',
        icon: getTimelineIcon('payment_created', 'completed')
      });

      // Payment Status (no approval flows)
      let paymentStatus = 'completed';
      let paymentDescription = `💰 Payment Paid: ${payment.payment_number}`;
      
      timeline.push({
        id: `payment_status_${payment.id}`,
        title: `Payment Status`,
        description: paymentDescription,
        date: payment.updated_at || payment.created_at,
        status: paymentStatus,
        icon: getTimelineIcon('payment_status', paymentStatus)
      });
    });
  } else if (pr.status === 'APPROVED' && pr.purchase_orders && pr.purchase_orders.length > 0) {
    // Check if PO is approved, then show payment ready
    const hasApprovedPO = pr.purchase_orders.some(po => po.status === 'approved');
    if (hasApprovedPO) {
      timeline.push({
        id: 'payment_ready',
        title: 'Payment Ready',
        description: 'Ready to create payment',
        date: null,
        status: 'pending',
        icon: getTimelineIcon('payment_ready', 'pending')
      });
    }
  }

  return timeline;
};

// Get pending approver info
const getPendingApprover = (pr) => {
  // Check PR approval flows first
  if (pr.approval_flows && pr.approval_flows.length > 0) {
    const pendingFlow = pr.approval_flows.find(flow => flow.status === 'PENDING');
    if (pendingFlow) {
      return `PR: ${pendingFlow.approver?.nama_lengkap || 'Unknown'}`;
    }
  }
  
  // Check PO approval flows
  if (pr.purchase_orders && pr.purchase_orders.length > 0) {
    for (const po of pr.purchase_orders) {
      if (po.approval_flows && po.approval_flows.length > 0) {
        const pendingPOFlow = po.approval_flows.find(flow => flow.status === 'PENDING');
        if (pendingPOFlow) {
          return `PO: ${pendingPOFlow.approver?.nama_lengkap || 'Unknown'}`;
        }
      }
    }
  }
  
  
  return null;
};

// Get count functions for summary cards
const getPendingCount = () => {
  return prList.value.filter(pr => {
    // Check PR approval flows
    if (pr.approval_flows && pr.approval_flows.length > 0) {
      if (pr.approval_flows.some(flow => flow.status === 'PENDING')) {
        return true;
      }
    }
    
    // Check PO approval flows
    if (pr.purchase_orders && pr.purchase_orders.length > 0) {
      for (const po of pr.purchase_orders) {
        if (po.approval_flows && po.approval_flows.length > 0) {
          if (po.approval_flows.some(flow => flow.status === 'PENDING')) {
            return true;
          }
        }
      }
    }
    
    
    return false;
  }).length;
};

const getApprovedCount = () => {
  return prList.value.filter(pr => pr.status === 'APPROVED').length;
};

const getRejectedCount = () => {
  return prList.value.filter(pr => pr.status === 'REJECTED').length;
};

const getPOPendingCount = () => {
  return prList.value.filter(pr => {
    if (pr.purchase_orders && pr.purchase_orders.length > 0) {
      for (const po of pr.purchase_orders) {
        if (po.approval_flows && po.approval_flows.length > 0) {
          if (po.approval_flows.some(flow => flow.status === 'PENDING')) {
            return true;
          }
        }
      }
    }
    return false;
  }).length;
};

// Apply filters
const applyFilters = () => {
  filters.value.page = 1; // Reset to first page when filtering
  fetchPRTrackingData(1);
};

// Clear filters
const clearFilters = () => {
  filters.value = {
    search: '',
    status: '',
    division: '',
    dateFrom: '',
    dateTo: '',
    per_page: filters.value.per_page, // Keep per_page
    page: 1
  };
  fetchPRTrackingData(1);
};

// Change per page
const changePerPage = () => {
  filters.value.page = 1; // Reset to first page when changing per_page
  fetchPRTrackingData(1);
};

// Go to page
const goToPage = (page) => {
  if (page >= 1 && page <= pagination.value.last_page) {
    fetchPRTrackingData(page);
  }
};

// Get page number from link
const getPageFromLink = (link) => {
  if (!link || !link.url) return null;
  const match = link.url.match(/[?&]page=(\d+)/);
  return match ? parseInt(match[1]) : null;
};

onMounted(() => {
  fetchPRTrackingData();
  fetchDivisions();
});
</script>

<template>
  <AppLayout title="PR Tracking Report">
    <div class="w-full py-8 px-4">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa fa-chart-line text-blue-500"></i>
          PR Tracking Report
        </h1>
        <Link
          href="/purchase-requisitions"
          class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded"
        >
          <i class="fas fa-arrow-left mr-2"></i>
          Back to PR List
        </Link>
      </div>

      <!-- Filters -->
      <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Filters</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-7 gap-4">
          <!-- Search -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
            <input
              v-model="filters.search"
              type="text"
              placeholder="PR Number, Title..."
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>

          <!-- Status -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select
              v-model="filters.status"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="">All Status</option>
              <option value="DRAFT">Draft</option>
              <option value="SUBMITTED">Submitted</option>
              <option value="PENDING">Pending Approval</option>
              <option value="APPROVED">Approved</option>
              <option value="REJECTED">Rejected</option>
              <option value="PROCESSED">Processed</option>
            </select>
          </div>

          <!-- Division -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Division</label>
            <select
              v-model="filters.division"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="">All Divisions</option>
              <option v-for="division in divisions" :key="division.id" :value="division.id">
                {{ division.nama_divisi }}
              </option>
            </select>
          </div>

          <!-- Date From -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
            <input
              v-model="filters.dateFrom"
              type="date"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>

          <!-- Date To -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
            <input
              v-model="filters.dateTo"
              type="date"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>

          <!-- Per Page -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Per Page</label>
            <select
              v-model="filters.per_page"
              @change="changePerPage"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option :value="10">10</option>
              <option :value="15">15</option>
              <option :value="25">25</option>
              <option :value="50">50</option>
              <option :value="100">100</option>
            </select>
          </div>

          <!-- Action Buttons -->
          <div class="flex items-end space-x-2">
            <button
              @click="applyFilters"
              class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
            >
              <i class="fa fa-search mr-1"></i>
              Filter
            </button>
            <button
              @click="clearFilters"
              class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700"
            >
              <i class="fa fa-times mr-1"></i>
              Clear
            </button>
          </div>
        </div>
      </div>

      <!-- Summary Cards -->
      <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-4">
          <div class="flex items-center">
            <div class="p-2 bg-blue-100 rounded-lg">
              <i class="fa fa-file-invoice text-blue-600"></i>
            </div>
            <div class="ml-3">
              <p class="text-sm font-medium text-gray-500">Total PR</p>
              <p class="text-lg font-semibold text-gray-900">{{ prList.length }}</p>
            </div>
          </div>
        </div>
        
        <div class="bg-white rounded-lg shadow p-4">
          <div class="flex items-center">
            <div class="p-2 bg-orange-100 rounded-lg">
              <i class="fa fa-clock text-orange-600"></i>
            </div>
            <div class="ml-3">
              <p class="text-sm font-medium text-gray-500">Pending Approval</p>
              <p class="text-lg font-semibold text-gray-900">{{ getPendingCount() }}</p>
            </div>
          </div>
        </div>
        
        <div class="bg-white rounded-lg shadow p-4">
          <div class="flex items-center">
            <div class="p-2 bg-green-100 rounded-lg">
              <i class="fa fa-check-circle text-green-600"></i>
            </div>
            <div class="ml-3">
              <p class="text-sm font-medium text-gray-500">Approved</p>
              <p class="text-lg font-semibold text-gray-900">{{ getApprovedCount() }}</p>
            </div>
          </div>
        </div>
        
        <div class="bg-white rounded-lg shadow p-4">
          <div class="flex items-center">
            <div class="p-2 bg-red-100 rounded-lg">
              <i class="fa fa-times-circle text-red-600"></i>
            </div>
            <div class="ml-3">
              <p class="text-sm font-medium text-gray-500">Rejected</p>
              <p class="text-lg font-semibold text-gray-900">{{ getRejectedCount() }}</p>
            </div>
          </div>
        </div>
        
        <div class="bg-white rounded-lg shadow p-4">
          <div class="flex items-center">
            <div class="p-2 bg-purple-100 rounded-lg">
              <i class="fa fa-shopping-cart text-purple-600"></i>
            </div>
            <div class="ml-3">
              <p class="text-sm font-medium text-gray-500">PO Pending</p>
              <p class="text-lg font-semibold text-gray-900">{{ getPOPendingCount() }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- PR List -->
      <div class="bg-white rounded-xl shadow-lg">
        <div class="p-6">
          <h2 class="text-lg font-semibold text-gray-800 mb-4">Purchase Requisitions</h2>
          
          <!-- Loading -->
          <div v-if="loading" class="text-center py-8">
            <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
            <p class="mt-2 text-gray-600">Loading PR data...</p>
          </div>

          <!-- No Data -->
          <div v-else-if="prList.length === 0" class="text-center py-8">
            <i class="fa fa-inbox text-4xl text-gray-400 mb-2"></i>
            <p class="text-gray-600">No PR found</p>
          </div>

          <!-- PR List -->
          <div v-else class="space-y-4">
            <div
              v-for="pr in prList"
              :key="pr.id"
              class="border border-gray-200 rounded-lg hover:shadow-md transition-shadow"
            >
              <!-- PR Header -->
              <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                <div class="flex items-center justify-between">
                  <div class="flex items-center space-x-4">
                    <button
                      @click="togglePR(pr.id)"
                      class="text-blue-600 hover:text-blue-800"
                    >
                      <i :class="expandedPRs[pr.id] ? 'fas fa-chevron-down' : 'fas fa-chevron-right'"></i>
                    </button>
                    <div>
                      <h3 class="text-lg font-semibold text-gray-900">{{ pr.pr_number }}</h3>
                      <p class="text-sm text-gray-600">{{ pr.title }}</p>
                      <p class="text-sm text-gray-500">{{ pr.division?.nama_divisi || 'Unknown Division' }}</p>
                      <p class="text-xs text-gray-400 mt-1">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium" :class="getModeBadgeClass(pr.mode)">
                          {{ getModeLabel(pr.mode) }}
                        </span>
                      </p>
                    </div>
                  </div>
                  <div class="text-right">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" :class="getStatusColor(pr.status)">
                      {{ pr.status }}
                    </span>
                    <p class="text-sm font-semibold text-gray-900 mt-1">{{ formatCurrency(pr.amount) }}</p>
                    <p class="text-xs text-gray-500">{{ formatDate(pr.created_at) }}</p>
                    
                    <!-- Pending Approver Info -->
                    <div v-if="getPendingApprover(pr)" class="mt-2">
                      <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-2">
                        <div class="flex items-center">
                          <i class="fa fa-clock text-yellow-600 mr-2"></i>
                          <div class="text-xs">
                            <p class="font-medium text-yellow-800">Pending Approval</p>
                            <p class="text-yellow-700">{{ getPendingApprover(pr) }}</p>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Timeline -->
              <div v-if="expandedPRs[pr.id]" class="p-4">
                <h4 class="text-md font-semibold text-gray-800 mb-4 flex items-center">
                  <i class="fa fa-timeline mr-2 text-blue-500"></i>
                  PR Timeline
                </h4>
                
                <div class="relative">
                  <!-- Timeline Line -->
                  <div class="absolute left-4 top-0 bottom-0 w-0.5 bg-gray-300"></div>
                  
                  <!-- Timeline Items -->
                  <div class="space-y-6">
                    <div
                      v-for="(item, index) in getPRTimeline(pr)"
                      :key="item.id"
                      class="relative flex items-start"
                    >
                      <!-- Timeline Icon -->
                      <div class="relative z-10 flex items-center justify-center w-8 h-8 rounded-full border-2 border-white shadow-sm" :class="getTimelineStatusColor(item.status)">
                        <i :class="item.icon" class="text-white text-sm"></i>
                      </div>
                      
                      <!-- Timeline Content -->
                      <div class="ml-4 flex-1">
                        <div class="flex items-center justify-between">
                          <h5 class="text-sm font-medium text-gray-900">{{ item.title }}</h5>
                          <span v-if="item.date" class="text-xs text-gray-500">{{ formatDate(item.date) }}</span>
                        </div>
                        <p class="text-sm text-gray-600 mt-1">{{ item.description }}</p>
                        <div class="mt-2">
                          <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium" :class="{
                            'bg-green-100 text-green-800': item.status === 'completed',
                            'bg-blue-100 text-blue-800': item.status === 'current',
                            'bg-gray-100 text-gray-800': item.status === 'pending',
                            'bg-red-100 text-red-800': item.status === 'rejected'
                          }">
                            {{ getTimelineStatusText(item.status) }}
                          </span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Pagination -->
          <div v-if="!loading && prList.length > 0" class="mt-6 flex flex-col sm:flex-row justify-between items-center gap-4">
            <!-- Pagination Info -->
            <div class="text-sm text-gray-700">
              Menampilkan <span class="font-medium">{{ pagination.from || 0 }}</span> sampai 
              <span class="font-medium">{{ pagination.to || 0 }}</span> dari 
              <span class="font-medium">{{ pagination.total }}</span> data
            </div>

            <!-- Pagination Controls -->
            <div class="flex items-center gap-2">
              <!-- Previous Button -->
              <button
                @click="goToPage(pagination.current_page - 1)"
                :disabled="pagination.current_page === 1"
                class="px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <i class="fas fa-chevron-left mr-1"></i> Previous
              </button>

              <!-- Page Numbers -->
              <div class="flex items-center gap-1">
                <button
                  v-for="link in pagination.links"
                  :key="link.label"
                  @click="goToPage(getPageFromLink(link))"
                  :disabled="!link.url || link.active"
                  :class="[
                    'px-3 py-2 border rounded-md text-sm font-medium',
                    link.active
                      ? 'bg-blue-600 text-white border-blue-600 cursor-default'
                      : link.url
                      ? 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'
                      : 'bg-gray-50 text-gray-400 border-gray-200 cursor-not-allowed'
                  ]"
                  v-html="link.label"
                ></button>
              </div>

              <!-- Next Button -->
              <button
                @click="goToPage(pagination.current_page + 1)"
                :disabled="pagination.current_page === pagination.last_page"
                class="px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                Next <i class="fas fa-chevron-right ml-1"></i>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
