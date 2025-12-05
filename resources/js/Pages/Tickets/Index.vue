<template>
  <AppLayout title="Ticketing System">
    <div class="w-full py-8 px-4">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-ticket-alt text-blue-500"></i> Ticketing System
        </h1>
        <button @click="openCreate" class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
          + Buat Ticket Baru
        </button>
      </div>

      <!-- Statistics Cards -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <!-- Total -->
        <div class="rounded-xl shadow-lg p-6 border-l-4 border-blue-500 bg-white hover:shadow-xl transition-all">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">Total Tickets</p>
              <p class="text-2xl font-bold text-gray-900">{{ statistics.total }}</p>
            </div>
            <i class="fa-solid fa-ticket-alt text-4xl text-blue-300"></i>
          </div>
        </div>
        <!-- Open -->
        <div class="rounded-xl shadow-lg p-6 border-l-4 border-yellow-500 bg-white hover:shadow-xl transition-all">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">Open</p>
              <p class="text-2xl font-bold text-gray-900">{{ statistics.open }}</p>
            </div>
            <i class="fa-solid fa-clock text-4xl text-yellow-300"></i>
          </div>
        </div>
        <!-- In Progress -->
        <div class="rounded-xl shadow-lg p-6 border-l-4 border-orange-500 bg-white hover:shadow-xl transition-all">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">In Progress</p>
              <p class="text-2xl font-bold text-gray-900">{{ statistics.in_progress }}</p>
            </div>
            <i class="fa-solid fa-spinner text-4xl text-orange-300"></i>
          </div>
        </div>
        <!-- Closed -->
        <div class="rounded-xl shadow-lg p-6 border-l-4 border-green-500 bg-white hover:shadow-xl transition-all">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">Closed</p>
              <p class="text-2xl font-bold text-gray-900">{{ statistics.closed }}</p>
            </div>
            <i class="fa-solid fa-check-circle text-4xl text-green-300"></i>
          </div>
        </div>
      </div>

      <!-- Filter and Search -->
      <div class="flex flex-col md:flex-row gap-4 mb-6">
        <input
          type="text"
          v-model="search"
          @input="onSearchInput"
          placeholder="Cari ticket number, title..."
          class="flex-1 px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
        />
        <select
          v-model="status"
          @change="debouncedSearch"
          class="w-full md:w-auto px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
        >
          <option value="all">Semua Status</option>
          <option value="open">Open</option>
          <option value="in_progress">In Progress</option>
          <option value="resolved">Resolved</option>
          <option value="closed">Closed</option>
        </select>
        <select
          v-model="priority"
          @change="debouncedSearch"
          class="w-full md:w-auto px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
        >
          <option value="all">Semua Prioritas</option>
          <option v-for="p in filterOptions.priorities" :key="p.id" :value="p.id">{{ p.name }}</option>
        </select>
        <select
          v-model="division"
          @change="debouncedSearch"
          class="w-full md:w-auto px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
        >
          <option value="all">Semua Divisi</option>
          <option v-for="d in filterOptions.divisions" :key="d.id" :value="d.id">{{ d.nama_divisi }}</option>
        </select>
        <select
          v-model="perPage"
          @change="debouncedSearch"
          class="w-full md:w-auto px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
        >
          <option value="15">15 Per Halaman</option>
          <option value="30">30 Per Halaman</option>
          <option value="50">50 Per Halaman</option>
        </select>
      </div>

      <!-- Tickets Table -->
      <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ticket</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Priority</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Divisi</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created By</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created At</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Date</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Comments</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="ticket in data.data" :key="ticket.id" class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm font-medium text-gray-900">{{ ticket.ticket_number }}</div>
                </td>
                <td class="px-6 py-4">
                  <div class="text-sm text-gray-900 max-w-xs truncate">{{ ticket.title }}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span :class="[
                    'inline-flex px-2 py-1 text-xs font-semibold rounded-full',
                    'bg-blue-100 text-blue-800'
                  ]">
                    {{ ticket.category?.name || '-' }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span :class="[
                    'inline-flex px-2 py-1 text-xs font-semibold rounded-full',
                    getPriorityColor(ticket.priority?.level)
                  ]">
                    {{ ticket.priority?.name || '-' }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span :class="[
                    'inline-flex px-2 py-1 text-xs font-semibold rounded-full',
                    getStatusColor(ticket.status?.slug)
                  ]">
                    {{ ticket.status?.name || '-' }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ ticket.divisi?.nama_divisi || '-' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ ticket.creator?.nama_lengkap || '-' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  {{ new Date(ticket.created_at).toLocaleDateString('id-ID') }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm">
                  <div v-if="ticket.due_date" class="flex items-center gap-2">
                    <span class="text-gray-900">{{ new Date(ticket.due_date).toLocaleDateString('id-ID') }}</span>
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
                          :class="getDueDateBadgeClass(ticket.due_date, ticket.status?.slug)">
                      <i class="fas fa-clock mr-1"></i>
                      {{ getDueDateStatus(ticket.due_date) }}
                    </span>
                  </div>
                  <span v-else class="text-gray-400">-</span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  <div class="flex items-center gap-2">
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
                          :class="getCommentBadgeClass(ticket.comments_count)">
                      <i class="fa-solid fa-comment mr-1"></i>
                      {{ ticket.comments_count || 0 }}
                    </span>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                  <div class="flex gap-2">
                    <button @click="viewTicket(ticket)" class="text-blue-600 hover:text-blue-900" title="View">
                      <i class="fa-solid fa-eye"></i>
                    </button>
                    <button @click="editTicket(ticket)" class="text-green-600 hover:text-green-900" title="Edit">
                      <i class="fa-solid fa-edit"></i>
                    </button>
                    <button @click="deleteTicket(ticket)" class="text-red-600 hover:text-red-900" title="Delete">
                      <i class="fa-solid fa-trash"></i>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Empty State -->
        <div v-if="data.data.length === 0" class="text-center py-12">
          <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fa-solid fa-ticket-alt text-3xl text-gray-400"></i>
          </div>
          <h3 class="text-lg font-medium text-gray-600 mb-2">No Tickets Found</h3>
          <p class="text-gray-500 mb-6">Start by creating your first ticket</p>
          <button @click="openCreate" class="bg-blue-600 text-white px-6 py-3 rounded-lg font-medium hover:bg-blue-700 transition-colors">
            <i class="fa-solid fa-plus mr-2"></i>
            Create New Ticket
          </button>
        </div>
      </div>

      <!-- Pagination -->
      <div class="mt-4 flex flex-col sm:flex-row justify-between items-center gap-4">
        <!-- Pagination Info -->
        <div class="text-sm text-gray-600">
          Menampilkan {{ data.from || 0 }} sampai {{ data.to || 0 }} dari {{ data.total || 0 }} tickets
        </div>
        
        <!-- Pagination Navigation -->
        <nav class="flex rounded-lg shadow-sm -space-x-px" aria-label="Pagination">
          <button 
            @click.stop.prevent="goToPage(data.first_page_url)" 
            :disabled="!data.first_page_url"
            :class="[
              'px-3 py-2 text-sm border border-gray-300 rounded-l-lg transition-colors',
              !data.first_page_url 
                ? 'bg-gray-100 text-gray-400 cursor-not-allowed' 
                : 'bg-white text-gray-700 hover:bg-gray-50 cursor-pointer'
            ]"
          >
            First
          </button>
          <button 
            @click.stop.prevent="goToPage(data.prev_page_url)" 
            :disabled="!data.prev_page_url"
            :class="[
              'px-3 py-2 text-sm border border-gray-300 transition-colors',
              !data.prev_page_url 
                ? 'bg-gray-100 text-gray-400 cursor-not-allowed' 
                : 'bg-white text-gray-700 hover:bg-gray-50 cursor-pointer'
            ]"
          >
            Previous
          </button>
          <template v-for="(link, i) in data.links" :key="i">
            <button 
              v-if="link.url" 
              @click.stop.prevent="goToPage(link.url)" 
              :disabled="link.active"
              :class="[
                'px-3 py-2 text-sm border border-gray-300 transition-colors',
                link.active 
                  ? 'bg-blue-600 text-white border-blue-600 cursor-default' 
                  : 'bg-white text-gray-700 hover:bg-gray-50 border-gray-300 cursor-pointer'
              ]" 
              v-html="link.label"
            ></button>
            <span 
              v-else 
              class="px-3 py-2 text-sm border border-gray-200 text-gray-400 bg-gray-50 cursor-not-allowed" 
              v-html="link.label"
            ></span>
          </template>
          <button 
            @click.stop.prevent="goToPage(data.next_page_url)" 
            :disabled="!data.next_page_url"
            :class="[
              'px-3 py-2 text-sm border border-gray-300 transition-colors',
              !data.next_page_url 
                ? 'bg-gray-100 text-gray-400 cursor-not-allowed' 
                : 'bg-white text-gray-700 hover:bg-gray-50 cursor-pointer'
            ]"
          >
            Next
          </button>
          <button 
            @click.stop.prevent="goToPage(data.last_page_url)" 
            :disabled="!data.last_page_url"
            :class="[
              'px-3 py-2 text-sm border border-gray-300 rounded-r-lg transition-colors',
              !data.last_page_url 
                ? 'bg-gray-100 text-gray-400 cursor-not-allowed' 
                : 'bg-white text-gray-700 hover:bg-gray-50 cursor-pointer'
            ]"
          >
            Last
          </button>
        </nav>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

const props = defineProps({
  data: Object,
  filters: Object,
  filterOptions: Object,
  statistics: {
    type: Object,
    default: () => ({
      total: 0,
      open: 0,
      in_progress: 0,
      closed: 0
    })
  },
});

const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || 'all');
const priority = ref(props.filters?.priority || 'all');
const division = ref(props.filters?.division || 'all');
const perPage = ref(props.filters?.per_page || 15);

const debouncedSearch = debounce(() => {
  router.get('/tickets', {
    search: search.value,
    status: status.value,
    priority: priority.value,
    division: division.value,
    per_page: perPage.value,
  }, { preserveState: true, replace: true });
}, 400);

function onSearchInput() {
  debouncedSearch();
}

function goToPage(url) {
  if (!url) return;
  
  try {
    // Handle relative URLs
    const fullUrl = url.startsWith('http') ? url : window.location.origin + url;
    const urlObj = new URL(fullUrl);
    
    // Preserve current filters
    urlObj.searchParams.set('search', search.value);
    urlObj.searchParams.set('status', status.value);
    urlObj.searchParams.set('priority', priority.value);
    urlObj.searchParams.set('division', division.value);
    urlObj.searchParams.set('per_page', perPage.value);
    
    // Extract pathname and query string
    const path = urlObj.pathname + '?' + urlObj.searchParams.toString();
    
    router.visit(path, { preserveState: true, replace: true });
  } catch (error) {
    console.error('Error in goToPage:', error);
    // Fallback: use router.get with query params
    router.get('/tickets', {
      page: url.match(/page=(\d+)/)?.[1] || 1,
      search: search.value,
      status: status.value,
      priority: priority.value,
      division: division.value,
      per_page: perPage.value,
    }, { preserveState: true, replace: true });
  }
}

function openCreate() {
  router.visit('/tickets/create');
}

function viewTicket(ticket) {
  router.visit(`/tickets/${ticket.id}`);
}

function editTicket(ticket) {
  router.visit(`/tickets/${ticket.id}/edit`);
}

async function deleteTicket(ticket) {
  const result = await Swal.fire({
    title: 'Hapus Ticket?',
    text: `Yakin ingin menghapus ticket ${ticket.ticket_number}?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Ya, Hapus!',
    cancelButtonText: 'Batal',
  });
  
  if (!result.isConfirmed) return;
  
  try {
    const response = await axios.delete(`/tickets/${ticket.id}`);
    if (response.data.success) {
      Swal.fire('Berhasil', response.data.message, 'success');
      router.reload();
    }
  } catch (error) {
    Swal.fire('Error', error.response?.data?.message || 'Gagal menghapus ticket', 'error');
  }
}

function getStatusColor(status) {
  return {
    'open': 'bg-blue-100 text-blue-800',
    'in_progress': 'bg-yellow-100 text-yellow-800',
    'pending': 'bg-purple-100 text-purple-800',
    'resolved': 'bg-green-100 text-green-800',
    'closed': 'bg-gray-100 text-gray-800',
    'cancelled': 'bg-red-100 text-red-800'
  }[status] || 'bg-gray-100 text-gray-800';
}

function getCommentBadgeClass(commentCount) {
  if (!commentCount || commentCount === 0) {
    return 'bg-gray-100 text-gray-500';
  } else if (commentCount <= 2) {
    return 'bg-blue-100 text-blue-600';
  } else if (commentCount <= 5) {
    return 'bg-yellow-100 text-yellow-600';
  } else {
    return 'bg-red-100 text-red-600';
  }
}

function getDueDateStatus(dueDate) {
  if (!dueDate) return 'No Due Date';
  
  const today = new Date();
  const due = new Date(dueDate);
  const diffTime = due - today;
  const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
  
  if (diffDays < 0) {
    return 'Overdue';
  } else if (diffDays === 0) {
    return 'Due Today';
  } else if (diffDays === 1) {
    return 'Due Tomorrow';
  } else if (diffDays <= 3) {
    return `${diffDays} days left`;
  } else {
    return `${diffDays} days left`;
  }
}

function getDueDateBadgeClass(dueDate, status) {
  if (!dueDate) return 'bg-gray-100 text-gray-500';
  
  // If ticket is closed/resolved, show green
  if (status === 'closed' || status === 'resolved') {
    return 'bg-green-100 text-green-600';
  }
  
  const today = new Date();
  const due = new Date(dueDate);
  const diffTime = due - today;
  const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
  
  if (diffDays < 0) {
    // Overdue - Red
    return 'bg-red-100 text-red-600';
  } else if (diffDays === 0) {
    // Due today - Orange
    return 'bg-orange-100 text-orange-600';
  } else if (diffDays === 1) {
    // Due tomorrow - Yellow
    return 'bg-yellow-100 text-yellow-600';
  } else if (diffDays <= 3) {
    // Due in 2-3 days - Light yellow
    return 'bg-yellow-50 text-yellow-700';
  } else {
    // More than 3 days - Green
    return 'bg-green-100 text-green-600';
  }
}

function getPriorityColor(level) {
  return {
    1: 'bg-green-100 text-green-800', // Low
    2: 'bg-yellow-100 text-yellow-800', // Medium
    3: 'bg-orange-100 text-orange-800', // High
    4: 'bg-red-100 text-red-800' // Critical
  }[level] || 'bg-gray-100 text-gray-800';
}

watch([search, status, priority, division, perPage], () => {
  debouncedSearch();
});
</script>
