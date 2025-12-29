<template>
  <AppLayout>
    <div class="min-h-screen bg-gradient-to-br from-amber-50 via-orange-50 to-red-50">
      <!-- Header dengan design premium -->
      <div class="bg-gradient-to-r from-amber-900 via-amber-800 to-amber-900 text-white shadow-2xl">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
          <div class="flex flex-col md:flex-row justify-between items-center gap-4">
            <div>
              <h1 class="text-4xl md:text-5xl font-serif font-bold mb-2 tracking-wide">
                <i class="fa-solid fa-book-open mr-3 text-amber-200"></i>
                Menu Books
              </h1>
              <p class="text-amber-100 text-lg font-light">Manage Your Menu Collections</p>
            </div>
            <button
              @click="openCreateModal"
              class="bg-gradient-to-r from-amber-600 to-amber-700 hover:from-amber-700 hover:to-amber-800 text-white px-6 py-3 rounded-lg shadow-lg transform hover:scale-105 transition-all duration-300 flex items-center gap-2 font-semibold"
            >
              <i class="fa-solid fa-plus"></i>
              Create Menu Book
            </button>
          </div>
        </div>
      </div>

      <!-- Filter Outlet -->
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 -mt-6">
        <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-xl p-6 mb-6 border border-amber-100">
          <div class="flex items-center gap-4">
            <div class="relative flex-1">
              <i class="fa-solid fa-store absolute left-4 top-1/2 transform -translate-y-1/2 text-amber-600"></i>
              <select
                v-model="outletFilter"
                @change="handleFilter"
                class="w-full pl-12 pr-4 py-3 rounded-xl border-2 border-amber-200 focus:border-amber-500 focus:ring-2 focus:ring-amber-300 transition-all duration-300 bg-white/90 appearance-none cursor-pointer"
              >
                <option value="">All Outlets</option>
                <option v-for="outlet in outlets" :key="outlet.id_outlet" :value="outlet.id_outlet">
                  {{ outlet.nama_outlet }}
                </option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <!-- Menu Books Grid -->
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div v-if="books.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <div
            v-for="book in books"
            :key="book.id"
            class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-xl overflow-hidden border border-amber-100 hover:shadow-2xl transition-all duration-300 transform hover:scale-105 group cursor-pointer"
            @click="viewBook(book.id)"
          >
            <div class="p-6">
              <div class="flex items-start justify-between mb-4">
                <div class="flex-1">
                  <h3 class="text-2xl font-serif text-amber-900 mb-2 group-hover:text-amber-700 transition-colors">
                    {{ book.name }}
                  </h3>
                  <p v-if="book.description" class="text-amber-600 text-sm mb-3 line-clamp-2">
                    {{ book.description }}
                  </p>
                </div>
                <span
                  :class="book.status === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700'"
                  class="px-3 py-1 rounded-full text-xs font-semibold"
                >
                  {{ book.status }}
                </span>
              </div>
              
              <div class="flex items-center justify-between text-amber-600">
                <div class="flex items-center gap-2">
                  <i class="fa-solid fa-file-lines"></i>
                  <span class="text-sm font-medium">{{ book.pages_count }} Pages</span>
                </div>
                <i class="fa-solid fa-arrow-right group-hover:translate-x-1 transition-transform"></i>
              </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="px-6 pb-6 flex flex-wrap gap-2">
              <button
                @click.stop="viewBook(book.id)"
                class="flex-1 bg-gradient-to-r from-amber-600 to-amber-700 hover:from-amber-700 hover:to-amber-800 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-all duration-300"
              >
                <i class="fa-solid fa-eye mr-2"></i>
                View
              </button>
              <button
                @click.stop="openLandingPage"
                class="px-4 py-2 bg-gradient-to-r from-yellow-500 to-yellow-600 hover:from-yellow-600 hover:to-yellow-700 text-black rounded-lg transition-all duration-300 font-semibold shadow-md hover:shadow-lg"
                title="View Customer Landing Page"
              >
                <i class="fa-solid fa-globe mr-1"></i>
                <span class="hidden sm:inline">Landing</span>
              </button>
              <button
                @click.stop="editBook(book)"
                class="px-4 py-2 bg-amber-100 hover:bg-amber-200 text-amber-700 rounded-lg transition-all duration-300"
                title="Edit Menu Book"
              >
                <i class="fa-solid fa-edit"></i>
              </button>
              <button
                @click.stop="deleteBook(book)"
                class="px-4 py-2 bg-red-100 hover:bg-red-200 text-red-700 rounded-lg transition-all duration-300"
                title="Delete Menu Book"
              >
                <i class="fa-solid fa-trash"></i>
              </button>
            </div>
          </div>
        </div>

        <!-- Empty State -->
        <div v-else class="text-center py-20">
          <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-xl p-12 border border-amber-100">
            <i class="fa-solid fa-book-open text-6xl text-amber-300 mb-4"></i>
            <h3 class="text-2xl font-serif text-amber-800 mb-2">No Menu Books Found</h3>
            <p class="text-amber-600 mb-6">Start by creating your first menu book</p>
            <button
              @click="openCreateModal"
              class="bg-gradient-to-r from-amber-600 to-amber-700 hover:from-amber-700 hover:to-amber-800 text-white px-6 py-3 rounded-lg shadow-lg transform hover:scale-105 transition-all duration-300"
            >
              <i class="fa-solid fa-plus mr-2"></i>
              Create First Menu Book
            </button>
          </div>
        </div>
      </div>

      <!-- Create/Edit Book Modal -->
      <div v-if="showBookModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm" @click.self="closeBookModal">
        <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full mx-4">
          <div class="p-6">
            <div class="flex justify-between items-center mb-6 pb-4 border-b border-amber-200">
              <h2 class="text-3xl font-serif text-amber-900">
                <i class="fa-solid fa-book mr-3 text-amber-600"></i>
                {{ editingBook ? 'Edit Menu Book' : 'Create Menu Book' }}
              </h2>
              <button
                @click="closeBookModal"
                class="w-10 h-10 rounded-full bg-amber-100 hover:bg-amber-200 text-amber-700 flex items-center justify-center transition-all duration-300 hover:scale-110"
              >
                <i class="fa-solid fa-times"></i>
              </button>
            </div>

            <form @submit.prevent="submitBook" class="space-y-6">
              <div>
                <label class="block text-sm font-semibold text-amber-800 mb-2">
                  <i class="fa-solid fa-heading mr-2"></i>
                  Book Name *
                </label>
                <input
                  v-model="bookForm.name"
                  type="text"
                  required
                  class="w-full px-4 py-3 rounded-xl border-2 border-amber-200 focus:border-amber-500 focus:ring-2 focus:ring-amber-300 transition-all duration-300 bg-white"
                  placeholder="e.g., Menu Makanan, Menu Minuman"
                />
              </div>

              <div>
                <label class="block text-sm font-semibold text-amber-800 mb-2">
                  <i class="fa-solid fa-align-left mr-2"></i>
                  Description
                </label>
                <textarea
                  v-model="bookForm.description"
                  rows="4"
                  class="w-full px-4 py-3 rounded-xl border-2 border-amber-200 focus:border-amber-500 focus:ring-2 focus:ring-amber-300 transition-all duration-300 bg-white"
                  placeholder="Optional description for this menu book"
                ></textarea>
              </div>

              <div>
                <label class="block text-sm font-semibold text-amber-800 mb-2">
                  <i class="fa-solid fa-toggle-on mr-2"></i>
                  Status
                </label>
                <select
                  v-model="bookForm.status"
                  class="w-full px-4 py-3 rounded-xl border-2 border-amber-200 focus:border-amber-500 focus:ring-2 focus:ring-amber-300 transition-all duration-300 bg-white"
                >
                  <option value="active">Active</option>
                  <option value="inactive">Inactive</option>
                </select>
              </div>

              <!-- Outlets Selection -->
              <div>
                <label class="block text-sm font-semibold text-amber-800 mb-2">
                  <i class="fa-solid fa-store mr-2"></i>
                  Outlets (Select outlets where this menu book will be available) *
                </label>
                <div class="border-2 border-amber-200 rounded-xl p-4 max-h-64 overflow-y-auto bg-white">
                  <div v-if="!outlets || outlets.length === 0" class="text-center text-amber-500 py-4">
                    <i class="fa-solid fa-inbox text-2xl mb-2"></i>
                    <p>No outlets available</p>
                  </div>
                  <div v-else class="space-y-2">
                    <label
                      v-for="outlet in outlets"
                      :key="outlet.id_outlet"
                      class="flex items-center p-3 rounded-lg hover:bg-amber-50 cursor-pointer transition-all duration-200 border border-transparent hover:border-amber-200"
                    >
                      <input
                        type="checkbox"
                        :value="outlet.id_outlet"
                        v-model="bookForm.outlet_ids"
                        class="w-5 h-5 text-amber-600 border-amber-300 rounded focus:ring-amber-500"
                      />
                      <div class="ml-3 flex-1">
                        <p class="font-medium text-amber-900">{{ outlet.nama_outlet }}</p>
                        <p v-if="outlet.lokasi" class="text-sm text-amber-600">{{ outlet.lokasi }}</p>
                      </div>
                    </label>
                  </div>
                </div>
                <p class="mt-2 text-sm text-amber-600">
                  Selected: {{ bookForm.outlet_ids.length }} outlet(s)
                </p>
              </div>

              <div class="flex gap-4 pt-4 border-t border-amber-200">
                <button
                  type="button"
                  @click="closeBookModal"
                  class="flex-1 py-3 rounded-xl border-2 border-amber-300 text-amber-700 hover:bg-amber-50 font-semibold transition-all duration-300"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  :disabled="processing"
                  class="flex-1 py-3 rounded-xl bg-gradient-to-r from-amber-600 to-amber-700 hover:from-amber-700 hover:to-amber-800 text-white font-semibold shadow-lg transform hover:scale-105 transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:scale-100"
                >
                  <span v-if="processing">
                    <i class="fa-solid fa-spinner fa-spin mr-2"></i>
                    Processing...
                  </span>
                  <span v-else>
                    <i class="fa-solid fa-check mr-2"></i>
                    {{ editingBook ? 'Update Book' : 'Create Book' }}
                  </span>
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Swal from 'sweetalert2';

const props = defineProps({
  books: Array,
  outlets: Array,
  filters: Object,
});

const showBookModal = ref(false);
const editingBook = ref(null);
const processing = ref(false);
const outletFilter = ref(props.filters?.outlet || '');

const bookForm = ref({
  name: '',
  description: '',
  status: 'active',
  outlet_ids: [],
});

const openCreateModal = () => {
  editingBook.value = null;
  bookForm.value = {
    name: '',
    description: '',
    status: 'active',
    outlet_ids: [],
  };
  showBookModal.value = true;
};

const editBook = (book) => {
  editingBook.value = book;
  bookForm.value = {
    name: book.name,
    description: book.description || '',
    status: book.status,
    outlet_ids: book.outlets?.map(outlet => outlet.id_outlet) || [],
  };
  showBookModal.value = true;
};

const closeBookModal = () => {
  showBookModal.value = false;
  editingBook.value = null;
};

const submitBook = () => {
  processing.value = true;
  const url = editingBook.value
    ? `/menu-book/${editingBook.value.id}`
    : '/menu-book';
  
  const method = editingBook.value ? 'put' : 'post';
  
  const formData = {
    ...bookForm.value,
    outlet_ids: JSON.stringify(bookForm.value.outlet_ids),
  };
  
  router[method](url, formData, {
    onSuccess: () => {
      closeBookModal();
      Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: editingBook.value ? 'Menu book updated successfully!' : 'Menu book created successfully!',
        timer: 2000,
        showConfirmButton: false,
      });
    },
    onError: () => {
      processing.value = false;
    },
    onFinish: () => {
      processing.value = false;
    },
  });
};

const handleFilter = () => {
  router.get('/menu-book', {
    outlet: outletFilter.value,
  }, {
    preserveState: true,
    preserveScroll: true,
  });
};

const viewBook = (bookId) => {
  router.visit(`/menu-book/${bookId}`);
};

const deleteBook = (book) => {
  Swal.fire({
    title: 'Are you sure?',
    text: `Delete "${book.name}"? This will also delete all pages in this book!`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Yes, delete it!',
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(`/menu-book/${book.id}`, {
        onSuccess: () => {
          Swal.fire('Deleted!', 'Menu book has been deleted.', 'success');
        },
      });
    }
  });
};

const openLandingPage = () => {
  // Open landing page in new tab
  window.open('/menu', '_blank');
};
</script>
