<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
    <div class="bg-white rounded-2xl shadow-2xl p-0 w-full max-w-3xl relative animate-fadeIn overflow-y-auto max-h-screen">
      <button @click="$emit('close')" class="absolute top-3 right-3 text-gray-400 hover:text-red-500 text-xl z-10">
        <i class="fas fa-times"></i>
      </button>
      <div class="flex flex-col">
        <!-- Tab Navigation -->
        <div class="flex border-b bg-gradient-to-r from-blue-50 to-indigo-50 rounded-t-2xl">
          <button v-for="tab in tabs" :key="tab.key" @click="activeTab = tab.key"
            :class="['flex-1 flex flex-col items-center py-3 transition-all duration-300', activeTab === tab.key ? 'bg-white shadow text-blue-700 font-bold' : 'text-gray-500 hover:text-blue-600']">
            <i :class="[tab.icon, 'text-lg mb-1']"></i>
            <span class="text-xs">{{ tab.label }}</span>
          </button>
        </div>
        <!-- Tab Content -->
        <transition name="fade-slide" mode="out-in">
          <div :key="activeTab" class="p-6">
            <template v-if="loading">
              <div class="flex items-center justify-center h-40 text-blue-400 animate-pulse"><i class="fas fa-spinner fa-spin text-3xl"></i></div>
            </template>
            <template v-else>
              <div v-if="activeTab === 'info'">
                <h2 class="text-lg font-bold mb-2">Info Task</h2>
                <div class="mb-2"><span class="font-semibold">Task Number:</span> {{ detail.info.task_number }}</div>
                <div class="mb-2"><span class="font-semibold">Created By:</span> {{ detail.info.created_by || '-' }}</div>
                <div class="mb-2"><span class="font-semibold">Title:</span> {{ detail.info.title }}</div>
                <div class="mb-2"><span class="font-semibold">Description:</span> {{ detail.info.description || '-' }}</div>
                <div class="mb-2"><span class="font-semibold">Outlet:</span> {{ detail.info.outlet_name }}</div>
                <div class="mb-2"><span class="font-semibold">Due Date:</span> {{ detail.info.due_date }}</div>
                <div class="mb-2"><span class="font-semibold">Status:</span> {{ detail.info.status }}</div>
                <div class="mb-2"><span class="font-semibold">Assigned To:</span>
                  <span v-for="member in detail.assigned_to" :key="member" class="inline-block bg-indigo-100 text-indigo-700 rounded px-2 py-0.5 mr-1 text-xs font-semibold">{{ member }}</span>
                </div>
              </div>
              <div v-else-if="activeTab === 'attachments'">
                <h2 class="text-lg font-bold mb-2">Attachments</h2>
                <div v-if="detail.attachments.length === 0 && detail.documents.length === 0" class="text-gray-400">No attachments</div>
                <div v-else>
                  <div class="flex flex-wrap gap-3 mb-3">
                    <template v-for="(file, idx) in detail.attachments" :key="file.id">
                      <template v-if="file.file_type && file.file_type.startsWith('image/')">
                        <img :src="'/storage/' + file.file_path" class="w-20 h-20 object-cover rounded shadow border cursor-pointer" @click="openLightbox(attachmentMedia, idx)" />
                      </template>
                      <template v-else-if="file.file_type && file.file_type.startsWith('video/')">
                        <video :src="'/storage/' + file.file_path" class="w-20 h-20 object-cover rounded shadow border cursor-pointer" @click="openLightbox(attachmentMedia, idx)" />
                      </template>
                      <template v-else>
                        <div class="w-20 h-20 flex items-center justify-center bg-gray-100 rounded shadow border cursor-pointer" @click="openLightbox(attachmentMedia, idx)">
                          <i class="fas fa-paperclip text-2xl text-blue-400"></i>
                        </div>
                      </template>
                    </template>
                  </div>
                  <div v-for="doc in detail.documents" :key="doc.id" class="mb-2 flex items-center gap-2">
                    <i class="fas fa-file-alt text-green-400"></i>
                    <a :href="'/storage/' + doc.file_path" target="_blank" class="underline hover:text-green-600">{{ doc.file_name }}</a>
                  </div>
                </div>
              </div>
              <div v-else-if="activeTab === 'action_plan'">
                <h2 class="text-lg font-bold mb-2">Action Plan</h2>
                <div v-if="detail.action_plans.length === 0" class="text-gray-400">No action plan</div>
                <div v-else>
                  <div v-for="ap in detail.action_plans" :key="ap.id" class="mb-4 p-3 rounded-xl bg-blue-50 shadow-sm">
                    <div class="font-semibold mb-1">{{ ap.description }}</div>
                    <div class="flex flex-wrap gap-3">
                      <template v-for="(media, idx) in ap.media" :key="media.id">
                        <template v-if="media.media_type === 'image' || media.media_type === 'photo'">
                          <img :src="'/storage/' + media.file_path" class="w-20 h-20 object-cover rounded shadow border cursor-pointer" @click="openLightbox(actionPlanMedia(ap), idx)" />
                        </template>
                        <template v-else-if="media.media_type === 'video'">
                          <video :src="'/storage/' + media.file_path" class="w-20 h-20 object-cover rounded shadow border cursor-pointer" @click="openLightbox(actionPlanMedia(ap), idx)" />
                        </template>
                        <template v-else>
                          <div class="w-20 h-20 flex items-center justify-center bg-gray-100 rounded shadow border cursor-pointer" @click="openLightbox(actionPlanMedia(ap), idx)">
                            <i class="fas fa-paperclip text-2xl text-blue-400"></i>
                          </div>
                        </template>
                      </template>
                    </div>
                  </div>
                </div>
              </div>
              <div v-else-if="activeTab === 'pr'">
                <h2 class="text-lg font-bold mb-2">Purchase Requisition (PR)</h2>
                <div v-if="detail.pr.length === 0" class="text-gray-400">No PR</div>
                <div v-else>
                  <div v-for="pr in detail.pr" :key="pr.id" class="mb-4 p-3 rounded-xl bg-yellow-50 shadow-sm">
                    <div class="font-semibold">PR Number: {{ pr.pr_number }}</div>
                    <div>Status: {{ pr.status }}</div>
                    <div>Total: {{ formatRupiah(pr.total_amount) }}</div>
                    <div class="mt-2">
                      <div class="font-semibold text-xs mb-1">Items:</div>
                      <ul class="list-disc ml-5">
                        <li v-for="item in pr.items" :key="item.id">{{ item.item_name }} ({{ item.quantity }} x {{ formatRupiah(item.price) }})</li>
                      </ul>
                    </div>
                  </div>
                </div>
              </div>
              <div v-else-if="activeTab === 'po'">
                <h2 class="text-lg font-bold mb-2">Purchase Order (PO)</h2>
                <div v-if="detail.po.length === 0" class="text-gray-400">No PO</div>
                <div v-else>
                  <div v-for="po in detail.po" :key="po.id" class="mb-4 p-3 rounded-xl bg-pink-50 shadow-sm">
                    <div class="font-semibold">PO Number: {{ po.po_number }}</div>
                    <div>Status: {{ po.status }}</div>
                    <div>Total: {{ formatRupiah(po.total_amount) }}</div>
                    <div class="mt-2">
                      <div class="font-semibold text-xs mb-1">Items:</div>
                      <ul class="list-disc ml-5">
                        <li v-for="item in po.items" :key="item.id">{{ item.item_name }} ({{ item.quantity }} x {{ formatRupiah(item.price) }})</li>
                      </ul>
                    </div>
                    <div class="mt-2">
                      <div class="font-semibold text-xs mb-1">Invoices:</div>
                      <ul class="list-disc ml-5">
                        <li v-for="inv in po.invoices" :key="inv.id">
                          <a :href="'/storage/' + inv.invoice_file_path" target="_blank" class="underline text-blue-600">{{ inv.invoice_number }}</a> ({{ inv.invoice_date }})
                          <img v-if="inv.invoice_file_path" :src="'/storage/' + inv.invoice_file_path" class="w-20 h-20 object-cover rounded shadow border cursor-pointer mt-1" @click="openLightbox([{ type: 'image', url: '/storage/' + inv.invoice_file_path, caption: inv.invoice_number }], 0)" />
                        </li>
                      </ul>
                    </div>
                    <div class="mt-2">
                      <div class="font-semibold text-xs mb-1">Good Receives:</div>
                      <div v-if="po.receives.length > 0" class="text-xs text-gray-500 mb-2">Notes: {{ po.receives[0].notes || '-' }}</div>
                      <div class="flex flex-row flex-wrap gap-3 ml-5">
                        <template v-for="rec in po.receives" :key="rec.id">
                          <template v-if="rec.file_type && rec.file_type.startsWith('image/')">
                            <img :src="'/storage/' + rec.file_path" class="w-20 h-20 object-cover rounded shadow border cursor-pointer" @click="openLightbox([{ type: 'image', url: '/storage/' + rec.file_path, caption: rec.notes || 'Good Receive' }], 0)" />
                          </template>
                          <template v-else-if="rec.file_type && rec.file_type.startsWith('video/')">
                            <video :src="'/storage/' + rec.file_path" class="w-20 h-20 object-cover rounded shadow border cursor-pointer" @click="openLightbox([{ type: 'video', url: '/storage/' + rec.file_path, caption: rec.notes || 'Good Receive' }], 0)" />
                          </template>
                        </template>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div v-else-if="activeTab === 'retail'">
                <h2 class="text-lg font-bold mb-2">Retail</h2>
                <div v-if="detail.retail.length === 0" class="text-gray-400">No retail data</div>
                <div v-else>
                  <div v-for="r in detail.retail" :key="r.id" class="mb-4 p-3 rounded-xl bg-green-50 shadow-sm">
                    <div class="font-semibold">Toko: {{ r.nama_toko }}</div>
                    <div>Alamat: {{ r.alamat_toko }}</div>
                    <div v-if="r.all_invoice_images && r.all_invoice_images.length" class="mt-2">
                      <div class="text-xs font-semibold mb-1">Invoice:</div>
                      <div class="flex flex-row flex-wrap gap-3">
                        <template v-for="(img, idx) in r.all_invoice_images" :key="img.id">
                          <img :src="'/storage/' + img.file_path" class="w-20 h-20 object-cover rounded shadow border cursor-pointer" @click="openLightbox(r.all_invoice_images.map(i => ({type: 'image', url: '/storage/' + i.file_path, caption: i.file_path.split('/').pop()})), idx)" />
                        </template>
                      </div>
                    </div>
                    <div class="mt-2">
                      <div class="font-semibold text-xs mb-1">Items:</div>
                      <ul class="list-disc ml-5">
                        <li v-for="item in r.items" :key="item.id">{{ item.nama_barang }} ({{ item.qty }} x {{ formatRupiah(item.harga_barang) }})
                          <div class="flex flex-wrap gap-3 mt-1">
                            <template v-for="(img, idx) in item.barang_images" :key="img.id">
                              <img :src="'/storage/' + img.file_path" class="w-20 h-20 object-cover rounded shadow border cursor-pointer" @click="openLightbox(retailImages(r), idx)" />
                            </template>
                          </div>
                        </li>
                      </ul>
                    </div>
                  </div>
                </div>
              </div>
              <div v-else-if="activeTab === 'evidence'">
                <h2 class="text-lg font-bold mb-2">Evidence</h2>
                <div v-if="detail.evidence.length === 0" class="text-gray-400">No evidence</div>
                <div v-else>
                  <div v-for="ev in detail.evidence" :key="ev.id" class="mb-4 p-3 rounded-xl bg-indigo-50 shadow-sm">
                    <div class="font-semibold">{{ ev.notes || 'Evidence' }}</div>
                    <div class="flex flex-wrap gap-3 mt-2">
                      <template v-for="(photo, idx) in ev.photos" :key="photo.id">
                        <img :src="'/storage/' + photo.path" class="w-20 h-20 object-cover rounded shadow border cursor-pointer" @click="openLightbox(evidenceMedia(ev), idx)" />
                      </template>
                      <template v-for="(video, idx) in ev.videos" :key="video.id">
                        <video :src="'/storage/' + video.path" class="w-20 h-20 object-cover rounded shadow border cursor-pointer" @click="openLightbox(evidenceMedia(ev), ev.photos.length + idx)" />
                      </template>
                    </div>
                  </div>
                </div>
              </div>
            </template>
          </div>
        </transition>
      </div>
    </div>
  </div>
  <LightboxModal :show="lightboxShow" :mediaList="lightboxMedia" :startIndex="lightboxIndex" @close="lightboxShow = false" />
</template>
<script setup>
import { ref, watch, onMounted, computed } from 'vue';
import LightboxModal from './LightboxModal.vue';
const props = defineProps({ task: Object, show: Boolean });
const emit = defineEmits(['close']);
const tabs = [
  { key: 'info', label: 'Info', icon: 'fas fa-info-circle' },
  { key: 'attachments', label: 'Attachments', icon: 'fas fa-paperclip' },
  { key: 'action_plan', label: 'Action Plan', icon: 'fas fa-tasks' },
  { key: 'pr', label: 'PR', icon: 'fas fa-file-invoice' },
  { key: 'po', label: 'PO', icon: 'fas fa-file-contract' },
  { key: 'retail', label: 'Retail', icon: 'fas fa-store' },
  { key: 'evidence', label: 'Evidence', icon: 'fas fa-camera' },
];
const activeTab = ref('info');
const detail = ref({ info: {}, assigned_to: [], attachments: [], documents: [], action_plans: [], pr: [], po: [], retail: [], evidence: [] });
const loading = ref(false);
// Lightbox state
const lightboxShow = ref(false);
const lightboxMedia = ref([]);
const lightboxIndex = ref(0);
function openLightbox(mediaArr, idx) {
  lightboxMedia.value = mediaArr;
  lightboxIndex.value = idx;
  lightboxShow.value = true;
}
function formatRupiah(val) {
  if (!val) return 'Rp 0';
  return 'Rp ' + Number(val).toLocaleString('id-ID');
}
watch(() => props.show, async (val) => {
  if (val && props.task && props.task.id) {
    activeTab.value = 'info';
    loading.value = true;
    try {
      const res = await fetch(`/api/maintenance-tasks/${props.task.id}/detail`);
      detail.value = await res.json();
    } finally {
      loading.value = false;
    }
  }
});
// Helper computed untuk media array per tab
const attachmentMedia = computed(() => detail.value.attachments.map(f => ({
  type: f.file_type && f.file_type.startsWith('image/') ? 'image' : (f.file_type && f.file_type.startsWith('video/') ? 'video' : 'file'),
  url: '/storage/' + f.file_path,
  caption: f.file_name
})));
function actionPlanMedia(ap) {
  return ap.media.map(f => ({
    type: (f.media_type === 'image' || f.media_type === 'photo') ? 'image' : (f.media_type === 'video' ? 'video' : 'file'),
    url: '/storage/' + f.file_path,
    caption: f.file_path.split('/').pop()
  }));
}
function retailImages(r) {
  return r.items.flatMap(item => item.barang_images.map(img => ({
    type: 'image', url: '/storage/' + img.file_path, caption: img.file_path.split('/').pop()
  })));
}
function retailInvoiceImages(r) {
  return r.items.flatMap(item => item.invoice_images.map(img => ({
    type: 'image', url: '/storage/' + img.file_path, caption: img.file_path.split('/').pop()
  })));
}
function evidenceMedia(ev) {
  const photos = ev.photos.map(p => ({ type: 'image', url: '/storage/' + p.path, caption: p.path.split('/').pop() }));
  const videos = ev.videos.map(v => ({ type: 'video', url: '/storage/' + v.path, caption: v.path.split('/').pop() }));
  return [...photos, ...videos];
}
</script>
<style scoped>
.fade-slide-enter-active, .fade-slide-leave-active {
  transition: all 0.3s cubic-bezier(.4,2,.6,1);
}
.fade-slide-enter-from {
  opacity: 0;
  transform: translateY(20px) scale(0.98);
}
.fade-slide-leave-to {
  opacity: 0;
  transform: translateY(-20px) scale(0.98);
}
</style> 