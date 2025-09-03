<template>
  <!-- Camera Modal with higher z-index -->
  <Teleport to="body">
    <CameraModal 
      v-if="showCameraModal"
      :mode="cameraMode"
      @close="closeCameraModal"
      @capture="onCameraCapture"
      class="!z-[9999]"
    />
  </Teleport>

  <!-- Preview Modal - Teleported to body -->
  <Teleport to="body">
    <div v-if="showPreviewModal" class="fixed inset-0 z-[99999] flex items-center justify-center">
      <div class="fixed inset-0 bg-black/80" @click="closePreviewModal"></div>
      <div class="relative w-full max-w-5xl bg-white rounded-2xl p-6 shadow-xl mx-4">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-lg font-medium text-gray-900">Preview</h3>
          <button @click="closePreviewModal" class="text-gray-400 hover:text-red-500">
            <i class="fas fa-times"></i>
          </button>
        </div>
        
        <!-- Image Preview -->
        <div v-if="previewType === 'image'" class="flex justify-center">
          <img :src="previewUrl" class="max-h-[80vh] max-w-full object-contain" />
        </div>
        
        <!-- Video Preview -->
        <div v-else-if="previewType === 'video'" class="flex justify-center">
          <video :src="previewUrl" controls class="max-h-[80vh] max-w-full"></video>
        </div>
      </div>
    </div>
  </Teleport>

  <!-- Comment Modal -->
  <TransitionRoot appear :show="true" as="template">
    <Dialog as="div" @close="emit('close')" class="relative z-[8888]">
      <TransitionChild
        enter="duration-300 ease-out"
        enter-from="opacity-0"
        enter-to="opacity-100"
        leave="duration-200 ease-in"
        leave-from="opacity-100"
        leave-to="opacity-0"
      >
        <DialogOverlay class="fixed inset-0 bg-black/40" />
      </TransitionChild>

      <div class="fixed inset-0 overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4">
          <TransitionChild
            enter="duration-300 ease-out"
            enter-from="opacity-0 scale-95"
            enter-to="opacity-100 scale-100"
            leave="duration-200 ease-in"
            leave-from="opacity-100 scale-100"
            leave-to="opacity-0 scale-95"
          >
            <DialogPanel class="w-[98%] max-w-[2000px] transform overflow-hidden rounded-2xl bg-white p-6 shadow-xl transition-all">
              <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900">Komentar</h3>
                <button @click="emit('close')" class="text-gray-400 hover:text-red-500">
                  <i class="fas fa-times"></i>
                </button>
              </div>

              <!-- Daftar Komentar -->
              <div class="space-y-4 max-h-[500px] overflow-y-auto mb-4 pr-2">
                <div v-if="isLoadingComments" class="text-center py-4">
                  <i class="fas fa-spinner fa-spin text-blue-500 text-2xl"></i>
                  <p class="text-gray-600 mt-2">Memuat komentar...</p>
                </div>
                <div v-else-if="comments.length === 0" class="text-center py-4">
                  <i class="far fa-comment text-gray-400 text-2xl"></i>
                  <p class="text-gray-500 mt-2">Belum ada komentar</p>
                </div>
                <div v-else v-for="comment in comments" :key="comment.id" class="bg-gray-50 rounded-lg p-4">
                  <div class="flex items-start gap-3">
                    <div class="w-10 h-10 rounded-full bg-blue-500 flex items-center justify-center text-white">
                      {{ getInitials(comment.user_name) }}
                    </div>
                    <div class="flex-1">
                      <div class="flex justify-between items-start">
                        <div>
                          <span class="font-medium text-gray-900">{{ comment.user_name }}</span>
                          <span class="text-xs text-gray-500 ml-2">{{ formatDate(comment.created_at) }}</span>
                        </div>
                        <button v-if="comment.user_id === user.id" @click="deleteComment(comment.id)" class="text-red-400 hover:text-red-600 ml-2" title="Hapus komentar">
                          <i class="fas fa-trash"></i>
                        </button>
                      </div>
                      <p class="text-gray-700 mt-1 text-base">{{ comment.comment }}</p>
                      
                      <!-- Attachments -->
                      <div v-if="comment.attachments?.length" class="mt-3 flex flex-wrap gap-3">
                        <div v-for="file in comment.attachments" :key="file.id" 
                             class="relative group cursor-pointer"
                             @click="openAttachment(file)">
                          <!-- Image -->
                          <img v-if="file.file_type.startsWith('image/')" 
                               :src="'/storage/' + file.file_path" 
                               class="w-32 h-32 object-cover rounded-lg border shadow-sm hover:shadow-md transition-all" />
                          
                          <!-- Video -->
                          <div v-else-if="file.file_type.startsWith('video/')" 
                               class="w-40 h-32 rounded-lg bg-gray-100 flex items-center justify-center relative group hover:bg-gray-200 transition-all border shadow-sm hover:shadow-md">
                            <i class="fas fa-play text-gray-500 text-2xl group-hover:scale-110 transition-transform"></i>
                            <video :src="'/storage/' + file.file_path" class="hidden" />
                          </div>
                          
                          <!-- Other Files -->
                          <div v-else class="w-40 h-32 rounded-lg bg-gray-100 flex flex-col items-center justify-center gap-2 hover:bg-gray-200 transition-all border shadow-sm hover:shadow-md">
                            <i class="fas fa-file text-gray-500 text-2xl"></i>
                            <span class="text-xs text-gray-600 px-2 text-center">{{ file.file_name }}</span>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Form Komentar -->
              <div class="border-t pt-4">
                <textarea 
                  v-model="newComment"
                  @input="saveDraft"
                  rows="4"
                  class="w-full border rounded-lg p-3 mb-3 text-base focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  placeholder="Tulis komentar... (Ctrl+Enter untuk kirim)"
                ></textarea>
                
                <!-- Draft info -->
                <div v-if="newComment.trim() || uploadedFiles.length > 0" class="text-xs text-gray-500 mb-3 flex items-center gap-2">
                  <i class="fas fa-save text-blue-500"></i>
                  <span>Draft tersimpan otomatis</span>
                </div>
                
                <!-- Keyboard shortcuts info -->
                <div class="text-xs text-gray-400 mb-3">
                  <span class="font-medium">Keyboard shortcuts:</span> 
                  <kbd class="px-1 py-0.5 bg-gray-100 rounded text-xs">Ctrl+Enter</kbd> untuk kirim, 
                  <kbd class="px-1 py-0.5 bg-gray-100 rounded text-xs">Esc</kbd> untuk tutup
                </div>

                <!-- Preview Media -->
                <div v-if="uploadedFiles.length" class="flex flex-wrap gap-3 mb-3">
                  <div v-for="(file, i) in uploadedFiles" :key="i" class="relative group">
                    <img v-if="file.type.startsWith('image/')" 
                         :src="file.preview" 
                         class="w-32 h-32 object-cover rounded-lg border shadow-sm" />
                    <video v-else-if="file.type.startsWith('video/')" 
                           :src="file.preview" 
                           class="w-40 h-32 rounded-lg border shadow-sm" />
                    <div v-else 
                         class="w-40 h-32 rounded-lg bg-gray-100 flex flex-col items-center justify-center gap-2 border shadow-sm">
                      <i class="fas fa-file text-gray-500 text-2xl"></i>
                      <span class="text-xs text-gray-600 px-2 text-center">{{ file.file.name }}</span>
                    </div>
                    <button @click="removeFile(i)" 
                            class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center hover:bg-red-600">
                      <i class="fas fa-times"></i>
                    </button>
                  </div>
                </div>

                <div class="flex justify-between items-center">
                  <div class="flex gap-3">
                    <button @click="openCamera('photo')" class="p-2 text-gray-600 hover:text-blue-500 hover:bg-blue-50 rounded-lg transition-all" title="Ambil Foto">
                      <i class="fas fa-camera text-lg"></i>
                    </button>
                    <button @click="openCamera('video')" class="p-2 text-gray-600 hover:text-blue-500 hover:bg-blue-50 rounded-lg transition-all" title="Rekam Video">
                      <i class="fas fa-video text-lg"></i>
                    </button>
                    <button @click="$refs.fileInput.click()" class="p-2 text-gray-600 hover:text-blue-500 hover:bg-blue-50 rounded-lg transition-all" title="Upload File">
                      <i class="fas fa-paperclip text-lg"></i>
                    </button>
                    <input 
                      ref="fileInput"
                      type="file"
                      multiple
                      accept="image/*,video/*,application/pdf,.doc,.docx,.xls,.xlsx"
                      class="hidden"
                      @change="onFileSelected"
                    />
                  </div>
                  <button 
                    @click="submitComment"
                    :disabled="!canSubmit || isSubmitting"
                    :class="[
                      'px-6 py-2 rounded-lg text-white text-base font-medium transition-all flex items-center gap-2',
                      canSubmit && !isSubmitting ? 'bg-blue-500 hover:bg-blue-600' : 'bg-gray-300 cursor-not-allowed'
                    ]"
                  >
                    <i v-if="isSubmitting" class="fas fa-spinner fa-spin"></i>
                    {{ isSubmitting ? 'Menyimpan...' : 'Kirim' }}
                  </button>
                </div>
              </div>
            </DialogPanel>
          </TransitionChild>
        </div>
      </div>
    </Dialog>
  </TransitionRoot>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import {
  TransitionRoot,
  TransitionChild,
  Dialog,
  DialogOverlay,
  DialogPanel,
} from '@headlessui/vue';
import CameraModal from './CameraModal.vue';
import axios from 'axios';
import Swal from 'sweetalert2';

const props = defineProps({
  taskId: {
    type: [Number, String],
    required: true
  }
});

const emit = defineEmits(['close', 'comment-added']);

const comments = ref([]);
const newComment = ref('');
const uploadedFiles = ref([]);
const showCameraModal = ref(false);
const cameraMode = ref('photo');
const isSubmitting = ref(false); // Loading state untuk submit comment
const isLoadingComments = ref(false); // Loading state untuk fetch comments

// Ambil user login
const user = usePage().props.auth?.user || {};

// Preview modal state
const showPreviewModal = ref(false);
const previewUrl = ref('');
const previewType = ref('');

// Ambil data komentar saat komponen dimount
const fetchComments = async () => {
  if (!props.taskId) {
    console.error('fetchComments: No taskId provided');
    return;
  }
  
  console.log('Fetching comments for taskId:', props.taskId);
  isLoadingComments.value = true;
  
  try {
    const response = await axios.get(`/api/maintenance-comments/${props.taskId}`);
    console.log('Comments fetched successfully:', response.data);
    comments.value = response.data;
  } catch (error) {
    console.error('Error fetching comments:', error);
  } finally {
    isLoadingComments.value = false;
  }
};

// Keyboard shortcuts
function handleKeydown(event) {
  // Ctrl/Cmd + Enter to submit comment
  if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
    event.preventDefault();
    submitComment();
  }
  
  // Escape to close modal
  if (event.key === 'Escape') {
    emit('close');
  }
}

// Auto-save draft
function saveDraft() {
  if (newComment.value.trim() || uploadedFiles.value.length > 0) {
    localStorage.setItem(`comment_draft_${props.taskId}`, JSON.stringify({
      comment: newComment.value,
      timestamp: Date.now()
    }));
  }
}

// Load draft
function loadDraft() {
  try {
    const draft = localStorage.getItem(`comment_draft_${props.taskId}`);
    if (draft) {
      const draftData = JSON.parse(draft);
      // Only load draft if it's less than 1 hour old
      if (Date.now() - draftData.timestamp < 3600000) {
        newComment.value = draftData.comment;
      } else {
        localStorage.removeItem(`comment_draft_${props.taskId}`);
      }
    }
  } catch (error) {
    console.error('Error loading draft:', error);
  }
}

// Clear draft after successful submission
function clearDraft() {
  localStorage.removeItem(`comment_draft_${props.taskId}`);
}

// Lifecycle
onMounted(() => {
  console.log('CommentModal mounted with taskId:', props.taskId);
  
  // Validasi taskId
  if (!props.taskId) {
    console.error('CommentModal: No taskId provided');
    Swal.fire({
      title: 'Error',
      text: 'Task ID tidak valid. Silakan coba lagi.',
      icon: 'error'
    });
    emit('close');
    return;
  }
  
  fetchComments();
  loadDraft();
  document.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
  document.removeEventListener('keydown', handleKeydown);
});

const canSubmit = computed(() => {
  return newComment.value.trim() !== '' || uploadedFiles.value.length > 0;
});

function getInitials(name) {
  if (!name) return 'NA';
  return name
    .split(' ')
    .map(word => word[0])
    .join('')
    .toUpperCase()
    .slice(0, 2);
}

function formatDate(dateString) {
  const date = new Date(dateString);
  return new Intl.DateTimeFormat('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  }).format(date);
}

function openCamera(mode) {
  cameraMode.value = mode;
  showCameraModal.value = true;
}

function closeCameraModal() {
  showCameraModal.value = false;
}

function onCameraCapture(data) {
  if (cameraMode.value === 'photo') {
    // Data URL dari foto
    uploadedFiles.value.push({
      file: dataURLtoFile(data, 'photo.png'),
      preview: data,
      type: 'image/png'
    });
  } else {
    // Blob dari video
    uploadedFiles.value.push({
      file: new File([data], 'video.webm', { type: 'video/webm' }),
      preview: URL.createObjectURL(data),
      type: 'video/webm'
    });
  }
  showCameraModal.value = false;
}

function onFileSelected(event) {
  const files = Array.from(event.target.files);
  files.forEach(file => {
    uploadedFiles.value.push({
      file,
      preview: file.type.startsWith('image/') ? URL.createObjectURL(file) : null,
      type: file.type
    });
  });
  event.target.value = ''; // Reset input
}

function removeFile(index) {
  const file = uploadedFiles.value[index];
  if (file.preview && !file.preview.startsWith('data:')) {
    URL.revokeObjectURL(file.preview);
  }
  uploadedFiles.value.splice(index, 1);
}

function dataURLtoFile(dataurl, filename) {
  const arr = dataurl.split(',');
  const mime = arr[0].match(/:(.*?);/)[1];
  const bstr = atob(arr[1]);
  let n = bstr.length;
  const u8arr = new Uint8Array(n);
  while (n--) {
    u8arr[n] = bstr.charCodeAt(n);
  }
  return new File([u8arr], filename, { type: mime });
}

async function submitComment() {
  if (!canSubmit.value) return;
  if (isSubmitting.value) return; // Prevent double submission

  // Validasi taskId
  if (!props.taskId) {
    Swal.fire({
      title: 'Error',
      text: 'Task ID tidak valid. Silakan coba lagi.',
      icon: 'error'
    });
    return;
  }

  // Konfirmasi jika comment kosong tapi ada file
  if (!newComment.value.trim() && uploadedFiles.value.length > 0) {
    const result = await Swal.fire({
      title: 'Konfirmasi',
      text: 'Anda tidak menulis komentar. Apakah Anda yakin ingin mengirim hanya file saja?',
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Ya, kirim file',
      cancelButtonText: 'Tulis komentar dulu'
    });
    
    if (!result.isConfirmed) {
      return;
    }
  }

  isSubmitting.value = true;

  try {
    const formData = new FormData();
    formData.append('task_id', props.taskId);
    formData.append('comment', newComment.value || 'File attachment only');
    
    uploadedFiles.value.forEach((item, i) => {
      formData.append(`attachments[]`, item.file);
    });

    const response = await axios.post('/api/maintenance-comments', formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    });

    if (response.data.success) {
      newComment.value = '';
      uploadedFiles.value = [];
      await fetchComments();
      emit('comment-added');
      clearDraft(); // Clear draft after successful submission
      
      // Show success message
      Swal.fire({
        title: 'Berhasil!',
        text: 'Komentar berhasil ditambahkan.',
        icon: 'success',
        timer: 2000,
        showConfirmButton: false
      });
    }
  } catch (error) {
    console.error('Error submitting comment:', error);
    
    let errorMessage = 'Terjadi kesalahan saat menyimpan komentar.';
    
    if (error.response?.status === 422) {
      errorMessage = 'Data komentar tidak valid. Silakan periksa input Anda.';
    } else if (error.response?.status === 413) {
      errorMessage = 'Ukuran file terlalu besar. Silakan gunakan file yang lebih kecil.';
    } else if (error.response?.status === 500) {
      errorMessage = 'Server error. Silakan coba lagi nanti.';
    }
    
    Swal.fire({
      title: 'Gagal Menyimpan Komentar',
      text: errorMessage,
      icon: 'error',
      didOpen: () => {
        const swal = document.querySelector('.swal2-container');
        if (swal) swal.style.zIndex = 200000;
      }
    });
  } finally {
    isSubmitting.value = false;
  }
}

// Function to open preview modal
function openPreviewModal(file) {
  previewUrl.value = '/storage/' + file.file_path;
  previewType.value = file.file_type.startsWith('image/') ? 'image' : 'video';
  showPreviewModal.value = true;
}

// Function to close preview modal
function closePreviewModal() {
  showPreviewModal.value = false;
  previewUrl.value = '';
  previewType.value = '';
}

function openAttachment(file) {
  openPreviewModal(file);
}

// Hapus komentar dengan SweetAlert2
async function deleteComment(commentId) {
  const result = await Swal.fire({
    title: 'Yakin ingin menghapus komentar ini?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, hapus!',
    cancelButtonText: 'Batal',
    confirmButtonColor: '#e3342f',
    cancelButtonColor: '#6c757d',
    didOpen: () => {
      const swal = document.querySelector('.swal2-container');
      if (swal) swal.style.zIndex = 200000;
    }
  });
  if (result.isConfirmed) {
    try {
      await axios.delete(`/api/maintenance-comments/${commentId}`);
      await fetchComments();
      Swal.fire({
        title: 'Terhapus!',
        text: 'Komentar berhasil dihapus.',
        icon: 'success',
        didOpen: () => {
          const swal = document.querySelector('.swal2-container');
          if (swal) swal.style.zIndex = 200000;
        }
      });
    } catch (error) {
      Swal.fire({
        title: 'Gagal',
        text: 'Gagal menghapus komentar',
        icon: 'error',
        didOpen: () => {
          const swal = document.querySelector('.swal2-container');
          if (swal) swal.style.zIndex = 200000;
        }
      });
    }
  }
}
</script>

<style scoped>
/* Custom scrollbar untuk daftar komentar */
.overflow-y-auto {
  scrollbar-width: thin;
  scrollbar-color: #CBD5E1 transparent;
}

.overflow-y-auto::-webkit-scrollbar {
  width: 6px;
}

.overflow-y-auto::-webkit-scrollbar-track {
  background: transparent;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
  background-color: #CBD5E1;
  border-radius: 3px;
}

.overflow-y-auto::-webkit-scrollbar-thumb:hover {
  background-color: #94A3B8;
}
</style> 