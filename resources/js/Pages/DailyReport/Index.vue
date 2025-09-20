<script setup>
import { ref, watch, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

const props = defineProps({
  data: Object,
  filters: Object,
  statistics: {
    type: Object,
    default: () => ({
      total: 0,
      draft: 0,
      completed: 0
    })
  },
  permissions: {
    type: Object,
    default: () => ({
      can_edit: false,
      current_user_id: null,
    }),
  },
});

const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || 'all');
const perPage = ref(props.filters?.per_page || 15);

const debouncedSearch = debounce(() => {
  router.get('/daily-report', {
    search: search.value,
    status: status.value,
    per_page: perPage.value,
  }, { preserveState: true, replace: true });
}, 400);

function onSearchInput() {
  debouncedSearch();
}

function goToPage(url) {
  if (url) {
    const urlObj = new URL(url);
    urlObj.searchParams.set('search', search.value);
    urlObj.searchParams.set('status', status.value);
    urlObj.searchParams.set('per_page', perPage.value);
    
    router.visit(urlObj.toString(), { preserveState: true, replace: true });
  }
}

function openCreate() {
  router.visit('/daily-report/create');
}

function viewReport(report) {
  router.visit(`/daily-report/${report.id}`);
}

function inspectReport(report) {
  router.visit(`/daily-report/${report.id}/inspect`);
}

function postInspectionReport(report) {
  router.visit(`/daily-report/${report.id}/post-inspection`);
}

async function deleteReport(report) {
  const result = await Swal.fire({
    title: 'Hapus Report?',
    text: `Yakin ingin menghapus daily report untuk ${report.outlet?.nama_outlet}?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Ya, Hapus!',
    cancelButtonText: 'Batal',
  });
  
  if (!result.isConfirmed) return;
  
  try {
    const response = await axios.delete(`/daily-report/${report.id}`);
    if (response.data.success) {
      Swal.fire('Berhasil', response.data.message, 'success');
      router.reload();
    }
  } catch (error) {
    Swal.fire('Error', error.response?.data?.message || 'Gagal menghapus report', 'error');
  }
}

function getStatusColor(status) {
  return {
    'draft': 'bg-yellow-100 text-yellow-800',
    'completed': 'bg-green-100 text-green-800'
  }[status] || 'bg-gray-100 text-gray-800';
}

function getStatusText(status) {
  return {
    'draft': 'Draft',
    'completed': 'Completed'
  }[status] || 'Unknown';
}

function getReportRating(report) {
  if (!report.report_areas || report.report_areas.length === 0) {
    return 0;
  }
  
  const goodAreas = report.report_areas.filter(area => area.status === 'G').length;
  const notGoodAreas = report.report_areas.filter(area => area.status === 'NG').length;
  const inspectedAreas = goodAreas + notGoodAreas;
  
  if (inspectedAreas === 0) {
    return 0;
  }
  
  return Math.round((goodAreas / inspectedAreas) * 100);
}

function getReportStarRating(report) {
  const rating = getReportRating(report);
  
  if (rating >= 81) return 5;
  if (rating >= 61) return 4;
  if (rating >= 41) return 3;
  if (rating >= 21) return 2;
  if (rating >= 1) return 1;
  return 0;
}

function canEditReport(report) {
  // User can edit if they have admin role OR if they are the creator
  return props.permissions.can_edit || report.user_id === props.permissions.current_user_id;
}

function getInitials(name) {
  if (!name) return 'U';
  const words = name.split(' ');
  if (words.length >= 2) {
    return (words[0][0] + words[1][0]).toUpperCase();
  }
  return words[0][0].toUpperCase();
}

function getInspectionTimeText(time) {
  return {
    'lunch': 'Lunch',
    'dinner': 'Dinner'
  }[time] || 'Unknown';
}

// Comment functions
async function addComment(report) {
  if (!report.newComment?.trim()) return;
  
  try {
    const response = await axios.post(`/daily-report/${report.id}/comments`, {
      comment: report.newComment.trim()
    });
    
    if (response.data.success) {
      // Add comment to the report's comments array
      if (!report.comments) {
        report.comments = [];
      }
      report.comments.unshift(response.data.data);
      report.newComment = '';
      
      // Auto-expand comments when new comment is added
      report.commentsExpanded = true;
      
      Swal.fire('Berhasil', 'Komentar berhasil ditambahkan', 'success');
    }
  } catch (error) {
    Swal.fire('Error', error.response?.data?.message || 'Gagal menambahkan komentar', 'error');
  }
}

async function deleteComment(comment) {
  const result = await Swal.fire({
    title: 'Hapus Komentar?',
    text: 'Yakin ingin menghapus komentar ini?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Ya, Hapus!',
    cancelButtonText: 'Batal',
  });
  
  if (!result.isConfirmed) return;
  
  try {
    const response = await axios.delete(`/daily-report/comments/${comment.id}`);
    if (response.data.success) {
      // Remove comment from all reports
      props.data.data.forEach(report => {
        if (report.comments) {
          const index = report.comments.findIndex(c => c.id === comment.id);
          if (index > -1) {
            report.comments.splice(index, 1);
          }
        }
      });
      
      Swal.fire('Berhasil', 'Komentar berhasil dihapus', 'success');
    }
  } catch (error) {
    Swal.fire('Error', error.response?.data?.message || 'Gagal menghapus komentar', 'error');
  }
}

function editComment(comment) {
  const newComment = prompt('Edit komentar:', comment.comment);
  if (newComment !== null && newComment.trim() !== comment.comment) {
    updateComment(comment, newComment.trim());
  }
}

async function updateComment(comment, newText) {
  try {
    const response = await axios.put(`/daily-report/comments/${comment.id}`, {
      comment: newText
    });
    
    if (response.data.success) {
      // Update comment in all reports
      props.data.data.forEach(report => {
        if (report.comments) {
          const foundComment = report.comments.find(c => c.id === comment.id);
          if (foundComment) {
            foundComment.comment = newText;
            foundComment.updated_at = response.data.data.updated_at;
          }
        }
      });
      
      Swal.fire('Berhasil', 'Komentar berhasil diperbarui', 'success');
    }
  } catch (error) {
    Swal.fire('Error', error.response?.data?.message || 'Gagal memperbarui komentar', 'error');
  }
}

function replyToComment(report, parentComment) {
  report.newComment = `@${parentComment.user.nama_lengkap} `;
  // Focus on textarea (you might need to add ref to textarea)
}

function formatTimeAgo(dateString) {
  const date = new Date(dateString);
  const now = new Date();
  const diffInSeconds = Math.floor((now - date) / 1000);
  
  if (diffInSeconds < 60) {
    return 'baru saja';
  } else if (diffInSeconds < 3600) {
    const minutes = Math.floor(diffInSeconds / 60);
    return `${minutes} menit yang lalu`;
  } else if (diffInSeconds < 86400) {
    const hours = Math.floor(diffInSeconds / 3600);
    return `${hours} jam yang lalu`;
  } else if (diffInSeconds < 2592000) {
    const days = Math.floor(diffInSeconds / 86400);
    return `${days} hari yang lalu`;
  } else {
    return date.toLocaleDateString('id-ID', {
      day: 'numeric',
      month: 'short',
      year: 'numeric'
    });
  }
}

// Comment toggle functions
function toggleComments(report) {
  report.commentsExpanded = !report.commentsExpanded;
}

function getCommentCount(report) {
  return report.comments ? report.comments.length : 0;
}

function getCommentCountText(report) {
  const count = getCommentCount(report);
  if (count === 0) {
    return 'Tulis komentar';
  } else if (count === 1) {
    return '1 komentar';
  } else {
    return `${count} komentar`;
  }
}

watch([status, perPage], () => {
  router.get('/daily-report', {
    search: search.value,
    status: status.value,
    per_page: perPage.value,
  }, { preserveState: true, replace: true });
});
</script>

<template>
  <AppLayout title="Daily Report">
    <div class="max-w-7xl w-full mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-clipboard-list text-blue-500"></i> Daily Report
        </h1>
        <button @click="openCreate" class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
          + Buat Report Baru
        </button>
      </div>

      <!-- Statistics Cards -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <!-- Total -->
        <div class="rounded-xl shadow-lg p-6 border-l-4 border-blue-500 bg-white hover:shadow-xl transition-all">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">Total Reports</p>
              <p class="text-2xl font-bold text-gray-900">{{ statistics.total }}</p>
              <p class="text-xs text-gray-500">100% dari total</p>
            </div>
            <div class="bg-blue-100 p-3 rounded-full">
              <i class="fa-solid fa-clipboard-list text-blue-600 text-xl"></i>
            </div>
          </div>
        </div>

        <!-- Draft -->
        <div class="rounded-xl shadow-lg p-6 border-l-4 border-yellow-500 bg-white hover:shadow-xl transition-all">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">Draft Reports</p>
              <p class="text-2xl font-bold text-gray-900">{{ statistics.draft }}</p>
              <p class="text-xs text-gray-500">{{ statistics.total > 0 ? Math.round((statistics.draft / statistics.total) * 100) : 0 }}% dari total</p>
            </div>
            <div class="bg-yellow-100 p-3 rounded-full">
              <i class="fa-solid fa-edit text-yellow-600 text-xl"></i>
            </div>
          </div>
        </div>

        <!-- Completed -->
        <div class="rounded-xl shadow-lg p-6 border-l-4 border-green-500 bg-white hover:shadow-xl transition-all">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">Completed Reports</p>
              <p class="text-2xl font-bold text-gray-900">{{ statistics.completed }}</p>
              <p class="text-xs text-gray-500">{{ statistics.total > 0 ? Math.round((statistics.completed / statistics.total) * 100) : 0 }}% dari total</p>
            </div>
            <div class="bg-green-100 p-3 rounded-full">
              <i class="fa-solid fa-check-circle text-green-600 text-xl"></i>
            </div>
          </div>
        </div>
      </div>

      <!-- Filters -->
      <div class="mb-4 flex gap-4 items-center">
        <select v-model="status" class="form-input rounded-xl">
          <option value="all">Semua Status</option>
          <option value="draft">Draft</option>
          <option value="completed">Completed</option>
        </select>
        
        <select v-model="perPage" class="form-input rounded-xl">
          <option value="10">10 per halaman</option>
          <option value="15">15 per halaman</option>
          <option value="25">25 per halaman</option>
          <option value="50">50 per halaman</option>
          <option value="100">100 per halaman</option>
        </select>
        
        <input
          v-model="search"
          @input="onSearchInput"
          type="text"
          placeholder="Cari outlet, department..."
          class="flex-1 px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
        />
      </div>

      <!-- Report Cards -->
      <div v-if="data.data.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <div 
          v-for="report in data.data" 
          :key="report.id" 
          class="bg-white rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 overflow-hidden border border-gray-100"
        >
          <!-- Card Header -->
          <div class="p-6 pb-4">
            <div class="flex items-start justify-between mb-4">
              <div class="flex-1">
                <h3 class="text-lg font-bold text-gray-800 mb-1">{{ report.outlet?.nama_outlet || '-' }}</h3>
                <p class="text-sm text-gray-600">{{ report.department?.nama_departemen || '-' }}</p>
              </div>
              <span :class="[
                'px-3 py-1 rounded-full text-xs font-semibold',
                getStatusColor(report.status)
              ]">
                {{ getStatusText(report.status) }}
              </span>
            </div>

            <!-- User Info -->
            <div class="flex items-center gap-4 mb-4">
              <div v-if="report.user?.avatar" class="w-16 h-16 rounded-full overflow-hidden border-3 border-white shadow-xl">
                <img :src="`/storage/${report.user.avatar}`" :alt="report.user.nama_lengkap" class="w-full h-full object-cover" />
              </div>
              <div v-else class="w-16 h-16 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white text-lg font-bold border-3 border-white shadow-xl">
                {{ getInitials(report.user?.nama_lengkap || 'U') }}
              </div>
              <div>
                <p class="font-medium text-gray-800 text-lg">{{ report.user?.nama_lengkap || '-' }}</p>
                <p v-if="report.user?.jabatan?.nama_jabatan" class="text-sm text-gray-500">{{ report.user.jabatan.nama_jabatan }}</p>
              </div>
            </div>

            <!-- Inspection Time -->
            <div class="flex items-center gap-2 mb-4">
              <i class="fa-solid fa-clock text-gray-400"></i>
              <span class="px-3 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">
                {{ getInspectionTimeText(report.inspection_time) }}
              </span>
            </div>

            <!-- Rating Section -->
            <div v-if="report.status === 'completed'" class="mb-4">
              <div class="flex items-center justify-between mb-2">
                <span class="text-sm font-medium text-gray-600">Inspection Rating</span>
                <span class="text-2xl font-bold text-blue-600">{{ getReportRating(report) }}%</span>
              </div>
              <!-- Star Rating -->
              <div class="flex items-center gap-2 mb-2">
                <div class="flex space-x-1">
                  <i 
                    v-for="star in 5" 
                    :key="star"
                    :class="[
                      'fa-star text-sm',
                      star <= getReportStarRating(report) ? 'fa-solid text-yellow-400' : 'fa-regular text-gray-300'
                    ]"
                  ></i>
                </div>
                <span class="text-xs text-gray-500">{{ getReportStarRating(report) }}/5</span>
              </div>
              <!-- Progress Bar -->
              <div class="w-full bg-gray-200 rounded-full h-2">
                <div 
                  class="bg-gradient-to-r from-blue-500 to-blue-600 h-2 rounded-full transition-all duration-500" 
                  :style="{ width: getReportRating(report) + '%' }"
                ></div>
              </div>
            </div>

            <!-- Created Date -->
            <div class="flex items-center gap-2 text-sm text-gray-500">
              <i class="fa-solid fa-calendar"></i>
              <span>{{ new Date(report.created_at).toLocaleDateString('id-ID', { 
                day: 'numeric', 
                month: 'long', 
                year: 'numeric' 
              }) }}</span>
            </div>
          </div>

               <!-- Comments Section -->
               <div class="px-6 py-4 border-t border-gray-100">
                 <!-- Comment Toggle Button -->
                 <div class="flex items-center justify-between mb-4">
                   <button 
                     @click="toggleComments(report)"
                     class="flex items-center gap-2 text-sm font-medium text-gray-600 hover:text-blue-600 transition-colors"
                   >
                     <i :class="[
                       'fa-solid transition-transform duration-200',
                       report.commentsExpanded ? 'fa-chevron-up' : 'fa-chevron-down'
                     ]"></i>
                     <span>
                       {{ getCommentCountText(report) }}
                     </span>
                   </button>
                   
                   <!-- Comment Count Badge -->
                   <div v-if="getCommentCount(report) > 0" class="flex items-center gap-1">
                     <i class="fa-solid fa-comment text-gray-400 text-xs"></i>
                     <span class="text-xs text-gray-500">{{ getCommentCount(report) }}</span>
                   </div>
                 </div>

                 <!-- Expanded Comments Section -->
                 <div v-if="report.commentsExpanded" class="space-y-4">
                   <!-- Comment Input -->
                   <div class="flex gap-3">
                     <div v-if="$page.props.auth.user.avatar" class="w-8 h-8 rounded-full overflow-hidden border border-gray-200 flex-shrink-0">
                       <img :src="`/storage/${$page.props.auth.user.avatar}`" :alt="$page.props.auth.user.nama_lengkap" class="w-full h-full object-cover" />
                     </div>
                     <div v-else class="w-8 h-8 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white text-xs font-bold border border-gray-200 flex-shrink-0">
                       {{ getInitials($page.props.auth.user.nama_lengkap) }}
                     </div>
                     <div class="flex-1">
                       <textarea
                         v-model="report.newComment"
                         @keydown.enter.prevent="addComment(report)"
                         placeholder="Tulis komentar..."
                         class="w-full px-3 py-2 border border-gray-200 rounded-lg resize-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                         rows="2"
                       ></textarea>
                       <div class="flex justify-end mt-2">
                         <button
                           @click="addComment(report)"
                           :disabled="!report.newComment?.trim()"
                           class="bg-blue-600 text-white px-4 py-1 rounded-lg text-xs font-medium hover:bg-blue-700 transition-colors disabled:bg-gray-300 disabled:cursor-not-allowed"
                         >
                           <i class="fa-solid fa-paper-plane mr-1"></i>
                           Kirim
                         </button>
                       </div>
                     </div>
                   </div>

                   <!-- Comments List -->
                   <div v-if="report.comments && report.comments.length > 0" class="space-y-3">
                     <div 
                       v-for="comment in report.comments" 
                       :key="comment.id"
                       class="flex gap-3"
                     >
                       <!-- Comment Avatar -->
                       <div v-if="comment.user?.avatar" class="w-8 h-8 rounded-full overflow-hidden border border-gray-200 flex-shrink-0">
                         <img :src="`/storage/${comment.user.avatar}`" :alt="comment.user.nama_lengkap" class="w-full h-full object-cover" />
                       </div>
                       <div v-else class="w-8 h-8 rounded-full bg-gradient-to-br from-gray-400 to-gray-600 flex items-center justify-center text-white text-xs font-bold border border-gray-200 flex-shrink-0">
                         {{ getInitials(comment.user?.nama_lengkap || 'U') }}
                       </div>
                       
                       <!-- Comment Content -->
                       <div class="flex-1">
                         <div class="bg-gray-50 rounded-lg p-3">
                           <div class="flex items-center gap-2 mb-1">
                             <span class="font-medium text-sm text-gray-800">{{ comment.user?.nama_lengkap || 'Unknown' }}</span>
                             <span class="text-xs text-gray-500">{{ formatTimeAgo(comment.created_at) }}</span>
                           </div>
                           <p class="text-sm text-gray-700">{{ comment.comment }}</p>
                         </div>
                         
                         <!-- Comment Actions -->
                         <div class="flex items-center gap-4 mt-2 text-xs text-gray-500">
                           <button 
                             @click="replyToComment(report, comment)"
                             class="hover:text-blue-600 transition-colors"
                           >
                             <i class="fa-solid fa-reply mr-1"></i>
                             Balas
                           </button>
                           <button 
                             v-if="comment.user_id === $page.props.auth.user.id"
                             @click="editComment(comment)"
                             class="hover:text-green-600 transition-colors"
                           >
                             <i class="fa-solid fa-edit mr-1"></i>
                             Edit
                           </button>
                           <button 
                             v-if="comment.user_id === $page.props.auth.user.id"
                             @click="deleteComment(comment)"
                             class="hover:text-red-600 transition-colors"
                           >
                             <i class="fa-solid fa-trash mr-1"></i>
                             Hapus
                           </button>
                         </div>
                       </div>
                     </div>
                   </div>

                   <!-- No Comments -->
                   <div v-else class="text-center py-4 text-gray-500 text-sm">
                     <i class="fa-solid fa-comment-slash mb-2 block text-2xl"></i>
                     Belum ada komentar
                   </div>
                 </div>
               </div>

               <!-- Card Footer - Action Buttons -->
               <div class="px-6 py-4 bg-gray-50 border-t border-gray-100">
                 <div class="flex gap-2">
                   <button 
                     @click="viewReport(report)" 
                     class="flex-1 bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors flex items-center justify-center gap-2"
                   >
                     <i class="fa-solid fa-eye"></i>
                     View
                   </button>
                   
                   <button 
                     v-if="report.status === 'draft' && canEditReport(report)" 
                     @click="inspectReport(report)" 
                     class="flex-1 bg-green-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-green-700 transition-colors flex items-center justify-center gap-2"
                   >
                     <i class="fa-solid fa-play"></i>
                     Continue
                   </button>
                   
                   <button 
                     v-if="report.status === 'completed' && canEditReport(report)" 
                     @click="inspectReport(report)" 
                     class="flex-1 bg-orange-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-orange-700 transition-colors flex items-center justify-center gap-2"
                   >
                     <i class="fa-solid fa-edit"></i>
                     Edit
                   </button>
                   
                   <button 
                     v-if="report.status === 'completed' && canEditReport(report)" 
                     @click="postInspectionReport(report)" 
                     class="flex-1 bg-purple-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-purple-700 transition-colors flex items-center justify-center gap-2"
                   >
                     <i class="fa-solid fa-clipboard-list"></i>
                     Post
                   </button>
                   
                   <button 
                     v-if="canEditReport(report)" 
                     @click="deleteReport(report)" 
                     class="bg-red-100 text-red-700 px-3 py-2 rounded-lg text-sm font-medium hover:bg-red-200 transition-colors"
                     title="Delete"
                   >
                     <i class="fa-solid fa-trash"></i>
                   </button>
                 </div>
               </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else class="text-center py-12">
        <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
          <i class="fa-solid fa-clipboard-list text-3xl text-gray-400"></i>
        </div>
        <h3 class="text-lg font-medium text-gray-600 mb-2">No Daily Reports Found</h3>
        <p class="text-gray-500 mb-6">Start by creating your first daily report</p>
        <button @click="openCreate" class="bg-blue-600 text-white px-6 py-3 rounded-lg font-medium hover:bg-blue-700 transition-colors">
          <i class="fa-solid fa-plus mr-2"></i>
          Create New Report
        </button>
      </div>

      <!-- Pagination -->
      <div class="mt-4 flex flex-col sm:flex-row justify-between items-center gap-4">
        <!-- Pagination Info -->
        <div class="text-sm text-gray-600">
          Menampilkan {{ data.from || 0 }} sampai {{ data.to || 0 }} dari {{ data.total || 0 }} reports
        </div>
        
        <!-- Pagination Navigation -->
        <nav v-if="data.links && data.links.length > 3" class="inline-flex -space-x-px">
          <template v-for="(link, i) in data.links" :key="i">
            <button 
              v-if="link.url" 
              @click="goToPage(link.url)" 
              :class="[
                'px-3 py-2 text-sm border border-gray-300 transition-colors',
                link.active 
                  ? 'bg-blue-600 text-white border-blue-600' 
                  : 'bg-white text-gray-700 hover:bg-gray-50 border-gray-300'
              ]" 
              v-html="link.label"
            ></button>
            <span 
              v-else 
              class="px-3 py-2 text-sm border border-gray-200 text-gray-400 bg-gray-50" 
              v-html="link.label"
            ></span>
          </template>
        </nav>
      </div>
    </div>
  </AppLayout>
</template>
