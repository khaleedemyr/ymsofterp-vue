<template>
  <div class="min-h-screen bg-gradient-to-br from-black via-gray-900 to-black relative overflow-hidden">
    <!-- Animated Background Elements -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
      <div class="absolute -top-40 -right-40 w-80 h-80 bg-yellow-500 rounded-full mix-blend-multiply filter blur-xl opacity-15 animate-blob"></div>
      <div class="absolute -bottom-40 -left-40 w-80 h-80 bg-yellow-400 rounded-full mix-blend-multiply filter blur-xl opacity-10 animate-blob animation-delay-2000"></div>
      <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-80 h-80 bg-yellow-600 rounded-full mix-blend-multiply filter blur-xl opacity-8 animate-blob animation-delay-4000"></div>
    </div>

    <!-- Header -->
    <div class="relative z-10 pt-20 pb-16 px-4 sm:px-6 lg:px-8">
      <div class="max-w-7xl mx-auto text-center">
        <div class="inline-block mb-6">
          <h1 class="text-6xl md:text-8xl font-bold text-transparent bg-clip-text bg-gradient-to-r from-yellow-400 via-amber-300 to-yellow-400 mb-4 animate-fade-in">
            <i class="fa-solid fa-utensils mr-4"></i>
            Our Menu
          </h1>
          <div class="h-1 w-32 bg-gradient-to-r from-transparent via-yellow-400 to-transparent mx-auto"></div>
        </div>
        <p class="text-xl md:text-2xl text-gray-300 font-light max-w-2xl mx-auto animate-fade-in-delay">
          Select an outlet to explore our exquisite menu collection
        </p>
      </div>
    </div>

    <!-- Outlets Grid -->
    <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-20">
      <div v-if="outlets.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
        <div
          v-for="(outlet, index) in outlets"
          :key="outlet.id_outlet"
          @click="selectOutlet(outlet.id_outlet)"
          class="group relative overflow-hidden rounded-3xl bg-gradient-to-br from-gray-800/90 to-gray-900/90 backdrop-blur-xl border border-yellow-500/30 shadow-2xl cursor-pointer transform transition-all duration-500 hover:scale-105 hover:shadow-yellow-500/50 hover:border-yellow-400/60"
          :style="{ animationDelay: `${index * 100}ms` }"
        >
          <!-- Shine Effect -->
          <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/10 to-transparent -translate-x-full group-hover:translate-x-full transition-transform duration-1000"></div>
          
          <!-- Content -->
          <div class="relative p-8 h-full flex flex-col">
            <!-- Icon -->
            <div class="mb-6 flex justify-center">
              <div class="w-20 h-20 rounded-2xl bg-gradient-to-br from-yellow-500 to-yellow-600 flex items-center justify-center shadow-lg shadow-yellow-500/30 transform group-hover:rotate-12 group-hover:shadow-yellow-500/50 transition-all duration-300">
                <i class="fa-solid fa-store text-3xl text-black font-bold"></i>
              </div>
            </div>

            <!-- Outlet Name -->
            <h3 class="text-2xl font-bold text-white mb-3 text-center group-hover:text-yellow-400 transition-colors duration-300">
              {{ outlet.nama_outlet }}
            </h3>

            <!-- Location -->
            <p v-if="outlet.lokasi" class="text-gray-400 text-sm mb-6 text-center flex items-center justify-center gap-2">
              <i class="fa-solid fa-location-dot text-yellow-400"></i>
              {{ outlet.lokasi }}
            </p>

            <!-- View Button -->
            <div class="mt-auto">
              <div class="flex items-center justify-center gap-2 text-yellow-400 font-semibold group-hover:gap-4 transition-all duration-300">
                <span>View Menu</span>
                <i class="fa-solid fa-arrow-right transform group-hover:translate-x-2 transition-transform duration-300"></i>
              </div>
            </div>
          </div>

          <!-- Decorative Corner -->
          <div class="absolute top-0 right-0 w-32 h-32 bg-gradient-to-br from-yellow-500/30 to-transparent rounded-bl-full opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else class="text-center py-20">
        <div class="bg-gray-800/90 backdrop-blur-xl rounded-3xl p-12 border border-yellow-500/30 max-w-md mx-auto shadow-2xl">
          <i class="fa-solid fa-store-slash text-6xl text-yellow-500/50 mb-4"></i>
          <h3 class="text-2xl font-bold text-white mb-2">No Outlets Available</h3>
          <p class="text-gray-400">Menu books are not available at the moment.</p>
        </div>
      </div>
    </div>

    <!-- Footer -->
    <div class="relative z-10 text-center py-8 text-gray-400 text-sm">
      <p>© {{ new Date().getFullYear() }} All Rights Reserved</p>
    </div>
  </div>
</template>

<script setup>
import { router } from '@inertiajs/vue3';

const props = defineProps({
  outlets: Array,
});

const selectOutlet = (outletId) => {
  router.visit(`/menu/outlet/${outletId}`);
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

@keyframes fade-in {
  from {
    opacity: 0;
    transform: translateY(-20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.animate-blob {
  animation: blob 7s infinite;
}

.animation-delay-2000 {
  animation-delay: 2s;
}

.animation-delay-4000 {
  animation-delay: 4s;
}

.animate-fade-in {
  animation: fade-in 1s ease-out;
}

.animate-fade-in-delay {
  animation: fade-in 1s ease-out 0.3s both;
}
</style>

