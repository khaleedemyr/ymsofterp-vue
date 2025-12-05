<script setup>
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Swal from 'sweetalert2';

const props = defineProps({
  roulette: Object,
});

const isSubmitting = ref(false);

const form = useForm({
  nama: props.roulette?.nama || '',
  email: props.roulette?.email || '',
  no_hp: props.roulette?.no_hp || '',
});

async function submit() {
  const confirm = await Swal.fire({
    title: 'Update Data?',
    text: 'Apakah Anda yakin ingin mengupdate data roulette ini?',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Ya, Update',
    cancelButtonText: 'Batal',
  });
  if (!confirm.isConfirmed) return;

  isSubmitting.value = true;
  try {
    await form.put(route('roulette.update', props.roulette.id), {
      onSuccess: () => {
        Swal.fire('Berhasil', 'Data roulette berhasil diupdate!', 'success').then(() => {
          router.visit('/roulette');
        });
      },
      onError: (errors) => {
        Swal.fire('Gagal', 'Gagal mengupdate data roulette!', 'error');
        console.error('Update error:', errors);
      },
    });
  } catch (e) {
    Swal.fire('Gagal', 'Gagal mengupdate data roulette!', 'error');
    console.error('Update error:', e);
  } finally {
    isSubmitting.value = false;
  }
}

function cancel() {
  router.visit('/roulette');
}
</script>

<template>
  <AppLayout title="Edit Data Roulette">
    <div class="max-w-2xl mx-auto bg-white rounded-xl shadow-lg p-8 mt-8">
      <h1 class="text-2xl font-bold mb-6 text-purple-800">Edit Data Roulette</h1>
      <form @submit.prevent="submit" class="space-y-6">
        <div>
          <label class="block text-sm font-medium text-gray-700">Nama *</label>
          <input 
            v-model="form.nama" 
            class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-purple-400 focus:border-purple-400" 
            required 
            maxlength="255"
            placeholder="Masukkan nama lengkap"
          />
          <div v-if="form.errors.nama" class="text-red-500 text-sm mt-1">{{ form.errors.nama }}</div>
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700">Email</label>
          <input 
            v-model="form.email" 
            type="email" 
            class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-purple-400 focus:border-purple-400" 
            maxlength="255"
            placeholder="Masukkan email"
          />
          <div v-if="form.errors.email" class="text-red-500 text-sm mt-1">{{ form.errors.email }}</div>
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700">No HP</label>
          <input 
            v-model="form.no_hp" 
            class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-purple-400 focus:border-purple-400" 
            maxlength="15"
            placeholder="Masukkan nomor HP"
          />
          <div v-if="form.errors.no_hp" class="text-red-500 text-sm mt-1">{{ form.errors.no_hp }}</div>
        </div>
        
        <div class="flex justify-end gap-2 mt-6">
          <button 
            type="button" 
            @click="cancel" 
            class="px-4 py-2 rounded bg-gray-200 text-gray-700 hover:bg-gray-300 transition"
          >
            Batal
          </button>
          <button 
            type="submit" 
            class="px-4 py-2 rounded bg-purple-600 text-white hover:bg-purple-700 transition" 
            :disabled="isSubmitting"
          >
            <span v-if="isSubmitting">Mengupdate...</span>
            <span v-else>Update</span>
          </button>
        </div>
      </form>
    </div>
  </AppLayout>
</template> 