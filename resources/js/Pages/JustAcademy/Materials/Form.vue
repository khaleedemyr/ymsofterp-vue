<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import JaLayout from '@/Components/JustAcademy/JaLayout.vue';
import {
  jaUi,
  jaFormErrors,
  jaToastSuccess,
  jaConfirm,
  jaOpenProgressDialog,
  jaHandleUploadError,
} from '@/composables/useJustAcademyUi';
import { VIDEO_TUTORIAL_MAX_BYTES } from '@/utils/videoTutorialCompress.js';

const props = defineProps({ material: Object });

const title = ref(props.material?.title || '');
const type = ref(props.material?.type || 'pdf');
const url = ref(props.material?.url || '');
const description = ref(props.material?.description || '');
const isActive = ref(props.material?.is_active ?? true);
const selectedFile = ref(null);
const isSubmitting = ref(false);
const compressingVideo = ref(false);
const uploadPercent = ref(0);

const isVideo = computed(() => type.value === 'video');
const fileLabel = computed(() => {
  if (selectedFile.value) return selectedFile.value.name;
  if (props.material?.file_path) return props.material.file_path.split('/').pop();
  return isVideo.value ? 'Klik untuk memilih file video' : 'Klik untuk memilih file';
});

function updateCompressSwal(dialog, message, ratio) {
  dialog.setProgress(Math.round(ratio * 100), message);
}

async function handleFileChange(event) {
  const file = event.target.files[0];
  if (!file) return;

  if (isVideo.value) {
    const allowed = ['video/mp4', 'video/webm', 'video/avi', 'video/quicktime', 'video/x-msvideo'];
    if (!allowed.includes(file.type) && !/\.(mp4|webm|avi|mov)$/i.test(file.name)) {
      jaFormErrors({ file: ['Tipe video tidak didukung. Gunakan MP4, WebM, AVI, atau MOV.'] });
      event.target.value = '';
      return;
    }

    compressingVideo.value = true;
    try {
      let processed = file;
      if (file.size > VIDEO_TUTORIAL_MAX_BYTES) {
        const dialog = jaOpenProgressDialog('Mengompres video…', 'File melebihi 100 MB — kompresi otomatis di browser.');
        const { ensureVideoTutorialFileUnderMax } = await import('@/utils/videoTutorialCompress.js');
        processed = await ensureVideoTutorialFileUnderMax(file, {
          onProgress: ({ message, ratio }) => updateCompressSwal(dialog, message, ratio),
        });
        dialog.close();
      }

      if (processed.size > VIDEO_TUTORIAL_MAX_BYTES) {
        jaFormErrors({ file: ['Setelah dikompres video masih di atas 100 MB. Gunakan file lebih pendek atau isi URL.'] });
        event.target.value = '';
        return;
      }

      selectedFile.value = processed;
    } catch (e) {
      jaHandleUploadError({ message: e?.message || 'Gagal memproses video' });
      event.target.value = '';
    } finally {
      compressingVideo.value = false;
    }
    return;
  }

  if (file.size > 50 * 1024 * 1024) {
    jaFormErrors({ file: ['Ukuran file maksimal 50 MB untuk tipe ini.'] });
    event.target.value = '';
    return;
  }

  selectedFile.value = file;
}

async function submit() {
  if (!title.value.trim()) {
    jaFormErrors({ title: ['Judul wajib diisi'] });
    return;
  }

  if (isVideo.value && !selectedFile.value && !url.value && !props.material?.file_path) {
    jaFormErrors({ file: ['Pilih file video atau isi URL'] });
    return;
  }

  if (selectedFile.value) {
    const confirmed = await jaConfirm({
      title: 'Konfirmasi Upload',
      text: `Upload "${selectedFile.value.name}"? Proses ini mungkin memakan waktu beberapa menit.`,
      confirmText: 'Ya, Upload',
    });
    if (!confirmed.isConfirmed) return;
    await submitWithAxios();
    return;
  }

  await submitWithAxios();
}

async function submitWithAxios() {
  isSubmitting.value = true;
  uploadPercent.value = 0;

  const progress = jaOpenProgressDialog(
    selectedFile.value ? 'Mengupload file…' : 'Menyimpan…',
    selectedFile.value ? 'Mohon tunggu, file sedang diunggah…' : 'Menyimpan data materi…',
  );

  const formData = new FormData();
  formData.append('title', title.value);
  formData.append('type', type.value);
  formData.append('url', url.value || '');
  formData.append('description', description.value || '');
  formData.append('is_active', isActive.value ? '1' : '0');
  if (selectedFile.value) {
    formData.append('file', selectedFile.value);
  }

  const uploadUrl = props.material
    ? route('just-academy.materials.update', props.material.id)
    : route('just-academy.materials.store');

  if (props.material) {
    formData.append('_method', 'PUT');
  }

  try {
    const { data } = await axios.post(uploadUrl, formData, {
      headers: { Accept: 'application/json', 'Content-Type': 'multipart/form-data' },
      onUploadProgress: (e) => {
        if (!e.total) return;
        const pct = Math.round((e.loaded / e.total) * 100);
        uploadPercent.value = pct;
        progress.setProgress(pct, `Mengupload… ${pct}%`);
      },
    });

    progress.close();
    jaToastSuccess(data.message || 'Materi berhasil disimpan');
    router.visit(data.redirect || route('just-academy.materials.index'));
  } catch (error) {
    progress.close();
    jaHandleUploadError(error);
  } finally {
    isSubmitting.value = false;
  }
}
</script>

<template>
  <JaLayout
    :title="material ? 'Edit Materi' : 'Materi Baru'"
    subtitle="Upload file atau tautan untuk pustaka training"
    icon="fa-solid fa-file-lines"
    narrow
  >
    <form :class="[jaUi.card, jaUi.cardBody, 'space-y-5']" @submit.prevent="submit">
      <div>
        <label :class="jaUi.label">Judul</label>
        <input v-model="title" :class="jaUi.input" required :disabled="isSubmitting || compressingVideo" />
      </div>

      <div>
        <label :class="jaUi.label">Tipe</label>
        <select v-model="type" :class="jaUi.input" :disabled="isSubmitting || compressingVideo">
          <option value="pdf">PDF</option>
          <option value="video">Video</option>
          <option value="link">Link</option>
          <option value="doc">Dokumen</option>
          <option value="other">Lainnya</option>
        </select>
      </div>

      <div>
        <label :class="jaUi.label">{{ isVideo ? 'File Video' : 'File' }}</label>
        <div
          class="rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50/50 p-6 text-center transition hover:border-indigo-400"
          :class="{ 'opacity-60 pointer-events-none': isSubmitting || compressingVideo }"
        >
          <input
            id="ja-material-file"
            type="file"
            class="hidden"
            :accept="isVideo ? 'video/mp4,video/webm,video/avi,video/quicktime,.mov' : undefined"
            :disabled="isSubmitting || compressingVideo"
            @change="handleFileChange"
          />
          <label for="ja-material-file" class="cursor-pointer">
            <div v-if="compressingVideo" class="flex flex-col items-center gap-3 py-2">
              <i class="fa-solid fa-spinner fa-spin text-3xl text-indigo-500" />
              <p class="text-sm font-medium text-slate-700">Mengompres video…</p>
            </div>
            <template v-else>
              <i
                class="mb-3 text-4xl"
                :class="isVideo ? 'fa-solid fa-cloud-upload-alt text-indigo-400' : 'fa-solid fa-file-arrow-up text-slate-400'"
              />
              <p class="text-base font-medium text-slate-800">{{ fileLabel }}</p>
              <p v-if="isVideo" class="mt-2 text-xs text-slate-500">
                MP4, WebM, AVI, MOV — target maks. 100 MB (lebih besar dikompres otomatis di browser)
              </p>
              <p v-else class="mt-2 text-xs text-slate-500">Maksimal 50 MB</p>
            </template>
          </label>
        </div>
        <p v-if="material?.file_path && !selectedFile" class="mt-2 text-xs text-slate-500">
          File saat ini: {{ material.file_path }}
        </p>
      </div>

      <div v-if="isSubmitting && uploadPercent > 0" class="rounded-xl bg-indigo-50 p-4">
        <div class="mb-2 flex items-center justify-between text-xs font-medium text-indigo-800">
          <span class="flex items-center gap-2"><i class="fa-solid fa-spinner fa-spin" /> Mengupload</span>
          <span>{{ uploadPercent }}%</span>
        </div>
        <div class="h-2 overflow-hidden rounded-full bg-indigo-100">
          <div
            class="h-2 rounded-full bg-gradient-to-r from-indigo-500 to-violet-600 transition-all duration-200"
            :style="{ width: uploadPercent + '%' }"
          />
        </div>
      </div>

      <div>
        <label :class="jaUi.label">URL {{ isVideo ? '(alternatif jika video di hosting luar)' : '(opsional)' }}</label>
        <input v-model="url" :class="jaUi.input" :disabled="isSubmitting || compressingVideo" placeholder="https://..." />
      </div>

      <div>
        <label :class="jaUi.label">Deskripsi</label>
        <textarea v-model="description" rows="3" :class="jaUi.input" :disabled="isSubmitting || compressingVideo" />
      </div>

      <label class="flex items-center gap-2 text-sm text-slate-600">
        <input v-model="isActive" type="checkbox" class="rounded border-slate-300 text-indigo-600" :disabled="isSubmitting || compressingVideo" />
        Aktif
      </label>

      <button type="submit" :class="jaUi.btnPrimary" :disabled="isSubmitting || compressingVideo">
        <i v-if="isSubmitting || compressingVideo" class="fa-solid fa-spinner fa-spin" />
        <i v-else class="fa-solid fa-upload" />
        {{ isSubmitting ? 'Mengupload…' : compressingVideo ? 'Mengompres…' : 'Simpan' }}
      </button>
    </form>
  </JaLayout>
</template>
