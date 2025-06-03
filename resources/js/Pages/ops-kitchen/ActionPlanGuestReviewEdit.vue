<template>
  <AppLayout>
    <div class="max-w-xl w-full mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-clipboard-list text-blue-500"></i> Edit Action Plan Guest Review
        </h1>
        <button @click="$inertia.visit('/ops-kitchen/action-plan-guest-review')" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg flex items-center gap-2">
          <i class="fa-solid fa-arrow-left"></i> Kembali
        </button>
      </div>
      <div class="bg-white rounded-xl shadow-lg p-6">
        <form @submit.prevent="submitForm">
          <!-- Informasi Umum -->
          <h2 class="text-lg font-semibold text-gray-800 mb-2">Informasi Umum</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
              <label class="block text-sm font-medium text-gray-700">Outlet</label>
              <select v-model="form.outlet" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                <option value="">Pilih Outlet</option>
                <option v-for="o in props.outlets" :key="o.id_outlet" :value="o.id_outlet">{{ o.nama_outlet }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Tanggal</label>
              <input type="date" v-model="form.tanggal" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Dept. Concern</label>
              <input type="text" v-model="form.dept" readonly class="mt-1 block w-full rounded-md border-gray-300 bg-gray-50 shadow-sm" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">PIC</label>
              <select v-model="form.pic" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                <option value="">Pilih PIC</option>
                <option v-for="p in props.pics" :key="p.id" :value="p.id">{{ p.nama_lengkap }}</option>
              </select>
            </div>
          </div>

          <!-- Detail Komplain -->
          <h2 class="text-lg font-semibold text-gray-800 mb-2 mt-6">Detail Komplain</h2>
          <div class="grid grid-cols-1 gap-4 mb-4">
            <div>
              <label class="block text-sm font-medium text-gray-700">Problem</label>
              <textarea v-model="form.problem" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required></textarea>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Analisa</label>
              <textarea v-model="form.analisa" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required></textarea>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Preventive Action</label>
              <textarea v-model="form.preventive" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required></textarea>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Status</label>
              <select v-model="form.status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                <option value="">Pilih Status</option>
                <option value="Done">Done</option>
                <option value="Progress">Progress</option>
              </select>
            </div>
          </div>

          <!-- Dokumentasi -->
          <h2 class="text-lg font-semibold text-gray-800 mb-2 mt-6">Dokumentasi</h2>
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Upload Foto (max 5)</label>
            <input type="file" multiple @change="handleImageUpload" accept="image/*" class="mt-1 block w-full" />
          </div>
          <div v-if="previewImages.length > 0 || oldImages.length > 0" class="mb-4">
            <div class="font-bold mb-2">Preview Gambar:</div>
            <div class="flex flex-wrap gap-2">
              <div v-for="(img, i) in oldImages" :key="'old'+img.id" class="relative">
                <img :src="'/storage/' + img.path" style="max-width:90px;max-height:90px;border-radius:8px;object-fit:cover;" />
                <button @click.prevent="removeOldImage(img.id)" class="absolute top-1 right-1 text-red-500 bg-white rounded-full p-1"><i class="fa-solid fa-xmark"></i></button>
              </div>
              <div v-for="(img, i) in previewImages" :key="'new'+i" class="relative">
                <img :src="img" style="max-width:90px;max-height:90px;border-radius:8px;object-fit:cover;" />
                <button @click.prevent="removeImage(i)" class="absolute top-1 right-1 text-red-500 bg-white rounded-full p-1"><i class="fa-solid fa-xmark"></i></button>
              </div>
            </div>
          </div>

          <div class="mt-6 flex justify-end">
            <button
              type="submit"
              class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg flex items-center gap-2"
              :disabled="form.processing"
            >
              <i v-if="form.processing" class="fa-solid fa-spinner fa-spin"></i>
              <i v-else class="fa-solid fa-save"></i>
              {{ form.processing ? 'Menyimpan...' : 'Update' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>
<script setup>
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import Swal from 'sweetalert2'
import AppLayout from '@/Layouts/AppLayout.vue'
const props = defineProps({ plan: Object, outlets: Array, pics: Array })
const form = useForm({
  outlet: props.plan.outlet,
  tanggal: props.plan.tanggal,
  dept: props.plan.dept,
  pic: props.plan.pic,
  problem: props.plan.problem,
  analisa: props.plan.analisa,
  preventive: props.plan.preventive,
  status: props.plan.status,
  documentation: []
})
const oldImages = ref([...props.plan.images])
const deletedImages = ref([])
const previewImages = ref([])
const handleImageUpload = (event) => {
  const files = event.target.files;
  for (let i = 0; i < files.length; i++) {
    const file = files[i];
    form.documentation.push(file);
    const url = URL.createObjectURL(file);
    previewImages.value.push(url);
  }
}
const removeImage = (index) => {
  form.documentation.splice(index, 1);
  previewImages.value.splice(index, 1);
}
const removeOldImage = (id) => {
  deletedImages.value.push(id)
  const idx = oldImages.value.findIndex(img => img.id === id)
  if (idx !== -1) oldImages.value.splice(idx, 1)
}
const submitForm = async () => {
  const result = await Swal.fire({
    title: 'Konfirmasi',
    text: 'Apakah anda yakin ingin update data ini?',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Ya, Update',
    cancelButtonText: 'Batal',
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33'
  })
  if (result.isConfirmed) {
    form.put(`/ops-kitchen/action-plan-guest-review/${props.plan.id}`, {
      data: { deleted_images: deletedImages.value },
      onSuccess: () => {
        Swal.fire({
          title: 'Berhasil!',
          text: 'Data berhasil diupdate',
          icon: 'success',
          confirmButtonColor: '#3085d6'
        })
      },
      onError: (errors) => {
        Swal.fire({
          title: 'Error!',
          text: 'Terjadi kesalahan saat update data',
          icon: 'error',
          confirmButtonColor: '#3085d6'
        })
      }
    })
  }
}
</script> 