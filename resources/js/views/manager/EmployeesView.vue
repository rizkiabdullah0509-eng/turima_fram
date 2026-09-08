<template>
  <div>
    <!-- Banner WhatsApp Bot Penugasan -->
    <div class="mb-4 p-4 rounded-xl border border-emerald-200 bg-emerald-50/90 flex flex-col sm:flex-row gap-3 sm:items-center justify-between">
      <div>
        <div class="flex items-center gap-2 font-display font-bold text-emerald-950 text-sm">
          <span>📱 WhatsApp Bot Penugasan Tugas Harian</span>
          <span class="text-[10px] bg-emerald-200 text-emerald-900 font-bold px-2 py-0.5 rounded-full">Khusus Manajer &amp; Berakses</span>
        </div>
        <p class="text-xs text-emerald-800 mt-1">
          Manajer dan staf dengan akses <b>Jadwal Tim</b> dapat langsung menugaskan tugas harian karyawan cukup via WhatsApp tanpa perlu membuka aplikasi web.
        </p>
      </div>
      <div class="flex flex-wrap gap-2 shrink-0 self-start sm:self-auto">
        <button class="btn btn-sm bg-emerald-600 hover:bg-emerald-700 text-white font-medium flex items-center gap-1.5" type="button" @click="openBotModal">
          <span>📷 Scan QR / Status Bot</span>
          <span v-if="botStatus === 'WORKING'" class="inline-block w-2 h-2 rounded-full bg-emerald-300"></span>
          <span v-else-if="botStatus === 'SCAN_QR_CODE'" class="inline-block w-2 h-2 rounded-full bg-amber-300 animate-ping"></span>
        </button>
        <button class="btn btn-sm bg-emerald-800 hover:bg-emerald-900 text-white font-medium" type="button" @click="showBotFormatHelp = true">
          📖 Contoh Format Chat
        </button>
      </div>
    </div>

    <div class="grid lg:grid-cols-2 gap-4 items-start">
      <div class="card p-4">
      <div class="flex justify-between items-start mb-3">
        <div>
          <div class="font-display font-bold text-base">Daftar Karyawan</div>
          <div class="text-inkmuted text-xs">Kelola anggota tim dan batas jam kerja mingguan.</div>
        </div>
        <button class="btn btn-primary btn-sm" @click="showAddEmployee = true">+ Tambah Karyawan</button>
      </div>

      <div class="overflow-x-auto">
      <table class="w-full min-w-[900px] text-sm">
        <thead>
          <tr class="text-[11px] uppercase text-inkmuted border-b-2 border-line">
            <th class="text-left py-2">Nama</th>
            <th class="text-left py-2">Posisi</th>
            <th class="text-left py-2">No. WhatsApp</th>
            <th class="text-left py-2">Username</th>
            <th class="text-left py-2">Sandi</th>
            <th class="text-left py-2">Akses</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="e in employees" :key="e.id" class="border-b border-line">
            <td class="py-2 font-bold">{{ e.name }}</td>
            <td class="py-2">{{ e.position }}</td>
            <td class="py-2">
              <span v-if="e.phone" class="font-mono text-xs text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">
                {{ e.phone }}
              </span>
              <span v-else class="text-xs text-inkfaint italic">-</span>
            </td>
            <td class="py-2"><span class="font-mono text-[11px] bg-surfacealt px-1.5 py-0.5 rounded">{{ e.username }}</span></td>
            <td class="py-2">
              <span class="font-mono text-xs tracking-wider">••••••••</span>
              <div class="text-[10px] text-inkfaint">Tersimpan aman</div>
            </td>
            <td class="py-2">
              <span class="tag" :class="e.can_manage_schedule ? 'tag-approved' : 'tag-pending'">
                {{ e.can_manage_schedule ? 'Jadwal Tim' : 'Karyawan Biasa' }}
              </span>
            </td>
            <td class="py-2 text-right whitespace-nowrap">
              <button class="btn btn-ghost btn-sm" @click="toggleAccess(e)">{{ e.can_manage_schedule ? 'Cabut Akses' : 'Beri Akses' }}</button>
              <button class="btn btn-ghost btn-sm" @click="openEditCredentials(e)">Edit Akun</button>
              <button class="btn btn-ghost btn-sm" @click="resetPassword(e)">Reset Sandi</button>
              <button class="btn btn-ghost btn-sm" @click="removeEmployee(e)">Hapus</button>
            </td>
          </tr>
        </tbody>
      </table>
      </div>
    </div>

    <div class="card p-4">
      <div class="flex justify-between items-start mb-3">
        <div>
          <div class="font-display font-bold text-base">Template Shift</div>
          <div class="text-inkmuted text-xs">Blok jam kerja siap pakai untuk penjadwalan cepat.</div>
        </div>
        <button class="btn btn-primary btn-sm" @click="openAddShift">+ Tambah Shift</button>
      </div>
      <div v-for="s in shifts" :key="s.id" class="flex justify-between items-center py-2.5 border-b border-line last:border-0">
        <div class="flex items-center gap-2.5">
          <span class="w-3 h-3 rounded-sm" :class="dotClass(s.color)"></span>
          <div>
            <div class="font-bold text-sm">{{ s.name }}</div>
            <div class="font-mono text-inkmuted text-[11px]">{{ s.start_time.slice(0,5) }}–{{ s.end_time.slice(0,5) }}</div>
          </div>
        </div>
        <div class="flex gap-1.5">
          <button class="btn btn-ghost btn-sm" @click="openEditShift(s)">Edit</button>
          <button class="btn btn-ghost btn-sm" @click="removeShift(s)">Hapus</button>
        </div>
      </div>
      <div v-if="shifts.length === 0" class="text-inkfaint text-center py-6 text-sm">Belum ada template shift.</div>
    </div>

    <!-- Modal tambah karyawan -->
    <Transition name="app-modal" appear>
    <div v-if="showAddEmployee" class="fixed inset-0 bg-black/45 flex items-center justify-center z-[100] p-5" @click.self="showAddEmployee = false">
      <div class="app-modal-card bg-white rounded-xl shadow-2xl w-full max-w-sm p-6">
        <h3 class="font-display font-bold text-base mb-3">Tambah Karyawan</h3>
        <div class="field mb-2"><label>Nama</label><input v-model="form.name" placeholder="Nama lengkap"></div>
        <div class="field mb-2"><label>Posisi</label><input v-model="form.position" placeholder="Contoh: Kasir"></div>
        <div class="field mb-2"><label>No. WhatsApp (opsional)</label><input v-model="form.phone" placeholder="Contoh: 081234567890"></div>
        <div class="field mb-2"><label>Maks Jam / Minggu</label><input type="number" v-model="form.max_hours"></div>
        <div class="field mb-2"><label>Username (opsional)</label><input v-model="form.username" placeholder="Contoh: dewi"></div>
        <div class="field mb-3"><label>Kata Sandi (opsional, default 12345)</label><input v-model="form.password" placeholder="12345"></div>
        <label class="flex items-center gap-2 text-sm mb-4">
          <input type="checkbox" v-model="form.can_manage_schedule" class="accent-teal w-4 h-4">
          Beri akses kelola Jadwal &amp; Tugas semua karyawan
        </label>
        <div class="flex justify-end gap-2">
          <button class="btn btn-ghost" @click="showAddEmployee = false">Batal</button>
          <button class="btn btn-primary" @click="submitEmployee">Simpan</button>
        </div>
      </div>
    </div>
    </Transition>

    <!-- Modal tambah / edit shift -->
    <Transition name="app-modal" appear>
    <div v-if="showShiftModal" class="fixed inset-0 bg-black/45 flex items-center justify-center z-[100] p-5" @click.self="closeShiftModal">
      <div class="app-modal-card bg-white rounded-xl shadow-2xl w-full max-w-sm p-6">
        <h3 class="font-display font-bold text-base mb-3">{{ editingShift ? 'Edit Template Shift' : 'Tambah Template Shift' }}</h3>
        <div class="field mb-2"><label>Nama Shift</label><input v-model="shiftForm.name" placeholder="Contoh: Sore"></div>
        <div class="field mb-2"><label>Jam Mulai</label><input type="time" v-model="shiftForm.start_time"></div>
        <div class="field mb-2"><label>Jam Selesai</label><input type="time" v-model="shiftForm.end_time"></div>
        <div class="field mb-3">
          <label>Warna</label>
          <select v-model="shiftForm.color">
            <option value="amber">🟡 Amber</option>
            <option value="teal">🟢 Teal</option>
            <option value="plum">🟣 Plum</option>
          </select>
        </div>
        <!-- Preview warna -->
        <div class="mb-4 px-3 py-2 rounded-lg text-[11px] font-semibold inline-flex items-center gap-2" :class="previewChipClass">
          <span>{{ shiftForm.name || 'Preview Shift' }}</span>
          <span class="font-mono opacity-75">{{ shiftForm.start_time }}–{{ shiftForm.end_time }}</span>
        </div>
        <div class="flex justify-end gap-2">
          <button class="btn btn-ghost" @click="closeShiftModal">Batal</button>
          <button class="btn btn-primary" @click="submitShift">{{ editingShift ? 'Simpan Perubahan' : 'Tambahkan' }}</button>
        </div>
      </div>
    </div>
    </Transition>

    <!-- Modal edit akun karyawan -->
    <div v-if="showCredentialsModal" class="fixed inset-0 bg-black/45 flex items-center justify-center z-[100] p-5" @click.self="closeCredentialsModal">
      <div class="bg-white rounded-xl shadow-2xl w-full max-w-sm p-6">
        <h3 class="font-display font-bold text-base mb-1">Edit Akun Karyawan</h3>
        <p class="text-inkmuted text-xs mb-4">{{ editingCredentials?.name }}</p>
        <div class="field mb-3">
          <label>Username</label>
          <input v-model="credentialForm.username" autocomplete="username" placeholder="Contoh: andi">
        </div>
        <div class="field mb-3">
          <label>No. WhatsApp</label>
          <input v-model="credentialForm.phone" placeholder="Contoh: 081234567890">
          <p class="text-[10px] text-inkfaint mt-1">Digunakan untuk bot input tugas harian via WhatsApp.</p>
        </div>
        <div class="field mb-2">
          <label>Kata Sandi Baru</label>
          <input v-model="credentialForm.password" type="password" autocomplete="new-password" placeholder="Kosongkan jika tidak diubah">
        </div>
        <p class="text-[11px] text-inkfaint mb-4">Minimal 4 karakter. Kata sandi lama tidak ditampilkan demi keamanan.</p>
        <div class="flex justify-end gap-2">
          <button class="btn btn-ghost" @click="closeCredentialsModal">Batal</button>
          <button class="btn btn-primary" @click="submitCredentials">Simpan Akun</button>
        </div>
      </div>
    </div>

    <ConfirmDialog
      :show="!!confirmation"
      :title="confirmation?.title || 'Konfirmasi'"
      :message="confirmation?.message || ''"
      :confirm-label="confirmation?.confirmLabel || 'Ya, Lanjutkan'"
      @confirm="confirmAction"
      @cancel="confirmation = null"
    />
    </div>

    <!-- Modal Petunjuk Format Chat WhatsApp Bot -->
    <Transition name="app-modal" appear>
    <div v-if="showBotFormatHelp" class="fixed inset-0 bg-black/45 flex items-center justify-center z-[150] p-5" @click.self="showBotFormatHelp = false">
      <div class="app-modal-card bg-white rounded-xl shadow-2xl w-full max-w-md p-6">
        <h3 class="font-display font-bold text-base mb-1">Contoh Format Chat WhatsApp Bot</h3>
        <p class="text-inkmuted text-xs mb-3">Ketik nama/username karyawan dan daftar tugas bernomor ke nomor bot WhatsApp:</p>

        <div class="bg-gray-900 text-emerald-400 p-3.5 rounded-xl font-mono text-xs leading-relaxed overflow-x-auto select-all mb-3 whitespace-pre">budi 
1. membersikan rumput
2. ngasah arit
3. mencuci mobil

cici 
1. membersihkan selokan 
2. ngasih makan ayam 
3. membuat nasi</div>

        <div class="text-xs text-inkmuted space-y-1.5 mb-4">
          <div>💡 <b>Ketik "semua"</b> sebagai nama untuk menugaskan ke seluruh karyawan tim.</div>
          <div>💡 <b>Ketik "besok"</b> di baris paling awal jika ingin menugaskan untuk hari esok.</div>
          <div>💡 <b>Ketik "progres"</b> untuk memantau status penyelesaian tugas tim hari ini.</div>
        </div>

        <div class="flex justify-end">
          <button class="btn btn-primary btn-sm" @click="showBotFormatHelp = false">Tutup</button>
        </div>
      </div>
    </div>
    </Transition>

    <!-- Modal Koneksi & Scan QR WhatsApp Bot -->
    <Transition name="app-modal" appear>
    <div v-if="showBotModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-[150] p-4" @click.self="closeBotModal">
      <div class="app-modal-card bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 relative">
        <div class="flex justify-between items-start mb-4">
          <div>
            <h3 class="font-display font-bold text-base flex items-center gap-2">
              <span>🤖 Sambungkan Bot WhatsApp</span>
              <span v-if="botStatus === 'WORKING'" class="text-[11px] bg-emerald-100 text-emerald-800 font-bold px-2 py-0.5 rounded-full">Terhubung</span>
              <span v-else-if="botStatus === 'SCAN_QR_CODE'" class="text-[11px] bg-amber-100 text-amber-800 font-bold px-2 py-0.5 rounded-full">Perlu Scan QR</span>
              <span v-else class="text-[11px] bg-red-100 text-red-800 font-bold px-2 py-0.5 rounded-full">Menyiapkan</span>
            </h3>
            <p class="text-inkmuted text-xs mt-0.5">Kontainer mandiri WAHA Turima Farm (Port 3005)</p>
          </div>
          <button class="text-inkmuted hover:text-ink text-lg leading-none" @click="closeBotModal">✕</button>
        </div>

        <!-- Jika status WORKING -->
        <div v-if="botStatus === 'WORKING'" class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-center my-4">
          <div class="text-3xl mb-2">✅</div>
          <div class="font-bold text-emerald-900 text-sm">Bot WhatsApp Telah Terhubung!</div>
          <div class="text-xs text-emerald-700 mt-1 font-mono">
            Nomor: {{ botMe?.id || 'Aktif' }}
          </div>
          <p class="text-xs text-inkmuted mt-3">
            Manajer dapat langsung mengirimkan tugas harian via chat WhatsApp ke nomor di atas.
          </p>
          <div class="mt-4 pt-3 border-t border-emerald-200 flex justify-center">
            <button class="btn btn-sm bg-white border border-rose-300 text-rose-700 hover:bg-rose-50" :disabled="loadingBotAction" @click="handleRestartBot">
              🔄 Tautkan Ulang / Ganti Nomor
            </button>
          </div>
        </div>

        <!-- Jika status SCAN_QR_CODE atau FAILED/STOPPED/STARTING -->
        <div v-else class="text-center my-4">
          <div v-if="qrLoading" class="w-64 h-64 mx-auto rounded-xl bg-surfacealt border border-line flex flex-col items-center justify-center text-xs text-inkmuted p-4">
            <div class="animate-spin text-3xl mb-3">⏳</div>
            <span class="font-bold text-ink">Menyiapkan QR Code...</span>
            <span class="text-[11px] text-inkmuted mt-1 text-center">Menghubungkan ke WAHA Turima Farm</span>
          </div>

          <div v-else-if="qrError" class="w-64 h-64 mx-auto rounded-xl bg-rose-50 border border-rose-200 flex flex-col items-center justify-center text-xs text-rose-700 p-4">
            <div class="text-3xl mb-2">⚠️</div>
            <span class="font-bold text-rose-900">QR Code Sedang Disiapkan</span>
            <p class="text-[11px] text-rose-700 mt-1 mb-3 text-center">Sesi bot sedang di-restart untuk membersihkan status lama.</p>
            <button class="btn btn-sm bg-rose-600 hover:bg-rose-700 text-white text-xs" @click="fetchQrCode">
              🔄 Coba Muat Ulang
            </button>
          </div>

          <div v-else-if="qrBlobUrl" class="relative inline-block mx-auto p-2 bg-white rounded-xl border border-line shadow-sm">
            <img :src="qrBlobUrl" alt="QR Code WhatsApp" class="w-60 h-60 mx-auto rounded-lg object-contain" />
          </div>

          <div v-else class="w-64 h-64 mx-auto rounded-xl bg-surfacealt border border-line flex flex-col items-center justify-center text-xs text-inkmuted p-4">
            <button class="btn btn-sm bg-primary text-white" @click="fetchQrCode">
              📷 Tampilkan QR Code
            </button>
          </div>

          <div class="mt-3 text-xs text-inkmuted leading-relaxed max-w-xs mx-auto text-left space-y-1">
            <div class="font-bold text-ink">Langkah Scan:</div>
            <div>1. Buka <b>WhatsApp</b> di HP Anda</div>
            <div>2. Ketuk <b>Titik Tiga</b> (kanan atas) ➜ <b>Perangkat Tertaut</b></div>
            <div>3. Ketuk <b>Tautkan Perangkat</b> dan scan kode di atas</div>
          </div>

          <div class="mt-4 flex items-center justify-center gap-2">
            <button class="btn btn-sm bg-emerald-700 hover:bg-emerald-800 text-white text-xs flex items-center gap-1.5" :disabled="loadingBotAction || qrLoading" @click="handleRestartBot">
              <span v-if="loadingBotAction">⏳ Sedang Me-restart...</span>
              <span v-else>🔄 Buat / Refresh QR Code Baru</span>
            </button>
          </div>
        </div>

        <div class="mt-5 pt-3 border-t border-line flex items-center justify-between text-xs text-inkmuted">
          <span>Sesi: <b>default</b></span>
          <button class="btn btn-outline btn-sm" @click="closeBotModal">Tutup</button>
        </div>
      </div>
    </div>
    </Transition>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import api from '../../lib/api';
import ConfirmDialog from '../../components/ConfirmDialog.vue';

const employees = ref([]);
const shifts = ref([]);
const showAddEmployee = ref(false);
const showShiftModal = ref(false);
const showBotFormatHelp = ref(false);
const showBotModal = ref(false);
const botStatus = ref('');
const botMe = ref(null);
const qrBlobUrl = ref('');
const qrLoading = ref(false);
const qrError = ref(false);
const loadingBotAction = ref(false);
let botPollTimer = null;

async function checkBotStatus() {
  try {
    const { data } = await api.get('/whatsapp/status');
    botStatus.value = data?.waha?.status || '';
    botMe.value = data?.waha?.me || null;

    if (botStatus.value === 'WORKING' && qrBlobUrl.value) {
      URL.revokeObjectURL(qrBlobUrl.value);
      qrBlobUrl.value = '';
    }
  } catch (e) {
    botStatus.value = 'ERROR';
  }
}

async function fetchQrCode() {
  if (botStatus.value === 'WORKING') return;
  qrLoading.value = true;
  qrError.value = false;
  try {
    const response = await api.get('/whatsapp/qr', {
      params: { t: Date.now() },
      responseType: 'blob',
      timeout: 15000,
    });
    if (qrBlobUrl.value) {
      URL.revokeObjectURL(qrBlobUrl.value);
    }
    qrBlobUrl.value = URL.createObjectURL(response.data);
    await checkBotStatus();
  } catch (err) {
    console.error('Failed to load QR blob:', err);
    qrError.value = true;
  } finally {
    qrLoading.value = false;
  }
}

async function handleRestartBot() {
  loadingBotAction.value = true;
  try {
    await api.post('/whatsapp/restart');
    await fetchQrCode();
  } catch (e) {
    alert('Gagal merefresh QR Code bot.');
  } finally {
    loadingBotAction.value = false;
  }
}

async function openBotModal() {
  showBotModal.value = true;
  await checkBotStatus();
  if (botStatus.value !== 'WORKING') {
    fetchQrCode();
  }
  if (botPollTimer) clearInterval(botPollTimer);
  botPollTimer = setInterval(async () => {
    if (!showBotModal.value) return;
    await checkBotStatus();
  }, 3500);
}

function closeBotModal() {
  showBotModal.value = false;
  if (botPollTimer) {
    clearInterval(botPollTimer);
    botPollTimer = null;
  }
}

const showCredentialsModal = ref(false);
const editingShift = ref(null); // null = mode tambah, objek = mode edit
const editingCredentials = ref(null);
const confirmation = ref(null);

const form = ref({ name: '', position: '', phone: '', max_hours: 40, username: '', password: '', can_manage_schedule: false });
const shiftForm = ref({ name: '', start_time: '08:00', end_time: '16:00', color: 'amber' });
const credentialForm = ref({ username: '', password: '', phone: '' });

const previewChipClass = computed(() => ({
  amber: 'bg-[#FBE3B8] text-amberink',
  teal:  'bg-[#CDEAE2] text-tealink',
  plum:  'bg-[#E4D9F7] text-plumink',
}[shiftForm.value.color] || 'bg-[#FBE3B8] text-amberink'));

async function loadAll() {
  const [{ data: e }, { data: s }] = await Promise.all([api.get('/employees'), api.get('/shift-templates')]);
  employees.value = e;
  shifts.value = s;
}

onMounted(() => {
  loadAll();
  checkBotStatus();
});

function dotClass(color) {
  return { amber: 'bg-amber', teal: 'bg-teal', plum: 'bg-plum' }[color] || 'bg-amber';
}

function openAddShift() {
  editingShift.value = null;
  shiftForm.value = { name: '', start_time: '08:00', end_time: '16:00', color: 'amber' };
  showShiftModal.value = true;
}

function openEditShift(s) {
  editingShift.value = s;
  shiftForm.value = { name: s.name, start_time: s.start_time.slice(0,5), end_time: s.end_time.slice(0,5), color: s.color };
  showShiftModal.value = true;
}

function closeShiftModal() {
  showShiftModal.value = false;
  editingShift.value = null;
}

function openEditCredentials(employee) {
  editingCredentials.value = employee;
  credentialForm.value = { username: employee.username || '', password: '', phone: employee.phone || '' };
  showCredentialsModal.value = true;
}

function closeCredentialsModal() {
  showCredentialsModal.value = false;
  editingCredentials.value = null;
  credentialForm.value = { username: '', password: '', phone: '' };
}

async function submitCredentials() {
  if (!credentialForm.value.username.trim()) {
    alert('Username wajib diisi.');
    return;
  }

  const payload = {
    username: credentialForm.value.username.trim(),
    phone: credentialForm.value.phone ? credentialForm.value.phone.trim() : null,
  };
  if (credentialForm.value.password) {
    payload.password = credentialForm.value.password;
  }

  try {
    const { data: updated } = await api.put(`/employees/${editingCredentials.value.id}`, payload);
    const index = employees.value.findIndex(employee => employee.id === updated.id);
    employees.value[index] = updated;
    closeCredentialsModal();
  } catch (error) {
    alert(error.response?.data?.message || 'Gagal menyimpan akun karyawan.');
  }
}

async function submitEmployee() {
  if (!form.value.name.trim()) { alert('Nama wajib diisi.'); return; }
  try {
    await api.post('/employees', form.value);
    showAddEmployee.value = false;
    form.value = { name: '', position: '', phone: '', max_hours: 40, username: '', password: '', can_manage_schedule: false };
    await loadAll();
  } catch (e) {
    alert(e.response?.data?.message || 'Gagal menyimpan karyawan.');
  }
}

async function submitShift() {
  if (!shiftForm.value.name.trim()) { alert('Nama shift wajib diisi.'); return; }
  try {
    if (editingShift.value) {
      // Mode edit
      const { data: updated } = await api.put(`/shift-templates/${editingShift.value.id}`, shiftForm.value);
      const idx = shifts.value.findIndex(s => s.id === editingShift.value.id);
      shifts.value[idx] = updated;
    } else {
      // Mode tambah
      await api.post('/shift-templates', shiftForm.value);
      await loadAll();
    }
    closeShiftModal();
  } catch (e) {
    alert(e.response?.data?.message || 'Gagal menyimpan template shift.');
  }
}

function requestConfirmation(title, message, confirmLabel, action) {
  confirmation.value = { title, message, confirmLabel, action };
}

async function confirmAction() {
  const action = confirmation.value?.action;
  confirmation.value = null;

  if (action) await action();
}

function removeEmployee(e) {
  requestConfirmation(
    'Hapus karyawan?',
    `Karyawan ${e.name} beserta jadwal terkait akan dihapus. Tindakan ini tidak dapat dibatalkan.`,
    'Ya, Hapus',
    async () => {
      try {
        await api.delete(`/employees/${e.id}`);
        await loadAll();
      } catch (err) {
        alert(err.response?.data?.message || 'Gagal menghapus karyawan.');
      }
    }
  );
}

function removeShift(s) {
  requestConfirmation(
    'Hapus template shift?',
    `Template shift “${s.name}” akan hilang dari semua jadwal.`,
    'Ya, Hapus',
    async () => {
      try {
        await api.delete(`/shift-templates/${s.id}`);
        await loadAll();
      } catch (e) {
        alert(e.response?.data?.message || 'Gagal menghapus shift.');
      }
    }
  );
}

function resetPassword(e) {
  requestConfirmation(
    'Reset kata sandi?',
    `Kata sandi ${e.name} akan diubah menjadi “12345”.`,
    'Ya, Reset',
    async () => {
      await api.post(`/employees/${e.id}/reset-password`);
      alert('Kata sandi berhasil direset ke 12345.');
    }
  );
}

async function toggleAccess(e) {
  const { data } = await api.post(`/employees/${e.id}/toggle-schedule-access`);
  const idx = employees.value.findIndex(x => x.id === e.id);
  employees.value[idx] = data;
}
</script>
