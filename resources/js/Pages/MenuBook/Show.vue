<template>
  <AppLayout>
    <div class="min-h-screen bg-gradient-to-br from-amber-50 via-orange-50 to-red-50">
      <!-- Header dengan design premium -->
      <div class="bg-gradient-to-r from-amber-900 via-amber-800 to-amber-900 text-white shadow-2xl">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
          <div class="flex flex-col md:flex-row justify-between items-center gap-4">
            <div class="flex-1">
              <button
                @click="goBack"
                class="mb-4 text-amber-200 hover:text-white transition-colors flex items-center gap-2"
              >
                <i class="fa-solid fa-arrow-left"></i>
                Back to Menu Books
              </button>
              <h1 class="text-4xl md:text-5xl font-serif font-bold mb-2 tracking-wide">
                <i class="fa-solid fa-book-open mr-3 text-amber-200"></i>
                {{ menuBook.name }}
              </h1>
              <p v-if="menuBook.description" class="text-amber-100 text-lg font-light">{{ menuBook.description }}</p>
            </div>
            <button
              @click="openUploadModal"
              class="bg-gradient-to-r from-amber-600 to-amber-700 hover:from-amber-700 hover:to-amber-800 text-white px-6 py-3 rounded-lg shadow-lg transform hover:scale-105 transition-all duration-300 flex items-center gap-2 font-semibold"
            >
              <i class="fa-solid fa-plus"></i>
              Add Page
            </button>
          </div>
        </div>
      </div>

      <!-- Search & Filter Bar -->
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-xl p-6 mb-6 border border-amber-100">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="relative">
              <i class="fa-solid fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-amber-600"></i>
              <input
                v-model="searchQuery"
                type="text"
                placeholder="Search menu items..."
                class="w-full pl-12 pr-4 py-3 rounded-xl border-2 border-amber-200 focus:border-amber-500 focus:ring-2 focus:ring-amber-300 transition-all duration-300 bg-white/90"
                @input="handleSearch"
              />
            </div>
            <div class="relative">
              <i class="fa-solid fa-filter absolute left-4 top-1/2 transform -translate-y-1/2 text-amber-600"></i>
              <select
                v-model="categoryFilter"
                @change="handleFilter"
                class="w-full pl-12 pr-4 py-3 rounded-xl border-2 border-amber-200 focus:border-amber-500 focus:ring-2 focus:ring-amber-300 transition-all duration-300 bg-white/90 appearance-none cursor-pointer"
              >
                <option value="">All Categories</option>
                <option v-for="category in categories" :key="category.id" :value="category.id">
                  {{ category.name }}
                </option>
              </select>
            </div>
          </div>
        </div>

        <!-- Zoom Controls -->
        <div class="flex justify-center mb-6">
          <div class="bg-white/80 backdrop-blur-sm rounded-full shadow-xl p-3 flex items-center gap-4 border border-amber-100">
            <button
              @click="zoomOut"
              class="w-10 h-10 rounded-full bg-amber-100 hover:bg-amber-200 text-amber-700 flex items-center justify-center transition-all duration-300 hover:scale-110"
              :disabled="zoomLevel <= 0.5"
            >
              <i class="fa-solid fa-minus"></i>
            </button>
            <span class="text-amber-700 font-semibold min-w-[60px] text-center">{{ Math.round(zoomLevel * 100) }}%</span>
            <button
              @click="zoomIn"
              class="w-10 h-10 rounded-full bg-amber-100 hover:bg-amber-200 text-amber-700 flex items-center justify-center transition-all duration-300 hover:scale-110"
              :disabled="zoomLevel >= 2"
            >
              <i class="fa-solid fa-plus"></i>
            </button>
            <button
              @click="resetZoom"
              class="w-10 h-10 rounded-full bg-amber-600 hover:bg-amber-700 text-white flex items-center justify-center transition-all duration-300 hover:scale-110 ml-2"
            >
              <i class="fa-solid fa-arrows-rotate"></i>
            </button>
          </div>
        </div>

        <!-- Book Viewer -->
        <div v-if="filteredPages.length > 0" class="flex justify-center items-center min-h-[600px]">
          <div class="relative" :style="{ transform: `scale(${zoomLevel})`, transformOrigin: 'center', transition: 'transform 0.3s ease' }">
            <!-- Desktop: 2 pages view -->
            <div v-if="!isMobile" class="book-container relative">
              <div class="flex gap-4 items-center">
                <!-- Left Page -->
                <div
                  class="book-page shadow-2xl rounded-l-lg overflow-hidden bg-white"
                  :class="{ 'flip-left': isFlipping && flipDirection === 'left' }"
                >
                  <img
                    v-if="currentLeftPage && getImageUrl(currentLeftPage.image)"
                    :src="getImageUrl(currentLeftPage.image)"
                    :alt="`Page ${currentLeftPage.page_order}`"
                    class="w-full h-full object-contain"
                    draggable="false"
                    @error="(e) => { e.target.style.display = 'none'; }"
                  />
                  <div v-else class="w-[500px] h-[700px] bg-gradient-to-br from-amber-50 to-amber-100 flex items-center justify-center">
                    <div class="text-center text-amber-400">
                      <i class="fa-solid fa-book-open text-6xl mb-4"></i>
                      <p class="text-xl font-serif">Cover</p>
                    </div>
                  </div>
                </div>

                <!-- Right Page -->
                <div
                  class="book-page shadow-2xl rounded-r-lg overflow-hidden bg-white"
                  :class="{ 'flip-right': isFlipping && flipDirection === 'right' }"
                >
                  <img
                    v-if="currentRightPage && getImageUrl(currentRightPage.image)"
                    :src="getImageUrl(currentRightPage.image)"
                    :alt="`Page ${currentRightPage.page_order}`"
                    class="w-full h-full object-contain"
                    draggable="false"
                    @error="(e) => { e.target.style.display = 'none'; }"
                  />
                  <div v-else class="w-[500px] h-[700px] bg-gradient-to-br from-amber-50 to-amber-100 flex items-center justify-center">
                    <div class="text-center text-amber-400">
                      <i class="fa-solid fa-book-open text-6xl mb-4"></i>
                      <p class="text-xl font-serif">End</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Mobile: Single page -->
            <div v-else class="book-container-mobile">
              <div
                class="book-page-mobile shadow-2xl rounded-lg overflow-hidden bg-white"
                :class="{ 'flip': isFlipping }"
              >
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

        <!-- Empty State -->
        <div v-else class="text-center py-20">
          <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-xl p-12 border border-amber-100">
            <i class="fa-solid fa-book-open text-6xl text-amber-300 mb-4"></i>
            <h3 class="text-2xl font-serif text-amber-800 mb-2">No Pages Found</h3>
            <p class="text-amber-600 mb-6">Start by adding your first menu page</p>
            <button
              @click="openUploadModal"
              class="bg-gradient-to-r from-amber-600 to-amber-700 hover:from-amber-700 hover:to-amber-800 text-white px-6 py-3 rounded-lg shadow-lg transform hover:scale-105 transition-all duration-300"
            >
              <i class="fa-solid fa-plus mr-2"></i>
              Add First Page
            </button>
          </div>
        </div>

        <!-- Navigation Controls -->
        <div v-if="filteredPages.length > 0" class="flex justify-center items-center gap-4 mt-8">
          <button
            @click="previousPage"
            :disabled="currentPageIndex === 0"
            class="w-14 h-14 rounded-full bg-gradient-to-r from-amber-600 to-amber-700 hover:from-amber-700 hover:to-amber-800 text-white shadow-lg transform hover:scale-110 transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:scale-100 flex items-center justify-center"
          >
            <i class="fa-solid fa-chevron-left text-xl"></i>
          </button>

          <div class="bg-white/80 backdrop-blur-sm rounded-full shadow-xl px-6 py-3 border border-amber-100 flex items-center gap-4">
            <span class="text-amber-700 font-semibold">
              Page {{ currentPageIndex + 1 }} of {{ totalPages }}
            </span>
            <!-- Page Actions -->
            <div class="flex gap-2 ml-4 pl-4 border-l border-amber-200">
              <button
                v-if="currentLeftPage || currentRightPage || currentPage"
                @click="editCurrentPage"
                class="w-10 h-10 rounded-full bg-blue-500 hover:bg-blue-600 text-white shadow-lg transform hover:scale-110 transition-all duration-300 flex items-center justify-center"
                title="Edit Current Page"
              >
                <i class="fa-solid fa-edit"></i>
              </button>
              <button
                v-if="currentLeftPage || currentRightPage || currentPage"
                @click="deleteCurrentPage"
                class="w-10 h-10 rounded-full bg-red-500 hover:bg-red-600 text-white shadow-lg transform hover:scale-110 transition-all duration-300 flex items-center justify-center"
                title="Delete Current Page"
              >
                <i class="fa-solid fa-trash"></i>
              </button>
            </div>
          </div>

          <button
            @click="nextPage"
            :disabled="currentPageIndex >= totalPages - 1"
            class="w-14 h-14 rounded-full bg-gradient-to-r from-amber-600 to-amber-700 hover:from-amber-700 hover:to-amber-800 text-white shadow-lg transform hover:scale-110 transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:scale-100 flex items-center justify-center"
          >
            <i class="fa-solid fa-chevron-right text-xl"></i>
          </button>
        </div>

        <!-- Page Thumbnails -->
        <div v-if="filteredPages.length > 0" class="mt-8 bg-white/80 backdrop-blur-sm rounded-2xl shadow-xl p-6 border border-amber-100">
          <h3 class="text-xl font-serif text-amber-800 mb-4 text-center">Page Navigation</h3>
          <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8 gap-4">
            <div
              v-for="(page, index) in filteredPages"
              :key="page.id"
              class="relative group"
              :class="{ 'ring-4 ring-amber-500 rounded-lg': index === currentPageIndex }"
            >
              <div
                @click="goToPage(index)"
                class="aspect-[3/4] rounded-lg overflow-hidden shadow-md group-hover:shadow-xl transition-all duration-300 transform group-hover:scale-105 bg-white cursor-pointer"
              >
                <img
                  v-if="getImageUrl(page.image)"
                  :src="getImageUrl(page.image)"
                  :alt="`Page ${page.page_order}`"
                  class="w-full h-full object-cover"
                  @error="(e) => { e.target.style.display = 'none'; }"
                />
              </div>
              <div class="absolute bottom-0 left-0 right-0 bg-black/60 text-white text-xs text-center py-1 rounded-b-lg">
                {{ page.page_order }}
              </div>
              <!-- Action Buttons - Always Visible -->
              <div class="absolute top-2 right-2 flex gap-1 z-10">
                <button
                  @click.stop="editPage(page)"
                  class="w-8 h-8 rounded-full bg-blue-500 hover:bg-blue-600 text-white flex items-center justify-center shadow-lg transition-all duration-300 hover:scale-110 opacity-90 hover:opacity-100"
                  title="Edit Page"
                >
                  <i class="fa-solid fa-edit text-xs"></i>
                </button>
                <button
                  @click.stop="deletePage(page)"
                  class="w-8 h-8 rounded-full bg-red-500 hover:bg-red-600 text-white flex items-center justify-center shadow-lg transition-all duration-300 hover:scale-110 opacity-90 hover:opacity-100"
                  title="Delete Page"
                >
                  <i class="fa-solid fa-trash text-xs"></i>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Upload Modal -->
      <Teleport to="body">
        <div v-if="canShowModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm" @click.self="closeUploadModal">
          <div class="bg-white rounded-2xl shadow-2xl max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <MenuBookForm
              v-if="props.menuBook && props.menuBook.id"
              :key="`form-${props.menuBook.id}-${showUploadModal}-${editingPage?.id || 'new'}`"
              :menuBookId="props.menuBook.id"
              :items="props.items || []"
              :categories="props.categories || []"
              :subCategories="props.subCategories || []"
              :editingPage="editingPage"
              @close="closeUploadModal"
              @success="handlePageAdded"
            />
          </div>
        </div>
      </Teleport>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, Teleport } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import MenuBookForm from './Form.vue';
import Swal from 'sweetalert2';

const props = defineProps({
  menuBook: Object,
  pages: Array,
  categories: Array,
  subCategories: Array,
  items: Array,
  filters: Object,
});

const searchQuery = ref(props.filters?.search || '');
const categoryFilter = ref(props.filters?.category || '');
const zoomLevel = ref(1);
const currentPageIndex = ref(0);
const isFlipping = ref(false);
const flipDirection = ref('');
const showUploadModal = ref(false);
const editingPage = ref(null);

// Computed to check if modal can be shown
const canShowModal = computed(() => {
  return showUploadModal.value && props.menuBook && props.menuBook.id;
});
const isMobile = ref(false);

const filteredPages = computed(() => {
  let result = [...props.pages];

  if (searchQuery.value) {
    const search = searchQuery.value.toLowerCase();
    result = result.filter(page => {
      return page.items?.some(item => 
        item.name?.toLowerCase().includes(search) ||
        item.sku?.toLowerCase().includes(search)
      );
    });
  }

  if (categoryFilter.value) {
    result = result.filter(page => {
      return page.categories?.some(cat => cat.id === parseInt(categoryFilter.value));
    });
  }

  return result.sort((a, b) => a.page_order - b.page_order);
});

const totalPages = computed(() => {
  return Math.ceil(filteredPages.value.length / 2);
});

const currentLeftPage = computed(() => {
  const index = currentPageIndex.value * 2;
  return filteredPages.value[index] || null;
});

const currentRightPage = computed(() => {
  const index = currentPageIndex.value * 2 + 1;
  return filteredPages.value[index] || null;
});

const currentPage = computed(() => {
  return filteredPages.value[currentPageIndex.value] || null;
});

const checkMobile = () => {
  isMobile.value = window.innerWidth < 768;
};

const handleSearch = () => {
  currentPageIndex.value = 0;
  router.get(`/menu-book/${props.menuBook.id}`, {
    search: searchQuery.value,
    category: categoryFilter.value,
  }, {
    preserveState: true,
    preserveScroll: true,
  });
};

const handleFilter = () => {
  currentPageIndex.value = 0;
  router.get(`/menu-book/${props.menuBook.id}`, {
    search: searchQuery.value,
    category: categoryFilter.value,
  }, {
    preserveState: true,
    preserveScroll: true,
  });
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
    }, 300);
  }
};

const previousPage = () => {
  if (currentPageIndex.value > 0) {
    flipDirection.value = 'left';
    isFlipping.value = true;
    setTimeout(() => {
      currentPageIndex.value--;
      isFlipping.value = false;
    }, 300);
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
    }, 300);
  }
};

const getImageUrl = (imagePath) => {
  if (!imagePath) return null;
  try {
    // Jika sudah full URL, return langsung
    if (imagePath.startsWith('http://') || imagePath.startsWith('https://')) {
      return imagePath;
    }
    // Jika sudah dimulai dengan /storage/, return langsung
    if (imagePath.startsWith('/storage/')) {
      return imagePath;
    }
    // Jika path relatif, tambahkan /storage/
    return `/storage/${imagePath}`;
  } catch (error) {
    console.error('Error processing image:', error);
    return null;
  }
};

const openUploadModal = () => {
  editingPage.value = null;
  showUploadModal.value = true;
};

const closeUploadModal = () => {
  showUploadModal.value = false;
  editingPage.value = null;
};

const editPage = (page) => {
  editingPage.value = page;
  showUploadModal.value = true;
};

const editCurrentPage = () => {
  const page = currentLeftPage.value || currentRightPage.value || currentPage.value;
  if (page) {
    editPage(page);
  }
};

const deletePage = (page) => {
  Swal.fire({
    title: 'Delete Page?',
    text: `Are you sure you want to delete page ${page.page_order}? This action cannot be undone.`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Yes, delete it!',
    cancelButtonText: 'Cancel',
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(`/menu-book/page/${page.id}`, {
        onSuccess: () => {
          Swal.fire('Deleted!', 'Page has been deleted.', 'success');
          // Reset to first page if current page was deleted
          if (currentPageIndex.value >= filteredPages.value.length - 1) {
            currentPageIndex.value = Math.max(0, filteredPages.value.length - 2);
          }
          router.reload({ only: ['pages'] });
        },
        onError: () => {
          Swal.fire('Error!', 'Failed to delete page.', 'error');
        },
      });
    }
  });
};

const deleteCurrentPage = () => {
  const page = currentLeftPage.value || currentRightPage.value || currentPage.value;
  if (page) {
    deletePage(page);
  }
};

const handlePageAdded = () => {
  closeUploadModal();
  router.reload({ only: ['pages'] });
};

const goBack = () => {
  router.visit('/menu-book');
};

// Keyboard navigation
const handleKeyPress = (e) => {
  if (e.key === 'ArrowLeft') previousPage();
  if (e.key === 'ArrowRight') nextPage();
  if (e.key === '+' || e.key === '=') zoomIn();
  if (e.key === '-') zoomOut();
};

// Touch/swipe for mobile
let touchStartX = 0;
let touchEndX = 0;

const handleTouchStart = (e) => {
  touchStartX = e.changedTouches[0].screenX;
};

const handleTouchEnd = (e) => {
  touchEndX = e.changedTouches[0].screenX;
  handleSwipe();
};

const handleSwipe = () => {
  const swipeThreshold = 50;
  const diff = touchStartX - touchEndX;
  
  if (Math.abs(diff) > swipeThreshold) {
    if (diff > 0) {
      nextPage();
    } else {
      previousPage();
    }
  }
};

onMounted(() => {
  checkMobile();
  window.addEventListener('resize', checkMobile);
  window.addEventListener('keydown', handleKeyPress);
  window.addEventListener('touchstart', handleTouchStart);
  window.addEventListener('touchend', handleTouchEnd);
});

onUnmounted(() => {
  window.removeEventListener('resize', checkMobile);
  window.removeEventListener('keydown', handleKeyPress);
  window.removeEventListener('touchstart', handleTouchStart);
  window.removeEventListener('touchend', handleTouchEnd);
});
</script>

<style scoped>
.book-page {
  width: 500px;
  height: 700px;
  transition: transform 0.6s cubic-bezier(0.645, 0.045, 0.355, 1);
}

.book-page-mobile {
  width: 90vw;
  max-width: 400px;
  height: 600px;
  transition: transform 0.6s cubic-bezier(0.645, 0.045, 0.355, 1);
}

.flip-left {
  transform: perspective(1000px) rotateY(-15deg);
}

.flip-right {
  transform: perspective(1000px) rotateY(15deg);
}

.flip {
  transform: perspective(1000px) rotateY(5deg);
}

@media (max-width: 768px) {
  .book-page {
    width: 90vw;
    height: 600px;
  }
}
</style>

