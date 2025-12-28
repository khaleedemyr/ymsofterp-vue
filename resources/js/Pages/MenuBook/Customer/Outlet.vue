<template>
  <div class="min-h-screen bg-gradient-to-br from-slate-900 via-purple-900 to-slate-900 relative overflow-hidden">
    <!-- Animated Background -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
      <div class="absolute -top-40 -right-40 w-80 h-80 bg-purple-500 rounded-full mix-blend-multiply filter blur-xl opacity-20 animate-blob"></div>
      <div class="absolute -bottom-40 -left-40 w-80 h-80 bg-yellow-500 rounded-full mix-blend-multiply filter blur-xl opacity-20 animate-blob animation-delay-2000"></div>
    </div>

    <!-- Header -->
    <div class="relative z-10 pt-12 pb-8 px-4 sm:px-6 lg:px-8">
      <div class="max-w-7xl mx-auto">
        <!-- Back Button -->
        <button
          @click="goBack"
          class="mb-6 text-white/80 hover:text-yellow-400 transition-colors duration-300 flex items-center gap-2 group"
        >
          <i class="fa-solid fa-arrow-left transform group-hover:-translate-x-1 transition-transform duration-300"></i>
          <span>Back to Outlets</span>
        </button>

        <!-- Outlet Info -->
        <div class="text-center mb-12">
          <div class="inline-block mb-4">
            <div class="w-24 h-24 rounded-2xl bg-gradient-to-br from-yellow-400 to-amber-500 flex items-center justify-center shadow-2xl mx-auto mb-4">
              <i class="fa-solid fa-store text-4xl text-white"></i>
            </div>
          </div>
          <h1 class="text-4xl md:text-6xl font-bold text-transparent bg-clip-text bg-gradient-to-r from-yellow-400 via-amber-300 to-yellow-400 mb-3">
            {{ outlet.nama_outlet }}
          </h1>
          <p v-if="outlet.lokasi" class="text-gray-300 text-lg flex items-center justify-center gap-2">
            <i class="fa-solid fa-location-dot text-yellow-400"></i>
            {{ outlet.lokasi }}
          </p>
        </div>
      </div>
    </div>

    <!-- Menu Books Grid -->
    <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-20">
      <div v-if="menuBooks.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
        <div
          v-for="(book, index) in menuBooks"
          :key="book.id"
          @click="viewMenuBook(book.id)"
          class="group relative overflow-hidden rounded-3xl bg-gradient-to-br from-white/10 to-white/5 backdrop-blur-xl border border-white/20 shadow-2xl cursor-pointer transform transition-all duration-500 hover:scale-105 hover:shadow-yellow-500/50"
          :style="{ animationDelay: `${index * 100}ms` }"
        >
          <!-- Shine Effect -->
          <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/10 to-transparent -translate-x-full group-hover:translate-x-full transition-transform duration-1000"></div>
          
          <!-- Content -->
          <div class="relative p-8 h-full flex flex-col">
            <!-- Book Icon -->
            <div class="mb-6 flex justify-center">
              <div class="w-20 h-20 rounded-2xl bg-gradient-to-br from-amber-400 to-yellow-500 flex items-center justify-center shadow-lg transform group-hover:rotate-12 transition-transform duration-300">
                <i class="fa-solid fa-book-open text-3xl text-white"></i>
              </div>
            </div>

            <!-- Book Name -->
            <h3 class="text-2xl font-bold text-white mb-3 text-center group-hover:text-yellow-300 transition-colors duration-300">
              {{ book.name }}
            </h3>

            <!-- Description -->
            <p v-if="book.description" class="text-gray-400 text-sm mb-4 text-center line-clamp-2">
              {{ book.description }}
            </p>

            <!-- Pages Count -->
            <div class="mt-auto flex items-center justify-center gap-2 text-yellow-400 mb-4">
              <i class="fa-solid fa-file-lines"></i>
              <span class="text-sm font-medium">{{ book.pages_count }} Pages</span>
            </div>

            <!-- View Button -->
            <div class="flex items-center justify-center gap-2 text-yellow-400 font-semibold group-hover:gap-4 transition-all duration-300">
              <span>Explore Menu</span>
              <i class="fa-solid fa-arrow-right transform group-hover:translate-x-2 transition-transform duration-300"></i>
            </div>
          </div>

          <!-- Decorative Corner -->
          <div class="absolute top-0 right-0 w-32 h-32 bg-gradient-to-br from-amber-400/20 to-transparent rounded-bl-full opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else class="text-center py-20">
        <div class="bg-white/10 backdrop-blur-xl rounded-3xl p-12 border border-white/20 max-w-md mx-auto">
          <i class="fa-solid fa-book-open text-6xl text-gray-400 mb-4"></i>
          <h3 class="text-2xl font-bold text-white mb-2">No Menu Books Available</h3>
          <p class="text-gray-400 mb-6">This outlet doesn't have any menu books yet.</p>
          <button
            @click="goBack"
            class="px-6 py-3 bg-gradient-to-r from-yellow-400 to-amber-500 text-white rounded-xl font-semibold hover:shadow-lg transform hover:scale-105 transition-all duration-300"
          >
            Back to Outlets
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { router } from '@inertiajs/vue3';

const props = defineProps({
  outlet: Object,
  menuBooks: Array,
});

const goBack = () => {
  router.visit('/menu');
};

const viewMenuBook = (bookId) => {
  router.visit(`/menu/book/${bookId}`);
};
</script>

<style scoped>
@keyframes blob {
  0%, 100% {
    transform: translate(0, 0) scale(1);
  }
  33% {
    transform: translate(30px, -50px) scale(1.1);
  }
  66% {
    transform: translate(-20px, 20px) scale(0.9);
  }
}

.animate-blob {
  animation: blob 7s infinite;
}

.animation-delay-2000 {
  animation-delay: 2s;
}
</style>

