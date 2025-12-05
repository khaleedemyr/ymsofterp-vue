<template>
  <div class="min-h-screen bg-gradient-to-br from-blue-50 to-white py-12 px-4">
    <div class="max-w-5xl mx-auto">
      <h1 class="text-4xl font-extrabold text-blue-900 mb-8 text-center drop-shadow-lg animate-fade-in-down">
        <i class="fa-solid fa-briefcase"></i> Karir & Lowongan Pekerjaan
      </h1>
      <div v-if="loading" class="text-center py-20">
        <i class="fa fa-spinner fa-spin text-3xl text-blue-600"></i>
      </div>
      <transition-group name="list" tag="div" class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <div v-for="job in jobs" :key="job.id" class="relative group rounded-3xl shadow-2xl bg-white overflow-hidden transform transition-all duration-300 hover:scale-105 hover:shadow-3xl animate-fade-in-up">
          <div class="relative h-48 overflow-hidden">
            <img :src="bannerUrl(job.banner)" alt="Banner" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700" />
            <div class="absolute inset-0 bg-gradient-to-t from-blue-900/70 to-transparent"></div>
            <div class="absolute top-4 left-4 bg-white/80 rounded-xl px-4 py-1 text-xs font-bold text-blue-900 shadow animate-fade-in-right">
              Tutup: {{ formatDate(job.closing_date) }}
            </div>
          </div>
          <div class="p-6 flex flex-col gap-2">
            <h2 class="text-2xl font-bold text-blue-800 mb-1 drop-shadow-sm animate-fade-in-up">{{ job.position }}</h2>
            <div class="flex items-center gap-2 text-blue-600 font-semibold animate-fade-in-up">
              <i class="fa fa-map-marker-alt"></i> {{ job.location }}
            </div>
            <div class="text-gray-600 line-clamp-3 animate-fade-in-up" v-html="job.description"></div>
            <button @click="openDetail(job)" class="mt-4 self-end bg-gradient-to-r from-blue-600 to-blue-400 text-white px-6 py-2 rounded-xl shadow-lg font-bold text-lg hover:scale-105 hover:shadow-2xl transition-all duration-300 animate-bounce-in">
              Lihat Detail
            </button>
          </div>
          <div class="absolute -z-10 blur-2xl opacity-40 left-1/2 top-1/2 w-72 h-72 bg-blue-200 rounded-full transform -translate-x-1/2 -translate-y-1/2 scale-110 group-hover:scale-125 transition-all duration-700"></div>
        </div>
      </transition-group>
      <JobVacancyDetail v-if="showDetail" :job="selectedJob" @close="showDetail = false" />
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import JobVacancyDetail from './JobVacancyDetail.vue';

const jobs = ref([]);
const loading = ref(true);
const showDetail = ref(false);
const selectedJob = ref(null);

function bannerUrl(path) {
  return path ? `/storage/${path}` : '/images/job-default-banner.jpg';
}
function formatDate(date) {
  if (!date) return '';
  return new Date(date).toLocaleDateString('id-ID', { year: 'numeric', month: 'long', day: 'numeric' });
}
function openDetail(job) {
  selectedJob.value = job;
  showDetail.value = true;
}
onMounted(async () => {
  loading.value = true;
  const res = await axios.get('/api/job-vacancies');
  jobs.value = res.data;
  loading.value = false;
});
</script>

<style scoped>
@keyframes fade-in-up {
  from { opacity: 0; transform: translateY(40px); }
  to { opacity: 1; transform: none; }
}
@keyframes fade-in-down {
  from { opacity: 0; transform: translateY(-40px); }
  to { opacity: 1; transform: none; }
}
@keyframes fade-in-right {
  from { opacity: 0; transform: translateX(-40px); }
  to { opacity: 1; transform: none; }
}
@keyframes bounce-in {
  0% { transform: scale(0.8); opacity: 0; }
  60% { transform: scale(1.1); opacity: 1; }
  100% { transform: scale(1); }
}
.animate-fade-in-up { animation: fade-in-up 0.7s; }
.animate-fade-in-down { animation: fade-in-down 0.7s; }
.animate-fade-in-right { animation: fade-in-right 0.7s; }
.animate-bounce-in { animation: bounce-in 0.7s; }
.list-enter-active, .list-leave-active { transition: all 0.7s cubic-bezier(.25,.8,.25,1); }
.list-enter-from { opacity: 0; transform: translateY(40px) scale(0.95); }
.list-leave-to { opacity: 0; transform: translateY(40px) scale(0.95); }
</style> 