<template>
  <div>
    <h1 class="text-2xl font-bold mb-4">{{ menu ? 'Edit' : 'Create' }} Menu</h1>
    <form @submit.prevent="submit">
      <div class="mb-4">
        <label class="block font-semibold">Name</label>
        <input v-model="form.name" class="input input-bordered w-full" required />
      </div>
      <div class="mb-4">
        <label class="block font-semibold">Code</label>
        <input v-model="form.code" class="input input-bordered w-full" required />
      </div>
      <div class="mb-4">
        <label class="block font-semibold">Parent Menu</label>
        <select v-model="form.parent_id" class="input input-bordered w-full">
          <option :value="null">- None -</option>
          <option v-for="p in parents" :key="p.id" :value="p.id">{{ p.name }}</option>
        </select>
      </div>
      <div class="mb-4">
        <label class="block font-semibold">Route</label>
        <input v-model="form.route" class="input input-bordered w-full" />
      </div>
      <div class="mb-4">
        <label class="block font-semibold">Icon</label>
        <input v-model="form.icon" class="input input-bordered w-full" />
      </div>
      <button type="submit" class="btn btn-primary">Save</button>
      <Link href="/menus" class="btn btn-secondary ml-2">Cancel</Link>
    </form>
  </div>
</template>
<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
const props = defineProps({ menu: Object, parents: Array });
const form = ref({
  name: props.menu?.name || '',
  code: props.menu?.code || '',
  parent_id: props.menu?.parent_id || null,
  route: props.menu?.route || '',
  icon: props.menu?.icon || '',
});
function submit() {
  if (props.menu) {
    router.put(`/menus/${props.menu.id}`, form.value);
  } else {
    router.post('/menus', form.value);
  }
}
</script> 