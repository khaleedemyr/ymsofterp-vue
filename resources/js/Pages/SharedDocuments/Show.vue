<template>
    <AppLayout>
        <div class="space-y-6">
            <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-indigo-600 via-purple-600 to-pink-700 p-8 text-white shadow-2xl">
                <div class="absolute inset-0 bg-black/10"></div>
                <div class="relative z-10 flex items-center justify-between">
                    <div>
                        <h1 class="text-4xl font-bold mb-2">{{ document.title }}</h1>
                        <p class="text-blue-100 text-lg">{{ document.description || 'Dokumen read-only' }}</p>
                    </div>
                    <div class="flex gap-2">
                        <Link
                            :href="route('shared-documents.index')"
                            class="inline-flex items-center px-4 py-2 bg-white/20 rounded-xl text-white hover:bg-white/30"
                        >
                            <i class="fas fa-arrow-left mr-2"></i>
                            Kembali
                        </Link>
                        <a
                            :href="downloadUrl"
                            download
                            class="inline-flex items-center px-4 py-2 bg-white/20 rounded-xl text-white hover:bg-white/30"
                        >
                            <i class="fas fa-download mr-2"></i>
                            Download
                        </a>
                    </div>
                </div>
            </div>

            <div class="bg-white/80 backdrop-blur-xl rounded-2xl shadow-xl border border-white/20 p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-gray-700">
                    <div><strong>Nama File:</strong> {{ document.filename }}</div>
                    <div><strong>Tipe:</strong> {{ document.file_type?.toUpperCase() }}</div>
                    <div><strong>Ukuran:</strong> {{ formatFileSize(document.file_size) }}</div>
                    <div><strong>Dibuat:</strong> {{ formatDate(document.created_at) }}</div>
                    <div><strong>Oleh:</strong> {{ document.creator?.nama_lengkap || document.creator?.name || '-' }}</div>
                    <div><strong>Folder:</strong> {{ document.folder?.name || 'Root' }}</div>
                </div>
            </div>

            <div class="bg-white/80 backdrop-blur-xl rounded-2xl shadow-xl border border-white/20 p-6">
                <h3 class="text-xl font-semibold text-gray-900 mb-4 flex items-center">
                    <i class="fas fa-eye mr-2 text-blue-500"></i>
                    Preview
                </h3>

                <iframe
                    v-if="isPdf"
                    :src="previewUrl"
                    class="w-full rounded-xl border border-gray-200"
                    style="height: 70vh;"
                ></iframe>

                <div v-else class="text-center py-12 bg-gray-50 rounded-xl border border-gray-200">
                    <div class="text-gray-600 mb-4">Preview untuk tipe file ini tidak tersedia.</div>
                    <a
                        :href="downloadUrl"
                        download
                        class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
                    >
                        <i class="fas fa-download mr-2"></i>
                        Download Dokumen
                    </a>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    document: {
        type: Object,
        required: true
    }
})

const downloadUrl = computed(() => `${window.location.origin}/shared-documents/${props.document.id}/download`)
const isPdf = computed(() => (props.document.file_type || '').toLowerCase() === 'pdf')
const previewUrl = computed(() => `${downloadUrl.value}#toolbar=1`)

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('id-ID', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    })
}

const formatFileSize = (bytes) => {
    if (!bytes) return '0 Bytes'
    const k = 1024
    const sizes = ['Bytes', 'KB', 'MB', 'GB']
    const i = Math.floor(Math.log(bytes) / Math.log(k))
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}
</script>

<style scoped>
@keyframes fade-in-up {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes fade-in {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

.animate-fade-in-up {
    animation: fade-in-up 0.6s ease-out forwards;
}

.animate-fade-in {
    animation: fade-in 0.3s ease-out forwards;
}

.animation-delay-200 {
    animation-delay: 200ms;
}

.animation-delay-400 {
    animation-delay: 400ms;
}

/* Glass effect enhancement */
.backdrop-blur-xl {
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
}

.backdrop-blur-sm {
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
}

/* Smooth transitions */
* {
    transition-property: all;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
}
</style> 