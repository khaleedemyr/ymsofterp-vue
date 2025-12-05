<template>
  <AppLayout>
    <div class="w-full min-h-screen bg-gray-50 py-8 px-2">
      <div class="max-w-7xl w-full mx-auto">
        <div class="flex justify-between items-center mb-6">
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-user-shield text-blue-500"></i> Role Management
          </h1>
          <button @click="showCreateModal = true" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg flex items-center gap-2 transition-all duration-200 shadow-md">
            <i class="fa-solid fa-plus"></i>
            Add Role
          </button>
        </div>
        <div class="flex justify-between items-center mb-4">
          <input v-model="search" type="text" placeholder="Search role name..." class="w-72 px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500" />
        </div>
        <div class="bg-white rounded-xl shadow-lg overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-if="filteredRoles.length === 0">
                <td colspan="3" class="text-center py-10 text-gray-400">Tidak ada data role.</td>
              </tr>
              <tr v-for="role in paginatedRoles" :key="role.id" class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap">{{ role.name }}</td>
                <td class="px-6 py-4 whitespace-nowrap">{{ role.description }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium flex justify-end gap-2">
                  <button @click="openEditModal(role)" class="text-blue-600 hover:text-blue-900" title="Edit">
                    <i class="fa-solid fa-pen-to-square"></i>
                  </button>
                  <button @click="deleteRole(role.id)" class="text-red-600 hover:text-red-900" title="Delete">
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
      <!-- Modal Create Role (Wizard Stepper) -->
      <transition name="fade">
        <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40">
          <div class="bg-white rounded-2xl shadow-2xl max-w-4xl w-full p-0 relative animate-fadeInUp flex flex-col h-[80vh]">
            <!-- Header & Stepper Sticky -->
            <div class="p-8 pb-2 sticky top-0 z-10 bg-white">
              <button @click="closeCreateModal" class="absolute top-4 right-4 text-gray-400 hover:text-red-500 text-2xl transition-all">
                <i class="fa-solid fa-xmark"></i>
              </button>
              <h2 class="text-xl font-bold mb-6 flex items-center gap-2">
                <i class="fa-solid fa-plus text-blue-500"></i> Create Role
              </h2>
              <div class="mb-4">
                <label class="block font-semibold mb-1 flex items-center gap-2">
                  <i class="fa-solid fa-user-tag text-gray-400"></i> Name
                </label>
                <input v-model="createForm.name" type="text" class="block w-full py-2 rounded-lg border border-gray-300 focus:ring-blue-500 focus:border-blue-500" required />
              </div>
              <div class="mb-4">
                <label class="block font-semibold mb-1 flex items-center gap-2">
                  <i class="fa-solid fa-align-left text-gray-400"></i> Description
                </label>
                <textarea v-model="createForm.description" rows="3" class="block w-full py-2 rounded-lg border border-gray-300 focus:ring-blue-500 focus:border-blue-500"></textarea>
              </div>
              <!-- Stepper Navigation -->
              <div class="flex justify-between mb-2 overflow-x-auto whitespace-nowrap px-2 gap-2">
                <div v-for="(parent, idx) in parentMenus" :key="parent.id" class="flex flex-col items-center min-w-[90px]">
                  <div :class="['w-8 h-8 rounded-full flex items-center justify-center font-bold', currentStep === idx ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-500']">
                    {{ idx+1 }}
                  </div>
                  <div class="text-xs mt-1 text-center truncate w-full" style="min-width: 70px">
                    {{ parent.name }}
                  </div>
                </div>
                <div class="flex flex-col items-center min-w-[90px]">
                  <div :class="['w-8 h-8 rounded-full flex items-center justify-center font-bold', isPreviewStep ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-500']">
                    {{ parentMenus.length + 1 }}
                  </div>
                  <div class="text-xs mt-1 text-center truncate w-full" style="min-width: 70px">Preview</div>
                </div>
              </div>
            </div>
            <!-- Step Content: Child Menus & Permissions (Scrollable) -->
            <form @submit.prevent="submitCreate" class="flex flex-col flex-1 min-h-0">
              <div v-if="parentMenus.length && !isPreviewStep" class="overflow-y-auto max-h-[400px] px-8 pb-4 custom-scrollbar">
                <div v-for="(parent, idx) in parentMenus" :key="parent.id" v-show="currentStep === idx">
                  <!-- Select All Parent -->
                  <div class="mb-2 flex items-center gap-2">
                    <input type="checkbox" :checked="isAllParentSelected(parent.id, createForm)" @change="toggleAllParent(parent.id, createForm)" id="select-all-parent-{{parent.id}}" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    <label :for="'select-all-parent-' + parent.id" class="font-semibold cursor-pointer">Select All</label>
                  </div>
                  <div v-for="child in childMenus(parent.id)" :key="child.id" class="mb-4 border rounded-lg p-4">
                    <div class="flex items-center gap-2 mb-2">
                      <h3 class="font-semibold flex-1">{{ child.name }}</h3>
                      <!-- Select All Menu -->
                      <input type="checkbox" :checked="isAllMenuSelected(child.id, createForm)" @change="toggleAllMenu(child.id, createForm)" :id="'select-all-menu-' + child.id" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                      <label :for="'select-all-menu-' + child.id" class="text-xs font-medium cursor-pointer">Select All</label>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                      <label v-for="action in ['view', 'create', 'update', 'delete']" :key="action" class="flex items-center gap-2">
                        <input type="checkbox"
                               :value="`${child.id}-${action}`"
                               v-model="createForm.permissions"
                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span class="text-sm capitalize">{{ action }}</span>
                      </label>
                    </div>
                  </div>
                </div>
              </div>
              <!-- Step Preview -->
              <div v-if="isPreviewStep" class="overflow-y-auto max-h-[400px] px-8 pb-4 custom-scrollbar">
                <h3 class="text-lg font-bold mb-2 flex items-center gap-2"><i class="fa-solid fa-eye text-blue-500"></i> Preview Role</h3>
                <div class="mb-4">
                  <div class="font-semibold">Name:</div>
                  <div>{{ createForm.name }}</div>
                </div>
                <div class="mb-4">
                  <div class="font-semibold">Description:</div>
                  <div>{{ createForm.description || '-' }}</div>
                </div>
                <div class="mb-4">
                  <div class="font-semibold mb-2">Permissions:</div>
                  <div v-if="createForm.permissions.length === 0" class="text-gray-400 italic">No permissions selected.</div>
                  <div v-else>
                    <div v-for="menu in menus" :key="menu.id">
                      <div v-if="['view','create','update','delete'].some(a => createForm.permissions.includes(`${menu.id}-${a}`))" class="mb-2">
                        <div class="font-semibold">{{ menu.name }}</div>
                        <div class="flex flex-wrap gap-2 mt-1">
                          <span v-for="action in ['view','create','update','delete']"
                                :key="action"
                                v-if="createForm.permissions.includes(`${menu.id}-${action}`)"
                                class="px-2 py-1 rounded bg-blue-100 text-blue-800 text-xs capitalize font-semibold border border-blue-200">
                            {{ action }}
                          </span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <!-- Stepper Navigation Button Sticky -->
              <div class="flex justify-between p-8 pt-4 sticky bottom-0 z-10 bg-white border-t">
                <button type="button" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-6 py-2 rounded-lg flex items-center gap-2" @click="prevStep" :disabled="currentStep === 0 || isSaving">
                  <i class="fa-solid fa-arrow-left"></i>
                  Sebelumnya
                </button>
                <button v-if="!isPreviewStep" type="button" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg flex items-center gap-2" @click="nextStep" :disabled="isSaving">
                  Selanjutnya
                  <i class="fa-solid fa-arrow-right"></i>
                </button>
                <button v-else type="submit" class="bg-green-500 hover:bg-green-600 text-white px-6 py-2 rounded-lg flex items-center gap-2" :disabled="isSaving">
                  <i v-if="isSaving" class="fa-solid fa-spinner fa-spin"></i>
                  <i v-else class="fa-solid fa-save"></i> Simpan
                </button>
              </div>
            </form>
          </div>
        </div>
      </transition>
      <!-- Modal Edit Role (Wizard Stepper) -->
      <transition name="fade">
        <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40">
          <div class="bg-white rounded-2xl shadow-2xl max-w-4xl w-full p-0 relative animate-fadeInUp flex flex-col h-[80vh]">
            <!-- Header & Stepper Sticky -->
            <div class="p-8 pb-2 sticky top-0 z-10 bg-white">
              <button @click="closeEditModal" class="absolute top-4 right-4 text-gray-400 hover:text-red-500 text-2xl transition-all">
                <i class="fa-solid fa-xmark"></i>
              </button>
              <h2 class="text-xl font-bold mb-6 flex items-center gap-2">
                <i class="fa-solid fa-pen-to-square text-blue-500"></i> Edit Role
              </h2>
              <div class="mb-4">
                <label class="block font-semibold mb-1 flex items-center gap-2">
                  <i class="fa-solid fa-user-tag text-gray-400"></i> Name
                </label>
                <input v-model="editForm.name" type="text" class="block w-full py-2 rounded-lg border border-gray-300 focus:ring-blue-500 focus:border-blue-500" required />
              </div>
              <div class="mb-4">
                <label class="block font-semibold mb-1 flex items-center gap-2">
                  <i class="fa-solid fa-align-left text-gray-400"></i> Description
                </label>
                <textarea v-model="editForm.description" rows="3" class="block w-full py-2 rounded-lg border border-gray-300 focus:ring-blue-500 focus:border-blue-500"></textarea>
              </div>
              <!-- Stepper Navigation -->
              <div class="flex justify-between mb-2 overflow-x-auto whitespace-nowrap px-2 gap-2">
                <div v-for="(parent, idx) in parentMenus" :key="parent.id" class="flex flex-col items-center min-w-[90px]">
                  <div :class="['w-8 h-8 rounded-full flex items-center justify-center font-bold', editStep === idx ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-500']">
                    {{ idx+1 }}
                  </div>
                  <div class="text-xs mt-1 text-center truncate w-full" style="min-width: 70px">
                    {{ parent.name }}
                  </div>
                </div>
              </div>
            </div>
            <!-- Step Content: Child Menus & Permissions (Scrollable) -->
            <form @submit.prevent="submitEdit" class="flex flex-col flex-1 min-h-0">
              <div v-if="parentMenus.length" class="overflow-y-auto max-h-[400px] px-8 pb-4 custom-scrollbar">
                <div v-for="(parent, idx) in parentMenus" :key="parent.id" v-show="editStep === idx">
                  <!-- Select All Parent -->
                  <div class="mb-2 flex items-center gap-2">
                    <input type="checkbox" :checked="isAllParentSelected(parent.id, editForm)" @change="toggleAllParent(parent.id, editForm)" id="select-all-parent-{{parent.id}}" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    <label :for="'select-all-parent-' + parent.id" class="font-semibold cursor-pointer">Select All</label>
                  </div>
                  <div v-for="child in childMenus(parent.id)" :key="child.id" class="mb-4 border rounded-lg p-4">
                    <div class="flex items-center gap-2 mb-2">
                      <h3 class="font-semibold flex-1">{{ child.name }}</h3>
                      <!-- Select All Menu -->
                      <input type="checkbox" :checked="isAllMenuSelected(child.id, editForm)" @change="toggleAllMenu(child.id, editForm)" :id="'select-all-menu-' + child.id" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                      <label :for="'select-all-menu-' + child.id" class="text-xs font-medium cursor-pointer">Select All</label>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                      <label v-for="action in ['view', 'create', 'update', 'delete']" :key="action" class="flex items-center gap-2">
                        <input type="checkbox"
                               :value="`${child.id}-${action}`"
                               v-model="editForm.permissions"
                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span class="text-sm capitalize">{{ action }}</span>
                      </label>
                    </div>
                  </div>
                </div>
              </div>
              <!-- Step Preview -->
              <div v-if="isEditPreviewStep" class="overflow-y-auto max-h-[400px] px-8 pb-4 custom-scrollbar">
                <h3 class="text-lg font-bold mb-2 flex items-center gap-2"><i class="fa-solid fa-eye text-blue-500"></i> Preview Role</h3>
                <div class="mb-4">
                  <div class="font-semibold">Name:</div>
                  <div>{{ editForm.name }}</div>
                </div>
                <div class="mb-4">
                  <div class="font-semibold">Description:</div>
                  <div>{{ editForm.description || '-' }}</div>
                </div>
                <div class="mb-4">
                  <div class="font-semibold mb-2">Permissions:</div>
                  <div v-if="editForm.permissions.length === 0" class="text-gray-400 italic">No permissions selected.</div>
                  <div v-else>
                    <div v-for="menu in menus" :key="menu.id">
                      <div v-if="['view','create','update','delete'].some(a => editForm.permissions.includes(`${menu.id}-${a}`))" class="mb-2">
                        <div class="font-semibold">{{ menu.name }}</div>
                        <div class="flex flex-wrap gap-2 mt-1">
                          <span v-for="action in ['view','create','update','delete']"
                                :key="action"
                                v-if="editForm.permissions.includes(`${menu.id}-${action}`)"
                                class="px-2 py-1 rounded bg-blue-100 text-blue-800 text-xs capitalize font-semibold border border-blue-200">
                            {{ action }}
                          </span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <!-- Stepper Navigation Button Sticky -->
              <div class="flex justify-between p-8 pt-4 sticky bottom-0 z-10 bg-white border-t">
                <button type="button" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-6 py-2 rounded-lg flex items-center gap-2" @click="prevEditStep" :disabled="editStep === 0 || isSaving">
                  <i class="fa-solid fa-arrow-left"></i>
                  Sebelumnya
                </button>
                <button v-if="editStep < parentMenus.length - 1" type="button" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg flex items-center gap-2" @click="nextEditStep" :disabled="isSaving">
                  Selanjutnya
                  <i class="fa-solid fa-arrow-right"></i>
                </button>
                <button v-else type="submit" class="bg-green-500 hover:bg-green-600 text-white px-6 py-2 rounded-lg flex items-center gap-2" :disabled="isSaving">
                  <i v-if="isSaving" class="fa-solid fa-spinner fa-spin"></i>
                  <i v-else class="fa-solid fa-save"></i> Simpan
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
import { ref, computed } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({ 
  roles: { type: Array, default: () => [] },
  menus: { type: Array, default: () => [] }
});

const search = ref('');
const currentPage = ref(1);
const perPage = 15;
const showCreateModal = ref(false);
const showEditModal = ref(false);
const createForm = ref({ name: '', description: '', permissions: [] });
const editForm = ref({ id: null, name: '', description: '', permissions: [] });
const isSaving = ref(false);

const filteredRoles = computed(() => {
  if (!search.value) return props.roles;
  return props.roles.filter(role =>
    role.name.toLowerCase().includes(search.value.toLowerCase())
  );
});

const totalPages = computed(() => Math.ceil(filteredRoles.value.length / perPage));

const paginatedRoles = computed(() => {
  const start = (currentPage.value - 1) * perPage;
  return filteredRoles.value.slice(start, start + perPage);
});

const parentMenus = computed(() => (props.menus || []).filter(m => !m.parent_id));
const childMenus = (parentId) => (props.menus || []).filter(m => m.parent_id === parentId);
const currentStep = ref(0);
const editStep = ref(0);

const previewStepIdx = computed(() => parentMenus.value.length);
const isPreviewStep = computed(() => currentStep.value === previewStepIdx.value);
const isEditPreviewStep = computed(() => editStep.value === previewStepIdx.value);

function deleteRole(id) {
  Swal.fire({
    title: 'Hapus Role?',
    text: 'Role yang dihapus tidak dapat dikembalikan!',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Ya, hapus!',
    cancelButtonText: 'Batal'
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(`/roles/${id}`, {
        onSuccess: () => {
          Swal.fire({ icon: 'success', title: 'Berhasil', text: 'Role berhasil dihapus!' });
        },
        onError: () => {
          Swal.fire({ icon: 'error', title: 'Gagal', text: 'Gagal menghapus role.' });
        }
      });
    }
  });
}

function submitCreate() {
  isSaving.value = true;
  router.post('/roles', createForm.value, {
    onSuccess: () => {
      isSaving.value = false;
      Swal.fire({ icon: 'success', title: 'Berhasil', text: 'Role berhasil disimpan!' });
      showCreateModal.value = false;
      currentStep.value = 0;
      createForm.value.permissions = [];
    },
    onError: () => {
      isSaving.value = false;
      Swal.fire({ icon: 'error', title: 'Gagal', text: 'Gagal menyimpan role.' });
    }
  });
}

function openEditModal(role) {
  editForm.value = {
    id: role.id,
    name: role.name,
    description: role.description,
    permissions: role.permissions.map(p => `${p.menu_id}-${p.action}`)
  };
  showEditModal.value = true;
}

function submitEdit() {
  isSaving.value = true;
  router.put(`/roles/${editForm.value.id}`, editForm.value, {
    onSuccess: () => {
      isSaving.value = false;
      Swal.fire({ icon: 'success', title: 'Berhasil', text: 'Role berhasil diupdate!' });
      showEditModal.value = false;
      editForm.value = { id: null, name: '', description: '', permissions: [] };
    },
    onError: () => {
      isSaving.value = false;
      Swal.fire({ icon: 'error', title: 'Gagal', text: 'Gagal update role.' });
    }
  });
}

function nextStep() {
  if (currentStep.value < previewStepIdx.value) currentStep.value++;
}
function prevStep() {
  if (currentStep.value > 0) currentStep.value--;
}
function nextEditStep() {
  if (editStep.value < previewStepIdx.value) editStep.value++;
}
function prevEditStep() {
  if (editStep.value > 0) editStep.value--;
}
function closeCreateModal() { showCreateModal.value = false; currentStep.value = 0; createForm.value.permissions = []; }
function closeEditModal() { showEditModal.value = false; editStep.value = 0; editForm.value.permissions = []; }

function parentPermissionKeys(parentId, form) {
  return childMenus(parentId).flatMap(child => ['view','create','update','delete'].map(a => `${child.id}-${a}`));
}
function isAllParentSelected(parentId, form) {
  const keys = parentPermissionKeys(parentId, form);
  return keys.length > 0 && keys.every(k => form.permissions.includes(k));
}
function toggleAllParent(parentId, form) {
  const keys = parentPermissionKeys(parentId, form);
  if (isAllParentSelected(parentId, form)) {
    form.permissions = form.permissions.filter(p => !keys.includes(p));
  } else {
    form.permissions = Array.from(new Set([...form.permissions, ...keys]));
  }
}
function menuPermissionKeys(childId) {
  return ['view','create','update','delete'].map(a => `${childId}-${a}`);
}
function isAllMenuSelected(childId, form) {
  const keys = menuPermissionKeys(childId);
  return keys.every(k => form.permissions.includes(k));
}
function toggleAllMenu(childId, form) {
  const keys = menuPermissionKeys(childId);
  if (isAllMenuSelected(childId, form)) {
    form.permissions = form.permissions.filter(p => !keys.includes(p));
  } else {
    form.permissions = Array.from(new Set([...form.permissions, ...keys]));
  }
}
</script>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.3s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
@keyframes fadeInUp {
  from { opacity: 0; transform: translateY(40px); }
  to { opacity: 1; transform: translateY(0); }
}
.animate-fadeInUp { animation: fadeInUp 0.3s; }
.custom-scrollbar {
  scrollbar-width: thin;
  scrollbar-color: #cbd5e1 #f1f5f9;
}
.custom-scrollbar::-webkit-scrollbar {
  width: 8px;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: #f1f5f9;
}
</style> 