<template>
    <AppLayout>
        <div class="shared-docs-shell space-y-6">
            <div class="relative overflow-hidden rounded-3xl bg-slate-900 p-8 text-white shadow-[0_24px_60px_-24px_rgba(15,23,42,0.8)]">
                <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(56,189,248,0.22),transparent_45%),radial-gradient(circle_at_bottom_left,rgba(16,185,129,0.18),transparent_42%)]"></div>
                <div class="relative z-10 flex items-center justify-between gap-4">
                    <div>
                        <h1 class="text-4xl font-bold mb-2">{{ document.title }}</h1>
                        <p class="text-slate-200 text-lg">{{ document.description || 'Dokumen read-only' }}</p>
                    </div>
                    <div class="flex gap-2">
                        <Link
                            :href="route('shared-documents.index')"
                            class="inline-flex items-center px-4 py-2 bg-white/10 border border-white/20 rounded-xl text-white hover:bg-white/20"
                        >
                            <i class="fas fa-arrow-left mr-2"></i>
                            Kembali
                        </Link>
                        <a
                            :href="downloadUrl"
                            download
                            class="inline-flex items-center px-4 py-2 bg-white/10 border border-white/20 rounded-xl text-white hover:bg-white/20"
                        >
                            <i class="fas fa-download mr-2"></i>
                            Download
                        </a>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                <div class="flex flex-col md:flex-row md:items-center gap-4 mb-6">
                    <div class="w-14 h-14 rounded-xl flex items-center justify-center text-white" :class="fileTypeColor">
                        <i :class="fileTypeIcon" class="doc-type-icon text-2xl"></i>
                    </div>
                    <div>
                        <p class="text-sm text-slate-500">{{ document.filename }}</p>
                        <div class="flex items-center gap-2 mt-1">
                            <span class="inline-flex items-center px-2 py-1 rounded-md text-[11px] font-semibold uppercase tracking-wide bg-slate-100 text-slate-700">
                                {{ fileTypeLabel }}
                            </span>
                            <span :class="accessBadgeClass" class="inline-flex items-center px-2 py-1 rounded-md text-[11px] font-semibold uppercase tracking-wide">
                                {{ accessLabel }}
                            </span>
                        </div>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-gray-700">
                    <div><strong>Ukuran:</strong> {{ formatFileSize(document.file_size) }}</div>
                    <div><strong>Dibuat:</strong> {{ formatDate(document.created_at) }}</div>
                    <div><strong>Oleh:</strong> {{ document.creator?.nama_lengkap || document.creator?.name || '-' }}</div>
                    <div><strong>Folder:</strong> {{ document.folder?.name || 'Root' }}</div>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                <h3 class="text-xl font-semibold text-gray-900 mb-4 flex items-center">
                    <i :class="canUseOnlyOffice ? 'fas fa-pen-to-square' : 'fas fa-eye'" class="mr-2 text-slate-500"></i>
                    {{ canUseOnlyOffice ? 'Editor' : 'Preview' }}
                </h3>

                <div v-if="canUseOnlyOffice" class="space-y-4">
                    <div id="onlyoffice-editor" class="w-full rounded-xl border border-gray-200 bg-gray-50" style="height: 78vh;"></div>
                    <div class="text-xs text-slate-500">
                        {{ onlyOfficeModeLabel }}
                    </div>
                </div>

                <div v-else-if="isPdf" class="space-y-4">
                    <object
                        :data="previewUrl"
                        type="application/pdf"
                        class="w-full rounded-xl border border-gray-200 bg-gray-50"
                        style="height: 70vh;"
                    >
                        <div class="h-full min-h-[320px] flex flex-col items-center justify-center text-center px-6">
                            <p class="text-gray-700 font-semibold mb-2">Preview PDF tidak bisa ditampilkan di browser ini.</p>
                            <p class="text-sm text-gray-500 mb-4">Silakan buka di tab baru atau download file.</p>
                            <div class="flex flex-wrap justify-center gap-2">
                                <a
                                    :href="inlinePreviewUrl"
                                    target="_blank"
                                    rel="noopener"
                                    class="inline-flex items-center px-4 py-2 bg-slate-800 text-white rounded-lg hover:bg-slate-900"
                                >
                                    <i class="fas fa-external-link-alt mr-2"></i>
                                    Buka di Tab Baru
                                </a>
                                <a
                                    :href="downloadUrl"
                                    download
                                    class="inline-flex items-center px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700"
                                >
                                    <i class="fas fa-download mr-2"></i>
                                    Download PDF
                                </a>
                            </div>
                        </div>
                    </object>
                </div>

                <div v-else class="text-center py-12 bg-gray-50 rounded-xl border border-gray-200">
                    <div class="text-gray-600 mb-4">Preview untuk tipe file ini tidak tersedia.</div>
                    <a
                        :href="downloadUrl"
                        download
                        class="inline-flex items-center px-4 py-2 bg-slate-800 text-white rounded-lg hover:bg-slate-900"
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
import { computed, onBeforeUnmount, onMounted } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

let docEditorInstance = null

const props = defineProps({
    document: {
        type: Object,
        required: true
    },
    onlyoffice: {
        type: Object,
        default: () => ({
            enabled: false,
            config: null,
        }),
    },
})

const page = usePage()

const downloadUrl = computed(() => route('shared-documents.download', props.document.id))
const inlinePreviewUrl = computed(() => route('shared-documents.preview', props.document.id))
const isPdf = computed(() => (props.document.file_type || '').toLowerCase() === 'pdf')
const isOnlyOfficeFile = computed(() => ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'].includes((props.document.file_type || '').toLowerCase()))
const previewUrl = computed(() => `${inlinePreviewUrl.value}#toolbar=1`)
const canUseOnlyOffice = computed(() => Boolean(props.onlyoffice?.enabled && props.onlyoffice?.config && isOnlyOfficeFile.value))
const onlyOfficeModeLabel = computed(() => {
    const mode = props.onlyoffice?.config?.editorConfig?.mode || 'view'
    return mode === 'edit'
        ? 'Dokumen terbuka dalam mode edit kolaboratif.'
        : 'Dokumen terbuka dalam mode view (tanpa edit).'
})

const fileTypeLabel = computed(() => (props.document.file_type || 'file').toUpperCase())

const fileTypeIcon = computed(() => {
    const iconMap = {
        pdf: 'fas fa-file-pdf',
        doc: 'fas fa-file-word',
        docx: 'fas fa-file-word',
        xls: 'fas fa-file-excel',
        xlsx: 'fas fa-file-excel',
        ppt: 'fas fa-file-powerpoint',
        pptx: 'fas fa-file-powerpoint',
        zip: 'fas fa-file-archive',
        rar: 'fas fa-file-archive',
        txt: 'fas fa-file-lines',
        csv: 'fas fa-file-csv'
    }

    const key = (props.document.file_type || '').toLowerCase()
    return iconMap[key] || 'fas fa-file'
})

const fileTypeColor = computed(() => {
    const colorMap = {
        pdf: 'bg-red-600',
        doc: 'bg-blue-600',
        docx: 'bg-blue-600',
        xls: 'bg-emerald-600',
        xlsx: 'bg-emerald-600',
        ppt: 'bg-amber-600',
        pptx: 'bg-amber-600',
        zip: 'bg-violet-600',
        rar: 'bg-violet-600',
        txt: 'bg-slate-600',
        csv: 'bg-teal-600'
    }

    const key = (props.document.file_type || '').toLowerCase()
    return colorMap[key] || 'bg-slate-600'
})

const accessLabel = computed(() => {
    const currentUserId = page.props?.auth?.user?.id
    if (!currentUserId) {
        if (props.document.is_public) return 'Publik'
        return 'View'
    }

    if (props.document.created_by === currentUserId) return 'Owner'

    const userPermission = props.document.permissions?.find((p) => p.user_id === currentUserId)
    if (userPermission?.permission === 'admin') return 'Admin'
    if (props.document.is_public) return 'Publik'
    return 'View'
})

const accessBadgeClass = computed(() => {
    if (accessLabel.value === 'Owner') return 'bg-indigo-100 text-indigo-700'
    if (accessLabel.value === 'Admin') return 'bg-emerald-100 text-emerald-700'
    if (accessLabel.value === 'Publik') return 'bg-amber-100 text-amber-700'
    return 'bg-slate-100 text-slate-700'
})

onMounted(() => {
    if (!canUseOnlyOffice.value) {
        return
    }

    if (!window.DocsAPI || !window.DocsAPI.DocEditor) {
        console.error('OnlyOffice DocsAPI belum tersedia.')
        return
    }

    docEditorInstance = new window.DocsAPI.DocEditor('onlyoffice-editor', props.onlyoffice.config)
})

onBeforeUnmount(() => {
    if (docEditorInstance && typeof docEditorInstance.destroyEditor === 'function') {
        docEditorInstance.destroyEditor()
    }
})

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
.shared-docs-shell {
    min-height: calc(100vh - 120px);
    background:
        radial-gradient(circle at top right, rgba(148, 163, 184, 0.12), transparent 40%),
        linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
    border-radius: 20px;
    padding: 10px;
}

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

.doc-type-icon {
    transition: transform 180ms ease, filter 180ms ease;
}

.doc-type-icon:hover {
    transform: translateY(-1px) scale(1.06);
    filter: drop-shadow(0 2px 4px rgba(15, 23, 42, 0.22));
}

.animation-delay-400 {
    animation-delay: 400ms;
}

/* Glass effect enhancement */
.backdrop-blur-xl {
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
}

</style> 