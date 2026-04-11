<script setup>
const props = defineProps({
  user: { type: Object, default: null },
  sizeClass: { type: String, default: 'w-9 h-9' },
});

const emit = defineEmits(['preview']);

function avatarUrl(user) {
  if (!user?.avatar) return '/images/avatar-default.png';
  const a = String(user.avatar).trim();
  if (!a) return '/images/avatar-default.png';
  if (a.startsWith('http://') || a.startsWith('https://') || a.startsWith('/')) {
    return a;
  }
  return `/storage/${a}`;
}

function hasUploadedAvatar(user) {
  return !!(user?.avatar && String(user.avatar).trim());
}

function initials(name) {
  if (!name || typeof name !== 'string') return '?';
  const parts = name.trim().split(/\s+/).filter(Boolean);
  if (!parts.length) return '?';
  if (parts.length === 1) return parts[0].charAt(0).toUpperCase();
  return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
}

function onPreview() {
  if (!props.user) return;
  emit('preview', {
    src: avatarUrl(props.user),
    alt: props.user.nama_lengkap || 'Pengguna',
  });
}
</script>

<template>
  <button
    v-if="user"
    type="button"
    class="shrink-0 rounded-full border-2 border-white shadow-md ring-1 ring-gray-200/80 overflow-hidden bg-gradient-to-br from-sky-100 to-blue-200 hover:ring-blue-400 hover:shadow-lg transition-all focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1"
    :class="sizeClass"
    :title="`Lihat foto — ${user.nama_lengkap || ''}`"
    :aria-label="`Buka foto profil ${user.nama_lengkap || 'pengguna'}`"
    @click.stop="onPreview"
  >
    <img
      v-if="hasUploadedAvatar(user)"
      :src="avatarUrl(user)"
      :alt="user.nama_lengkap || ''"
      class="w-full h-full object-cover"
    />
    <span
      v-else
      class="flex h-full w-full items-center justify-center text-[11px] font-bold text-blue-900 leading-none select-none"
    >
      {{ initials(user.nama_lengkap) }}
    </span>
  </button>
</template>
