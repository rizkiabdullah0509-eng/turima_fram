<template>
  <div class="grid lg:grid-cols-2 gap-4 items-start">
    <div class="card p-4">
      <div class="font-display font-bold text-base mb-1">Ajukan Cuti</div>
      <p class="text-inkmuted text-xs mb-3">Manajer akan meninjau permintaan Anda.</p>
      <div class="field mb-2"><label>Tanggal Mulai</label><input type="date" v-model="leaveForm.start_date"></div>
      <div class="field mb-2"><label>Tanggal Selesai</label><input type="date" v-model="leaveForm.end_date"></div>
      <div class="field mb-3"><label>Alasan</label><textarea v-model="leaveForm.reason" rows="2" placeholder="Contoh: acara keluarga, sakit, dll."></textarea></div>
      <button class="btn btn-primary" @click="submitLeave">Kirim Permintaan Cuti</button>

      <div class="text-[11px] uppercase font-bold text-inkfaint mt-5 mb-1">Status Cuti Saya</div>
      <div v-if="myLeaves.length === 0" class="text-inkfaint text-center py-4">Belum ada pengajuan cuti.</div>
      <div v-for="l in myLeaves" :key="l.id" class="flex justify-between items-center py-2 border-b border-line last:border-0 text-sm">
        <div>{{ formatDateRange(l.start_date, l.end_date) }}</div>
        <span class="tag" :class="l.status === 'approved' ? 'tag-approved' : l.status === 'rejected' ? 'tag-rejected' : 'tag-pending'">
          {{ l.status === 'approved' ? 'Disetujui' : l.status === 'rejected' ? 'Ditolak' : 'Menunggu' }}
        </span>
      </div>
    </div>

    <div class="card p-4">
      <div class="font-display font-bold text-base mb-1">Ajukan Tukar Shift</div>
      <p class="text-inkmuted text-xs mb-3">Pilih shift Anda minggu ini dan rekan pengganti.</p>

      <template v-if="myShiftOptions.length">
        <div class="field mb-2">
          <label>Shift Saya</label>
          <select v-model="swapForm.date">
            <option v-for="o in myShiftOptions" :key="o.date" :value="o.date">{{ o.label }}</option>
          </select>
        </div>
        <div class="field mb-3">
          <label>Tukar dengan</label>
          <select v-model="swapForm.to_user_id">
            <option v-for="e in otherEmployees" :key="e.id" :value="e.id">{{ e.name }}</option>
          </select>
        </div>
        <button class="btn btn-primary" @click="submitSwap">Kirim Permintaan Tukar</button>
      </template>
      <div v-else class="text-inkfaint text-center py-4">Anda tidak memiliki shift terjadwal minggu ini untuk ditukar.</div>

      <div class="text-[11px] uppercase font-bold text-inkfaint mt-5 mb-1">Permintaan Tukar Terkait Saya</div>
      <div v-if="mySwaps.length === 0" class="text-inkfaint text-center py-4">Belum ada permintaan tukar shift.</div>
      <div v-for="s in mySwaps" :key="s.id" class="flex justify-between items-start py-2 border-b border-line last:border-0 text-sm">
        <div>
          {{ s.from_user.name }} → {{ s.to_user.name }}
          <div class="font-mono text-inkmuted text-xs">{{ formatDate(s.date) }} · {{ s.shift_template.name }}</div>
        </div>
        <div v-if="s.to_user_id === me.id && s.status === 'pending_peer'" class="flex gap-1.5 shrink-0">
          <button class="btn btn-teal btn-sm" @click="peerRespond(s, true)">Terima</button>
          <button class="btn btn-coral btn-sm" @click="peerRespond(s, false)">Tolak</button>
        </div>
        <span v-else class="tag" :class="swapTagClass(s.status)">{{ swapLabel(s.status) }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import api from '../../lib/api';
import { useAuthStore } from '../../stores/auth';
import { localDateISO, formatDate, formatDateRange } from '../../lib/date';

const auth = useAuthStore();
const me = auth.user;

const leaveForm = ref({ start_date: '', end_date: '', reason: '' });
const swapForm = ref({ date: '', to_user_id: null });

const myLeaves = ref([]);
const mySwaps = ref([]);
const employees = ref([]);
const mySchedule = ref([]);

function iso(d) { return localDateISO(d); }
function mondayOf(date) {
  const d = new Date(date);
  const day = d.getDay();
  const diff = day === 0 ? -6 : 1 - day;
  d.setDate(d.getDate() + diff);
  d.setHours(0, 0, 0, 0);
  return d;
}

async function loadAll() {
  const [{ data: leaves }, { data: swaps }, { data: week }] = await Promise.all([
    api.get('/leave-requests/mine'),
    api.get('/swap-requests/mine'),
    api.get('/schedule/week', { params: { week_start: iso(mondayOf(new Date())) } }),
  ]);
  myLeaves.value = leaves;
  mySwaps.value = swaps;
  employees.value = week.employees;
  mySchedule.value = week.schedules.filter(s => s.user_id === me.id);
  if (swapForm.value.to_user_id === null && otherEmployees.value.length) {
    swapForm.value.to_user_id = otherEmployees.value[0].id;
  }
}
onMounted(loadAll);

const otherEmployees = computed(() => employees.value.filter(e => e.id !== me.id));
const dayNames = ['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'];
const myShiftOptions = computed(() => mySchedule.value.map(s => {
  const d = new Date(s.date);
  const dow = (d.getDay() + 6) % 7;
  return { date: s.date, label: `${dayNames[dow]} ${s.date} — ${s.shift_template.name} (${s.shift_template.start_time.slice(0,5)}-${s.shift_template.end_time.slice(0,5)})` };
}));

async function submitLeave() {
  if (!leaveForm.value.start_date || !leaveForm.value.end_date) { alert('Pilih tanggal mulai dan selesai.'); return; }
  if (leaveForm.value.end_date < leaveForm.value.start_date) { alert('Tanggal selesai harus setelah tanggal mulai.'); return; }
  await api.post('/leave-requests', leaveForm.value);
  leaveForm.value = { start_date: '', end_date: '', reason: '' };
  await loadAll();
}
async function submitSwap() {
  await api.post('/swap-requests', swapForm.value);
  await loadAll();
}
async function peerRespond(s, accept) {
  await api.post(`/swap-requests/${s.id}/peer-respond`, { accept });
  await loadAll();
}
function swapLabel(status) {
  return {
    pending_peer: 'Menunggu rekan', pending_manager: 'Menunggu manajer', approved: 'Disetujui',
    rejected_peer: 'Ditolak rekan', rejected_manager: 'Ditolak manajer',
  }[status] || status;
}
function swapTagClass(status) {
  if (status === 'approved') return 'tag-approved';
  if (status.startsWith('rejected')) return 'tag-rejected';
  return 'tag-pending';
}
</script>
