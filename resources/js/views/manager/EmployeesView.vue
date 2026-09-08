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
      <button class="btn btn-sm bg-emerald-700 hover:bg-emerald-800 text-white font-medium shrink-0 self-start sm:self-auto" type="button" @click="showBotFormatHelp = true">
        📖 Contoh Format Chat
      </button>
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
onMounted(loadAll);

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
