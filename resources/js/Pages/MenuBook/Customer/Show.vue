<template>
  <div class="min-h-screen bg-gradient-to-br from-black via-gray-900 to-black relative overflow-hidden">
    <!-- Animated Background -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
      <div class="absolute -top-40 -right-40 w-80 h-80 bg-yellow-500 rounded-full mix-blend-multiply filter blur-xl opacity-10 animate-blob"></div>
      <div class="absolute -bottom-40 -left-40 w-80 h-80 bg-yellow-400 rounded-full mix-blend-multiply filter blur-xl opacity-8 animate-blob animation-delay-2000"></div>
    </div>

    <!-- Header -->
    <div class="relative z-10 pt-8 pb-6 px-4 sm:px-6 lg:px-8">
      <div class="max-w-7xl mx-auto">
        <!-- Navigation -->
        <div class="flex items-center justify-between mb-6">
          <button
            @click="goBack"
            class="text-white/80 hover:text-yellow-500 transition-colors duration-300 flex items-center gap-2 group"
          >
            <i class="fa-solid fa-arrow-left transform group-hover:-translate-x-1 transition-transform duration-300"></i>
            <span>Back</span>
          </button>
          
          <!-- Menu Book Title -->
          <div class="text-center flex-1">
            <h1 class="text-2xl md:text-3xl font-bold text-transparent bg-clip-text bg-gradient-to-r from-yellow-400 via-yellow-500 to-yellow-400">
              {{ menuBook.name }}
            </h1>
            <p v-if="outlet" class="text-gray-400 text-sm mt-1">{{ outlet.nama_outlet }}</p>
          </div>

          <div class="w-24"></div> <!-- Spacer for centering -->
        </div>
      </div>
    </div>

    <!-- Book Viewer Container -->
    <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-8">
      <div v-if="pages.length > 0" class="flex flex-col items-center">
        <!-- Zoom Controls -->
        <div 
          class="fixed z-50 select-none"
          :style="{ top: controlsPosition.top + 'px', left: controlsPosition.left + 'px', transform: 'translate(-50%, 0)' }"
          @mousedown="startDrag"
          @touchstart="startDrag"
        >
          <div 
            class="bg-gray-800/90 backdrop-blur-xl rounded-full shadow-2xl p-3 flex items-center gap-4 border border-yellow-500/30"
            :class="{ 'cursor-grabbing': isDragging, 'cursor-grab': !isDragging }"
          >
            <button
              @click="zoomOut"
              class="w-12 h-12 rounded-full bg-gray-700/80 hover:bg-gray-600/80 text-white flex items-center justify-center transition-all duration-300 hover:scale-110 disabled:opacity-50 disabled:cursor-not-allowed"
              :disabled="zoomLevel <= 0.5"
            >
              <i class="fa-solid fa-minus"></i>
            </button>
            <span class="text-white font-semibold min-w-[70px] text-center text-lg">{{ Math.round(zoomLevel * 100) }}%</span>
            <button
              @click="zoomIn"
              class="w-12 h-12 rounded-full bg-gray-700/80 hover:bg-gray-600/80 text-white flex items-center justify-center transition-all duration-300 hover:scale-110 disabled:opacity-50 disabled:cursor-not-allowed"
              :disabled="zoomLevel >= 2"
            >
              <i class="fa-solid fa-plus"></i>
            </button>
            <button
              @click="resetZoom"
              class="w-12 h-12 rounded-full bg-gradient-to-r from-yellow-500 to-yellow-600 hover:from-yellow-600 hover:to-yellow-700 text-black flex items-center justify-center transition-all duration-300 hover:scale-110 ml-2 shadow-lg shadow-yellow-500/30"
            >
              <i class="fa-solid fa-arrows-rotate"></i>
            </button>
          </div>
        </div>

        <!-- Book Pages Viewer -->
        <div class="flex justify-center items-center min-h-[600px] mt-24">
          <div class="relative" :style="{ transform: `scale(${zoomLevel})`, transformOrigin: 'center', transition: 'transform 0.3s ease' }">
            <!-- Desktop: 2 pages view -->
            <div v-if="!isMobile" class="book-container relative" style="perspective: 3000px;">
              <div class="flex gap-6 items-center relative">
                <!-- Left Page -->
                <div
                  class="book-page shadow-2xl rounded-l-2xl overflow-hidden bg-gradient-to-br from-yellow-50 to-amber-50 relative border-4 border-yellow-300"
                  :class="{ 
                    'flip-left': isFlipping && flipDirection === 'left',
                    'flip-right-active': isFlipping && flipDirection === 'right'
                  }"
                  style="transform-origin: right center;"
                >
                  <div class="page-content">
                    <img
                      v-if="currentLeftPage && getImageUrl(currentLeftPage.image)"
                      :src="getImageUrl(currentLeftPage.image)"
                      :alt="`Page ${currentLeftPage.page_order}`"
                      class="w-full h-full object-contain"
                      draggable="false"
                      @error="(e) => { e.target.style.display = 'none'; }"
                    />
                    <div v-else class="w-[500px] h-[700px] bg-gradient-to-br from-yellow-50 to-amber-50 flex items-center justify-center">
                      <div class="text-center text-yellow-600">
                        <i class="fa-solid fa-book-open text-6xl mb-4"></i>
                        <p class="text-xl font-serif font-bold">Cover</p>
                      </div>
                    </div>
                  </div>
                  <!-- Page Shadow Effect -->
                  <div class="absolute inset-0 pointer-events-none">
                    <div class="absolute right-0 top-0 bottom-0 w-2 bg-gradient-to-r from-transparent via-black/10 to-transparent"></div>
                  </div>
                </div>

                <!-- Right Page -->
                <div
                  class="book-page shadow-2xl rounded-r-2xl overflow-hidden bg-gradient-to-br from-yellow-50 to-amber-50 relative border-4 border-yellow-300"
                  :class="{ 
                    'flip-right': isFlipping && flipDirection === 'right',
                    'flip-left-active': isFlipping && flipDirection === 'left'
                  }"
                  style="transform-origin: left center;"
                >
                  <div class="page-content">
                    <img
                      v-if="currentRightPage && getImageUrl(currentRightPage.image)"
                      :src="getImageUrl(currentRightPage.image)"
                      :alt="`Page ${currentRightPage.page_order}`"
                      class="w-full h-full object-contain"
                      draggable="false"
                      @error="(e) => { e.target.style.display = 'none'; }"
                    />
                    <div v-else class="w-[500px] h-[700px] bg-gradient-to-br from-yellow-50 to-amber-50 flex items-center justify-center">
                      <div class="text-center text-yellow-600">
                        <i class="fa-solid fa-book-open text-6xl mb-4"></i>
                        <p class="text-xl font-serif font-bold">End</p>
                      </div>
                    </div>
                  </div>
                  <!-- Page Shadow Effect -->
                  <div class="absolute inset-0 pointer-events-none">
                    <div class="absolute left-0 top-0 bottom-0 w-2 bg-gradient-to-l from-transparent via-black/10 to-transparent"></div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Mobile: Single page -->
            <div v-else class="book-container-mobile" style="perspective: 2000px;">
              <div
                class="book-page-mobile shadow-2xl rounded-2xl overflow-hidden bg-gradient-to-br from-yellow-50 to-amber-50 relative border-4 border-yellow-300"
                :class="{ 'flip': isFlipping }"
              >
                <div class="page-content">
                  <img
                    v-if="currentPage && getImageUrl(currentPage.image)"
                    :src="getImageUrl(currentPage.image)"
                    :alt="`Page ${currentPage.page_order}`"
                    class="w-full h-full object-contain"
                    draggable="false"
                    @error="(e) => { e.target.style.display = 'none'; }"
                  />
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Navigation Controls -->
        <div class="flex justify-center items-center gap-6 mt-8">
          <button
            @click="previousPage"
            :disabled="currentPageIndex === 0"
            class="w-16 h-16 rounded-full bg-gray-800/90 backdrop-blur-xl hover:bg-gray-700/90 text-white shadow-2xl transform hover:scale-110 transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:scale-100 flex items-center justify-center border border-yellow-500/30 hover:border-yellow-500/50"
          >
            <i class="fa-solid fa-chevron-left text-2xl"></i>
          </button>

          <div class="bg-gray-800/90 backdrop-blur-xl rounded-full shadow-2xl px-8 py-4 border border-yellow-500/30 flex items-center gap-4">
            <span class="text-white font-semibold text-lg">
              Page {{ currentPageIndex + 1 }} of {{ totalPages }}
            </span>
          </div>

          <button
            @click="nextPage"
            :disabled="currentPageIndex >= totalPages - 1"
            class="w-16 h-16 rounded-full bg-gray-800/90 backdrop-blur-xl hover:bg-gray-700/90 text-white shadow-2xl transform hover:scale-110 transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:scale-100 flex items-center justify-center border border-yellow-500/30 hover:border-yellow-500/50"
          >
            <i class="fa-solid fa-chevron-right text-2xl"></i>
          </button>
        </div>

        <!-- Page Thumbnails -->
        <div class="mt-12 bg-gray-800/90 backdrop-blur-xl rounded-3xl shadow-2xl p-6 border border-yellow-500/30 w-full max-w-6xl">
          <h3 class="text-xl font-bold text-white mb-6 text-center">Page Navigation</h3>
          <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8 gap-4">
            <div
              v-for="(page, index) in pages"
              :key="page.id"
              class="relative group"
              :class="{ 'ring-4 ring-yellow-500 rounded-xl': index === currentPageIndex || (index === currentPageIndex + 1 && !isMobile) }"
            >
              <div
                @click="goToPage(index)"
                class="aspect-[3/4] rounded-xl overflow-hidden shadow-lg group-hover:shadow-2xl transition-all duration-300 transform group-hover:scale-110 bg-white cursor-pointer border-2 border-transparent group-hover:border-yellow-500"
              >
                <img
                  v-if="getImageUrl(page.image)"
                  :src="getImageUrl(page.image)"
                  :alt="`Page ${page.page_order}`"
                  class="w-full h-full object-cover"
                  @error="(e) => { e.target.style.display = 'none'; }"
                />
              </div>
              <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/80 to-transparent text-white text-xs text-center py-2 rounded-b-xl">
                {{ page.page_order }}
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else class="text-center py-20">
        <div class="bg-gray-800/90 backdrop-blur-xl rounded-3xl p-12 border border-yellow-500/30 max-w-md mx-auto shadow-2xl">
          <i class="fa-solid fa-book-open text-6xl text-yellow-500/50 mb-4"></i>
          <h3 class="text-2xl font-bold text-white mb-2">No Pages Available</h3>
          <p class="text-gray-400 mb-6">This menu book doesn't have any pages yet.</p>
          <button
            @click="goBack"
            class="px-6 py-3 bg-gradient-to-r from-yellow-500 to-yellow-600 text-black rounded-xl font-semibold hover:shadow-lg hover:shadow-yellow-500/50 transform hover:scale-105 transition-all duration-300"
          >
            Go Back
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
  menuBook: Object,
  pages: Array,
  outlet: Object,
});

const zoomLevel = ref(1);
const currentPageIndex = ref(0);
const isFlipping = ref(false);
const flipDirection = ref('');
const isMobile = ref(false);

// Draggable controls
const isDragging = ref(false);
const dragStart = ref({ x: 0, y: 0 });
const controlsPosition = ref({ top: 80, left: window.innerWidth / 2 });

const totalPages = computed(() => {
  return Math.ceil(props.pages.length / 2);
});

const currentLeftPage = computed(() => {
  const index = currentPageIndex.value * 2;
  return props.pages[index] || null;
});

const currentRightPage = computed(() => {
  const index = currentPageIndex.value * 2 + 1;
  return props.pages[index] || null;
});

const currentPage = computed(() => {
  return props.pages[currentPageIndex.value] || null;
});

const checkMobile = () => {
  isMobile.value = window.innerWidth < 768;
};

const zoomIn = () => {
  if (zoomLevel.value < 2) {
    zoomLevel.value = Math.min(zoomLevel.value + 0.1, 2);
  }
};

const zoomOut = () => {
  if (zoomLevel.value > 0.5) {
    zoomLevel.value = Math.max(zoomLevel.value - 0.1, 0.5);
  }
};

const resetZoom = () => {
  zoomLevel.value = 1;
};

const nextPage = () => {
  if (currentPageIndex.value < totalPages.value - 1) {
    flipDirection.value = 'right';
    isFlipping.value = true;
    setTimeout(() => {
      currentPageIndex.value++;
      isFlipping.value = false;
      flipDirection.value = '';
    }, 800);
  }
};

const previousPage = () => {
  if (currentPageIndex.value > 0) {
    flipDirection.value = 'left';
    isFlipping.value = true;
    setTimeout(() => {
      currentPageIndex.value--;
      isFlipping.value = false;
      flipDirection.value = '';
    }, 800);
  }
};

const goToPage = (index) => {
  const targetPageIndex = Math.floor(index / 2);
  if (targetPageIndex !== currentPageIndex.value) {
    flipDirection.value = targetPageIndex > currentPageIndex.value ? 'right' : 'left';
    isFlipping.value = true;
    setTimeout(() => {
      currentPageIndex.value = targetPageIndex;
      isFlipping.value = false;
      flipDirection.value = '';
    }, 800);
  }
};

const getImageUrl = (imagePath) => {
  if (!imagePath) return null;
  try {
    if (imagePath.startsWith('http://') || imagePath.startsWith('https://')) {
      return imagePath;
    }
    if (imagePath.startsWith('/storage/')) {
      return imagePath;
    }
    return `/storage/${imagePath}`;
  } catch (error) {
    console.error('Error processing image:', error);
    return null;
  }
};

const goBack = () => {
  if (props.outlet) {
    router.visit(`/menu/outlet/${props.outlet.id_outlet}`);
  } else {
    router.visit('/menu');
  }
};

// Keyboard navigation
const handleKeyPress = (e) => {
  if (e.key === 'ArrowLeft') previousPage();
  if (e.key === 'ArrowRight') nextPage();
  if (e.key === '+' || e.key === '=') zoomIn();
  if (e.key === '-') zoomOut();
};

// Touch/swipe for mobile (only for page navigation, not controls)
let touchStartX = 0;
let touchEndX = 0;
let touchStartY = 0;
let touchEndY = 0;

const handleTouchStart = (e) => {
  // Don't handle swipe if touching controls
  if (e.target.closest('.cursor-move')) {
    return;
  }
  touchStartX = e.changedTouches[0].screenX;
  touchStartY = e.changedTouches[0].screenY;
};

const handleTouchEnd = (e) => {
  // Don't handle swipe if touching controls
  if (e.target.closest('.cursor-move')) {
    return;
  }
  touchEndX = e.changedTouches[0].screenX;
  touchEndY = e.changedTouches[0].screenY;
  handleSwipe();
};

const handleSwipe = () => {
  const swipeThreshold = 50;
  const diffX = touchStartX - touchEndX;
  const diffY = Math.abs(touchStartY - touchEndY);
  
  // Only handle horizontal swipes (not vertical)
  if (Math.abs(diffX) > swipeThreshold && diffY < 100) {
    if (diffX > 0) {
      nextPage();
    } else {
      previousPage();
    }
  }
};

// Draggable controls functions
const startDrag = (e) => {
  // Don't start drag if clicking on a button or its icon
  if (e.target.closest('button') || e.target.tagName === 'I' || e.target.tagName === 'SPAN') {
    return;
  }
  
  isDragging.value = true;
  const clientX = e.touches ? e.touches[0].clientX : e.clientX;
  const clientY = e.touches ? e.touches[0].clientY : e.clientY;
  
  // Since we use translate(-50%, 0), the actual left position is centered
  // So we just need to track the offset from the click point
  dragStart.value = {
    x: clientX - controlsPosition.value.left,
    y: clientY - controlsPosition.value.top
  };
  
  e.preventDefault();
  e.stopPropagation();
};

const onDrag = (e) => {
  if (!isDragging.value) return;
  
  const clientX = e.touches ? e.touches[0].clientX : e.clientX;
  const clientY = e.touches ? e.touches[0].clientY : e.clientY;
  
  // Calculate new position (accounting for translate -50%)
  let newLeft = clientX;
  let newTop = clientY - dragStart.value.y;
  
  // Constrain to viewport (accounting for controls width ~250px)
  const controlsWidth = 250;
  const controlsHeight = 80;
  const maxLeft = window.innerWidth - (controlsWidth / 2);
  const minLeft = controlsWidth / 2;
  const maxTop = window.innerHeight - controlsHeight;
  
  newLeft = Math.max(minLeft, Math.min(newLeft, maxLeft));
  newTop = Math.max(20, Math.min(newTop, maxTop));
  
  controlsPosition.value = {
    top: newTop,
    left: newLeft
  };
  
  e.preventDefault();
};

const stopDrag = () => {
  if (isDragging.value) {
    isDragging.value = false;
    // Save to localStorage when drag ends
    localStorage.setItem('menuBookControlsPosition', JSON.stringify(controlsPosition.value));
  }
};

onMounted(() => {
  checkMobile();
  
  // Load saved controls position
  const savedPosition = localStorage.getItem('menuBookControlsPosition');
  if (savedPosition) {
    try {
      const pos = JSON.parse(savedPosition);
      // Validate position is still within viewport
      const controlsWidth = 250;
      const minLeft = controlsWidth / 2;
      const maxLeft = window.innerWidth - (controlsWidth / 2);
      if (pos.top >= 20 && pos.top <= window.innerHeight - 80 && 
          pos.left >= minLeft && pos.left <= maxLeft) {
        controlsPosition.value = pos;
      } else {
        // Reset to default if saved position is invalid
        controlsPosition.value = {
          top: 80,
          left: window.innerWidth / 2
        };
      }
    } catch (e) {
      console.error('Error loading controls position:', e);
      controlsPosition.value = {
        top: 80,
        left: window.innerWidth / 2
      };
    }
  } else {
    // Default position: center top
    controlsPosition.value = {
      top: 80,
      left: window.innerWidth / 2
    };
  }
  
  const handleResize = () => {
    checkMobile();
    // Adjust position on resize
    const controlsWidth = 250;
    const minLeft = controlsWidth / 2;
    const maxLeft = window.innerWidth - (controlsWidth / 2);
    const maxTop = window.innerHeight - 80;
    controlsPosition.value.left = Math.max(minLeft, Math.min(controlsPosition.value.left, maxLeft));
    controlsPosition.value.top = Math.max(20, Math.min(controlsPosition.value.top, maxTop));
  };
  
  window.addEventListener('resize', handleResize);
  window.addEventListener('keydown', handleKeyPress);
  window.addEventListener('touchstart', handleTouchStart);
  window.addEventListener('touchend', handleTouchEnd);
  window.addEventListener('mousemove', onDrag);
  window.addEventListener('touchmove', onDrag);
  window.addEventListener('mouseup', stopDrag);
  window.addEventListener('touchend', stopDrag);
});

onUnmounted(() => {
  window.removeEventListener('resize', checkMobile);
  window.removeEventListener('keydown', handleKeyPress);
  window.removeEventListener('touchstart', handleTouchStart);
  window.removeEventListener('touchend', handleTouchEnd);
  window.removeEventListener('mousemove', onDrag);
  window.removeEventListener('touchmove', onDrag);
  window.removeEventListener('mouseup', stopDrag);
});
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

.book-page {
  width: 500px;
  height: 700px;
  transform-style: preserve-3d;
  transition: transform 0.8s cubic-bezier(0.25, 0.46, 0.45, 0.94);
  position: relative;
}

.book-page-mobile {
  width: 90vw;
  max-width: 400px;
  height: 600px;
  transform-style: preserve-3d;
  transition: transform 0.8s cubic-bezier(0.25, 0.46, 0.45, 0.94);
  position: relative;
}

.page-content {
  width: 100%;
  height: 100%;
  backface-visibility: hidden;
  -webkit-backface-visibility: hidden;
  transform-style: preserve-3d;
}

.flip-left {
  transform: perspective(3000px) rotateY(-180deg) translateZ(0);
  z-index: 10;
}

.flip-right {
  transform: perspective(3000px) rotateY(180deg) translateZ(0);
  z-index: 10;
}

.flip-left-active {
  transform: perspective(3000px) rotateY(-30deg) translateZ(0);
  z-index: 5;
}

.flip-right-active {
  transform: perspective(3000px) rotateY(30deg) translateZ(0);
  z-index: 5;
}

.flip {
  transform: perspective(2000px) rotateY(180deg) translateZ(0);
}

.book-page.flip-left,
.book-page.flip-right {
  box-shadow: 
    -30px 0 60px rgba(0, 0, 0, 0.5),
    -10px 0 30px rgba(0, 0, 0, 0.4),
    inset -10px 0 30px rgba(0, 0, 0, 0.2);
}

.book-page.flip-left-active,
.book-page.flip-right-active {
  box-shadow: 
    -15px 0 40px rgba(0, 0, 0, 0.4),
    -5px 0 20px rgba(0, 0, 0, 0.3);
}

.book-page-mobile.flip {
  box-shadow: 
    -20px 0 50px rgba(0, 0, 0, 0.4),
    0 0 30px rgba(0, 0, 0, 0.3);
}

@media (max-width: 768px) {
  .book-page {
    width: 90vw;
    height: 600px;
  }
}
</style>

