<template>
  <div>
    <div class="card mb-4 flex flex-wrap items-center justify-between gap-3 p-4">
      <div>
        <div class="font-display text-base font-bold">Warning Telat</div>
        <p class="mt-1 text-xs text-inkmuted">Pantau keterlambatan absen masuk dan jadwal yang belum diabsen.</p>
      </div>
      <span class="tag" :class="warnings.length ? 'tag-rejected' : 'tag-approved'">
        {{ warnings.length ? `${warnings.length} peringatan` : 'Tidak ada peringatan' }}
      </span>
    </div>

    <div v-if="loading" class="card p-10 text-center text-inkfaint">Memuat peringatan...</div>

    <div v-else-if="loadError" class="card p-10 text-center text-coral">
      <div class="mb-2 font-bold">Gagal memuat peringatan</div>
      <div class="mb-4 text-sm">{{ loadError }}</div>
      <button class="btn btn-primary btn-sm" @click="load">Coba Lagi</button>
    </div>

    <div v-else-if="warnings.length === 0" class="card p-10 text-center">
      <div class="mb-1 text-2xl">✓</div>
      <div class="font-display font-bold">Tidak ada peringatan keterlambatan</div>
      <p class="mt-1 text-sm text-inkmuted">Terus pertahankan absensi tepat waktu.</p>
    </div>

    <div v-else class="card divide-y divide-line">
      <div v-for="warning in warnings" :key="`${warning.date}-${warning.shift}`" class="flex flex-wrap items-center justify-between gap-3 p-4">
        <div>
          <div class="flex items-center gap-2">
            <span class="tag tag-rejected">{{ warning.type === 'late' ? 'Terlambat' : 'Belum Absen' }}</span>
            <span class="font-display text-sm font-bold">{{ warning.message }}</span>
          </div>
          <div class="mt-2 text-xs text-inkmuted">
            {{ formatDate(warning.date) }} · Shift {{ warning.shift }} mulai {{ warning.shift_start }}
          </div>
        </div>
        <div class="text-right font-mono text-xs">
          <div class="text-inkmuted">Absen masuk</div>
          <div class="mt-1 font-bold text-coral">{{ warning.clock_in || 'Belum absen' }}</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import api from '../../lib/api';

const warnings = ref([]);
const loading = ref(true);
const loadError = ref(null);
let refreshTimer = null;

async function load({ silent = false } = {}) {
  if (!silent) {
    loading.value = true;
    loadError.value = null;
  }

  try {
    const { data } = await api.get('/attendance/late-warnings');
    warnings.value = data;
  } catch (error) {
    if (!silent) loadError.value = error.response?.data?.message || 'Terjadi kesalahan saat memuat peringatan.';
  } finally {
    if (!silent) loading.value = false;
  }
}

function formatDate(date) {
  const [year, month, day] = date.split('-').map(Number);
  return new Date(year, month - 1, day).toLocaleDateString('id-ID', {
    day: 'numeric', month: 'long', year: 'numeric',
  });
}

onMounted(() => {
  load();
  refreshTimer = window.setInterval(() => load({ silent: true }), 30000);
});

onUnmounted(() => {
  if (refreshTimer) window.clearInterval(refreshTimer);
});
</script>
