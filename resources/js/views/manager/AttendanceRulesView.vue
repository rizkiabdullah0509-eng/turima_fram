<template>
  <div class="max-w-2xl">
    <div class="card p-5">
      <div class="mb-5">
        <div class="font-display text-base font-bold">Aturan Absen Masuk</div>
        <p class="mt-1 text-sm text-inkmuted">Tentukan toleransi keterlambatan sebelum absen masuk ditutup otomatis.</p>
      </div>

      <div class="rounded-lg border border-line bg-surfacealt p-4 text-sm">
        <div class="font-bold">Cara kerja</div>
        <p class="mt-1 leading-6 text-inkmuted">
          Karyawan hanya dapat absen masuk sampai batas waktu setelah shift dimulai.
          Setelah batas tersebut lewat, absen masuk ditolak oleh sistem.
        </p>
      </div>

      <div class="field mt-5 max-w-xs">
        <label>Toleransi keterlambatan (menit)</label>
        <input v-model.number="graceMinutes" type="number" min="0" max="60">
      </div>

      <div class="mt-3 rounded-lg border border-[#F2D1CB] bg-[#FFF5F3] px-3 py-2 text-sm text-coralink">
        Contoh: shift mulai <strong>02.00</strong> dengan toleransi <strong>{{ graceMinutes || 0 }} menit</strong>
        hanya dapat absen hingga <strong>{{ deadlineExample }}</strong>.
      </div>

      <div v-if="message" class="mt-4 rounded-lg bg-brandlight px-3 py-2 text-sm text-tealink">{{ message }}</div>

      <div class="mt-5 flex justify-end">
        <button class="btn btn-primary" :disabled="saving" @click="save">
          {{ saving ? 'Menyimpan...' : 'Simpan Aturan' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import api from '../../lib/api';

const graceMinutes = ref(2);
const saving = ref(false);
const message = ref('');

const deadlineExample = computed(() => {
  const date = new Date(2000, 0, 1, 2, 0, 0);
  date.setMinutes(date.getMinutes() + Math.max(0, Number(graceMinutes.value) || 0));
  return date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', hour12: false }).replace(':', '.');
});

async function load() {
  const { data } = await api.get('/attendance/settings');
  graceMinutes.value = data.late_clock_in_grace_minutes;
}

async function save() {
  saving.value = true;
  message.value = '';
  try {
    const { data } = await api.put('/attendance/settings', {
      late_clock_in_grace_minutes: graceMinutes.value,
    });
    graceMinutes.value = data.late_clock_in_grace_minutes;
    message.value = data.message;
  } catch (error) {
    alert(error.response?.data?.message || 'Gagal menyimpan aturan absen.');
  } finally {
    saving.value = false;
  }
}

onMounted(load);
</script>
