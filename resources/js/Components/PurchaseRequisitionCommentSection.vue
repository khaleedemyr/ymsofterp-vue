<template>
  <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
    <h4 class="text-md font-semibold text-gray-900 dark:text-white mb-4 flex items-center">
      <i class="fas fa-comment mr-2 text-indigo-500"></i>
      Comments
      <span v-if="unreadCount > 0" class="ml-2 bg-red-500 text-white text-xs font-bold rounded-full w-5 h-5 flex items-center justify-center">
        {{ unreadCount > 99 ? '99+' : unreadCount }}
      </span>
    </h4>

    <!-- Add Comment Form -->
    <div class="mb-6 p-4 bg-white dark:bg-gray-600 rounded-lg border border-gray-200 dark:border-gray-500">
      <textarea
        v-model="newComment"
        rows="3"
        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-500 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-gray-700 dark:text-white"
        placeholder="Add a comment..."
      ></textarea>
      
      <!-- Attachment Upload -->
      <div class="mt-3">
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
          Attachment (Optional)
        </label>
        <div class="flex items-center gap-2">
          <input
            type="file"
            ref="attachmentInput"
            @change="handleAttachmentChange"
            accept="image/*,.pdf,.doc,.docx,.xls,.xlsx"
            class="hidden"
          />
          <button
            @click="$refs.attachmentInput.click()"
            type="button"
            class="px-3 py-2 bg-gray-200 dark:bg-gray-500 text-gray-700 dark:text-white rounded-md hover:bg-gray-300 dark:hover:bg-gray-600 text-sm"
          >
            <i class="fas fa-paperclip mr-2"></i>
            Choose File
          </button>
          <span v-if="selectedAttachment" class="text-sm text-gray-600 dark:text-gray-300 flex items-center gap-2">
            <i class="fas fa-file"></i>
            {{ selectedAttachment.name }}
            <button
              @click="removeAttachment"
              class="text-red-500 hover:text-red-700"
            >
              <i class="fas fa-times"></i>
            </button>
          </span>
          <span v-else class="text-sm text-gray-400 dark:text-gray-500">No file selected</span>
        </div>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Max size: 10MB (Images, PDF, Word, Excel)</p>
      </div>

      <div class="mt-3 flex items-center justify-between">
        <label class="flex items-center">
          <input
            v-model="isInternalComment"
            type="checkbox"
            class="mr-2"
          />
          <span class="text-sm text-gray-600 dark:text-gray-300">Internal comment</span>
        </label>
        <button
          @click="addComment"
          :disabled="!newComment.trim() || uploadingComment"
          class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <i v-if="uploadingComment" class="fas fa-spinner fa-spin mr-2"></i>
          <i v-else class="fas fa-paper-plane mr-2"></i>
          {{ uploadingComment ? 'Uploading...' : 'Add Comment' }}
        </button>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loadingComments" class="text-center py-8">
      <i class="fas fa-spinner fa-spin text-4xl text-indigo-500 mb-4"></i>
      <p class="text-gray-500 dark:text-gray-400">Loading comments...</p>
    </div>

    <!-- Comments List -->
    <div v-else-if="comments.length > 0" class="space-y-4">
      <div
        v-for="comment in comments"
        :key="comment.id"
        class="p-4 border border-gray-200 dark:border-gray-500 rounded-lg bg-white dark:bg-gray-600 hover:bg-gray-50 dark:hover:bg-gray-500 transition-colors"
      >
        <div class="flex items-center justify-between mb-2">
          <div class="flex items-center space-x-2">
            <span class="font-medium text-gray-900 dark:text-white">{{ comment.user?.nama_lengkap || 'Unknown User' }}</span>
            <span v-if="comment.is_internal" class="px-2 py-1 bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200 text-xs rounded-full">
              Internal
            </span>
          </div>
          <div class="flex items-center gap-2">
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ formatDate(comment.created_at) }} {{ formatTime(comment.created_at) }}</span>
            <!-- Edit/Delete buttons (only for own comments) -->
            <div v-if="comment.user_id === currentUserId" class="flex items-center gap-1">
              <button
                @click="editComment(comment)"
                class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 text-sm"
                title="Edit comment"
              >
                <i class="fas fa-edit"></i>
              </button>
              <button
                @click="deleteCommentConfirm(comment)"
                class="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 text-sm"
                title="Delete comment"
              >
                <i class="fas fa-trash"></i>
              </button>
            </div>
          </div>
        </div>
        <p class="text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ comment.comment }}</p>
        
        <!-- Attachment Display -->
        <div v-if="comment.attachment_path" class="mt-3 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-500">
          <div class="flex items-center gap-3">
            <!-- Image Preview -->
            <div v-if="isImageFile(comment.attachment_mime_type)" class="flex-shrink-0">
              <img
                :src="getAttachmentUrl(comment)"
                :alt="comment.attachment_name"
                class="w-24 h-24 object-cover rounded-lg border border-gray-300 dark:border-gray-500 cursor-pointer hover:opacity-80 transition-opacity"
                @click="openImageLightbox(comment)"
              />
            </div>
            <!-- File Icon for non-images -->
            <div v-else class="flex-shrink-0">
              <i :class="getFileIcon(comment.attachment_name)" class="text-3xl"></i>
            </div>
            
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ comment.attachment_name }}</p>
              <p class="text-xs text-gray-500 dark:text-gray-400">{{ formatFileSize(comment.attachment_size) }}</p>
            </div>
            
            <div class="flex items-center gap-2">
              <a
                :href="getAttachmentUrl(comment)"
                :download="comment.attachment_name"
                class="px-3 py-1.5 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm"
              >
                <i class="fas fa-download mr-1"></i>
                Download
              </a>
              <button
                v-if="isImageFile(comment.attachment_mime_type)"
                @click="openImageLightbox(comment)"
                class="px-3 py-1.5 bg-green-600 text-white rounded-md hover:bg-green-700 text-sm"
              >
                <i class="fas fa-eye mr-1"></i>
                View
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div v-else class="text-center py-8 text-gray-500 dark:text-gray-400">
      <i class="fas fa-comment text-4xl mb-4 text-gray-300 dark:text-gray-600"></i>
      <p>No comments yet</p>
    </div>

    <!-- Edit Comment Modal -->
    <div v-if="showEditCommentModal" class="fixed inset-0 z-[100004] flex items-center justify-center bg-black/40" @click="closeEditCommentModal">
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg w-full max-w-2xl p-6 relative" @click.stop>
        <button @click="closeEditCommentModal" class="absolute top-3 right-3 text-gray-400 hover:text-gray-600">
          <i class="fas fa-times text-lg"></i>
        </button>
        <div class="mb-4">
          <h3 class="text-lg font-semibold text-gray-800 dark:text-white flex items-center gap-2">
            <i class="fas fa-edit text-blue-500"></i>
            Edit Comment
          </h3>
        </div>

        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Comment</label>
            <textarea
              v-model="editingComment.comment"
              rows="4"
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-500 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
              placeholder="Enter your comment..."
            ></textarea>
          </div>

          <div>
            <label class="flex items-center">
              <input
                v-model="editingComment.is_internal"
                type="checkbox"
                class="mr-2"
              />
              <span class="text-sm text-gray-600 dark:text-gray-300">Internal comment</span>
            </label>
          </div>

          <div class="flex justify-end gap-2">
            <button
              @click="closeEditCommentModal"
              class="px-4 py-2 bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-white rounded-md hover:bg-gray-300 dark:hover:bg-gray-500"
            >
              Cancel
            </button>
            <button
              @click="updateComment"
              :disabled="!editingComment.comment.trim() || updatingComment"
              class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50"
            >
              <i v-if="updatingComment" class="fas fa-spinner fa-spin mr-2"></i>
              {{ updatingComment ? 'Updating...' : 'Update Comment' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Image Lightbox Modal -->
    <div v-if="showImageLightbox" class="fixed inset-0 z-[100005] flex items-center justify-center bg-black/80" @click="closeImageLightbox">
      <div class="relative max-w-4xl max-h-[90vh] p-4" @click.stop>
        <button @click="closeImageLightbox" class="absolute top-2 right-2 text-white hover:text-gray-300 z-10 bg-black/50 rounded-full p-2">
          <i class="fas fa-times text-xl"></i>
        </button>
        <img
          v-if="lightboxImage"
          :src="lightboxImageUrl"
          :alt="lightboxImage.attachment_name"
          class="max-w-full max-h-[90vh] object-contain rounded-lg shadow-2xl"
        />
        <div class="absolute bottom-4 left-4 right-4 text-center">
          <p class="text-white bg-black/50 px-3 py-1 rounded-lg text-sm">
            {{ lightboxImage?.attachment_name }}
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';

const props = defineProps({
  purchaseRequisitionId: {
    type: [Number, String],
    required: true
  },
  currentUserId: {
    type: [Number, String],
    required: true
  },
  unreadCount: {
    type: Number,
    default: 0
  }
});

const emit = defineEmits(['comment-added', 'comment-updated', 'comment-deleted']);

const comments = ref([]);
const loadingComments = ref(false);
const uploadingComment = ref(false);
const newComment = ref('');
const isInternalComment = ref(false);
const selectedAttachment = ref(null);
const attachmentInput = ref(null);

// Edit Comment Modal
const showEditCommentModal = ref(false);
const editingComment = ref({});
const updatingComment = ref(false);

// Image Lightbox
const showImageLightbox = ref(false);
const lightboxImage = ref(null);

// Load comments on mount
onMounted(() => {
  loadComments();
});

// Watch for purchase requisition ID changes
watch(() => props.purchaseRequisitionId, () => {
  if (props.purchaseRequisitionId) {
    loadComments();
  }
});

async function loadComments() {
  if (!props.purchaseRequisitionId) return;
  
  loadingComments.value = true;
  try {
    const response = await axios.get(`/purchase-requisitions/${props.purchaseRequisitionId}/comments`);
    if (response.data.success) {
      comments.value = response.data.data;
    }
  } catch (error) {
    console.error('Error loading comments:', error);
    Swal.fire({
      title: 'Error',
      text: 'Failed to load comments',
      icon: 'error',
      didOpen: () => {
        const swalContainer = document.querySelector('.swal2-container');
        if (swalContainer) {
          swalContainer.style.setProperty('z-index', '999999', 'important');
        }
      }
    });
  } finally {
    loadingComments.value = false;
  }
}

function handleAttachmentChange(event) {
  const file = event.target.files[0];
  if (file) {
    if (file.size > 10 * 1024 * 1024) {
      Swal.fire({
        title: 'Error',
        text: 'File size must be less than 10MB',
        icon: 'error',
        didOpen: () => {
          const swalContainer = document.querySelector('.swal2-container');
          if (swalContainer) {
            swalContainer.style.setProperty('z-index', '999999', 'important');
          }
        }
      });
      event.target.value = '';
      return;
    }
    selectedAttachment.value = file;
  }
}

function removeAttachment() {
  selectedAttachment.value = null;
  if (attachmentInput.value) {
    attachmentInput.value.value = '';
  }
}

async function addComment() {
  if (!newComment.value.trim() || !props.purchaseRequisitionId) return;
  
  uploadingComment.value = true;
  
  try {
    const formData = new FormData();
    formData.append('comment', newComment.value);
    formData.append('is_internal', isInternalComment.value ? '1' : '0');
    
    if (selectedAttachment.value) {
      formData.append('attachment', selectedAttachment.value);
    }
    
    const response = await axios.post(`/purchase-requisitions/${props.purchaseRequisitionId}/comments`, formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });
    
    if (response.status === 200 || response.status === 201) {
      await loadComments();
      
      newComment.value = '';
      isInternalComment.value = false;
      removeAttachment();
      
      emit('comment-added');
      
      Swal.fire({
        title: 'Success!',
        text: 'Comment added successfully',
        icon: 'success',
        timer: 1500,
        showConfirmButton: false,
        didOpen: () => {
          const swalContainer = document.querySelector('.swal2-container');
          if (swalContainer) {
            swalContainer.style.setProperty('z-index', '999999', 'important');
          }
        }
      });
    }
  } catch (error) {
    console.error('Error adding comment:', error);
    Swal.fire({
      title: 'Error',
      text: error.response?.data?.message || 'Failed to add comment',
      icon: 'error',
      didOpen: () => {
        const swalContainer = document.querySelector('.swal2-container');
        if (swalContainer) {
          swalContainer.style.setProperty('z-index', '999999', 'important');
        }
      }
    });
  } finally {
    uploadingComment.value = false;
  }
}

function editComment(comment) {
  editingComment.value = {
    id: comment.id,
    comment: comment.comment,
    is_internal: comment.is_internal || false,
  };
  showEditCommentModal.value = true;
}

function closeEditCommentModal() {
  showEditCommentModal.value = false;
  editingComment.value = {};
}

async function updateComment() {
  if (!editingComment.value.comment?.trim() || !props.purchaseRequisitionId) return;
  
  updatingComment.value = true;
  
  try {
    const response = await axios.put(
      `/purchase-requisitions/${props.purchaseRequisitionId}/comments/${editingComment.value.id}`,
      {
        comment: editingComment.value.comment,
        is_internal: editingComment.value.is_internal,
      }
    );
    
    if (response.data.success) {
      await loadComments();
      closeEditCommentModal();
      emit('comment-updated');
      
      Swal.fire({
        title: 'Success!',
        text: 'Comment updated successfully',
        icon: 'success',
        timer: 1500,
        showConfirmButton: false,
        didOpen: () => {
          const swalContainer = document.querySelector('.swal2-container');
          if (swalContainer) {
            swalContainer.style.setProperty('z-index', '999999', 'important');
          }
        }
      });
    }
  } catch (error) {
    console.error('Error updating comment:', error);
    Swal.fire({
      title: 'Error',
      text: error.response?.data?.message || 'Failed to update comment',
      icon: 'error',
      didOpen: () => {
        const swalContainer = document.querySelector('.swal2-container');
        if (swalContainer) {
          swalContainer.style.setProperty('z-index', '999999', 'important');
        }
      }
    });
  } finally {
    updatingComment.value = false;
  }
}

function deleteCommentConfirm(comment) {
  Swal.fire({
    title: 'Delete Comment?',
    text: 'Are you sure you want to delete this comment? This action cannot be undone.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#EF4444',
    cancelButtonColor: '#6B7280',
    confirmButtonText: 'Yes, Delete!',
    cancelButtonText: 'Cancel',
    reverseButtons: true,
    allowOutsideClick: false,
    didOpen: () => {
      const setZIndex = () => {
        const swalContainer = document.querySelector('.swal2-container');
        if (swalContainer) {
          swalContainer.style.setProperty('z-index', '999999', 'important');
          swalContainer.style.setProperty('position', 'fixed', 'important');
        }
        const swalPopup = document.querySelector('.swal2-popup');
        if (swalPopup) {
          swalPopup.style.setProperty('z-index', '999999', 'important');
        }
        const swalBackdrop = document.querySelector('.swal2-backdrop-show');
        if (swalBackdrop) {
          swalBackdrop.style.setProperty('z-index', '999998', 'important');
        }
      };
      setZIndex();
      setTimeout(setZIndex, 10);
      setTimeout(setZIndex, 50);
      setTimeout(setZIndex, 100);
    }
  }).then((result) => {
    if (result.isConfirmed) {
      deleteComment(comment);
    }
  });
}

async function deleteComment(comment) {
  try {
    const response = await axios.delete(
      `/purchase-requisitions/${props.purchaseRequisitionId}/comments/${comment.id}`
    );
    
    if (response.data.success) {
      await loadComments();
      emit('comment-deleted');
      
      Swal.fire({
        title: 'Deleted!',
        text: 'Comment has been deleted',
        icon: 'success',
        timer: 1500,
        showConfirmButton: false,
        didOpen: () => {
          const setZIndex = () => {
            const swalContainer = document.querySelector('.swal2-container');
            if (swalContainer) {
              swalContainer.style.setProperty('z-index', '999999', 'important');
            }
          };
          setZIndex();
          setTimeout(setZIndex, 10);
        }
      });
    }
  } catch (error) {
    console.error('Error deleting comment:', error);
    Swal.fire({
      title: 'Error',
      text: error.response?.data?.message || 'Failed to delete comment',
      icon: 'error',
      didOpen: () => {
        const setZIndex = () => {
          const swalContainer = document.querySelector('.swal2-container');
          if (swalContainer) {
            swalContainer.style.setProperty('z-index', '999999', 'important');
          }
        };
        setZIndex();
        setTimeout(setZIndex, 10);
      }
    });
  }
}

function getAttachmentUrl(comment) {
  if (!comment.attachment_path) return '';
  return `/storage/${comment.attachment_path}`;
}

function isImageFile(mimeType) {
  if (!mimeType) return false;
  return mimeType.startsWith('image/');
}

function getFileIcon(fileName) {
  if (!fileName) return 'fa-file text-gray-500';
  
  const extension = fileName.split('.').pop().toLowerCase();
  
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
    'txt': 'fa-file-alt text-gray-500',
    'zip': 'fa-file-archive text-yellow-500',
    'rar': 'fa-file-archive text-yellow-500',
  };
  
  return iconMap[extension] || 'fa-file text-gray-500';
}

function formatFileSize(bytes) {
  if (!bytes) return '0 Bytes';
  
  const k = 1024;
  const sizes = ['Bytes', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function formatDate(date) {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('id-ID', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
}

function formatTime(date) {
  if (!date) return '-';
  return new Date(date).toLocaleTimeString('id-ID', {
    hour: '2-digit',
    minute: '2-digit',
  });
}

function openImageLightbox(comment) {
  lightboxImage.value = comment;
  showImageLightbox.value = true;
}

function closeImageLightbox() {
  showImageLightbox.value = false;
  lightboxImage.value = null;
}

const lightboxImageUrl = computed(() => {
  if (!lightboxImage.value) return '';
  return getAttachmentUrl(lightboxImage.value);
});

// Expose loadComments method for parent component
defineExpose({
  loadComments
});
</script>

