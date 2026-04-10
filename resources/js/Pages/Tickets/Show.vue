<template>
  <AppLayout title="Detail Ticket">
    <div class="max-w-6xl w-full mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-ticket-alt text-blue-500"></i> {{ ticket.ticket_number }}
        </h1>
        <div class="flex gap-2">
          <button
            v-if="can_manage_tickets"
            @click="openCreatePayment"
            class="bg-emerald-600 text-white px-4 py-2 rounded-xl hover:bg-emerald-700 transition-colors"
          >
            <i class="fa-solid fa-money-bill-wave mr-2"></i> Create Payment
          </button>
          <button
            v-if="can_manage_tickets"
            @click="editTicket"
            class="bg-green-600 text-white px-4 py-2 rounded-xl hover:bg-green-700 transition-colors"
          >
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

          <!-- Payment Information -->
          <div class="bg-white rounded-2xl shadow-lg p-6">
            <div class="flex items-center justify-between mb-4">
              <h3 class="text-lg font-bold text-gray-800">Informasi Payment</h3>
              <button
                v-if="can_manage_tickets"
                @click="openCreatePayment"
                class="inline-flex items-center px-3 py-1.5 rounded-lg bg-emerald-100 text-emerald-700 text-xs font-semibold hover:bg-emerald-200"
              >
                <i class="fa-solid fa-plus mr-1"></i> Create Payment
              </button>
            </div>

            <div v-if="ticket.payment_info && ticket.payment_info.length" class="space-y-3">
              <div
                v-for="pr in ticket.payment_info"
                :key="`payment-info-${pr.id}`"
                class="border border-gray-200 rounded-lg p-3 bg-gray-50"
              >
                <div class="flex flex-wrap items-center gap-2 justify-between">
                  <a :href="`/purchase-requisitions/${pr.id}`" class="text-sm font-semibold text-blue-700 hover:text-blue-900">
                    {{ pr.pr_number }}
                  </a>
                  <span :class="['inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold', getPaymentStatusClass(pr.payment_status)]">
                    {{ getPaymentStatusLabel(pr.payment_status) }}
                  </span>
                </div>
                <div class="mt-1 text-xs text-gray-600">
                  PR Status: {{ pr.status || '-' }} | Mode: {{ pr.mode || '-' }}
                </div>
                <div class="mt-1 text-xs text-gray-500">
                  Paid {{ pr.paid_payments || 0 }} dari {{ pr.total_payments || 0 }} payment
                </div>
                <div class="mt-1 text-xs text-gray-500">
                  Dibuat: {{ formatDateTime(pr.created_at) }}
                </div>
              </div>
            </div>

            <div v-else class="text-sm text-gray-500">
              Belum ada Purchase Requisition / Payment yang terhubung ke ticket ini.
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
                  <div class="mt-2 flex flex-wrap items-center gap-2">
                    <button
                      type="button"
                      @click="openCommentFileUpload"
                      class="inline-flex items-center px-3 py-1.5 rounded-md bg-gray-100 text-gray-700 text-xs hover:bg-gray-200"
                    >
                      <i class="fas fa-upload mr-1"></i> Upload
                    </button>
                    <button
                      type="button"
                      @click="openCommentCamera"
                      class="inline-flex items-center px-3 py-1.5 rounded-md bg-blue-100 text-blue-700 text-xs hover:bg-blue-200"
                    >
                      <i class="fas fa-camera mr-1"></i> Camera
                    </button>
                    <input
                      ref="commentFileInput"
                      type="file"
                      multiple
                      accept="image/*,.pdf,.doc,.docx"
                      class="hidden"
                      @change="handleCommentFileUpload"
                    />
                  </div>
                  <div v-if="commentAttachments.length > 0" class="mt-2 grid grid-cols-2 md:grid-cols-3 gap-2">
                    <div
                      v-for="(attachment, index) in commentAttachments"
                      :key="`new-comment-attachment-${index}`"
                      class="relative border border-gray-200 rounded-lg p-2 bg-white"
                    >
                      <button
                        type="button"
                        @click="removeCommentAttachment(index)"
                        class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-[10px]"
                      >
                        <i class="fas fa-times"></i>
                      </button>
                      <div v-if="attachment.type.startsWith('image/')" class="space-y-1">
                        <img :src="attachment.preview" :alt="attachment.name" class="w-full h-20 object-cover rounded" />
                        <p class="text-[10px] text-gray-600 break-words">{{ attachment.name }}</p>
                      </div>
                      <div v-else class="flex items-center gap-2">
                        <i class="fas fa-file-alt text-gray-500"></i>
                        <p class="text-[10px] text-gray-600 break-words">{{ attachment.name }}</p>
                      </div>
                    </div>
                  </div>
                  <div class="flex justify-end mt-2">
                    <button
                      @click="addComment"
                      :disabled="!canSubmitComment"
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
                      <p v-if="comment.comment" class="text-sm text-gray-700">{{ comment.comment }}</p>
                      <div v-if="comment.attachments && comment.attachments.length > 0" class="mt-2 grid grid-cols-2 md:grid-cols-3 gap-2">
                        <div
                          v-for="attachment in comment.attachments"
                          :key="`comment-attachment-${attachment.id}`"
                          class="border border-gray-200 rounded-lg p-2 bg-white"
                        >
                          <a
                            :href="attachment.file_path.startsWith('/storage/') ? attachment.file_path : `/storage/${attachment.file_path}`"
                            target="_blank"
                            class="block"
                          >
                            <img
                              v-if="attachment.mime_type && attachment.mime_type.startsWith('image/')"
                              :src="attachment.file_path.startsWith('/storage/') ? attachment.file_path : `/storage/${attachment.file_path}`"
                              :alt="attachment.file_name"
                              class="w-full h-20 object-cover rounded"
                            />
                            <div v-else class="flex items-center gap-2 text-xs text-gray-700">
                              <i class="fas fa-file-alt text-gray-500"></i>
                              <span class="break-words">{{ attachment.file_name }}</span>
                            </div>
                            <p v-if="attachment.mime_type && attachment.mime_type.startsWith('image/')" class="text-[10px] text-gray-600 mt-1 break-words">
                              {{ attachment.file_name }}
                            </p>
                          </a>
                        </div>
                      </div>
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
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';
import VueEasyLightbox from 'vue-easy-lightbox';
import axios from 'axios';

const props = defineProps({
  ticket: Object,
  can_manage_tickets: {
    type: Boolean,
    default: false,
  },
});


const newComment = ref('');
const commentAttachments = ref([]);
const commentFileInput = ref(null);
const canSubmitComment = computed(() => {
  return !!newComment.value?.trim() || commentAttachments.value.length > 0;
});

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

function openCreatePayment() {
  router.visit(`/purchase-requisitions/create?mode=purchase_payment&ticket_id=${encodeURIComponent(props.ticket.id)}`);
}

async function addComment() {
  if (!canSubmitComment.value) return;
  
  try {
    const formData = new FormData();
    formData.append('comment', newComment.value?.trim() || '');
    commentAttachments.value.forEach((attachment, index) => {
      formData.append(`attachments[${index}]`, attachment.file);
    });

    const response = await axios.post(`/tickets/${props.ticket.id}/comments`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    });
    
    if (response.data.success) {
      // Add comment to the ticket's comments array
      if (!props.ticket.comments) {
        props.ticket.comments = [];
      }
      props.ticket.comments.unshift(response.data.data);
      newComment.value = '';
      commentAttachments.value = [];
      
      Swal.fire('Berhasil', 'Komentar berhasil ditambahkan', 'success');
    }
  } catch (error) {
    Swal.fire('Error', error.response?.data?.message || 'Gagal menambahkan komentar', 'error');
  }
}

function openCommentFileUpload() {
  commentFileInput.value?.click();
}

function handleCommentFileUpload(event) {
  const files = Array.from(event.target.files || []);
  files.forEach((file) => {
    if (file.size > 10 * 1024 * 1024) {
      return;
    }

    const attachment = {
      file,
      name: file.name,
      type: file.type || '',
      preview: null
    };

    if (file.type?.startsWith('image/')) {
      const reader = new FileReader();
      reader.onload = (e) => {
        attachment.preview = e.target.result;
      };
      reader.readAsDataURL(file);
    }

    commentAttachments.value.push(attachment);
  });

  event.target.value = '';
}

function removeCommentAttachment(index) {
  commentAttachments.value.splice(index, 1);
}

function openCommentCamera() {
  const modal = document.createElement('div');
  modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
  modal.innerHTML = `
    <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
      <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-semibold">Capture Camera</h3>
        <button id="comment-camera-close" class="text-gray-500 hover:text-gray-700">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <div class="space-y-4">
        <video id="comment-camera-video" class="w-full h-64 bg-gray-200 rounded" autoplay></video>
        <div class="flex gap-2">
          <button id="comment-camera-capture" class="flex-1 bg-blue-500 text-white py-2 px-4 rounded hover:bg-blue-600">
            <i class="fas fa-camera mr-2"></i>Capture
          </button>
          <button id="comment-camera-cancel" class="flex-1 bg-gray-500 text-white py-2 px-4 rounded hover:bg-gray-600">
            Cancel
          </button>
        </div>
      </div>
    </div>
  `;

  document.body.appendChild(modal);

  let streamRef = null;
  const closeModal = () => {
    if (streamRef) {
      streamRef.getTracks().forEach((track) => track.stop());
    }
    modal.remove();
  };

  modal.querySelector('#comment-camera-close')?.addEventListener('click', closeModal);
  modal.querySelector('#comment-camera-cancel')?.addEventListener('click', closeModal);

  navigator.mediaDevices.getUserMedia({ video: true })
    .then((stream) => {
      streamRef = stream;
      const video = modal.querySelector('#comment-camera-video');
      video.srcObject = stream;

      modal.querySelector('#comment-camera-capture')?.addEventListener('click', () => {
        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0);

        canvas.toBlob((blob) => {
          if (!blob) return;
          const file = new File([blob], `comment-camera-${Date.now()}.jpg`, { type: 'image/jpeg' });
          commentAttachments.value.push({
            file,
            name: file.name,
            type: file.type,
            preview: canvas.toDataURL('image/jpeg', 0.8)
          });
          closeModal();
        }, 'image/jpeg', 0.8);
      });
    })
    .catch(() => {
      Swal.fire('Error', 'Camera tidak dapat diakses', 'error');
      closeModal();
    });
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

function formatDateTime(dateString) {
  if (!dateString) return '-';
  const date = new Date(dateString);
  return date.toLocaleString('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function getPaymentStatusLabel(status) {
  if (status === 'PAID') return 'Sudah Paid';
  if (status === 'ON_PROCESS') return 'Payment Proses';
  return 'Belum Payment';
}

function getPaymentStatusClass(status) {
  if (status === 'PAID') return 'bg-emerald-100 text-emerald-700';
  if (status === 'ON_PROCESS') return 'bg-amber-100 text-amber-700';
  return 'bg-gray-100 text-gray-700';
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
