<template>
  <AppLayout>
    <div class="min-h-screen bg-gradient-to-br from-slate-900 via-purple-900 to-slate-900 p-6">
      <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl p-6">
        <div class="flex items-center justify-between mb-6">
          <h1 class="text-2xl font-bold text-white">Template Sertifikat</h1>
          <Link :href="route('lms.certificate-templates.create')"
                class="px-4 py-2 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-xl font-semibold hover:from-blue-600 hover:to-blue-700 transition-all">
            <i class="fas fa-plus mr-2"></i>
            Buat Template
          </Link>
        </div>

        <div v-if="templates.data.length === 0" class="text-white/70 text-center py-8">
          <i class="fas fa-certificate text-6xl text-white/20 mb-4"></i>
          <p>Belum ada template sertifikat.</p>
          <Link :href="route('lms.certificate-templates.create')"
                class="inline-block mt-4 px-4 py-2 bg-blue-500/20 border border-blue-500/30 rounded-lg text-blue-200 hover:bg-blue-500/30 transition-all">
            Buat Template Pertama
          </Link>
        </div>

        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <div v-for="template in templates.data" :key="template.id" 
               class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-xl overflow-hidden hover:border-white/30 transition-all">
            
            <!-- Template Preview -->
            <div class="relative h-40 bg-gradient-to-br from-blue-500/20 to-purple-500/20">
              <img v-if="template.background_image_url" 
                   :src="template.background_image_url" 
                   :alt="template.name"
                   class="w-full h-full object-cover opacity-80">
              <div class="absolute inset-0 bg-black/20 flex items-center justify-center">
                <i class="fas fa-certificate text-4xl text-white/80"></i>
              </div>
              
              <!-- Status Badge -->
              <div class="absolute top-2 right-2">
                <span :class="template.status === 'active' ? 'bg-green-500/20 text-green-200 border-green-500/30' : 'bg-gray-500/20 text-gray-200 border-gray-500/30'"
                      class="px-2 py-1 rounded-full text-xs border">
                  {{ template.status === 'active' ? 'Aktif' : 'Nonaktif' }}
                </span>
              </div>
            </div>

            <!-- Template Info -->
            <div class="p-4">
              <h3 class="text-lg font-semibold text-white mb-2">{{ template.name }}</h3>
              <p class="text-white/70 text-sm mb-4 line-clamp-2">{{ template.description || 'Tidak ada deskripsi' }}</p>
              
              <div class="flex items-center justify-between text-xs text-white/60 mb-4">
                <span>Dibuat oleh {{ template.creator?.nama_lengkap || 'Unknown' }}</span>
                <span>{{ formatDate(template.created_at) }}</span>
              </div>

              <!-- Actions -->
              <div class="flex items-center space-x-2">
                <Link :href="route('lms.certificate-templates.preview', template.id)"
                      class="flex-1 px-3 py-2 bg-blue-500/20 border border-blue-500/30 rounded-lg text-blue-200 hover:bg-blue-500/30 transition-all text-center text-xs">
                  <i class="fas fa-eye mr-1"></i>
                  Preview
                </Link>
                <Link :href="route('lms.certificate-templates.edit', template.id)"
                      class="flex-1 px-3 py-2 bg-yellow-500/20 border border-yellow-500/30 rounded-lg text-yellow-200 hover:bg-yellow-500/30 transition-all text-center text-xs">
                  <i class="fas fa-edit mr-1"></i>
                  Edit
                </Link>
                <button @click="deleteTemplate(template)"
                        class="px-3 py-2 bg-red-500/20 border border-red-500/30 rounded-lg text-red-200 hover:bg-red-500/30 transition-all text-xs">
                  <i class="fas fa-trash"></i>
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Pagination -->
        <div v-if="templates.links && templates.links.length > 3" class="flex flex-wrap gap-2 mt-6">
          <Link v-for="link in templates.links" :key="link.url + (link.label || '')"
                :href="link.url || '#'"
                :class="['px-3 py-1 rounded border border-white/20 text-white/80 text-sm', { 'bg-white/20': link.active, 'pointer-events-none opacity-50': !link.url }]"
                v-html="link.label" />
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import Swal from 'sweetalert2'

const props = defineProps({
  templates: { type: Object, default: () => ({ data: [] }) }
})

const formatDate = (dateStr) => {
  if (!dateStr) return '-'
  const date = new Date(dateStr)
  return date.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
}

const deleteTemplate = async (template) => {
  const result = await Swal.fire({
    title: 'Hapus Template?',
    text: `Template "${template.name}" akan dihapus permanen.`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#EF4444',
    cancelButtonColor: '#6B7280',
    confirmButtonText: 'Hapus',
    cancelButtonText: 'Batal'
  })

  if (result.isConfirmed) {
    router.delete(route('lms.certificate-templates.destroy', template.id), {
      onSuccess: () => {
        Swal.fire({ icon: 'success', title: 'Terhapus', text: 'Template berhasil dihapus.' })
      },
      onError: (errors) => {
        Swal.fire({ icon: 'error', title: 'Gagal', text: Object.values(errors)[0] || 'Gagal menghapus template.' })
      }
    })
  }
}
</script>
