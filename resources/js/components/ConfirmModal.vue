<template>
  <div v-if="show" class="fixed inset-0 bg-black/45 flex items-center justify-center z-[100] p-5" @click.self="$emit('cancel')">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-sm p-6">
      <h3 class="font-display font-bold text-base mb-1">Konfirmasi</h3>
      <p class="text-inkmuted text-sm mb-5">{{ message }}</p>
      <div class="flex justify-end gap-2">
        <button class="btn btn-ghost" @click="$emit('cancel')">Tidak</button>
        <button class="btn btn-coral" @click="$emit('confirm')">Ya</button>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  show: Boolean,
  message: String,
});
defineEmits(['confirm', 'cancel']);
</script>
