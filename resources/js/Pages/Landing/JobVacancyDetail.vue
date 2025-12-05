<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-60 animate-fade-in" @click.self="$emit('close')">
    <div class="bg-white rounded-3xl shadow-2xl max-w-2xl w-full overflow-hidden relative animate-slide-up">
      <button @click="$emit('close')" class="absolute top-4 right-4 text-gray-400 hover:text-red-500 text-3xl font-bold z-10">&times;</button>
      <div class="relative h-56 bg-gradient-to-t from-blue-900/80 to-blue-200">
        <img v-if="job.banner" :src="bannerUrl(job.banner)" class="w-full h-full object-cover object-center animate-zoom-in" />
        <div class="absolute inset-0 bg-gradient-to-t from-blue-900/80 to-transparent"></div>
        <div class="absolute bottom-4 left-6 bg-white/80 rounded-xl px-4 py-1 text-xs font-bold text-blue-900 shadow animate-fade-in-right">
          Tutup: {{ formatDate(job.closing_date) }}
        </div>
      </div>
      <div class="p-8 pt-4 flex flex-col gap-3">
        <h2 class="text-3xl font-extrabold text-blue-800 mb-1 drop-shadow animate-fade-in-up">{{ job.position }}</h2>
        <div class="flex items-center gap-2 text-blue-600 font-semibold animate-fade-in-up">
          <i class="fa fa-map-marker-alt"></i> {{ job.location }}
        </div>
        <div class="mt-2 animate-fade-in-up">
          <h3 class="font-bold text-blue-700 mb-1">Deskripsi Pekerjaan</h3>
          <div class="text-gray-700 whitespace-pre-line" v-html="job.description"></div>
        </div>
        <div v-if="job.requirements" class="mt-2 animate-fade-in-up">
          <h3 class="font-bold text-blue-700 mb-1">Kualifikasi / Persyaratan</h3>
          <div class="text-gray-700 whitespace-pre-line" v-html="job.requirements"></div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({ job: Object });
function bannerUrl(path) {
  return path ? `/storage/${path}` : '/images/job-default-banner.jpg';
}
function formatDate(date) {
  if (!date) return '';
  return new Date(date).toLocaleDateString('id-ID', { year: 'numeric', month: 'long', day: 'numeric' });
}
</script>

<style scoped>
@keyframes fade-in {
  from { opacity: 0; }
  to { opacity: 1; }
}
@keyframes slide-up {
  from { opacity: 0; transform: translateY(60px) scale(0.98); }
  to { opacity: 1; transform: none; }
}
@keyframes fade-in-up {
  from { opacity: 0; transform: translateY(30px); }
  to { opacity: 1; transform: none; }
}
@keyframes fade-in-right {
  from { opacity: 0; transform: translateX(-30px); }
  to { opacity: 1; transform: none; }
}
@keyframes zoom-in {
  from { transform: scale(1.08); opacity: 0.7; }
  to { transform: scale(1); opacity: 1; }
}
.animate-fade-in { animation: fade-in 0.4s; }
.animate-slide-up { animation: slide-up 0.5s cubic-bezier(.25,.8,.25,1); }
.animate-fade-in-up { animation: fade-in-up 0.7s; }
.animate-fade-in-right { animation: fade-in-right 0.7s; }
.animate-zoom-in { animation: zoom-in 1s; }
</style> 