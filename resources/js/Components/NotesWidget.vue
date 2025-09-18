<template>
    <div class="backdrop-blur-sm rounded-lg shadow-lg border p-4 h-full flex flex-col" 
         :class="isNight ? 'bg-slate-800/95 border-slate-600/50' : 'bg-white/95 border-slate-200'">
        <!-- Header -->
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold" :class="isNight ? 'text-white' : 'text-slate-700'">Notes</h3>
            <button @click="showAddNoteModal = true" 
                    class="bg-blue-500 text-white px-3 py-1 rounded-md text-sm hover:bg-blue-600 transition-colors">
                + Add Note
            </button>
        </div>

        <!-- Notes List -->
        <div class="flex-1 overflow-y-auto space-y-3 max-h-80">
            <div v-for="note in notes" :key="note.id" 
                 @click="openNoteDetail(note)"
                 class="rounded-lg p-3 border transition-colors cursor-pointer"
                 :class="isNight ? 'bg-slate-700/50 border-slate-600/50 hover:bg-slate-600/50' : 'bg-slate-50 border-slate-200 hover:bg-slate-100'">
                <div class="flex items-start justify-between mb-2">
                    <h4 class="font-medium text-sm" :class="isNight ? 'text-slate-200' : 'text-slate-800'">{{ note.title }}</h4>
                    <button @click.stop="deleteNote(note.id)" 
                            class="text-red-500 hover:text-red-700 text-xs">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                
                <p class="text-xs mb-2 line-clamp-1" :class="isNight ? 'text-slate-300' : 'text-slate-600'">{{ note.content }}</p>
                
                <!-- Attachments -->
                <div v-if="note.attachments && note.attachments.length > 0" class="flex flex-wrap gap-1 mb-2">
                    <div v-for="attachment in note.attachments" :key="attachment.id" 
                         class="flex items-center gap-1 px-2 py-1 rounded text-xs"
                         :class="isNight ? 'bg-blue-600/20 text-blue-300' : 'bg-blue-100 text-blue-700'">
                        <!-- Image thumbnail with lightbox -->
                        <div v-if="attachment.mime_type && attachment.mime_type.startsWith('image/')" 
                             class="flex items-center gap-1">
                            <img :src="`/storage/${attachment.file_path}`" 
                                 @click="openLightbox(`/storage/${attachment.file_path}`)"
                                 class="w-6 h-6 object-cover rounded cursor-pointer hover:opacity-80 transition-opacity"
                                 alt="Attachment thumbnail">
                        </div>
                        <!-- Non-image files -->
                        <div v-else class="flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                            </svg>
                        </div>
                    </div>
                </div>
                
                <div class="text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                    {{ formatDate(note.created_at) }}
                </div>
            </div>
            
            <div v-if="notes.length === 0" class="text-center text-sm py-8" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                No notes yet. Click "Add Note" to create your first note.
            </div>
        </div>
    </div>

    <!-- Add Note Modal -->
    <div v-if="showAddNoteModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto relative" :class="{ 'pointer-events-none': savingNote }">
            <!-- Loading Overlay -->
            <div v-if="savingNote" class="absolute inset-0 bg-white bg-opacity-75 flex items-center justify-center z-10 rounded-lg">
                <div class="flex flex-col items-center gap-2">
                    <svg class="animate-spin h-8 w-8 text-blue-500" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span class="text-sm text-slate-600">Saving note...</span>
                </div>
            </div>
            
            <h3 class="text-lg font-semibold mb-4">Add New Note</h3>
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Title</label>
                    <input v-model="newNote.title" type="text" placeholder="Enter note title"
                           class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Content</label>
                    <textarea v-model="newNote.content" rows="4" placeholder="Enter note content"
                              class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                </div>
                
                <!-- File Upload Section -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Attachments</label>
                    
                    <!-- Upload Methods -->
                    <div class="flex gap-2 mb-3">
                        <label class="flex items-center gap-2 bg-blue-50 text-blue-700 px-3 py-2 rounded-md cursor-pointer hover:bg-blue-100 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                            </svg>
                            Upload File
                            <input type="file" @change="handleFileUpload" multiple accept="image/*,.pdf,.doc,.docx,.txt" class="hidden">
                        </label>
                        
                        <button @click="captureFromCamera" 
                                class="flex items-center gap-2 bg-green-50 text-green-700 px-3 py-2 rounded-md hover:bg-green-100 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                            Camera
                        </button>
                    </div>
                    
                    <!-- Selected Files -->
                    <div v-if="selectedFiles.length > 0" class="space-y-2">
                        <div v-for="(file, index) in selectedFiles" :key="index" 
                             class="flex items-center justify-between bg-slate-50 p-2 rounded">
                            <div class="flex items-center gap-2">
                                <!-- Thumbnail for images -->
                                <div v-if="file.type.startsWith('image/')" class="relative">
                                    <img :src="getImageSrc(file)" 
                                         @click="openLightbox(getImageSrc(file))"
                                         class="w-8 h-8 object-cover rounded cursor-pointer hover:opacity-80 transition-opacity"
                                         alt="Thumbnail">
                                </div>
                                <!-- File icon for non-images -->
                                <svg v-else class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                <span class="text-sm text-slate-700">{{ file.name }}</span>
                                <span class="text-xs text-slate-500">({{ formatFileSize(file.size) }})</span>
                            </div>
                            <button @click="removeFile(index)" class="text-red-500 hover:text-red-700">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="flex justify-end gap-2 mt-6">
                <button @click="showAddNoteModal = false" 
                        :disabled="savingNote"
                        class="px-4 py-2 text-slate-600 border border-slate-300 rounded-md hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed">
                    Cancel
                </button>
                <button @click="saveNote" 
                        :disabled="savingNote"
                        class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2">
                    <svg v-if="savingNote" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    {{ savingNote ? 'Saving...' : 'Save Note' }}
                </button>
            </div>
        </div>
    </div>

    <!-- Camera Modal -->
    <div v-if="showCameraModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-2xl mx-4">
            <h3 class="text-lg font-semibold mb-4">Capture from Camera</h3>
            
            <div class="space-y-4">
                <div class="relative">
                    <video ref="videoRef" autoplay class="w-full h-64 bg-slate-100 rounded-md"></video>
                    <canvas ref="canvasRef" class="hidden"></canvas>
                </div>
                
                <div class="flex justify-center gap-2">
                    <button @click="startCamera" 
                            class="px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600">
                        Start Camera
                    </button>
                    <button @click="switchCamera" 
                            class="px-4 py-2 bg-purple-500 text-white rounded-md hover:bg-purple-600">
                        Switch Camera
                    </button>
                    <button @click="capturePhoto" 
                            class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600">
                        Capture
                    </button>
                    <button @click="closeCamera" 
                            class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Lightbox Modal -->
    <div v-if="showLightbox" class="fixed inset-0 bg-black bg-opacity-90 flex items-center justify-center z-50" @click="closeLightbox">
        <div class="relative max-w-4xl max-h-full p-4">
            <button @click="closeLightbox" 
                    class="absolute top-2 right-2 text-white text-2xl hover:text-gray-300 z-10">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
            <img :src="lightboxImage" 
                 class="max-w-full max-h-full object-contain rounded-lg"
                 @click.stop
                 alt="Full size image">
        </div>
    </div>

    <!-- Note Detail Modal -->
    <div v-if="showNoteDetailModal && selectedNote" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click="closeNoteDetail">
        <div class="bg-white rounded-lg p-6 w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto" @click.stop>
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-slate-800">{{ selectedNote.title }}</h3>
                <button @click="closeNoteDetail" 
                        class="text-slate-500 hover:text-slate-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <div class="space-y-4">
                <!-- Content -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Content</label>
                    <div class="bg-slate-50 rounded-lg p-4 text-sm text-slate-800 whitespace-pre-wrap">
                        {{ selectedNote.content || 'No content' }}
                    </div>
                </div>
                
                <!-- Attachments -->
                <div v-if="selectedNote.attachments && selectedNote.attachments.length > 0">
                    <label class="block text-sm font-medium text-slate-700 mb-2">Attachments</label>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                        <div v-for="attachment in selectedNote.attachments" :key="attachment.id" 
                             class="bg-slate-50 rounded-lg p-3 border border-slate-200">
                            <!-- Image attachments -->
                            <div v-if="attachment.mime_type && attachment.mime_type.startsWith('image/')" 
                                 class="text-center">
                                <img :src="`/storage/${attachment.file_path}`" 
                                     @click="openLightbox(`/storage/${attachment.file_path}`)"
                                     class="w-full h-24 object-cover rounded cursor-pointer hover:opacity-80 transition-opacity mb-2"
                                     alt="Attachment">
                                <p class="text-xs text-slate-600 truncate">{{ attachment.original_name }}</p>
                            </div>
                            <!-- Non-image files -->
                            <div v-else class="text-center">
                                <div class="w-full h-24 bg-slate-200 rounded flex items-center justify-center mb-2">
                                    <svg class="w-8 h-8 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                </div>
                                <p class="text-xs text-slate-600 truncate">{{ attachment.original_name }}</p>
                                <p class="text-xs text-slate-500">{{ formatFileSize(attachment.file_size) }}</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Created Date -->
                <div class="text-xs text-slate-500 pt-2 border-t border-slate-200">
                    Created: {{ formatDate(selectedNote.created_at) }}
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';

// Props
const props = defineProps({
    isNight: {
        type: Boolean,
        default: false
    }
});

const notes = ref([]);
const showAddNoteModal = ref(false);
const showCameraModal = ref(false);
const selectedFiles = ref([]);
const videoRef = ref(null);
const canvasRef = ref(null);
const stream = ref(null);
const currentCamera = ref('back');
const capturedImages = ref([]);
const showLightbox = ref(false);
const lightboxImage = ref(null);
const savingNote = ref(false);
const showNoteDetailModal = ref(false);
const selectedNote = ref(null);

const newNote = ref({
    title: '',
    content: '',
    attachments: []
});

const fetchNotes = async () => {
    try {
        const response = await axios.get('/api/notes');
        notes.value = response.data;
    } catch (error) {
        console.error('Error fetching notes:', error);
    }
};

const saveNote = async () => {
    if (!newNote.value.title.trim()) {
        alert('Title is required');
        return;
    }
    
    savingNote.value = true;
    
    try {
        const formData = new FormData();
        formData.append('title', newNote.value.title);
        formData.append('content', newNote.value.content);
        
        // Add files to form data
        selectedFiles.value.forEach((file, index) => {
            formData.append(`attachments[${index}]`, file);
        });
        
        const response = await axios.post('/api/notes', formData, {
            headers: {
                'Content-Type': 'multipart/form-data'
            }
        });
        
        notes.value.unshift(response.data);
        
        // Reset form
        newNote.value = {
            title: '',
            content: '',
            attachments: []
        };
        selectedFiles.value = [];
        showAddNoteModal.value = false;
    } catch (error) {
        console.error('Error saving note:', error);
        alert('Failed to save note');
    } finally {
        savingNote.value = false;
    }
};

const deleteNote = async (id) => {
    const result = await Swal.fire({
        title: 'Hapus Notes?',
        text: 'Apakah Anda yakin ingin menghapus notes ini?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal',
        reverseButtons: true
    });
    
    if (result.isConfirmed) {
        try {
            await axios.delete(`/api/notes/${id}`);
            notes.value = notes.value.filter(note => note.id !== id);
            
            // Show success message
            Swal.fire({
                title: 'Berhasil!',
                text: 'Notes berhasil dihapus.',
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
            });
        } catch (error) {
            console.error('Error deleting note:', error);
            Swal.fire({
                title: 'Error!',
                text: 'Gagal menghapus notes.',
                icon: 'error',
                confirmButtonText: 'OK'
            });
        }
    }
};

const handleFileUpload = (event) => {
    const files = Array.from(event.target.files);
    const filesWithThumbnails = [];
    
    files.forEach(file => {
        if (file.type.startsWith('image/')) {
            // Create thumbnail for images
            const reader = new FileReader();
            reader.onload = (e) => {
                file.thumbnail = e.target.result;
                filesWithThumbnails.push(file);
                
                // Check if all files are processed
                if (filesWithThumbnails.length === files.filter(f => f.type.startsWith('image/')).length) {
                    // Add all files (with and without thumbnails)
                    const allFiles = files.map(f => {
                        if (f.type.startsWith('image/') && !f.thumbnail) {
                            return filesWithThumbnails.find(tf => tf.name === f.name) || f;
                        }
                        return f;
                    });
                    selectedFiles.value.push(...allFiles);
                }
            };
            reader.onerror = (error) => {
                console.error('Error creating thumbnail:', error);
                filesWithThumbnails.push(file);
                
                // Check if all files are processed
                if (filesWithThumbnails.length === files.filter(f => f.type.startsWith('image/')).length) {
                    const allFiles = files.map(f => {
                        if (f.type.startsWith('image/') && !f.thumbnail) {
                            return filesWithThumbnails.find(tf => tf.name === f.name) || f;
                        }
                        return f;
                    });
                    selectedFiles.value.push(...allFiles);
                }
            };
            reader.readAsDataURL(file);
        } else {
            // Non-image files can be added immediately
            filesWithThumbnails.push(file);
        }
    });
    
    // If no image files, add all files immediately
    if (files.filter(f => f.type.startsWith('image/')).length === 0) {
        selectedFiles.value.push(...files);
    }
};

const removeFile = (index) => {
    selectedFiles.value.splice(index, 1);
};

const formatFileSize = (bytes) => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

const formatDate = (dateString) => {
    const date = new Date(dateString);
    return date.toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

const captureFromCamera = () => {
    showCameraModal.value = true;
};

const startCamera = async () => {
    try {
        // Stop existing stream if any
        if (stream.value) {
            stream.value.getTracks().forEach(track => track.stop());
        }
        
        const constraints = {
            video: { 
                width: { ideal: 1280 },
                height: { ideal: 720 },
                facingMode: currentCamera.value === 'front' ? 'user' : 'environment'
            } 
        };
        
        stream.value = await navigator.mediaDevices.getUserMedia(constraints);
        videoRef.value.srcObject = stream.value;
    } catch (error) {
        console.error('Error accessing camera:', error);
        alert('Unable to access camera');
    }
};

const switchCamera = async () => {
    currentCamera.value = currentCamera.value === 'front' ? 'back' : 'front';
    await startCamera();
};

const capturePhoto = () => {
    const video = videoRef.value;
    const canvas = canvasRef.value;
    const context = canvas.getContext('2d');
    
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    context.drawImage(video, 0, 0);
    
    canvas.toBlob((blob) => {
        const file = new File([blob], `camera-capture-${Date.now()}.jpg`, { type: 'image/jpeg' });
        
        // Create thumbnail for captured image
        const reader = new FileReader();
        reader.onload = (e) => {
            file.thumbnail = e.target.result;
            // Add file to selectedFiles after thumbnail is created
            selectedFiles.value.push(file);
        };
        reader.onerror = (error) => {
            console.error('Error creating thumbnail:', error);
            // Still add file even if thumbnail creation fails
            selectedFiles.value.push(file);
        };
        reader.readAsDataURL(blob);
        
        closeCamera();
    }, 'image/jpeg', 0.8);
};

const closeCamera = () => {
    if (stream.value) {
        stream.value.getTracks().forEach(track => track.stop());
        stream.value = null;
    }
    showCameraModal.value = false;
};

const openLightbox = (imageSrc) => {
    lightboxImage.value = imageSrc;
    showLightbox.value = true;
};

const closeLightbox = () => {
    showLightbox.value = false;
    lightboxImage.value = null;
};

const openNoteDetail = (note) => {
    selectedNote.value = note;
    showNoteDetailModal.value = true;
};

const closeNoteDetail = () => {
    showNoteDetailModal.value = false;
    selectedNote.value = null;
};

const getImageSrc = (file) => {
    if (file.thumbnail) {
        return file.thumbnail;
    }
    
    try {
        if (window.URL && window.URL.createObjectURL) {
            return window.URL.createObjectURL(file);
        }
    } catch (error) {
        console.error('Error creating object URL:', error);
    }
    
    return '';
};

onMounted(() => {
    fetchNotes();
    
    // Add keyboard support for lightbox
    const handleKeydown = (event) => {
        if (showLightbox.value && event.key === 'Escape') {
            closeLightbox();
        }
    };
    
    document.addEventListener('keydown', handleKeydown);
    
    // Cleanup on unmount
    return () => {
        document.removeEventListener('keydown', handleKeydown);
    };
});
</script>

<style scoped>
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
