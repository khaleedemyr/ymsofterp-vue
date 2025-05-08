<template>
  <Teleport to="body">
    <TransitionRoot appear :show="show" as="template">
      <Dialog as="div" @close="onClose" class="relative z-[99999]">
        <TransitionChild
          enter="duration-300 ease-out"
          enter-from="opacity-0"
          enter-to="opacity-100"
          leave="duration-200 ease-in"
          leave-from="opacity-100"
          leave-to="opacity-0"
        >
          <DialogOverlay class="fixed inset-0 bg-black/60" />
        </TransitionChild>
        <div class="fixed inset-0 overflow-y-auto">
          <div class="flex min-h-full items-center justify-center p-4">
            <TransitionChild
              enter="duration-300 ease-out"
              enter-from="opacity-0 scale-95"
              enter-to="opacity-100 scale-100"
              leave="duration-200 ease-in"
              leave-from="opacity-100 scale-100"
              leave-to="opacity-0 scale-95"
            >
              <DialogPanel class="w-full max-w-2xl transform overflow-hidden rounded-2xl bg-white p-6 shadow-xl transition-all">
                <div class="flex justify-between items-center mb-4">
                  <h3 class="text-lg font-bold text-gray-900">Assign Member</h3>
                  <button @click="onClose" class="text-gray-400 hover:text-red-500">
                    <i class="fas fa-times"></i>
                  </button>
                </div>
                <div class="mb-4">
                  <div class="text-sm font-semibold mb-2">Pilih Member</div>
                  <div class="grid grid-cols-2 gap-2 max-h-48 overflow-y-auto">
                    <button v-for="user in users" :key="user.id" @click="toggleUser(user)"
                      :class="['flex items-center gap-2 p-2 rounded hover:bg-blue-50 transition', isSelected(user) ? 'bg-blue-100' : 'bg-gray-50']">
                      <div class="w-8 h-8 rounded-full bg-blue-500 text-white flex items-center justify-center font-bold">
                        {{ getInitials(user.nama_lengkap) }}
                      </div>
                      <span class="truncate">{{ user.nama_lengkap }}</span>
                    </button>
                  </div>
                </div>
                <div class="mb-4">
                  <div class="text-sm font-semibold mb-2">Preview Member</div>
                  <div class="flex gap-2 flex-wrap">
                    <div v-for="user in selectedUsers" :key="user.id" class="relative group">
                      <div class="w-10 h-10 rounded-full bg-blue-600 text-white flex items-center justify-center font-bold text-base cursor-pointer">
                        {{ getInitials(user.nama_lengkap) }}
                      </div>
                      <button @click="removeUser(user)" class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center opacity-0 group-hover:opacity-100 transition">
                        <i class="fas fa-trash text-xs"></i>
                      </button>
                    </div>
                  </div>
                </div>
                <div class="flex justify-end gap-2 mt-6">
                  <button @click="props.onClose" class="px-4 py-2 rounded bg-gray-200 text-gray-700 hover:bg-gray-300">Batal</button>
                  <button @click="save" :disabled="selectedUsers.length === 0 || loading" class="px-6 py-2 rounded bg-blue-600 text-white font-semibold hover:bg-blue-700 disabled:bg-gray-300 disabled:cursor-not-allowed">
                    Simpan
                  </button>
                </div>
              </DialogPanel>
            </TransitionChild>
          </div>
        </div>
      </Dialog>
    </TransitionRoot>
  </Teleport>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue';
import { Dialog, DialogOverlay, DialogPanel, TransitionChild, TransitionRoot } from '@headlessui/vue';
import axios from 'axios';

const props = defineProps({
  show: Boolean,
  taskId: [Number, String],
  onClose: Function,
});

const emit = defineEmits(['saved']);

const users = ref([]);
const selectedUsers = ref([]);
const loading = ref(false);

function getInitials(name) {
  if (!name) return '';
  return name.split(' ').map(w => w[0]).join('').toUpperCase().slice(0,2);
}

function isSelected(user) {
  return selectedUsers.value.some(u => u.id === user.id);
}

function toggleUser(user) {
  if (isSelected(user)) {
    selectedUsers.value = selectedUsers.value.filter(u => u.id !== user.id);
  } else {
    selectedUsers.value.push(user);
  }
}

function removeUser(user) {
  selectedUsers.value = selectedUsers.value.filter(u => u.id !== user.id);
}

async function fetchUsers() {
  const { data } = await axios.get('/api/assignable-users');
  console.log('DATA USERS:', data);
  users.value = data;
}

async function fetchAssigned() {
  // Ambil member yang sudah di-assign
  const { data } = await axios.get(`/api/maintenance-members/${props.taskId}`);
  selectedUsers.value = data;
}

async function save() {
  loading.value = true;
  try {
    await axios.post('/api/assign-members', {
      task_id: props.taskId,
      user_ids: selectedUsers.value.map(u => u.id),
    });
    emit('saved');
    props.onClose();
  } finally {
    loading.value = false;
  }
}

watch(() => props.show, (val) => {
  if (val) {
    fetchUsers();
    fetchAssigned();
  }
}, { immediate: true });

onMounted(() => {
  if (props.show) {
    fetchUsers();
    fetchAssigned();
  }
});
</script> 