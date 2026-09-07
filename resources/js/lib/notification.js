import { ref } from 'vue';

export const activeNotification = ref(null);

let dismissTimer = null;

export function showNotification(message, type = 'error', duration = 6000) {
  if (dismissTimer) window.clearTimeout(dismissTimer);

  activeNotification.value = { message: String(message || 'Terjadi kesalahan.'), type };
  dismissTimer = window.setTimeout(dismissNotification, duration);
}

export function dismissNotification() {
  if (dismissTimer) window.clearTimeout(dismissTimer);
  dismissTimer = null;
  activeNotification.value = null;
}
