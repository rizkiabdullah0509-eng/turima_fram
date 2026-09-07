<template>
  <div>
    <div class="flex items-center gap-2 mb-4 flex-wrap">
      <button class="btn btn-ghost btn-sm" @click="shiftDay(-1)">← Sebelumnya</button>
      <div class="font-mono font-semibold text-sm bg-surfacealt px-3 py-1.5 rounded-lg">{{ dayLabel }}</div>
      <button class="btn btn-ghost btn-sm" @click="shiftDay(1)">Selanjutnya →</button>
      <button class="btn btn-ghost btn-sm" @click="goToday">Hari ini</button>
    </div>

    <div class="card p-4 mb-4">
      <div class="font-display font-bold text-base mb-1">Beri Tugas Hari Ini</div>
      <p class="text-inkmuted text-xs mb-3">Centang tugas yang ingin diberikan, lalu tugaskan. Karyawan dapat menyelesaikannya dalam urutan apa pun.</p>

      <div class="field mb-3">
        <label>Ditugaskan untuk</label>
        <select v-model="assignee">
          <option value="all">Semua Karyawan</option>
          <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}</option>
        </select>
      </div>

      <div class="field mb-3">
        <label>Daftar Tugas (centang yang ingin ditugaskan)</label>
        <div class="flex flex-col gap-1.5">
          <div v-for="tpl in templates" :key="tpl.id" class="flex items-center gap-2 rounded-lg border border-line bg-[#FBFCF9] px-3 py-2">
            <label :for="`task-template-${tpl.id}`" class="min-w-0 flex-1 cursor-pointer text-sm font-semibold">
              <span class="block truncate">{{ tpl.title }}</span>
            </label>
            <label class="flex h-9 w-9 shrink-0 cursor-pointer items-center justify-center rounded-md hover:bg-surfacealt" :title="`Pilih tugas ${tpl.title}`">
              <input :id="`task-template-${tpl.id}`" type="checkbox" v-model="checked[tpl.id]" class="h-4 w-4 accent-teal">
              <span class="sr-only">Pilih tugas {{ tpl.title }}</span>
            </label>
            <button class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md text-lg text-inkfaint hover:bg-[#FFF0ED] hover:text-coral" type="button" :title="`Hapus ${tpl.title}`" @click="removeTemplate(tpl.id)">&times;</button>
          </div>
          <div v-if="templates.length === 0" class="text-inkfaint text-sm p-3">Belum ada tugas di daftar.</div>
        </div>
      </div>

      <div class="field mb-3">
        <label>Tambah tugas lain ke daftar</label>
        <div v-if="canExportTasks" class="flex gap-2">
          <input v-model="newTitle" placeholder="Contoh: Cat pagar depan" class="flex-1">
          <button class="btn btn-ghost btn-sm" @click="addTemplate">+ Tambahkan ke daftar</button>
        </div>
      </div>

      <div v-if="isManager" class="field mb-4 rounded-lg border border-line bg-surfacealt/40 p-3">
        <label>Impor tugas harian dari Excel</label>
        <p class="mb-3 text-xs text-inkmuted">Isi template dengan username karyawan, tanggal (YYYY-MM-DD), urutan, dan tugas pada setiap baris. Setelah diimpor, tugas langsung masuk ke akun karyawan.</p>
        <div class="flex flex-wrap items-center gap-2">
          <button class="btn btn-ghost btn-sm" type="button" @click="downloadImportTemplate">⬇ Unduh Template Excel</button>
          <label class="btn btn-ghost btn-sm cursor-pointer">
            Pilih File Excel
            <input ref="importInput" class="hidden" type="file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" @change="selectImportFile">
          </label>
          <span v-if="importFile" class="max-w-full truncate text-xs text-inkmuted">{{ importFile.name }}</span>
          <button class="btn btn-primary btn-sm" type="button" :disabled="!importFile || importing" @click="importTasks">
            {{ importing ? 'Mengimpor...' : 'Impor Tugas' }}
          </button>
        </div>
      </div>

      <button class="btn btn-primary" @click="assignChecklist">Tugaskan Daftar Ini</button>
    </div>

    <div class="card p-4">
      <div class="flex justify-between items-center gap-3 mb-3 flex-wrap">
        <div class="font-display font-bold text-base">Daftar Tugas — {{ formatDate(date) }}</div>
        <div class="flex gap-2">
          <button class="btn btn-ghost btn-sm" @click="exportTasks('excel')">⬇ Ekspor Excel</button>
          <button class="btn btn-ghost btn-sm" @click="exportTasks('pdf')">⬇ Ekspor PDF</button>
        </div>
      </div>
      <div v-if="employeesWithTasks.length === 0" class="text-inkfaint text-center py-8">Belum ada tugas untuk tanggal ini.</div>
      <div v-for="emp in employeesWithTasks" :key="emp.id" class="mb-4">
        <div class="text-[11px] uppercase tracking-wide font-bold text-inkfaint mb-1">{{ emp.name }}</div>
        <div v-for="(t, i) in tasksFor(emp.id)" :key="t.id" class="flex justify-between items-start py-2.5 border-b border-line last:border-0">
          <div class="flex gap-2.5 items-start">
            <span class="font-mono text-[11px] text-inkfaint mt-0.5">#{{ i + 1 }}</span>
            <img v-if="t.photo_url" :src="t.photo_url" class="w-10 h-10 rounded-lg object-cover cursor-pointer" @click="viewPhoto(t.photo_url)">
            <div>
              <div class="font-bold text-sm">{{ t.title }}</div>
              <div v-if="t.status === 'done'" class="text-[11px] text-inkfaint">Selesai {{ formatDateTime(t.completed_at) }}</div>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <span class="tag" :class="t.status === 'done' ? 'tag-approved' : 'tag-pending'">{{ t.status === 'done' ? 'Selesai' : 'Belum' }}</span>
            <button class="btn btn-ghost btn-sm" @click="removeTask(t.id)">Hapus</button>
          </div>
        </div>
      </div>
    </div>

    <div v-if="photoModal" class="fixed inset-0 bg-black/45 flex items-center justify-center z-[100] p-5" @click.self="photoModal = null">
      <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6">
        <h3 class="font-display font-bold text-base mb-3">Foto Bukti</h3>
        <img :src="photoModal" class="w-full rounded-lg">
        <div class="flex justify-end mt-4"><button class="btn btn-ghost" @click="photoModal = null">Tutup</button></div>
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
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import api from '../../lib/api';
import { localDateISO, formatDate } from '../../lib/date';
import { useAuthStore } from '../../stores/auth';
import ConfirmDialog from '../../components/ConfirmDialog.vue';

function iso(d) { return localDateISO(d); }
function addDays(d, n) { const r = new Date(d); r.setDate(r.getDate() + n); return r; }

const date = ref(iso(new Date()));
const employees = ref([]);
const templates = ref([]);
const tasks = ref([]);
const assignee = ref('all');
const checked = ref({});
const newTitle = ref('');
const photoModal = ref(null);
const confirmation = ref(null);
const auth = useAuthStore();
const importFile = ref(null);
const importInput = ref(null);
const importing = ref(false);

const canExportTasks = computed(() => (
  auth.role === 'manager' || (auth.role === 'employee' && auth.user?.can_manage_schedule)
));
const isManager = computed(() => auth.role === 'manager');

const dayLabel = computed(() => {
  const d = new Date(date.value);
  const names = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
  return `${names[d.getDay()]}, ${formatDate(date.value)}`;
});

async function loadAll() {
  const [{ data: emps }, { data: tpl }, { data: tsk }] = await Promise.all([
    api.get('/employees'),
    api.get('/task-templates'),
    api.get('/tasks', { params: { date: date.value, scope: 'team' } }),
  ]);
  employees.value = emps;
  templates.value = tpl;
  tasks.value = tsk;
}
onMounted(loadAll);
watch(date, loadAll);

function shiftDay(n) { date.value = iso(addDays(new Date(date.value), n)); }
function goToday() { date.value = iso(new Date()); }

const employeesWithTasks = computed(() => employees.value.filter(e => tasks.value.some(t => t.user_id === e.id)));
function tasksFor(empId) {
  return tasks.value.filter(t => t.user_id === empId).sort((a, b) => a.sort_order - b.sort_order);
}

async function addTemplate() {
  if (!newTitle.value.trim()) { alert('Tulis nama tugas terlebih dahulu.'); return; }
  const { data: tpl } = await api.post('/task-templates', { title: newTitle.value.trim() });
  templates.value.push(tpl);
  checked.value[tpl.id] = true;
  newTitle.value = '';
}

function selectImportFile(event) {
  importFile.value = event.target.files?.[0] || null;
}

async function downloadErrorMessage(payload, fallback) {
  if (payload instanceof Blob) {
    try {
      const body = JSON.parse(await payload.text());
      return body.message || fallback;
    } catch {
      return fallback;
    }
  }

  return payload?.message || fallback;
}

async function downloadImportTemplate() {
  try {
    const response = await api.get('/tasks/import-template', { responseType: 'blob' });
    const contentType = String(response.headers['content-type'] || '').toLowerCase();

    if (!contentType.includes('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')) {
      throw new Error(await downloadErrorMessage(
        response.data,
        'Server belum mengirim template Excel yang valid. Muat ulang halaman lalu coba kembali.'
      ));
    }

    const url = URL.createObjectURL(response.data);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'template_impor_tugas_harian.xlsx';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
  } catch (error) {
    alert(
      error.message
      || await downloadErrorMessage(error.response?.data, 'Gagal mengunduh template Excel.')
    );
  }
}

async function importTasks() {
  if (!importFile.value) return;

  importing.value = true;
  const form = new FormData();
  form.append('file', importFile.value);
  try {
    const { data } = await api.post('/tasks/import-excel', form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    alert(data.message);
    importFile.value = null;
    if (importInput.value) importInput.value.value = '';
    await loadAll();
  } catch (error) {
    alert(error.response?.data?.message || 'Gagal mengimpor tugas dari Excel.');
  } finally {
    importing.value = false;
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

function removeTemplate(id) {
  const template = templates.value.find(t => t.id === id);
  requestConfirmation(
    'Hapus dari daftar tugas?',
    `Tugas “${template?.title || 'ini'}” akan dihapus dari daftar tugas yang tersedia.`,
    'Ya, Hapus',
    async () => {
      await api.delete(`/task-templates/${id}`);
      templates.value = templates.value.filter(t => t.id !== id);
      delete checked.value[id];
    }
  );
}
async function assignChecklist() {
  const titles = templates.value.filter(t => checked.value[t.id]).map(t => t.title);
  if (titles.length === 0) { alert('Centang minimal satu tugas terlebih dahulu.'); return; }
  await api.post('/tasks/bulk', { date: date.value, assignee: assignee.value, titles });
  checked.value = {};
  await loadAll();
}
function removeTask(id) {
  requestConfirmation(
    'Hapus tugas?',
    'Tugas ini akan dihapus dari daftar karyawan. Tindakan ini tidak dapat dibatalkan.',
    'Ya, Hapus',
    async () => {
      await api.delete(`/tasks/${id}`);
      await loadAll();
    }
  );
}
function viewPhoto(url) { photoModal.value = url; }
function formatDateTime(dt) {
  return dt ? new Date(dt).toLocaleString('id-ID') : '';
}

async function exportTasks(format) {
  const extension = format === 'excel' ? 'xlsx' : 'pdf';
  const endpoint = format === 'excel' ? '/tasks/export-excel' : '/tasks/export-pdf';

  try {
    const response = await api.get(endpoint, {
      params: { date: date.value },
      responseType: 'blob',
    });
    const url = URL.createObjectURL(response.data);
    const link = document.createElement('a');
    link.href = url;
    link.download = `daftar_tugas_${date.value}.${extension}`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
  } catch (e) {
    alert(e.response?.data?.message || 'Gagal mengekspor daftar tugas.');
  }
}
</script>
