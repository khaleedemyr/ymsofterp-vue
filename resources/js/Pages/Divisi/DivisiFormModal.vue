<script setup>
import { ref, watch, computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue';
import { XMarkIcon } from '@heroicons/vue/24/outline';
import axios from 'axios'; // Added axios import
import Swal from 'sweetalert2'; // Added Swal import

const props = defineProps({
  show: Boolean,
  mode: String, // 'create' | 'edit'
  divisi: Object,
});

const emit = defineEmits(['close', 'success']);

const form = useForm({
  nama_divisi: '',
  nominal_lembur: '',
  nominal_uang_makan: '',
  nominal_ph: '',
});

console.log('Form initialized with:', form.data());

const isSubmitting = ref(false);

const modalTitle = computed(() => {
  return props.mode === 'create' ? 'Buat Divisi Baru' : 'Edit Divisi';
});

const submitButtonText = computed(() => {
  return isSubmitting.value ? 'Menyimpan...' : (props.mode === 'create' ? 'Buat Divisi' : 'Update Divisi');
});

watch(() => props.show, (show) => {
  console.log('Modal show changed:', show);
  console.log('Props divisi:', props.divisi);
  
  if (show && props.divisi) {
    // Edit mode - populate form
    console.log('Populating form with divisi data:', {
      nama_divisi: props.divisi.nama_divisi,
      nominal_lembur: props.divisi.nominal_lembur,
      nominal_uang_makan: props.divisi.nominal_uang_makan,
      nominal_ph: props.divisi.nominal_ph,
    });
    
    form.nama_divisi = props.divisi.nama_divisi;
    form.nominal_lembur = formatNumber(String(props.divisi.nominal_lembur || ''));
    form.nominal_uang_makan = formatNumber(String(props.divisi.nominal_uang_makan || ''));
    form.nominal_ph = props.divisi.nominal_ph ? formatNumber(String(props.divisi.nominal_ph)) : '';
    
    console.log('Form after population:', {
      nama_divisi: form.nama_divisi,
      nominal_lembur: form.nominal_lembur,
      nominal_uang_makan: form.nominal_uang_makan,
      nominal_ph: form.nominal_ph,
    });
  } else if (show && !props.divisi) {
    // Create mode - reset form
    console.log('Resetting form for create mode');
    form.reset();
  }
});

function closeModal() {
  emit('close');
  form.reset();
}

function reloadPage() {
  window.location.reload();
}

function submit() {
  isSubmitting.value = true;
  
  console.log('Submitting form:', {
    mode: props.mode,
    divisiId: props.divisi?.id,
    formData: form.data()
  });
  
  console.log('Form values before cleaning:', {
    nama_divisi: form.nama_divisi,
    nominal_lembur: form.nominal_lembur,
    nominal_uang_makan: form.nominal_uang_makan,
    nominal_ph: form.nominal_ph,
  });
  
  // Clean nominal values before sending
  const cleanForm = {
    nama_divisi: form.nama_divisi,
    nominal_lembur: String(form.nominal_lembur || '').replace(/\./g, ''),
    nominal_uang_makan: String(form.nominal_uang_makan || '').replace(/\./g, ''),
    nominal_ph: form.nominal_ph ? String(form.nominal_ph).replace(/\./g, '') : null,
  };
  
  console.log('Clean form data:', cleanForm);
  
  // Get CSRF token
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
  console.log('CSRF Token:', csrfToken);
  
  if (props.mode === 'create') {
    // Use axios for debugging
    console.log('Sending POST request to: /divisis');
    
    axios.post('/divisis', cleanForm, {
      headers: {
        'X-CSRF-TOKEN': csrfToken,
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      }
    })
      .then(response => {
        console.log('Create success:', response);
        Swal.fire('Berhasil', response.data.message, 'success').then(() => {
          setTimeout(() => {
            reloadPage();
          }, 100);
        });
      })
      .catch(error => {
        console.error('Create error:', error);
        console.error('Error response:', error.response);
        if (error.response?.data?.errors) {
          // Handle validation errors
          Object.keys(error.response.data.errors).forEach(field => {
            form.setError(field, error.response.data.errors[field][0]);
          });
        } else {
          Swal.fire('Error', error.response?.data?.message || 'Terjadi kesalahan', 'error');
        }
      })
      .finally(() => {
        isSubmitting.value = false;
      });
  } else {
    // Use axios for debugging
    console.log('Sending PUT request to:', `/divisis/${props.divisi.id}`);
    
    // Add _method field for PUT request
    const formData = { ...cleanForm, _method: 'PUT' };
    
    axios.post(`/divisis/${props.divisi.id}`, formData, {
      headers: {
        'X-CSRF-TOKEN': csrfToken,
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      }
    })
      .then(response => {
        console.log('Update success:', response);
        Swal.fire('Berhasil', response.data.message, 'success').then(() => {
          setTimeout(() => {
            reloadPage();
          }, 100);
        });
      })
      .catch(error => {
        console.error('Update error:', error);
        console.error('Error response:', error.response);
        if (error.response?.data?.errors) {
          // Handle validation errors
          Object.keys(error.response.data.errors).forEach(field => {
            form.setError(field, error.response.data.errors[field][0]);
          });
        } else {
          Swal.fire('Error', error.response?.data?.message || 'Terjadi kesalahan', 'error');
        }
      })
      .finally(() => {
        isSubmitting.value = false;
      });
  }
}

function formatNumber(value) {
  // Handle null, undefined, or empty values
  if (!value && value !== 0) return '';
  
  // Convert to string and remove non-numeric characters
  const numericValue = String(value).replace(/[^\d]/g, '');
  
  // If no numeric value, return empty string
  if (!numericValue) return '';
  
  // Format with thousand separators
  return numericValue.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

function handleNominalInput(field, event) {
  const value = event.target.value || '';
  const formatted = formatNumber(value);
  form[field] = formatted;
}
</script>

<template>
  <TransitionRoot as="template" :show="show">
    <Dialog as="div" class="relative z-50" @close="closeModal">
      <TransitionChild as="template" enter="ease-out duration-300" enter-from="opacity-0" enter-to="opacity-100" leave="ease-in duration-200" leave-from="opacity-100" leave-to="opacity-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" />
      </TransitionChild>

      <div class="fixed inset-0 z-10 overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
          <TransitionChild as="template" enter="ease-out duration-300" enter-from="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" enter-to="opacity-100 translate-y-0 sm:scale-100" leave="ease-in duration-200" leave-from="opacity-100 translate-y-0 sm:scale-100" leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
            <DialogPanel class="relative transform overflow-hidden rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6">
              <div class="absolute right-0 top-0 hidden pr-4 pt-4 sm:block">
                <button type="button" class="rounded-md bg-white text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2" @click="closeModal">
                  <span class="sr-only">Close</span>
                  <XMarkIcon class="h-6 w-6" aria-hidden="true" />
                </button>
              </div>
              <div class="sm:flex sm:items-start">
                <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-blue-100 sm:mx-0 sm:h-10 sm:w-10">
                  <i class="fa-solid fa-building text-blue-600"></i>
                </div>
                <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left w-full">
                  <DialogTitle as="h3" class="text-base font-semibold leading-6 text-gray-900">
                    {{ modalTitle }}
                  </DialogTitle>
                  <div class="mt-4">
                    <form @submit.prevent="submit" class="space-y-4">
                      <!-- Nama Divisi -->
                      <div>
                        <label for="nama_divisi" class="block text-sm font-medium text-gray-700 mb-1">
                          Nama Divisi <span class="text-red-500">*</span>
                        </label>
                        <input
                          id="nama_divisi"
                          v-model="form.nama_divisi"
                          type="text"
                          class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                          :class="{ 'border-red-500': form.errors.nama_divisi }"
                          placeholder="Masukkan nama divisi"
                        />
                        <p v-if="form.errors.nama_divisi" class="mt-1 text-sm text-red-600">
                          {{ form.errors.nama_divisi }}
                        </p>
                      </div>

                      <!-- Nominal Lembur -->
                      <div>
                        <label for="nominal_lembur" class="block text-sm font-medium text-gray-700 mb-1">
                          Nominal Lembur <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                          <span class="absolute left-3 top-2 text-gray-500">Rp</span>
                          <input
                            id="nominal_lembur"
                            :value="form.nominal_lembur"
                            @input="handleNominalInput('nominal_lembur', $event)"
                            type="text"
                            class="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                            :class="{ 'border-red-500': form.errors.nominal_lembur }"
                            placeholder="0"
                          />
                        </div>
                        <p v-if="form.errors.nominal_lembur" class="mt-1 text-sm text-red-600">
                          {{ form.errors.nominal_lembur }}
                        </p>
                      </div>

                      <!-- Nominal Uang Makan -->
                      <div>
                        <label for="nominal_uang_makan" class="block text-sm font-medium text-gray-700 mb-1">
                          Nominal Uang Makan <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                          <span class="absolute left-3 top-2 text-gray-500">Rp</span>
                          <input
                            id="nominal_uang_makan"
                            :value="form.nominal_uang_makan"
                            @input="handleNominalInput('nominal_uang_makan', $event)"
                            type="text"
                            class="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                            :class="{ 'border-red-500': form.errors.nominal_uang_makan }"
                            placeholder="0"
                          />
                        </div>
                        <p v-if="form.errors.nominal_uang_makan" class="mt-1 text-sm text-red-600">
                          {{ form.errors.nominal_uang_makan }}
                        </p>
                      </div>

                      <!-- Nominal PH -->
                      <div>
                        <label for="nominal_ph" class="block text-sm font-medium text-gray-700 mb-1">
                          Nominal PH
                        </label>
                        <div class="relative">
                          <span class="absolute left-3 top-2 text-gray-500">Rp</span>
                          <input
                            id="nominal_ph"
                            :value="form.nominal_ph"
                            @input="handleNominalInput('nominal_ph', $event)"
                            type="text"
                            class="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                            :class="{ 'border-red-500': form.errors.nominal_ph }"
                            placeholder="0"
                          />
                        </div>
                        <p v-if="form.errors.nominal_ph" class="mt-1 text-sm text-red-600">
                          {{ form.errors.nominal_ph }}
                        </p>
                      </div>

                      <!-- Action Buttons -->
                      <div class="mt-6 flex justify-end gap-3">
                        <button
                          type="button"
                          class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                          @click="closeModal"
                        >
                          Batal
                        </button>
                        <button
                          type="submit"
                          :disabled="isSubmitting"
                          class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                          {{ submitButtonText }}
                        </button>
                      </div>
                    </form>
                  </div>
                </div>
              </div>
            </DialogPanel>
          </TransitionChild>
        </div>
      </div>
    </Dialog>
  </TransitionRoot>
</template> 