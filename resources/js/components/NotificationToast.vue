<template>
  <Transition name="notification-toast" appear>
    <div v-if="activeNotification" class="notification-toast-card fixed right-5 top-5 z-[300] w-[min(26rem,calc(100vw-2.5rem))] rounded-xl border p-4 shadow-2xl"
         :class="activeNotification.type === 'success' ? 'border-[#BFE3CD] bg-white text-tealink' : 'border-[#F2C4BC] bg-white text-coralink'"
         role="alert">
      <div class="flex items-start gap-3">
        <span class="mt-0.5 text-lg leading-none">{{ activeNotification.type === 'success' ? '✓' : '!' }}</span>
        <div class="min-w-0 flex-1">
          <div class="font-display text-sm font-bold">
            {{ activeNotification.type === 'success' ? 'Berhasil' : 'Pemberitahuan' }}
          </div>
          <p class="mt-1 text-sm leading-5 text-inkmuted">{{ activeNotification.message }}</p>
        </div>
        <button class="-mt-1 rounded p-1 text-inkmuted hover:bg-surfacealt" type="button" aria-label="Tutup notifikasi" @click="dismissNotification">✕</button>
      </div>
    </div>
  </Transition>
</template>

<script setup>
import { activeNotification, dismissNotification } from '../lib/notification';
</script>
