<template>
  <div v-if="show && Array.isArray(mediaList) && mediaList.length" class="fixed inset-0 z-50 flex items-center justify-center bg-black/80" @click.self="close">
    <div class="relative max-w-3xl w-full flex flex-col items-center">
      <button @click="close" class="absolute top-2 right-2 text-white text-2xl z-10"><i class="fas fa-times"></i></button>
      <div class="flex items-center justify-center w-full h-[60vh]">
        <template v-if="mediaList.length">
          <button v-if="mediaList.length > 1" @click.stop="prev" class="absolute left-2 top-1/2 -translate-y-1/2 text-white text-3xl bg-black/40 rounded-full p-2 hover:bg-black/70"><i class="fas fa-chevron-left"></i></button>
          <div class="flex-1 flex items-center justify-center">
            <template v-if="currentMedia.type === 'image'">
              <img :src="currentMedia.url" class="max-h-[60vh] max-w-full rounded shadow-lg" />
            </template>
            <template v-else-if="currentMedia.type === 'video'">
              <video :src="currentMedia.url" class="max-h-[60vh] max-w-full rounded shadow-lg" controls autoplay />
            </template>
          </div>
          <button v-if="mediaList.length > 1" @click.stop="next" class="absolute right-2 top-1/2 -translate-y-1/2 text-white text-3xl bg-black/40 rounded-full p-2 hover:bg-black/70"><i class="fas fa-chevron-right"></i></button>
        </template>
      </div>
      <div class="mt-4 text-white text-center text-xs">{{ currentMedia.caption }}</div>
    </div>
  </div>
</template>
<script setup>
import { computed, watch, onMounted, onUnmounted, ref } from 'vue';
const props = defineProps({
  show: Boolean,
  mediaList: Array, // [{type: 'image'|'video', url: '', caption: ''}]
  startIndex: Number
});
const emit = defineEmits(['close']);
const index = ref(props.startIndex || 0);
const currentMedia = computed(() => props.mediaList[index.value] || {});
function close() { emit('close'); }
function prev() { if (index.value > 0) index.value--; else index.value = props.mediaList.length - 1; }
function next() { if (index.value < props.mediaList.length - 1) index.value++; else index.value = 0; }
function onKey(e) {
  if (!props.show) return;
  if (e.key === 'Escape') close();
  if (e.key === 'ArrowLeft' && props.mediaList.length > 1) prev();
  if (e.key === 'ArrowRight' && props.mediaList.length > 1) next();
}
onMounted(() => { window.addEventListener('keydown', onKey); });
onUnmounted(() => { window.removeEventListener('keydown', onKey); });
watch(() => props.startIndex, (val) => { index.value = val || 0; });
watch(() => props.show, (val) => { if (!val) index.value = props.startIndex || 0; });
</script>
<style scoped>
</style> 