<template>
  <AppLayout title="Detail Ticket">
    <div class="max-w-6xl w-full mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-ticket-alt text-blue-500"></i> {{ ticket.ticket_number }}
        </h1>
        <div class="flex gap-2">
          <button @click="editTicket" class="bg-green-600 text-white px-4 py-2 rounded-xl hover:bg-green-700 transition-colors">
            <i class="fa-solid fa-edit mr-2"></i> Edit
          </button>
          <button @click="goBack" class="bg-gray-500 text-white px-4 py-2 rounded-xl hover:bg-gray-600 transition-colors">
            <i class="fa-solid fa-arrow-left mr-2"></i> Kembali
          </button>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-6">
          <!-- Ticket Details -->
          <div class="bg-white rounded-2xl shadow-lg p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">{{ ticket.title }}</h2>
            <div class="prose max-w-none">
              <p class="text-gray-700 whitespace-pre-wrap">{{ ticket.description }}</p>
            </div>
          </div>

          <!-- Comments Section -->
          <div class="bg-white rounded-2xl shadow-lg p-6">
            <!-- Comment Toggle Button -->
            <div class="flex items-center justify-between mb-4">
              <button 
                @click="toggleComments"
                class="flex items-center gap-2 text-sm font-medium text-gray-600 hover:text-blue-600 transition-colors"
              >
                <i :class="[
                  'fa-solid transition-transform duration-200',
                  commentsExpanded ? 'fa-chevron-up' : 'fa-chevron-down'
                ]"></i>
                <span>
                  {{ getCommentCountText() }}
                </span>
              </button>
              
              <!-- Comment Count Badge -->
              <div v-if="getCommentCount() > 0" class="flex items-center gap-1">
                <i class="fa-solid fa-comment text-gray-400 text-xs"></i>
                <span class="text-xs text-gray-500">{{ getCommentCount() }}</span>
              </div>
            </div>

            <!-- Expanded Comments Section -->
            <div v-if="commentsExpanded" class="space-y-4">
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
                    v-model="newComment"
                    @keydown.enter.prevent="addComment"
                    placeholder="Tulis komentar..."
                    class="w-full px-3 py-2 border border-gray-200 rounded-lg resize-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                    rows="2"
                  ></textarea>
                  <div class="flex justify-end mt-2">
                    <button
                      @click="addComment"
                      :disabled="!newComment?.trim()"
                      class="bg-blue-600 text-white px-4 py-1 rounded-lg text-xs font-medium hover:bg-blue-700 transition-colors disabled:bg-gray-300 disabled:cursor-not-allowed"
                    >
                      <i class="fa-solid fa-paper-plane mr-1"></i>
                      Kirim
                    </button>
                  </div>
                </div>
              </div>

              <!-- Comments List -->
              <div v-if="ticket.comments && ticket.comments.length > 0" class="space-y-3">
                <div 
                  v-for="comment in ticket.comments" 
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
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
          <!-- Ticket Info -->
          <div class="bg-white rounded-2xl shadow-lg p-6">
            <h3 class="text-lg font-bold text-gray-800 mb-4">Ticket Information</h3>
            
            <div class="space-y-4">
              <!-- Status -->
              <div>
                <label class="text-sm font-medium text-gray-600">Status</label>
                <div class="mt-1">
                  <span :class="[
                    'inline-flex px-3 py-1 text-sm font-semibold rounded-full',
                    getStatusColor(ticket.status?.slug)
                  ]">
                    {{ ticket.status?.name || '-' }}
                  </span>
                </div>
              </div>

              <!-- Priority -->
              <div>
                <label class="text-sm font-medium text-gray-600">Priority</label>
                <div class="mt-1">
                  <span :class="[
                    'inline-flex px-3 py-1 text-sm font-semibold rounded-full',
                    getPriorityColor(ticket.priority?.level)
                  ]">
                    {{ ticket.priority?.name || '-' }}
                  </span>
                </div>
              </div>

              <!-- Category -->
              <div>
                <label class="text-sm font-medium text-gray-600">Category</label>
                <p class="mt-1 text-sm text-gray-900">{{ ticket.category?.name || '-' }}</p>
              </div>

              <!-- Divisi -->
              <div>
                <label class="text-sm font-medium text-gray-600">Divisi</label>
                <p class="mt-1 text-sm text-gray-900">{{ ticket.divisi?.nama_divisi || '-' }}</p>
              </div>

              <!-- Outlet -->
              <div>
                <label class="text-sm font-medium text-gray-600">Outlet</label>
                <p class="mt-1 text-sm text-gray-900">{{ ticket.outlet?.nama_outlet || '-' }}</p>
              </div>


              <!-- Due Date -->
              <div v-if="ticket.due_date">
                <label class="text-sm font-medium text-gray-600">Due Date</label>
                <p class="mt-1 text-sm text-gray-900">{{ new Date(ticket.due_date).toLocaleString('id-ID') }}</p>
              </div>

              <!-- Attachments -->
              <div v-if="ticket.attachments && ticket.attachments.length > 0">
                <label class="text-sm font-medium text-gray-600">Attachments</label>
                <div class="mt-2 grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                  <div
                    v-for="attachment in ticket.attachments"
                    :key="attachment.id"
                    class="relative"
                  >
                    <!-- Image Thumbnail -->
                    <div v-if="attachment.mime_type && attachment.mime_type.startsWith('image/')" class="relative group cursor-pointer" @click.stop="openAttachmentLightbox(attachment)">
                      <div class="aspect-square bg-gray-100 border border-gray-200 rounded-lg overflow-hidden hover:shadow-lg transition-all duration-200">
                        <img
                          :src="attachment.file_path.startsWith('/storage/') ? attachment.file_path : `/storage/${attachment.file_path}`"
                          :alt="attachment.file_name"
                          class="w-full h-full object-cover hover:scale-105 transition-transform duration-200"
                          @click.stop="openAttachmentLightbox(attachment)"
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
                    <div v-else class="relative group">
                      <div class="aspect-square bg-gray-100 border border-gray-200 rounded-lg flex flex-col items-center justify-center p-2 cursor-pointer">
                        <i class="fas fa-file-pdf text-red-500 text-2xl mb-1" v-if="attachment.mime_type === 'application/pdf'"></i>
                        <i class="fas fa-file-alt text-gray-500 text-2xl mb-1" v-else></i>
                        <p class="text-xs text-gray-600 text-center truncate w-full">{{ attachment.file_name }}</p>
                      </div>
                      <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-20 transition-all duration-200 flex items-center justify-center">
                        <a
                          :href="attachment.file_path.startsWith('/storage/') ? attachment.file_path : `/storage/${attachment.file_path}`"
                          target="_blank"
                          class="opacity-0 group-hover:opacity-100 bg-white bg-opacity-90 text-gray-800 px-3 py-1 rounded-full text-sm transition-all duration-200"
                        >
                          <i class="fas fa-download mr-1"></i>Download
                        </a>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Created By -->
              <div>
                <label class="text-sm font-medium text-gray-600">Created By</label>
                <p class="mt-1 text-sm text-gray-900">{{ ticket.creator?.nama_lengkap || '-' }}</p>
              </div>

              <!-- Created At -->
              <div>
                <label class="text-sm font-medium text-gray-600">Created At</label>
                <p class="mt-1 text-sm text-gray-900">{{ new Date(ticket.created_at).toLocaleString('id-ID') }}</p>
              </div>

              <!-- Source -->
              <div v-if="ticket.source === 'daily_report'">
                <label class="text-sm font-medium text-gray-600">Source</label>
                <p class="mt-1 text-sm text-blue-600">
                  <i class="fa-solid fa-clipboard-list mr-1"></i>
                  Daily Report
                </p>
              </div>
            </div>
          </div>

          <!-- Ticket History Timeline -->
          <div class="bg-white rounded-2xl shadow-lg p-6">
            <h3 class="text-lg font-bold text-gray-800 mb-6 flex items-center gap-2">
              <i class="fas fa-history text-blue-500"></i>
              Timeline History
            </h3>
            
            <div v-if="ticket.history && ticket.history.length > 0" class="relative">
              <!-- Timeline Line -->
              <div class="absolute left-6 top-0 bottom-0 w-0.5 bg-gradient-to-b from-blue-200 via-blue-300 to-gray-200"></div>
              
              <div class="space-y-6">
                <div 
                  v-for="(history, index) in ticket.history" 
                  :key="history.id"
                  class="relative flex items-start gap-4"
                >
                  <!-- Timeline Icon -->
                  <div class="relative z-10 flex-shrink-0">
                    <div 
                      class="w-12 h-12 rounded-full flex items-center justify-center text-white shadow-lg"
                      :class="getHistoryIconClass(history.action)"
                    >
                      <i :class="getHistoryIcon(history.action)" class="text-sm"></i>
                    </div>
                  </div>
                  
                  <!-- Timeline Content -->
                  <div class="flex-1 min-w-0 pb-2">
                    <div class="bg-gray-50 rounded-xl p-4 border-l-4" :class="getHistoryBorderClass(history.action)">
                      <div class="flex items-start justify-between">
                        <div class="flex-1">
                          <h4 class="text-sm font-semibold text-gray-900 mb-1">
                            {{ getHistoryTitle(history.action) }}
                          </h4>
                          <p class="text-sm text-gray-700 mb-2">{{ getChangeDescription(history) }}</p>
                          
                          <!-- Field Changes (if any) -->
                          <div v-if="history.field_name && history.old_value && history.new_value" class="mb-3">
                            <div class="flex items-center gap-2 text-xs">
                              <span class="px-2 py-1 bg-red-100 text-red-700 rounded-full">
                                {{ history.old_value }}
                              </span>
                              <i class="fas fa-arrow-right text-gray-400"></i>
                              <span class="px-2 py-1 bg-green-100 text-green-700 rounded-full">
                                {{ history.new_value }}
                              </span>
                            </div>
                          </div>
                        </div>
                        
                        <!-- Time Badge -->
                        <div class="flex-shrink-0 ml-4">
                          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                            {{ formatTimeAgo(history.created_at) }}
                          </span>
                        </div>
                      </div>
                      
                      <!-- User Info -->
                      <div class="flex items-center gap-2 mt-3 pt-3 border-t border-gray-200">
                        <div class="w-6 h-6 bg-gray-300 rounded-full flex items-center justify-center">
                          <i class="fas fa-user text-xs text-gray-600"></i>
                        </div>
                        <span class="text-xs text-gray-600">
                          {{ history.user?.nama_lengkap || 'Unknown User' }}
                        </span>
                        <span class="text-xs text-gray-400">•</span>
                        <span class="text-xs text-gray-500">
                          {{ new Date(history.created_at).toLocaleString('id-ID') }}
                        </span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div v-else class="text-center py-8 text-gray-500">
              <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-history text-2xl text-gray-400"></i>
              </div>
              <p class="text-sm font-medium">Belum ada history</p>
              <p class="text-xs text-gray-400 mt-1">History akan muncul saat ada perubahan pada ticket</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- VueEasyLightbox Component -->
    <VueEasyLightbox
      :visible="visibleRef"
      :imgs="imgsRef"
      :index="indexRef"
      @hide="hideLightbox"
    />
  </AppLayout>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';
import VueEasyLightbox from 'vue-easy-lightbox';
import axios from 'axios';

const props = defineProps({
  ticket: Object,
});


const newComment = ref('');

// Lightbox state for VueEasyLightbox
const visibleRef = ref(false);
const indexRef = ref(0);
const imgsRef = ref([]);

// Comment state
const commentsExpanded = ref(false);

function goBack() {
  router.visit('/tickets');
}

// Lightbox methods for VueEasyLightbox
function showLightbox(images, index = 0) {
  imgsRef.value = images;
  indexRef.value = index;
  visibleRef.value = true;
}

function hideLightbox() {
  visibleRef.value = false;
}

function openAttachmentLightbox(attachment) {
  // Get all image attachments
  const imageAttachments = props.ticket.attachments.filter(att => 
    att.mime_type && att.mime_type.startsWith('image/')
  );
  
  // Find current attachment index
  const currentIndex = imageAttachments.findIndex(att => att.id === attachment.id);
  
  // Convert to array of image URLs for VueEasyLightbox
  const imageUrls = imageAttachments.map(att => 
    att.file_path.startsWith('/storage/') ? att.file_path : `/storage/${att.file_path}`
  );
  
  showLightbox(imageUrls, currentIndex);
}

function editTicket() {
  router.visit(`/tickets/${props.ticket.id}/edit`);
}

async function addComment() {
  if (!newComment.value?.trim()) return;
  
  try {
    const response = await axios.post(`/tickets/${props.ticket.id}/comments`, {
      comment: newComment.value.trim()
    });
    
    if (response.data.success) {
      // Add comment to the ticket's comments array
      if (!props.ticket.comments) {
        props.ticket.comments = [];
      }
      props.ticket.comments.unshift(response.data.data);
      newComment.value = '';
      
      Swal.fire('Berhasil', 'Komentar berhasil ditambahkan', 'success');
    }
  } catch (error) {
    Swal.fire('Error', error.response?.data?.message || 'Gagal menambahkan komentar', 'error');
  }
}

// Comment helper methods
function toggleComments() {
  commentsExpanded.value = !commentsExpanded.value;
}

function getCommentCount() {
  return props.ticket.comments ? props.ticket.comments.length : 0;
}

function getCommentCountText() {
  const count = getCommentCount();
  if (count === 0) {
    return 'Tambah komentar';
  } else if (count === 1) {
    return '1 komentar';
  } else {
    return `${count} komentar`;
  }
}

function getInitials(name) {
  if (!name) return 'U';
  const words = name.split(' ');
  if (words.length >= 2) {
    return (words[0][0] + words[1][0]).toUpperCase();
  }
  return name[0].toUpperCase();
}

async function editComment(comment) {
  const { value: newText } = await Swal.fire({
    title: 'Edit Komentar',
    input: 'textarea',
    inputValue: comment.comment,
    inputAttributes: {
      placeholder: 'Tulis komentar...',
      rows: 3
    },
    showCancelButton: true,
    confirmButtonText: 'Update',
    cancelButtonText: 'Batal',
    inputValidator: (value) => {
      if (!value || !value.trim()) {
        return 'Komentar tidak boleh kosong!';
      }
    }
  });

  if (newText && newText.trim() !== comment.comment) {
    try {
      const response = await axios.put(`/tickets/comments/${comment.id}`, {
        comment: newText.trim()
      });
      
      if (response.data.success) {
        comment.comment = newText.trim();
        comment.updated_at = response.data.data.updated_at;
        Swal.fire('Berhasil', 'Komentar berhasil diperbarui', 'success');
      }
    } catch (error) {
      Swal.fire('Error', error.response?.data?.message || 'Gagal memperbarui komentar', 'error');
    }
  }
}

async function deleteComment(comment) {
  const result = await Swal.fire({
    title: 'Hapus Komentar?',
    text: 'Komentar akan dihapus secara permanen',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Ya, Hapus!',
    cancelButtonText: 'Batal'
  });

  if (result.isConfirmed) {
    try {
      const response = await axios.delete(`/tickets/comments/${comment.id}`);
      
      if (response.data.success) {
        // Remove comment from the ticket's comments array
        const index = props.ticket.comments.findIndex(c => c.id === comment.id);
        if (index > -1) {
          props.ticket.comments.splice(index, 1);
        }
        Swal.fire('Berhasil', 'Komentar berhasil dihapus', 'success');
      }
    } catch (error) {
      Swal.fire('Error', error.response?.data?.message || 'Gagal menghapus komentar', 'error');
    }
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

function getPriorityColor(level) {
  return {
    1: 'bg-green-100 text-green-800', // Low
    2: 'bg-yellow-100 text-yellow-800', // Medium
    3: 'bg-orange-100 text-orange-800', // High
    4: 'bg-red-100 text-red-800' // Critical
  }[level] || 'bg-gray-100 text-gray-800';
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

function getChangeDescription(history) {
  // If there's a field change with old and new values
  if (history.field_name && history.old_value && history.new_value) {
    return `Changed ${history.field_name} from '${history.old_value}' to '${history.new_value}'`;
  }

  // If there's a description, use it
  if (history.description) {
    return history.description;
  }

  // Otherwise, use action text
  const actionTexts = {
    'created': 'Ticket created',
    'updated': 'Ticket updated',
    'assigned': 'Ticket assigned',
    'status_changed': 'Status changed',
    'priority_changed': 'Priority changed',
    'category_changed': 'Category changed',
    'comment_added': 'Comment added',
    'attachment_added': 'Attachment added',
    'resolved': 'Ticket resolved',
    'closed': 'Ticket closed',
    'reopened': 'Ticket reopened',
  };

  return actionTexts[history.action] || history.action?.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) || 'Action performed';
}

// Timeline helper methods
function getHistoryTitle(action) {
  const titles = {
    'created': 'Ticket Created',
    'updated': 'Ticket Updated',
    'assigned': 'Ticket Assigned',
    'status_changed': 'Status Changed',
    'priority_changed': 'Priority Changed',
    'category_changed': 'Category Changed',
    'comment_added': 'Comment Added',
    'attachment_added': 'Attachment Added',
    'resolved': 'Ticket Resolved',
    'closed': 'Ticket Closed',
    'reopened': 'Ticket Reopened',
  };

  return titles[action] || action?.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) || 'Action Performed';
}

function getHistoryIcon(action) {
  const icons = {
    'created': 'fas fa-plus-circle',
    'updated': 'fas fa-edit',
    'assigned': 'fas fa-user-check',
    'status_changed': 'fas fa-exchange-alt',
    'priority_changed': 'fas fa-flag',
    'category_changed': 'fas fa-tags',
    'comment_added': 'fas fa-comment',
    'attachment_added': 'fas fa-paperclip',
    'resolved': 'fas fa-check-circle',
    'closed': 'fas fa-times-circle',
    'reopened': 'fas fa-redo',
  };

  return icons[action] || 'fas fa-circle';
}

function getHistoryIconClass(action) {
  const classes = {
    'created': 'bg-green-500',
    'updated': 'bg-blue-500',
    'assigned': 'bg-purple-500',
    'status_changed': 'bg-yellow-500',
    'priority_changed': 'bg-orange-500',
    'category_changed': 'bg-indigo-500',
    'comment_added': 'bg-cyan-500',
    'attachment_added': 'bg-pink-500',
    'resolved': 'bg-green-600',
    'closed': 'bg-red-500',
    'reopened': 'bg-blue-600',
  };

  return classes[action] || 'bg-gray-500';
}

function getHistoryBorderClass(action) {
  const classes = {
    'created': 'border-green-400',
    'updated': 'border-blue-400',
    'assigned': 'border-purple-400',
    'status_changed': 'border-yellow-400',
    'priority_changed': 'border-orange-400',
    'category_changed': 'border-indigo-400',
    'comment_added': 'border-cyan-400',
    'attachment_added': 'border-pink-400',
    'resolved': 'border-green-500',
    'closed': 'border-red-400',
    'reopened': 'border-blue-500',
  };

  return classes[action] || 'border-gray-400';
}

// Lifecycle hooks
onMounted(() => {
  // Any initialization code can go here
});

onUnmounted(() => {
  // Cleanup code can go here
});
</script>
