<template>
  <div v-if="route.name === 'login'">
    <router-view />
  </div>
  <div v-else class="min-h-screen md:flex">
    <!-- Sidebar -->
    <aside class="hidden w-56 shrink-0 flex-col gap-6 bg-branddark p-4 text-white md:flex">
      <div class="flex items-center gap-2 px-1">
        <div class="w-7 h-7 rounded-lg -rotate-6" style="background: linear-gradient(135deg,#6FE3A5,#1E8449)"></div>
        <div class="font-display font-bold text-base">TURIMA FRAM</div>
      </div>
      <nav class="flex flex-col gap-1">
        <router-link
          v-for="item in navItems" :key="item.to"
          :to="item.to"
          class="nav-menu-item px-3 py-2 rounded-lg text-sm font-medium text-[#C7D3C4] hover:bg-white/10 hover:text-white"
          active-class="!bg-white/15 !text-white"
        >{{ item.label }}</router-link>
      </nav>
    </aside>

    <!-- Menu navigasi ponsel -->
    <div v-if="mobileMenuOpen" class="fixed inset-0 z-[250] bg-black/45 md:hidden" @click.self="mobileMenuOpen = false">
      <aside class="flex h-full w-72 flex-col gap-6 bg-branddark p-4 text-white shadow-2xl">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2 px-1">
            <div class="h-7 w-7 -rotate-6 rounded-lg" style="background: linear-gradient(135deg,#6FE3A5,#1E8449)"></div>
            <div class="font-display text-base font-bold">TURIMA FRAM</div>
          </div>
          <button class="rounded-lg p-2 text-lg hover:bg-white/10" type="button" aria-label="Tutup menu" @click="mobileMenuOpen = false">✕</button>
        </div>
        <nav class="flex flex-col gap-1">
          <router-link
            v-for="item in navItems" :key="item.to"
            :to="item.to"
            class="nav-menu-item rounded-lg px-3 py-2.5 text-sm font-medium text-[#C7D3C4] hover:bg-white/10 hover:text-white"
            active-class="!bg-white/15 !text-white"
            @click="mobileMenuOpen = false"
          >{{ item.label }}</router-link>
        </nav>
        <div class="mt-auto pt-4 border-t border-white/10">
          <button class="btn btn-coral btn-sm w-full justify-center" type="button" @click="promptLogout">
            Keluar
          </button>
        </div>
      </aside>
    </div>

    <!-- Main -->
    <div class="flex-1 flex flex-col min-w-0">
      <header class="flex flex-wrap items-center justify-between gap-3 border-b border-line bg-surface px-4 py-3 md:px-6 md:py-3.5">
        <div class="flex min-w-0 items-center gap-2.5">
          <button
            class="mobile-menu-button md:hidden"
            type="button"
            :aria-label="mobileMenuOpen ? 'Tutup menu' : 'Buka menu'"
            @click="mobileMenuOpen = !mobileMenuOpen"
          >
            <span class="mobile-menu-icon" :class="{ 'is-open': mobileMenuOpen }" aria-hidden="true">
              <span></span><span></span><span></span>
            </span>
          </button>
          <div class="min-w-0">
            <div class="font-display truncate text-lg font-bold">{{ pageTitle }}</div>
            <div class="truncate text-xs text-inkmuted">{{ modeLabel }}</div>
          </div>
        </div>
        <div class="flex items-center gap-2 sm:gap-3">
          <div class="flex items-center gap-2 rounded-xl bg-branddark px-2.5 py-1.5 font-mono text-xs font-bold tracking-wide text-white shadow-sm sm:px-3 sm:py-2 sm:text-sm" title="Jam saat ini">
            <span class="h-2.5 w-2.5 rounded-full bg-[#6FE3A5] shadow-[0_0_0_4px_rgba(111,227,165,0.16)] animate-pulse"></span>
            {{ liveClock }}
          </div>
          <button class="hidden sm:flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg border border-line hover:bg-surfacealt text-sm font-semibold transition" type="button" @click="openProfileModal" title="Klik untuk edit profil & no. WhatsApp">
            <span>👤 {{ auth.user?.name }}</span>
            <span v-if="auth.user?.phone" class="text-[10px] font-mono text-emerald-700 bg-emerald-100 px-1.5 py-0.5 rounded border border-emerald-300">WA: {{ auth.user.phone }}</span>
            <span v-else class="text-[10px] text-amberink bg-[#FBE3B8] px-1.5 py-0.5 rounded">+ No WA</span>
          </button>
          <button class="btn btn-ghost btn-sm" type="button" @click="promptLogout">Keluar</button>
          <div class="relative">
            <button class="w-9 h-9 rounded-lg border border-line bg-surface relative" @click="bellOpen = !bellOpen">
              🔔
              <span v-if="unreadCount > 0" class="absolute -top-1 -right-1 bg-coral text-white text-[10px] font-bold rounded-full min-w-[16px] h-4 flex items-center justify-center px-1">{{ unreadCount }}</span>
            </button>
            <div v-if="bellOpen" class="absolute right-0 top-11 w-80 max-h-96 overflow-auto card p-2 z-50">
              <div v-if="notices.length === 0" class="p-5 text-center text-inkfaint text-sm">Belum ada notifikasi.</div>
              <div v-for="n in notices" :key="n.id" class="p-2.5 rounded-lg hover:bg-surfacealt text-xs">
                {{ n.message }}
              </div>
            </div>
          </div>
        </div>
      </header>
      <main class="flex-1 overflow-auto p-4 md:p-6">
        <router-view v-slot="{ Component, route: activeRoute }">
          <Transition
            mode="out-in"
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="translate-y-2 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="-translate-y-1 opacity-0"
          >
            <component :is="Component" :key="activeRoute.fullPath" />
          </Transition>
        </router-view>
      </main>
    </div>
  </div>
  <NotificationToast />

  <!-- Dialog Konfirmasi Keluar (Ya / Tidak) -->
  <ConfirmDialog
    :show="showLogoutConfirm"
    title="Konfirmasi Keluar"
    message="Apakah Anda yakin ingin keluar dari akun? Anda perlu login kembali untuk mengakses jadwal."
    confirm-label="Ya, Keluar"
    @confirm="confirmLogout"
    @cancel="showLogoutConfirm = false"
  />

  <!-- Modal Edit Profil & WhatsApp -->
  <Transition name="app-modal" appear>
    <div v-if="showProfileModal" class="fixed inset-0 bg-black/45 flex items-center justify-center z-[200] p-5" @click.self="showProfileModal = false">
      <div class="app-modal-card bg-white rounded-xl shadow-2xl w-full max-w-sm p-6">
        <h3 class="font-display font-bold text-base mb-1">Pengaturan Profil &amp; WhatsApp</h3>
        <p class="text-inkmuted text-xs mb-4">Pengaturan akun Anda untuk akses aplikasi &amp; WhatsApp Bot.</p>

        <div class="field mb-3">
          <label>Nama Lengkap</label>
          <input v-model="profileForm.name" placeholder="Nama Anda">
        </div>

        <div class="field mb-3">
          <label>Username</label>
          <input :value="auth.user?.username" disabled class="bg-surfacealt opacity-70 cursor-not-allowed">
        </div>

        <div class="field mb-3">
          <label>Nomor WhatsApp</label>
          <input v-model="profileForm.phone" placeholder="Contoh: 081234567890">
          <p class="text-[10px] text-inkfaint mt-1">Nomor ini digunakan untuk otorisasi perintah WhatsApp Bot.</p>
        </div>

        <div class="field mb-4">
          <label>Kata Sandi Baru (opsional)</label>
          <input v-model="profileForm.password" type="password" placeholder="Kosongkan jika tidak diubah">
        </div>

        <div class="flex justify-end gap-2">
          <button class="btn btn-ghost" @click="showProfileModal = false">Batal</button>
          <button class="btn btn-primary" :disabled="savingProfile" @click="saveProfile">
            {{ savingProfile ? 'Menyimpan...' : 'Simpan Profil' }}
          </button>
        </div>
      </div>
    </div>
  </Transition>
</template>

<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from './stores/auth';
import api from './lib/api';
import NotificationToast from './components/NotificationToast.vue';
import ConfirmDialog from './components/ConfirmDialog.vue';
import { showNotification } from './lib/notification';

window.alert = (message) => showNotification(
  message,
  /berhasil|tersimpan|ditambahkan/i.test(String(message)) ? 'success' : 'error'
);

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();

const bellOpen = ref(false);
const notices = ref([]);
const mobileMenuOpen = ref(false);
const currentTime = ref(new Date());
let clockTimer = null;

async function loadNotices() {
  try {
    const { data } = await api.get('/notices');
    notices.value = data;
  } catch (e) { /* abaikan */ }
}

const unreadCount = computed(() => notices.value.filter(n => !n.is_read).length);
const liveClock = computed(() => currentTime.value
  .toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false })
  .replace(/:/g, '.'));

onMounted(() => {
  if (auth.isLoggedIn) loadNotices();
  clockTimer = window.setInterval(() => {
    currentTime.value = new Date();
  }, 1000);
});

onUnmounted(() => {
  if (clockTimer) window.clearInterval(clockTimer);
});

const navItems = computed(() => {
  if (auth.role === 'manager') {
    return [
      { to: '/jadwal', label: 'Jadwal' },
      { to: '/tugas-tim', label: 'Tugas Harian' },
      { to: '/karyawan', label: 'Karyawan & Shift' },
      { to: '/aturan-absen', label: 'Aturan Absen' },
      { to: '/permintaan', label: 'Permintaan' },
      { to: '/laporan', label: 'Laporan' },
    ];
  }
  if (auth.role === 'admin') {
    return [
      { to: '/jadwal', label: 'Jadwal' },
      { to: '/tugas-tim', label: 'Tugas Harian' },
    ];
  }
  const items = [
    { to: '/jadwal-saya', label: 'Jadwal Saya' },
    { to: '/tugas-saya', label: 'Tugas Harian' },
    { to: '/ajukan', label: 'Ajukan & Status' },
    { to: '/riwayat-absensi', label: 'Riwayat Absensi' },
    { to: '/warning-telat', label: 'Warning Telat' },
  ];
  if (auth.user?.can_manage_schedule) {
    items.push({ to: '/jadwal', label: 'Jadwal Tim' });
    items.push({ to: '/tugas-tim', label: 'Tugas Tim' });
  }
  return items;
});

const pageTitle = computed(() => {
  const found = navItems.value.find(i => i.to === route.path);
  return found?.label || 'TURIMA FRAM';
});

const modeLabel = computed(() => {
  if (auth.role === 'manager') return 'Mode Manajer';
  if (auth.role === 'admin') return 'Mode Admin';
  return `Mode Karyawan — ${auth.user?.name || ''}`;
});

const showLogoutConfirm = ref(false);

function promptLogout() {
  showLogoutConfirm.value = true;
}

async function confirmLogout() {
  showLogoutConfirm.value = false;
  mobileMenuOpen.value = false;
  await auth.logout();
  router.push({ name: 'login' });
}

// Alias untuk kompatibilitas
const handleLogout = promptLogout;

const showProfileModal = ref(false);
const savingProfile = ref(false);
const profileForm = ref({ name: '', phone: '', password: '' });

function openProfileModal() {
  profileForm.value = {
    name: auth.user?.name || '',
    phone: auth.user?.phone || '',
    password: '',
  };
  showProfileModal.value = true;
}

async function saveProfile() {
  if (!profileForm.value.name.trim()) {
    alert('Nama tidak boleh kosong.');
    return;
  }
  savingProfile.value = true;
  try {
    const payload = {
      name: profileForm.value.name.trim(),
      phone: profileForm.value.phone?.trim() || null,
    };
    if (profileForm.value.password) {
      payload.password = profileForm.value.password;
    }
    const { data } = await api.put('/me', payload);
    auth.setUser(data);
    showProfileModal.value = false;
    alert('Profil dan nomor WhatsApp berhasil disimpan!');
  } catch (error) {
    alert(error.response?.data?.message || 'Gagal menyimpan profil.');
  } finally {
    savingProfile.value = false;
  }
}
</script>
