<template>
  <div class="min-h-screen w-full flex items-center justify-center p-6"
       style="background: radial-gradient(circle at 15% 20%, rgba(111,227,165,0.28), transparent 42%), radial-gradient(circle at 85% 75%, rgba(255,255,255,0.10), transparent 45%), #0E4A2C;">
    <div class="w-full max-w-sm bg-white rounded-2xl shadow-xl p-8">
      <div class="flex items-center gap-2 mb-6">
        <div class="w-8 h-8 rounded-lg -rotate-6" style="background: linear-gradient(135deg,#6FE3A5,#1E8449)"></div>
        <div class="font-display font-bold text-lg">TURIMA FRAM</div>
      </div>

      <div class="flex bg-surfacealt rounded-lg p-1 mb-5">
        <button
          class="flex-1 py-2 rounded-md text-sm font-bold"
          :class="tab === 'manager' ? 'bg-white shadow-sm' : 'text-inkmuted'"
          @click="tab = 'manager'"
        >Manajer</button>
        <button
          class="flex-1 py-2 rounded-md text-sm font-bold"
          :class="tab === 'employee' ? 'bg-white shadow-sm' : 'text-inkmuted'"
          @click="tab = 'employee'"
        >Karyawan</button>
      </div>

      <div class="font-display font-bold text-base mb-1">
        {{ tab === 'manager' ? 'Masuk sebagai Manajer' : 'Masuk sebagai Karyawan' }}
      </div>
      <div class="text-inkmuted text-xs mb-4">
        {{ tab === 'manager' ? 'Kelola jadwal, karyawan, dan persetujuan permintaan.' : 'Lihat jadwal Anda, absen, dan ajukan permintaan.' }}
      </div>

      <div v-if="error" class="bg-[#F6D9D3] text-coralink text-xs font-semibold px-3 py-2 rounded-lg mb-3">
        {{ error }}
      </div>

      <form @submit.prevent="submit">
        <div class="field mb-3">
          <label>Username</label>
          <input v-model="username" :placeholder="tab === 'manager' ? 'manajer' : 'contoh: dewi'" autocomplete="username">
        </div>
        <div class="field mb-4">
          <label>Kata Sandi</label>
          <input v-model="password" type="password" autocomplete="current-password" placeholder="•••••">
        </div>
        <button type="submit" class="btn btn-primary w-full justify-center py-2.5" :disabled="loading">
          {{ loading ? 'Memproses...' : 'Masuk' }}
        </button>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const router = useRouter();
const auth = useAuthStore();

const tab = ref('manager');
const username = ref('');
const password = ref('');
const error = ref('');
const loading = ref(false);

async function submit() {
  error.value = '';
  loading.value = true;
  try {
    await auth.login(username.value, password.value);
    router.push(auth.role === 'employee' ? { name: 'jadwal-saya' } : { name: 'jadwal' });
  } catch (e) {
    error.value = e.response?.data?.message
      || e.response?.data?.errors?.username?.[0]
      || 'Username atau kata sandi salah.';
  } finally {
    loading.value = false;
  }
}
</script>
