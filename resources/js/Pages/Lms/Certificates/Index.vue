<template>
  <AppLayout>
    <div class="min-h-screen bg-gradient-to-br from-slate-900 via-purple-900 to-slate-900 p-6">
      <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl p-6">
        <div class="flex items-center justify-between mb-4">
          <h1 class="text-2xl font-bold text-white">Certificates</h1>
        </div>

        <div v-if="rows.length === 0" class="text-white/70">Belum ada sertifikat.</div>

        <div v-else class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead>
              <tr class="text-left text-white/80">
                <th class="py-2 pr-4">No</th>
                <th class="py-2 pr-4">Nomor Sertifikat</th>
                <th class="py-2 pr-4">Course</th>
                <th class="py-2 pr-4">User</th>
                <th class="py-2 pr-4">Terbit</th>
                <th class="py-2 pr-4">Status</th>
                <th class="py-2 pr-4">Aksi</th>
              </tr>
            </thead>
            <tbody class="text-white/90">
              <tr v-for="(item, idx) in rows" :key="item.id" class="border-t border-white/10">
                <td class="py-2 pr-4">{{ startIndex + idx }}</td>
                <td class="py-2 pr-4">{{ item.certificate_number }}</td>
                <td class="py-2 pr-4">{{ item.course?.title || '-' }}</td>
                <td class="py-2 pr-4">{{ item.user?.nama_lengkap || '-' }}</td>
                <td class="py-2 pr-4">{{ formatDate(item.issued_at) }}</td>
                <td class="py-2 pr-4">{{ item.status }}</td>
                <td class="py-2 pr-4">
                  <Link :href="route('lms.certificates.download', item.id)"
                        class="px-3 py-1 bg-blue-500/20 border border-blue-500/30 rounded-lg text-blue-200 hover:bg-blue-500/30 transition-all text-xs">
                    <i class="fas fa-download mr-1"></i>
                    Download
                  </Link>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="links.length" class="flex flex-wrap gap-2 mt-4">
          <Link
            v-for="l in links"
            :key="l.url + (l.label || '')"
            :href="l.url || '#'"
            :class="['px-3 py-1 rounded border border-white/20 text-white/80', { 'bg-white/20': l.active, 'pointer-events-none opacity-50': !l.url }]"
            v-html="l.label"
          />
        </div>
      </div>
    </div>
  </AppLayout>
  </template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps({
  certificates: { type: Object, default: () => ({ data: [] }) }
})

const rows = computed(() => props.certificates?.data || props.certificates || [])
const links = computed(() => props.certificates?.links || [])
const startIndex = computed(() => (props.certificates?.from ?? 1))

const formatDate = (v) => {
  if (!v) return '-'
  const d = new Date(v)
  if (isNaN(d)) return v
  const dd = String(d.getDate()).padStart(2, '0')
  const mm = String(d.getMonth() + 1).padStart(2, '0')
  const yy = d.getFullYear()
  return `${dd}/${mm}/${yy}`
}
</script>


