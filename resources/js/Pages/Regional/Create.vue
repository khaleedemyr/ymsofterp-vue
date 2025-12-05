<template>
  <AppLayout>
    <div class="w-full py-8 px-4">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-globe"></i> Assign Regional Outlets
        </h1>
        <Link :href="route('regional.index')" class="bg-gradient-to-r from-gray-500 to-gray-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
          <i class="fa fa-arrow-left mr-1"></i> Kembali
        </Link>
      </div>

      <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="p-6">
          <form @submit.prevent="submitForm">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
              <!-- User Selection -->
              <div class="space-y-2">
                <label class="block text-sm font-semibold text-gray-700">
                  <i class="fa-solid fa-user mr-2"></i>Pilih User
                </label>
                <Multiselect
                  v-model="form.user_id"
                  :options="userOptions"
                  :searchable="true"
                  :close-on-select="true"
                  :clear-on-select="false"
                  :preserve-search="true"
                  placeholder="Ketik nama atau email user..."
                  track-by="name"
                  label="name"
                  :preselect-first="false"
                  class="w-full"
                />
                <p v-if="form.errors && form.errors.user_id" class="text-red-500 text-xs mt-1">{{ form.errors.user_id }}</p>
              </div>

              <!-- Outlet Selection -->
              <div class="space-y-2">
                <label class="block text-sm font-semibold text-gray-700">
                  <i class="fa-solid fa-store mr-2"></i>Pilih Outlets
                </label>
                <Multiselect
                  v-model="form.outlet_ids"
                  :options="outletOptions"
                  :multiple="true"
                  :searchable="true"
                  :close-on-select="false"
                  :clear-on-select="false"
                  :preserve-search="true"
                  placeholder="Pilih outlet yang akan di-assign..."
                  track-by="nama_outlet"
                  label="nama_outlet"
                  :preselect-first="false"
                  class="w-full"
                />
                <p v-if="form.errors && form.errors.outlet_ids" class="text-red-500 text-xs mt-1">{{ form.errors.outlet_ids }}</p>
              </div>
            </div>

            <!-- Selected Info -->
            <div v-if="form.user_id || (form.outlet_ids && form.outlet_ids.length > 0)" class="mt-6 p-6 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl border border-blue-200 shadow-sm">
              <div class="flex items-center mb-4">
                <div class="bg-blue-100 p-2 rounded-lg mr-3">
                  <i class="fa-solid fa-check-circle text-blue-600"></i>
                </div>
                <h3 class="text-lg font-semibold text-blue-800">
                  Preview Assignment
                </h3>
              </div>
              
              <div class="space-y-4">
                <!-- User Info -->
                <div v-if="form.user_id" class="bg-white p-4 rounded-lg border border-blue-100">
                  <div class="flex items-center">
                    <div class="bg-green-100 p-2 rounded-full mr-3">
                      <i class="fa-solid fa-user text-green-600"></i>
                    </div>
                    <div>
                      <p class="text-sm text-gray-600">Selected User</p>
                      <p class="font-semibold text-gray-800">{{ getUserName(form.user_id) }}</p>
                    </div>
                  </div>
                </div>

                <!-- Outlets Info -->
                <div v-if="form.outlet_ids && form.outlet_ids.length > 0" class="bg-white p-4 rounded-lg border border-blue-100">
                  <div class="flex items-center mb-3">
                    <div class="bg-orange-100 p-2 rounded-full mr-3">
                      <i class="fa-solid fa-store text-orange-600"></i>
                    </div>
                    <div>
                      <p class="text-sm text-gray-600">Selected Outlets</p>
                      <p class="font-semibold text-gray-800">{{ form.outlet_ids.length }} outlet(s) selected</p>
                    </div>
                  </div>
                  
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                    <div v-for="(outletId, index) in form.outlet_ids" :key="outletId" class="flex items-center bg-blue-50 p-3 rounded-lg border border-blue-100">
                      <div class="bg-blue-100 p-1 rounded-full mr-2">
                        <i class="fa-solid fa-building text-blue-600 text-xs"></i>
                      </div>
                      <span class="text-sm font-medium text-blue-800">{{ getOutletName(outletId) }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex justify-end gap-3 mt-8 pt-6 border-t border-gray-200">
              <button 
                type="button" 
                @click="goBack" 
                class="px-6 py-3 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 font-semibold shadow-sm transition-all"
              >
                <i class="fa fa-times mr-2"></i>Batal
              </button>
              <button 
                type="submit" 
                :disabled="!canSubmit || isSubmitting"
                class="px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg font-bold shadow-lg hover:shadow-xl transition-all disabled:from-gray-400 disabled:to-gray-500 disabled:cursor-not-allowed"
              >
                <i v-if="isSubmitting" class="fa fa-spinner fa-spin mr-2"></i>
                <i v-else class="fa fa-save mr-2"></i>
                {{ isSubmitting ? 'Menyimpan...' : 'Simpan Assignment' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Multiselect from 'vue-multiselect';
import { ref, computed, onMounted, watch } from 'vue';
import { router, Link, useForm } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

// Import Multiselect CSS
import 'vue-multiselect/dist/vue-multiselect.min.css';

const props = defineProps({
  outlets: Array
});

const form = useForm({
  user_id: null,
  outlet_ids: []
});

const userOptions = ref([]);
const loadingUsers = ref(false);
const isSubmitting = ref(false);

const outletOptions = computed(() => {
  return props.outlets.map(outlet => ({
    id_outlet: outlet.id_outlet,
    nama_outlet: outlet.nama_outlet
  }));
});

const canSubmit = computed(() => {
  return form.user_id && form.outlet_ids.length > 0;
});

const getUserName = (userId) => {
  if (!userId) return '';
  // Handle both object and ID cases
  if (typeof userId === 'object' && userId.name) {
    return userId.name;
  }
  const user = userOptions.value.find(u => u.id === userId);
  return user ? user.name : '';
};

const getOutletName = (outletId) => {
  if (!outletId) return '';
  // Handle both object and ID cases
  if (typeof outletId === 'object' && outletId.nama_outlet) {
    return outletId.nama_outlet;
  }
  const outlet = outletOptions.value.find(o => o.id_outlet === outletId);
  return outlet ? outlet.nama_outlet : '';
};

// Load initial users on mount
onMounted(() => {
  console.log('Props outlets:', props.outlets);
  console.log('Outlet options:', outletOptions.value);
  loadUsers();
});

// Watch form changes
watch(() => form.user_id, (newVal) => {
  console.log('User selected:', newVal, typeof newVal);
});

watch(() => form.outlet_ids, (newVal) => {
  console.log('Outlets selected:', newVal, typeof newVal);
}, { deep: true });

const loadUsers = async () => {
  loadingUsers.value = true;
  try {
    const response = await fetch(`/api/regional/search-users?search=`);
    const users = await response.json();
    console.log('Users response:', users);
    userOptions.value = users;
  } catch (error) {
    console.error('Error loading users:', error);
  } finally {
    loadingUsers.value = false;
  }
};

function submitForm() {
  if (!form.user_id) {
    Swal.fire({
      icon: 'warning',
      title: 'Peringatan',
      text: 'Pilih user terlebih dahulu!',
      confirmButtonColor: '#3b82f6'
    });
    return;
  }
  
  if (form.outlet_ids.length === 0) {
    Swal.fire({
      icon: 'warning',
      title: 'Peringatan',
      text: 'Pilih minimal satu outlet!',
      confirmButtonColor: '#3b82f6'
    });
    return;
  }

  // Show confirmation dialog
  Swal.fire({
    title: 'Konfirmasi Assignment',
    html: `
      <div class="text-left">
        <p class="mb-2"><strong>User:</strong> ${getUserName(form.user_id)}</p>
        <p class="mb-2"><strong>Outlets:</strong> ${form.outlet_ids.length} outlet dipilih</p>
        <div class="text-sm text-gray-600">
          ${form.outlet_ids.map(outletId => getOutletName(outletId)).join(', ')}
        </div>
      </div>
    `,
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#3b82f6',
    cancelButtonColor: '#6b7280',
    confirmButtonText: 'Ya, Simpan!',
    cancelButtonText: 'Batal',
    showLoaderOnConfirm: true,
    preConfirm: () => {
      return new Promise((resolve) => {
        // Transform form data to correct format
        const submitData = {
          user_id: typeof form.user_id === 'object' ? form.user_id.id : form.user_id,
          outlet_ids: form.outlet_ids.map(outlet => 
            typeof outlet === 'object' ? outlet.id_outlet : outlet
          )
        };

        console.log('Submitting form data:', submitData);
        isSubmitting.value = true;

        // Use router.post instead of form.post to send custom data
        router.post('/regional', submitData, {
          onSuccess: () => {
            isSubmitting.value = false;
            Swal.fire({
              icon: 'success',
              title: 'Berhasil!',
              text: 'Regional assignment berhasil disimpan!',
              confirmButtonColor: '#10b981'
            });
            resolve();
          },
          onError: (errors) => {
            isSubmitting.value = false;
            console.error('Validation errors:', errors);
            Swal.fire({
              icon: 'error',
              title: 'Error!',
              text: 'Gagal menyimpan assignment. Silakan coba lagi.',
              confirmButtonColor: '#ef4444'
            });
            resolve();
          }
        });
      });
    },
    allowOutsideClick: () => !isSubmitting.value
  });
}

function goBack() {
  router.get('/regional');
}
</script>

<style scoped>
/* Custom styling for vue-multiselect */
:deep(.multiselect) {
  min-height: 48px;
  border: 1px solid #d1d5db;
  border-radius: 0.5rem;
}

:deep(.multiselect:focus-within) {
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

:deep(.multiselect__placeholder) {
  color: #6b7280;
  font-size: 0.875rem;
  padding: 12px 16px;
}

:deep(.multiselect__single) {
  padding: 12px 16px;
  font-size: 0.875rem;
  color: #374151;
}

:deep(.multiselect__input) {
  padding: 12px 16px;
  font-size: 0.875rem;
}

:deep(.multiselect__content-wrapper) {
  border: 1px solid #d1d5db;
  border-radius: 0.5rem;
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
}

:deep(.multiselect__option) {
  padding: 12px 16px;
  font-size: 0.875rem;
}

:deep(.multiselect__option--highlight) {
  background: #3b82f6;
  color: white;
}

:deep(.multiselect__option--selected) {
  background: #dbeafe;
  color: #1e40af;
}

:deep(.multiselect__tags) {
  padding: 8px 16px;
  min-height: 48px;
}

:deep(.multiselect__tag) {
  background: #dbeafe;
  color: #1e40af;
  border-radius: 4px;
  padding: 4px 8px;
  margin: 2px;
  font-size: 0.75rem;
}

:deep(.multiselect__tag-icon) {
  color: #1e40af;
}

:deep(.multiselect__tag-icon:hover) {
  background: #bfdbfe;
}
</style>