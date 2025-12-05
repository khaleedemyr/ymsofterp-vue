<template>
  <AppLayout>
    <div class="w-full min-h-screen bg-gray-50 py-8 px-2">
      <div class="max-w-7xl w-full mx-auto">
        <div class="flex justify-between items-center mb-6">
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-bars-progress text-blue-500"></i> Menu Management
          </h1>
          <button @click="showCreateModal = true" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg flex items-center gap-2 transition-all duration-200 shadow-md">
            <i class="fa-solid fa-plus"></i>
            Add Menu
          </button>
        </div>
        <div class="flex justify-between items-center mb-4">
          <input v-model="search" type="text" placeholder="Search menu name or code..." class="w-72 px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500" />
        </div>
        <div class="bg-white rounded-xl shadow-lg overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Code</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Parent</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Route</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Icon</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-if="filteredMenus.length === 0">
                <td colspan="6" class="text-center py-10 text-gray-400">Tidak ada data menu.</td>
              </tr>
              <tr v-for="menu in paginatedMenus" :key="menu.id" class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap">{{ menu.name }}</td>
                <td class="px-6 py-4 whitespace-nowrap">{{ menu.code }}</td>
                <td class="px-6 py-4 whitespace-nowrap">{{ menu.parent ? menu.parent.name : '-' }}</td>
                <td class="px-6 py-4 whitespace-nowrap">{{ menu.route }}</td>
                <td class="px-6 py-4 whitespace-nowrap"><i :class="menu.icon"></i> {{ menu.icon }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium flex justify-end gap-2">
                  <button @click="openEditModal(menu)" class="text-blue-600 hover:text-blue-900" title="Edit">
                    <i class="fa-solid fa-pen-to-square"></i>
                  </button>
                  <button @click="deleteMenu(menu.id)" class="text-red-600 hover:text-red-900" title="Delete">
                    <i class="fa-solid fa-trash"></i>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <!-- Pagination -->
        <div class="flex justify-end mt-4 gap-2">
          <button
            v-for="page in totalPages"
            :key="page"
            @click="currentPage = page"
            :class="[
              'px-3 py-1 rounded-lg border text-sm font-semibold',
              currentPage === page ? 'bg-blue-600 text-white shadow-lg' : 'bg-white text-blue-700 hover:bg-blue-50'
            ]"
          >
            {{ page }}
          </button>
        </div>
      </div>
      <!-- Modal Create Menu -->
      <transition name="fade">
        <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40">
          <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg p-8 relative animate-fadeInUp">
            <button @click="showCreateModal = false" class="absolute top-4 right-4 text-gray-400 hover:text-red-500 text-2xl transition-all">
              <i class="fa-solid fa-xmark"></i>
            </button>
            <h2 class="text-xl font-bold mb-6 flex items-center gap-2">
              <i class="fa-solid fa-plus text-blue-500"></i> Create Menu
            </h2>
            <form @submit.prevent="submitCreate">
              <div class="mb-4">
                <label class="block font-semibold mb-1 flex items-center gap-2">
                  <i class="fa-solid fa-bars text-gray-400"></i> Name
                </label>
                <input v-model="createForm.name" type="text" class="block w-full py-2 rounded-lg border border-gray-300 focus:ring-blue-500 focus:border-blue-500" required />
              </div>
              <div class="mb-4">
                <label class="block font-semibold mb-1 flex items-center gap-2">
                  <i class="fa-solid fa-code text-gray-400"></i> Code
                </label>
                <input v-model="createForm.code" type="text" class="block w-full py-2 rounded-lg border border-gray-300 focus:ring-blue-500 focus:border-blue-500" required />
              </div>
              <div class="mb-4">
                <label class="block font-semibold mb-1 flex items-center gap-2">
                  <i class="fa-solid fa-sitemap text-gray-400"></i> Parent Menu
                </label>
                <select v-model="createForm.parent_id" class="block w-full py-2 rounded-lg border border-gray-300 focus:ring-blue-500 focus:border-blue-500">
                  <option :value="null">- None -</option>
                  <option v-for="p in parentMenus" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>
              </div>
              <div class="mb-4">
                <label class="block font-semibold mb-1 flex items-center gap-2">
                  <i class="fa-solid fa-link text-gray-400"></i> Route
                </label>
                <input v-model="createForm.route" type="text" class="block w-full py-2 rounded-lg border border-gray-300 focus:ring-blue-500 focus:border-blue-500" />
              </div>
              <div class="mb-6">
                <label class="block font-semibold mb-1 flex items-center gap-2">
                  <i class="fa-solid fa-icons text-gray-400"></i> Icon
                </label>
                <input v-model="createForm.icon" type="text" class="block w-full py-2 rounded-lg border border-gray-300 focus:ring-blue-500 focus:border-blue-500" />
              </div>
              <div class="flex justify-end gap-2">
                <button type="button" @click="showCreateModal = false" class="px-4 py-2 rounded-lg bg-gray-200 text-gray-700 font-semibold hover:bg-gray-300 flex items-center gap-2 transition-all">
                  <i class="fa-solid fa-xmark"></i> Cancel
                </button>
                <button type="submit" class="px-4 py-2 rounded-lg bg-blue-600 text-white font-semibold hover:bg-blue-700 flex items-center gap-2 transition-all">
                  <i class="fa-solid fa-save"></i> Save
                </button>
              </div>
            </form>
          </div>
        </div>
      </transition>
      <!-- Modal Edit Menu -->
      <transition name="fade">
        <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40">
          <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg p-8 relative animate-fadeInUp">
            <button @click="showEditModal = false" class="absolute top-4 right-4 text-gray-400 hover:text-red-500 text-2xl transition-all">
              <i class="fa-solid fa-xmark"></i>
            </button>
            <h2 class="text-xl font-bold mb-6 flex items-center gap-2">
              <i class="fa-solid fa-pen-to-square text-blue-500"></i> Edit Menu
            </h2>
            <form @submit.prevent="submitEdit">
              <div class="mb-4">
                <label class="block font-semibold mb-1 flex items-center gap-2">
                  <i class="fa-solid fa-bars text-gray-400"></i> Name
                </label>
                <input v-model="editForm.name" type="text" class="block w-full py-2 rounded-lg border border-gray-300 focus:ring-blue-500 focus:border-blue-500" required />
              </div>
              <div class="mb-4">
                <label class="block font-semibold mb-1 flex items-center gap-2">
                  <i class="fa-solid fa-code text-gray-400"></i> Code
                </label>
                <input v-model="editForm.code" type="text" class="block w-full py-2 rounded-lg border border-gray-300 focus:ring-blue-500 focus:border-blue-500" required />
              </div>
              <div class="mb-4">
                <label class="block font-semibold mb-1 flex items-center gap-2">
                  <i class="fa-solid fa-sitemap text-gray-400"></i> Parent Menu
                </label>
                <select v-model="editForm.parent_id" class="block w-full py-2 rounded-lg border border-gray-300 focus:ring-blue-500 focus:border-blue-500">
                  <option :value="null">- None -</option>
                  <option v-for="p in parentMenus" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>
              </div>
              <div class="mb-4">
                <label class="block font-semibold mb-1 flex items-center gap-2">
                  <i class="fa-solid fa-link text-gray-400"></i> Route
                </label>
                <input v-model="editForm.route" type="text" class="block w-full py-2 rounded-lg border border-gray-300 focus:ring-blue-500 focus:border-blue-500" />
              </div>
              <div class="mb-6">
                <label class="block font-semibold mb-1 flex items-center gap-2">
                  <i class="fa-solid fa-icons text-gray-400"></i> Icon
                </label>
                <input v-model="editForm.icon" type="text" class="block w-full py-2 rounded-lg border border-gray-300 focus:ring-blue-500 focus:border-blue-500" />
              </div>
              <div class="flex justify-end gap-2">
                <button type="button" @click="showEditModal = false" class="px-4 py-2 rounded-lg bg-gray-200 text-gray-700 font-semibold hover:bg-gray-300 flex items-center gap-2 transition-all">
                  <i class="fa-solid fa-xmark"></i> Cancel
                </button>
                <button type="submit" class="px-4 py-2 rounded-lg bg-blue-600 text-white font-semibold hover:bg-blue-700 flex items-center gap-2 transition-all">
                  <i class="fa-solid fa-save"></i> Save
                </button>
              </div>
            </form>
          </div>
        </div>
      </transition>
    </div>
  </AppLayout>
</template>
<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
const props = defineProps({ menus: Array });
const search = ref('');
const currentPage = ref(1);
const perPage = 15;
const showCreateModal = ref(false);
const showEditModal = ref(false);
const createForm = ref({ name: '', code: '', parent_id: null, route: '', icon: '' });
const editForm = ref({ id: null, name: '', code: '', parent_id: null, route: '', icon: '' });
const parentMenus = computed(() => props.menus.filter(m => !m.parent_id));
const filteredMenus = computed(() => {
  if (!search.value) return props.menus;
  return props.menus.filter(menu =>
    (menu.name && menu.name.toLowerCase().includes(search.value.toLowerCase())) ||
    (menu.code && menu.code.toLowerCase().includes(search.value.toLowerCase()))
  );
});
const totalPages = computed(() => Math.ceil(filteredMenus.value.length / perPage));
const paginatedMenus = computed(() => {
  const start = (currentPage.value - 1) * perPage;
  return filteredMenus.value.slice(start, start + perPage);
});
function deleteMenu(id) {
  if (confirm('Delete this menu?')) {
    router.delete(`/menus/${id}`);
  }
}
function submitCreate() {
  router.post('/menus', createForm.value, {
    onSuccess: () => {
      showCreateModal.value = false;
      createForm.value = { name: '', code: '', parent_id: null, route: '', icon: '' };
    }
  });
}
function openEditModal(menu) {
  editForm.value = { ...menu };
  showEditModal.value = true;
}
function submitEdit() {
  router.put(`/menus/${editForm.value.id}`, editForm.value, {
    onSuccess: () => {
      showEditModal.value = false;
      editForm.value = { id: null, name: '', code: '', parent_id: null, route: '', icon: '' };
    }
  });
}
// Animasi modal
</script>
<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.3s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
@keyframes fadeInUp {
  from { opacity: 0; transform: translateY(40px); }
  to { opacity: 1; transform: translateY(0); }
}
.animate-fadeInUp { animation: fadeInUp 0.3s; }
</style>