<template>
  <Head :title="`Ticket ${ticket.ticket_number}`" />

  <div class="min-h-screen bg-gray-100">
    <header class="bg-white border-b border-gray-200 shadow-sm">
      <div class="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between gap-4">
        <div class="flex items-center gap-3 min-w-0">
          <div class="w-10 h-10 rounded-xl bg-blue-600 flex items-center justify-center text-white flex-shrink-0">
            <i class="fa-solid fa-ticket-alt"></i>
          </div>
          <div class="min-w-0">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Detail Ticket</p>
            <h1 class="text-lg sm:text-xl font-bold text-gray-800 truncate">{{ ticket.ticket_number }}</h1>
          </div>
        </div>
        <span class="text-xs text-gray-400 flex-shrink-0 hidden sm:inline">YMSoft ERP</span>
      </div>
    </header>

    <main class="max-w-6xl w-full mx-auto py-6 px-4">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
          <div class="bg-white rounded-2xl shadow-lg p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">{{ ticket.title }}</h2>
            <div class="prose max-w-none">
              <p class="text-gray-700 whitespace-pre-wrap">{{ ticket.description }}</p>
            </div>
          </div>

          <div class="bg-white rounded-2xl shadow-lg p-6">
            <h3 class="text-lg font-bold text-gray-800 mb-4">Informasi Payment</h3>
            <div v-if="ticket.payment_info && ticket.payment_info.length" class="space-y-3">
              <div
                v-for="pr in ticket.payment_info"
                :key="`payment-info-${pr.id}`"
                class="border border-gray-200 rounded-lg p-3 bg-gray-50"
              >
                <div class="flex flex-wrap items-center gap-2 justify-between">
                  <span class="text-sm font-semibold text-gray-800">{{ pr.pr_number }}</span>
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

          <div class="bg-white rounded-2xl shadow-lg p-6">
            <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
              <i class="fa-solid fa-comments text-blue-500"></i>
              Komentar
              <span v-if="getCommentCount() > 0" class="text-sm font-normal text-gray-500">({{ getCommentCount() }})</span>
            </h3>

            <div v-if="ticket.comments && ticket.comments.length > 0" class="space-y-3">
              <div v-for="comment in ticket.comments" :key="comment.id" class="flex gap-3">
                <div v-if="comment.user?.avatar" class="w-8 h-8 rounded-full overflow-hidden border border-gray-200 flex-shrink-0">
                  <img :src="`/storage/${comment.user.avatar}`" :alt="comment.user.nama_lengkap" class="w-full h-full object-cover" />
                </div>
                <div v-else class="w-8 h-8 rounded-full bg-gradient-to-br from-gray-400 to-gray-600 flex items-center justify-center text-white text-xs font-bold border border-gray-200 flex-shrink-0">
                  {{ getInitials(comment.user?.nama_lengkap || 'U') }}
                </div>
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
                          :href="attachmentUrl(attachment.file_path)"
                          target="_blank"
                          class="block"
                        >
                          <img
                            v-if="attachment.mime_type && attachment.mime_type.startsWith('image/')"
                            :src="attachmentUrl(attachment.file_path)"
                            :alt="attachment.file_name"
                            class="w-full h-20 object-cover rounded"
                          />
                          <div v-else class="flex items-center gap-2 text-xs text-gray-700">
                            <i class="fas fa-file-alt text-gray-500"></i>
                            <span class="break-words">{{ attachment.file_name }}</span>
                          </div>
                        </a>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div v-else class="text-center py-6 text-gray-500 text-sm">
              <i class="fa-solid fa-comment-slash mb-2 block text-2xl"></i>
              Belum ada komentar
            </div>
          </div>
        </div>

        <div class="space-y-6">
          <div class="bg-white rounded-2xl shadow-lg p-6">
            <h3 class="text-lg font-bold text-gray-800 mb-4">Ticket Information</h3>
            <div class="space-y-4">
              <div>
                <label class="text-sm font-medium text-gray-600">Status</label>
                <div class="mt-1">
                  <span :class="['inline-flex px-3 py-1 text-sm font-semibold rounded-full', getStatusColor(ticket.status?.slug)]">
                    {{ ticket.status?.name || '-' }}
                  </span>
                </div>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-600">Priority</label>
                <div class="mt-1">
                  <span :class="['inline-flex px-3 py-1 text-sm font-semibold rounded-full', getPriorityColor(ticket.priority?.level)]">
                    {{ ticket.priority?.name || '-' }}
                  </span>
                </div>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-600">Category</label>
                <p class="mt-1 text-sm text-gray-900">{{ ticket.category?.name || '-' }}</p>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-600">Divisi</label>
                <p class="mt-1 text-sm text-gray-900">{{ ticket.divisi?.nama_divisi || '-' }}</p>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-600">Outlet</label>
                <p class="mt-1 text-sm text-gray-900">{{ ticket.outlet?.nama_outlet || '-' }}</p>
              </div>
              <div v-if="ticket.assigned_users && ticket.assigned_users.length">
                <label class="text-sm font-medium text-gray-600">Assigned To</label>
                <div class="mt-2 flex flex-wrap gap-2">
                  <span
                    v-for="user in ticket.assigned_users"
                    :key="`assigned-${user.id}`"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-blue-50 text-blue-800 text-xs font-medium"
                  >
                    <i class="fas fa-user text-[10px]"></i>
                    {{ user.nama_lengkap }}
                  </span>
                </div>
              </div>
              <div v-if="ticket.due_date">
                <label class="text-sm font-medium text-gray-600">Due Date</label>
                <p class="mt-1 text-sm text-gray-900">{{ new Date(ticket.due_date).toLocaleString('id-ID') }}</p>
              </div>
              <div v-if="ticket.attachments && ticket.attachments.length > 0">
                <label class="text-sm font-medium text-gray-600">Attachments</label>
                <div class="mt-2 grid grid-cols-2 gap-3">
                  <div v-for="attachment in ticket.attachments" :key="attachment.id" class="relative">
                    <div
                      v-if="attachment.mime_type && attachment.mime_type.startsWith('image/')"
                      class="relative group cursor-pointer"
                      @click="openAttachmentLightbox(attachment)"
                    >
                      <div class="aspect-square bg-gray-100 border border-gray-200 rounded-lg overflow-hidden">
                        <img
                          :src="attachmentUrl(attachment.file_path)"
                          :alt="attachment.file_name"
                          class="w-full h-full object-cover"
                        />
                      </div>
                      <p class="text-[10px] text-gray-600 mt-1 truncate">{{ attachment.file_name }}</p>
                    </div>
                    <div v-else class="aspect-square bg-gray-100 border border-gray-200 rounded-lg flex flex-col items-center justify-center p-2">
                      <i class="fas fa-file-alt text-gray-500 text-xl mb-1"></i>
                      <a
                        :href="attachmentUrl(attachment.file_path)"
                        target="_blank"
                        class="text-[10px] text-blue-600 text-center break-words"
                      >
                        {{ attachment.file_name }}
                      </a>
                    </div>
                  </div>
                </div>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-600">Created By</label>
                <p class="mt-1 text-sm text-gray-900">{{ ticket.creator?.nama_lengkap || '-' }}</p>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-600">Created At</label>
                <p class="mt-1 text-sm text-gray-900">{{ new Date(ticket.created_at).toLocaleString('id-ID') }}</p>
              </div>
              <div v-if="ticket.source === 'daily_report'">
                <label class="text-sm font-medium text-gray-600">Source</label>
                <p class="mt-1 text-sm text-blue-600">
                  <i class="fa-solid fa-clipboard-list mr-1"></i>
                  Daily Report
                </p>
              </div>
            </div>
          </div>

          <div class="bg-white rounded-2xl shadow-lg p-6">
            <h3 class="text-lg font-bold text-gray-800 mb-6 flex items-center gap-2">
              <i class="fas fa-history text-blue-500"></i>
              Timeline History
            </h3>
            <div v-if="ticket.history && ticket.history.length > 0" class="relative">
              <div class="absolute left-6 top-0 bottom-0 w-0.5 bg-gradient-to-b from-blue-200 via-blue-300 to-gray-200"></div>
              <div class="space-y-6">
                <div
                  v-for="history in ticket.history"
                  :key="history.id"
                  class="relative flex items-start gap-4"
                >
                  <div class="relative z-10 flex-shrink-0">
                    <div
                      class="w-12 h-12 rounded-full flex items-center justify-center text-white shadow-lg"
                      :class="getHistoryIconClass(history.action)"
                    >
                      <i :class="getHistoryIcon(history.action)" class="text-sm"></i>
                    </div>
                  </div>
                  <div class="flex-1 min-w-0 pb-2">
                    <div class="bg-gray-50 rounded-xl p-4 border-l-4" :class="getHistoryBorderClass(history.action)">
                      <div class="flex items-start justify-between gap-2">
                        <div class="flex-1">
                          <h4 class="text-sm font-semibold text-gray-900 mb-1">
                            {{ getHistoryTitle(history.action) }}
                          </h4>
                          <p class="text-sm text-gray-700 mb-2">{{ getChangeDescription(history) }}</p>
                          <div v-if="history.field_name && history.old_value && history.new_value" class="mb-3">
                            <div class="flex items-center gap-2 text-xs flex-wrap">
                              <span class="px-2 py-1 bg-red-100 text-red-700 rounded-full">{{ history.old_value }}</span>
                              <i class="fas fa-arrow-right text-gray-400"></i>
                              <span class="px-2 py-1 bg-green-100 text-green-700 rounded-full">{{ history.new_value }}</span>
                            </div>
                          </div>
                        </div>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 flex-shrink-0">
                          {{ formatTimeAgo(history.created_at) }}
                        </span>
                      </div>
                      <div class="flex items-center gap-2 mt-3 pt-3 border-t border-gray-200">
                        <div class="w-6 h-6 bg-gray-300 rounded-full flex items-center justify-center">
                          <i class="fas fa-user text-xs text-gray-600"></i>
                        </div>
                        <span class="text-xs text-gray-600">{{ history.user?.nama_lengkap || 'Unknown User' }}</span>
                        <span class="text-xs text-gray-400">•</span>
                        <span class="text-xs text-gray-500">{{ new Date(history.created_at).toLocaleString('id-ID') }}</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div v-else class="text-center py-8 text-gray-500">
              <p class="text-sm font-medium">Belum ada history</p>
            </div>
          </div>
        </div>
      </div>
    </main>

    <VueEasyLightbox
      :visible="visibleRef"
      :imgs="imgsRef"
      :index="indexRef"
      @hide="hideLightbox"
    />
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import VueEasyLightbox from 'vue-easy-lightbox';

const props = defineProps({
  ticket: Object,
});

const visibleRef = ref(false);
const indexRef = ref(0);
const imgsRef = ref([]);

function attachmentUrl(filePath) {
  if (!filePath) return '#';
  return filePath.startsWith('/storage/') ? filePath : `/storage/${filePath}`;
}

function showLightbox(images, index = 0) {
  imgsRef.value = images;
  indexRef.value = index;
  visibleRef.value = true;
}

function hideLightbox() {
  visibleRef.value = false;
}

function openAttachmentLightbox(attachment) {
  const imageAttachments = props.ticket.attachments.filter(
    (att) => att.mime_type && att.mime_type.startsWith('image/')
  );
  const currentIndex = imageAttachments.findIndex((att) => att.id === attachment.id);
  const imageUrls = imageAttachments.map((att) => attachmentUrl(att.file_path));
  showLightbox(imageUrls, currentIndex);
}

function getCommentCount() {
  return props.ticket.comments ? props.ticket.comments.length : 0;
}

function getInitials(name) {
  if (!name) return 'U';
  const words = name.split(' ');
  if (words.length >= 2) {
    return (words[0][0] + words[1][0]).toUpperCase();
  }
  return name[0].toUpperCase();
}

function getStatusColor(status) {
  return {
    open: 'bg-blue-100 text-blue-800',
    in_progress: 'bg-yellow-100 text-yellow-800',
    pending: 'bg-purple-100 text-purple-800',
    resolved: 'bg-green-100 text-green-800',
    closed: 'bg-gray-100 text-gray-800',
    cancelled: 'bg-red-100 text-red-800',
  }[status] || 'bg-gray-100 text-gray-800';
}

function getPriorityColor(level) {
  return {
    1: 'bg-green-100 text-green-800',
    2: 'bg-yellow-100 text-yellow-800',
    3: 'bg-orange-100 text-orange-800',
    4: 'bg-red-100 text-red-800',
  }[level] || 'bg-gray-100 text-gray-800';
}

function formatTimeAgo(dateString) {
  const date = new Date(dateString);
  const now = new Date();
  const diffInSeconds = Math.floor((now - date) / 1000);

  if (diffInSeconds < 60) return 'baru saja';
  if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)} menit yang lalu`;
  if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)} jam yang lalu`;
  if (diffInSeconds < 2592000) return `${Math.floor(diffInSeconds / 86400)} hari yang lalu`;

  return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
}

function formatDateTime(dateString) {
  if (!dateString) return '-';
  return new Date(dateString).toLocaleString('id-ID', {
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
  if (history.field_name && history.old_value && history.new_value) {
    return `Changed ${history.field_name} from '${history.old_value}' to '${history.new_value}'`;
  }
  if (history.description) return history.description;

  const actionTexts = {
    created: 'Ticket created',
    updated: 'Ticket updated',
    assigned: 'Ticket assigned',
    status_changed: 'Status changed',
    priority_changed: 'Priority changed',
    category_changed: 'Category changed',
    comment_added: 'Comment added',
    attachment_added: 'Attachment added',
    resolved: 'Ticket closed',
    closed: 'Ticket closed',
    reopened: 'Ticket reopened',
  };

  return actionTexts[history.action] || history.action?.replace(/_/g, ' ').replace(/\b\w/g, (l) => l.toUpperCase()) || 'Action performed';
}

function getHistoryTitle(action) {
  const titles = {
    created: 'Ticket Created',
    updated: 'Ticket Updated',
    assigned: 'Ticket Assigned',
    status_changed: 'Status Changed',
    priority_changed: 'Priority Changed',
    category_changed: 'Category Changed',
    comment_added: 'Comment Added',
    attachment_added: 'Attachment Added',
    resolved: 'Ticket Closed',
    closed: 'Ticket Closed',
    reopened: 'Ticket Reopened',
  };

  return titles[action] || action?.replace(/_/g, ' ').replace(/\b\w/g, (l) => l.toUpperCase()) || 'Action Performed';
}

function getHistoryIcon(action) {
  const icons = {
    created: 'fas fa-plus-circle',
    updated: 'fas fa-edit',
    assigned: 'fas fa-user-check',
    status_changed: 'fas fa-exchange-alt',
    priority_changed: 'fas fa-flag',
    category_changed: 'fas fa-tags',
    comment_added: 'fas fa-comment',
    attachment_added: 'fas fa-paperclip',
    resolved: 'fas fa-check-circle',
    closed: 'fas fa-times-circle',
    reopened: 'fas fa-redo',
  };

  return icons[action] || 'fas fa-circle';
}

function getHistoryIconClass(action) {
  const classes = {
    created: 'bg-green-500',
    updated: 'bg-blue-500',
    assigned: 'bg-purple-500',
    status_changed: 'bg-yellow-500',
    priority_changed: 'bg-orange-500',
    category_changed: 'bg-indigo-500',
    comment_added: 'bg-cyan-500',
    attachment_added: 'bg-pink-500',
    resolved: 'bg-green-600',
    closed: 'bg-red-500',
    reopened: 'bg-blue-600',
  };

  return classes[action] || 'bg-gray-500';
}

function getHistoryBorderClass(action) {
  const classes = {
    created: 'border-green-400',
    updated: 'border-blue-400',
    assigned: 'border-purple-400',
    status_changed: 'border-yellow-400',
    priority_changed: 'border-orange-400',
    category_changed: 'border-indigo-400',
    comment_added: 'border-cyan-400',
    attachment_added: 'border-pink-400',
    resolved: 'border-green-500',
    closed: 'border-red-400',
    reopened: 'border-blue-500',
  };

  return classes[action] || 'border-gray-400';
}
</script>
