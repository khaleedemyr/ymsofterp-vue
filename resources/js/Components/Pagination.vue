<template>
  <nav v-if="links.length > 3" class="flex items-center gap-1">
    <template v-for="(link, key) in links" :key="key">
      <span v-if="!link.url" v-html="link.label" class="px-3 py-1 rounded text-gray-400"></span>
      <button
        v-else
        @click.prevent="$emit('navigate', link.url) || go(link.url)"
        v-html="link.label"
        :class="[
          'px-3 py-1 rounded',
          link.active ? 'bg-blue-600 text-white' : 'bg-white text-blue-600 hover:bg-blue-100 border border-blue-200'
        ]"
      ></button>
    </template>
  </nav>
</template>

<script setup>
import { router } from '@inertiajs/vue3';
const props = defineProps({
  links: Array
});
function go(url) {
  if (url) router.visit(url, { preserveState: true });
}
</script> 