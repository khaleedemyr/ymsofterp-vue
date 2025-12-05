<template>
  <div class="relative w-full">
    <div class="flex gap-4 overflow-x-auto pb-2 scrollbar-hide snap-x">
      <div v-for="(item, idx) in media" :key="item.id" class="media-card snap-center">
        <div class="media-preview">
          <img v-if="item.type === 'photo'" :src="item.url" class="object-cover w-full h-32 rounded-t-xl" />
          <div v-else class="flex items-center justify-center w-full h-32 bg-gray-200 rounded-t-xl">
            <i class="fas fa-play-circle text-4xl text-gray-400"></i>
          </div>
        </div>
        <div class="p-3 flex flex-col gap-1">
          <div class="font-bold text-sm truncate">{{ item.title }}</div>
          <div class="text-xs text-gray-500 truncate">{{ item.task_number }}</div>
          <div class="text-xs text-gray-400">{{ item.outlet }}</div>
        </div>
      </div>
    </div>
    <button v-if="media.length > 3" @click="scrollLeft" class="slider-btn left-2"><i class="fas fa-chevron-left"></i></button>
    <button v-if="media.length > 3" @click="scrollRight" class="slider-btn right-2"><i class="fas fa-chevron-right"></i></button>
  </div>
</template>
<script setup>
import { ref } from 'vue';
const props = defineProps({ media: Array });
const sliderRef = ref(null);
function scrollLeft() {
  if (sliderRef.value) sliderRef.value.scrollLeft -= 220;
}
function scrollRight() {
  if (sliderRef.value) sliderRef.value.scrollLeft += 220;
}
</script>
<style scoped>
.media-card {
  min-width: 200px;
  max-width: 200px;
  background: #fff;
  border-radius: 1.25rem;
  box-shadow: 0 4px 16px #e0e7ef44;
  overflow: hidden;
  transition: transform 0.2s;
  cursor: pointer;
}
.media-card:hover {
  transform: translateY(-4px) scale(1.03);
  box-shadow: 0 8px 24px #a5b4fc44;
}
.media-preview {
  width: 100%;
  height: 128px;
  background: #f3f4f6;
  display: flex;
  align-items: center;
  justify-content: center;
}
.slider-btn {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  background: #fff;
  border-radius: 50%;
  box-shadow: 0 2px 8px #e0e7ef44;
  width: 36px;
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.2rem;
  color: #6366f1;
  z-index: 10;
  border: none;
  outline: none;
  transition: background 0.2s;
}
.slider-btn:hover {
  background: #6366f1;
  color: #fff;
}
.left-2 { left: 0.5rem; }
.right-2 { right: 0.5rem; }
.scrollbar-hide::-webkit-scrollbar { display: none; }
</style> 