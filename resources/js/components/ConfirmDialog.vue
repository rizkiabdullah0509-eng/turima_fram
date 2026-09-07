<template>
  <Transition name="confirm-dialog" appear>
    <div
      v-if="show"
      class="fixed inset-0 z-[200] flex items-center justify-center bg-black/45 p-5"
      role="presentation"
      @click.self="$emit('cancel')"
    >
      <section
        class="confirm-dialog-card w-full max-w-sm rounded-xl bg-white p-6 shadow-2xl"
        role="dialog"
        aria-modal="true"
        aria-labelledby="confirm-dialog-title"
        aria-describedby="confirm-dialog-message"
      >
        <h3 id="confirm-dialog-title" class="font-display text-base font-bold text-ink mb-2">
          {{ title }}
        </h3>
        <p id="confirm-dialog-message" class="text-sm leading-6 text-inkmuted">
          {{ message }}
        </p>
        <div class="mt-5 flex justify-end gap-2">
          <button class="btn btn-ghost" type="button" @click="$emit('cancel')">Tidak</button>
          <button class="btn btn-coral" type="button" @click="$emit('confirm')">{{ confirmLabel }}</button>
        </div>
      </section>
    </div>
  </Transition>
</template>

<script setup>
defineProps({
  show: { type: Boolean, default: true },
  title: { type: String, default: 'Konfirmasi' },
  message: { type: String, required: true },
  confirmLabel: { type: String, default: 'Ya, Lanjutkan' },
});

defineEmits(['confirm', 'cancel']);
</script>
