<template>
  <div>
    <!-- Week navigation -->
    <div class="flex items-center gap-2 mb-4 flex-wrap">
      <button class="btn btn-ghost btn-sm" @click="shiftWeek(-7)">← Sebelumnya</button>
      <div class="font-mono font-semibold text-sm bg-surfacealt px-3 py-1.5 rounded-lg">{{ weekLabel }}</div>
      <button class="btn btn-ghost btn-sm" @click="shiftWeek(7)">Selanjutnya →</button>
      <button class="btn btn-ghost btn-sm" @click="goToday">Minggu ini</button>
      <input type="date" v-model="weekStart" class="border border-line rounded-lg px-2.5 py-1.5 text-xs">
      <span class="tag" :class="data?.published ? 'tag-approved' : 'tag-pending'">
        {{ data?.published ? 'Terpublikasi' : 'Draf' }}
      </span>
      <button v-if="data?.published" class="btn btn-ghost btn-sm" @click="unpublish">Batalkan publikasi</button>
      <button v-else class="btn btn-primary btn-sm" @click="publish">Publikasikan minggu ini</button>
      <button class="btn btn-ghost btn-sm ml-auto" @click="exportExcel">⬇ Ekspor Excel</button>
    </div>

    <div class="card mb-4 flex flex-wrap items-center justify-between gap-3 p-3">
      <div class="flex items-center gap-3">
        <span class="flex h-2.5 w-2.5 rounded-full bg-teal animate-pulse"></span>
        <div>
          <div class="text-sm font-bold">Absensi real-time aktif</div>
          <div class="text-xs text-inkmuted">Data jam dan foto absen diperbarui otomatis setiap 20 detik.</div>
        </div>
      </div>
      <div class="flex flex-wrap items-center gap-3 text-xs">
        <span class="tag tag-approved">Masuk {{ attendanceSummary.clockedIn }}</span>
        <span class="tag tag-pending">Belum absen {{ attendanceSummary.notClockedIn }}</span>
        <span class="font-mono text-inkfaint">Diperbarui {{ lastUpdatedLabel }}</span>
        <button class="btn btn-ghost btn-sm" @click="load()">↻ Perbarui</button>
      </div>
    </div>

    <div v-if="loading" class="card p-10 text-center text-inkfaint">Memuat jadwal...</div>

    <div v-else-if="loadError" class="card p-10 text-center text-coral">
      <div class="font-bold mb-2">Gagal memuat jadwal</div>
      <div class="text-sm mb-4">{{ loadError }}</div>
      <button class="btn btn-primary btn-sm" @click="load">Coba Lagi</button>
    </div>

    <div v-else class="card overflow-x-auto">
      <table class="w-full border-collapse min-w-[900px]">
        <thead>
          <tr class="bg-surfacealt text-[11px] uppercase text-inkmuted">
            <th class="text-left p-2.5 min-w-[170px]">Karyawan</th>
            <th v-for="(d, i) in days" :key="i" class="p-2.5 text-center min-w-[120px]">
              <div class="font-display text-ink text-[13px] normal-case">{{ dayNames[i] }}</div>
              <div class="font-mono text-inkfaint text-[11px] uppercase">{{ formatDay(d) }}</div>
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="emp in data.employees" :key="emp.id" class="border-t border-line">
            <td class="p-2 align-top">
              <div class="font-bold text-sm">{{ emp.name }}</div>
              <div class="text-inkmuted text-[11px]">{{ emp.position }}</div>
              <div class="font-mono text-[11px] mt-1">
                <span :class="scheduledHours(emp.id) > emp.max_hours ? 'text-coral font-bold' : 'text-inkmuted'">
                  {{ scheduledHours(emp.id).toFixed(1) }} / {{ emp.max_hours }}
                </span>
                <span class="text-teal font-semibold"> jam</span>
              </div>
            </td>
            <td v-for="(d, i) in days" :key="i" class="p-2 align-top border-l border-line">
              <div v-if="leaveOn(emp.id, d)" class="bg-[#F6D9D3] text-coralink text-[11px] font-bold rounded-lg px-2 py-1.5 text-center">
                🏖 Cuti
              </div>
              <div v-else
                   class="min-h-[56px] rounded-lg cursor-pointer hover:bg-[#F5F8F1] p-1"
                   @click="openAssign(emp, d)">
                <div v-if="scheduleOn(emp.id, d)" class="rounded-md px-2 py-1.5 text-[11px] font-semibold shadow-sm" :class="chipClass(scheduleOn(emp.id, d).shift_template.color)">
                  {{ scheduleOn(emp.id, d).shift_template.name }}
                  <div class="font-mono text-[10px] opacity-80">{{ scheduleOn(emp.id, d).shift_template.start_time.slice(0,5) }}–{{ scheduleOn(emp.id, d).shift_template.end_time.slice(0,5) }}</div>
                </div>
                <div v-else class="text-inkfaint text-[11px] text-center py-3">+ Tugaskan</div>
                <div class="text-[10px] mt-1.5 space-y-1" v-if="attendanceInfo(emp.id, d)">
                  <!-- Info waktu masuk & pulang -->
                  <div class="font-mono" :class="attendanceInfo(emp.id, d).warn ? 'text-coral font-bold' : 'text-inkmuted'">
                    {{ attendanceInfo(emp.id, d).text }}
                  </div>
                  <!-- Tombol foto: setiap foto di baris sendiri -->
                  <template v-if="attendanceInfo(emp.id, d).photos.length">
                    <button
                      v-for="photo in attendanceInfo(emp.id, d).photos"
                      :key="photo.label"
                      type="button"
                      class="w-full flex items-center gap-1.5 rounded-lg border bg-white px-1.5 py-0.5 transition-colors"
                      :class="photo.label === 'Masuk'
                        ? 'border-teal/30 hover:border-teal hover:bg-[#CDEAE2]/30'
                        : 'border-coral/30 hover:border-coral hover:bg-[#F6D9D3]/30'"
                      :title="`Lihat foto absen ${photo.label.toLowerCase()}`"
                      @click.stop="openAttendancePhoto(photo, emp.name, iso(d))"
                    >
                      <img
                        :src="photo.url"
                        :alt="`Bukti absen ${photo.label}`"
                        class="w-7 h-7 rounded-md object-cover flex-shrink-0 border"
                        :class="photo.label === 'Masuk' ? 'border-teal/40' : 'border-coral/40'"
                      >
                      <div class="flex flex-col items-start min-w-0">
                        <span
                          class="text-[9px] font-bold leading-none"
                          :class="photo.label === 'Masuk' ? 'text-tealink' : 'text-coralink'"
                        >📷 {{ photo.label }}</span>
                        <span class="text-[9px] font-mono text-inkfaint leading-tight">{{ photo.time }}</span>
                      </div>
                    </button>
                  </template>
                </div>
              </div>
            </td>
          </tr>
          <tr v-if="data.employees.length === 0">
            <td :colspan="8" class="p-10 text-center text-inkfaint">Belum ada karyawan.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal pilih shift -->
    <Transition name="app-modal" appear>
      <div v-if="assignModal" class="fixed inset-0 bg-black/45 flex items-center justify-center z-[100] p-5" @click.self="assignModal = null">
        <div class="app-modal-card bg-white rounded-xl shadow-2xl w-full max-w-sm p-6">
          <h3 class="font-display font-bold text-base">{{ assignModal.emp.name }}</h3>
          <p class="text-inkmuted text-xs mb-4">{{ formatDate(assignModal.date) }} · Pilih shift atau kosongkan slot.</p>
          <div class="flex flex-col gap-2">
            <button v-for="s in shiftTemplates" :key="s.id"
                    class="flex justify-between items-center px-3 py-2.5 rounded-lg border border-line hover:border-ink text-sm font-semibold"
                    @click="assignShift(s.id)">
              <span><span class="inline-block w-3 h-3 rounded-sm mr-2" :class="dotClass(s.color)"></span>{{ s.name }}
                <span class="font-mono text-inkmuted font-medium">({{ s.start_time.slice(0,5) }}-{{ s.end_time.slice(0,5) }})</span></span>
            </button>
            <button class="px-3 py-2.5 rounded-lg border border-line text-coral font-semibold text-sm text-left" @click="assignShift(null)">
              Kosongkan slot
            </button>
          </div>
          <div class="flex justify-end mt-4">
            <button class="btn btn-ghost" @click="assignModal = null">Tutup</button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Modal bukti foto absen & Peta Real Posisi Kerja -->
    <Transition name="app-modal" appear>
      <div v-if="attendancePhotoModal" class="fixed inset-0 bg-black/50 backdrop-blur-xs flex items-center justify-center z-[100] p-4 sm:p-5" @click.self="attendancePhotoModal = null">
        <div class="app-modal-card bg-white rounded-xl shadow-2xl w-full max-w-lg p-5 sm:p-6 max-h-[92vh] overflow-y-auto">
          <div class="flex items-center justify-between mb-2">
            <div>
              <h3 class="font-display font-bold text-base text-ink">Bukti Absen {{ attendancePhotoModal.label }}</h3>
              <p class="text-inkmuted text-xs">{{ attendancePhotoModal.employee }} · {{ formatDate(attendancePhotoModal.date) }} <span v-if="attendancePhotoModal.time">· {{ attendancePhotoModal.time }}</span></p>
            </div>
            <button type="button" class="text-inkfaint hover:text-ink text-base leading-none p-1 rounded-md transition" @click="attendancePhotoModal = null" aria-label="Tutup">
              ✕
            </button>
          </div>

          <!-- Foto Absensi Karyawan -->
          <div class="rounded-xl overflow-hidden border border-line bg-surfacealt max-h-[42vh] flex items-center justify-center mb-3">
            <img :src="attendancePhotoModal.url" :alt="`Foto absen ${attendancePhotoModal.label}`" class="w-full h-full max-h-[42vh] object-contain">
          </div>

          <!-- Peta Real & Verifikasi Posisi Kerja Manajer -->
          <div class="rounded-xl border border-line bg-surfacealt/70 p-3.5 mb-3 text-xs">
            <div class="flex items-center justify-between mb-2">
              <span class="font-bold flex items-center gap-1.5 text-ink">
                <span>📍</span> Posisi Kerja Karyawan (Maps Real)
              </span>
              <a
                v-if="attendancePhotoModal.lat && attendancePhotoModal.lng"
                :href="attendancePhotoModal.maps_url || ('https://www.google.com/maps?q=' + attendancePhotoModal.lat + ',' + attendancePhotoModal.lng)"
                target="_blank"
                rel="noopener"
                class="btn btn-teal btn-sm text-[11px] py-1 px-2.5 shadow-xs"
              >
                🗺️ Buka di Google Maps ↗
              </a>
            </div>

            <div v-if="attendancePhotoModal.lat && attendancePhotoModal.lng" class="space-y-2.5">
              <!-- Rincian Wilayah: Desa/Kelurahan, Kecamatan, Kabupaten/Kota -->
              <div class="grid grid-cols-3 gap-1.5 bg-white p-2.5 rounded-lg border border-line text-center">
                <div class="p-1 bg-surfacealt/40 rounded">
                  <div class="text-[9px] uppercase tracking-wide text-inkfaint font-semibold">Desa / Kel.</div>
                  <div class="text-[11px] font-bold text-ink truncate" :title="attendancePhotoModal.desa || '—'">
                    {{ attendancePhotoModal.desa || '—' }}
                  </div>
                </div>
                <div class="p-1 bg-surfacealt/40 rounded">
                  <div class="text-[9px] uppercase tracking-wide text-inkfaint font-semibold">Kecamatan</div>
                  <div class="text-[11px] font-bold text-ink truncate" :title="attendancePhotoModal.kecamatan || '—'">
                    {{ attendancePhotoModal.kecamatan || '—' }}
                  </div>
                </div>
                <div class="p-1 bg-surfacealt/40 rounded">
                  <div class="text-[9px] uppercase tracking-wide text-inkfaint font-semibold">Kabupaten / Kota</div>
                  <div class="text-[11px] font-bold text-ink truncate" :title="attendancePhotoModal.kabupaten || '—'">
                    {{ attendancePhotoModal.kabupaten || '—' }}
                  </div>
                </div>
              </div>

              <!-- Alamat Lengkap -->
              <div v-if="attendancePhotoModal.address" class="bg-white p-2 rounded-lg border border-line text-[11px] text-ink leading-snug">
                <div class="text-[9px] uppercase tracking-wide text-inkfaint font-semibold mb-0.5">Alamat Lengkap:</div>
                <div>{{ attendancePhotoModal.address }}</div>
              </div>

              <!-- Koordinat Latitude & Longitude -->
              <div class="flex items-center justify-between text-[11px] font-mono text-inkmuted bg-white/70 px-2 py-1 rounded border border-line">
                <span>Lat: {{ attendancePhotoModal.lat }}</span>
                <span>Lng: {{ attendancePhotoModal.lng }}</span>
              </div>

              <!-- Peta Interaktif OpenStreetMap Real dengan Marker -->
              <div class="rounded-lg overflow-hidden border border-line h-40 w-full shadow-2xs">
                <iframe
                  :src="'https://www.openstreetmap.org/export/embed.html?bbox=' + (attendancePhotoModal.lng - 0.004) + '%2C' + (attendancePhotoModal.lat - 0.003) + '%2C' + (attendancePhotoModal.lng + 0.004) + '%2C' + (attendancePhotoModal.lat + 0.003) + '&layer=mapnik&marker=' + attendancePhotoModal.lat + '%2C' + attendancePhotoModal.lng"
                  class="w-full h-full border-0"
                  loading="lazy"
                ></iframe>
              </div>
            </div>
            <div v-else class="text-inkfaint text-xs py-1">
              Data koordinat GPS belum tercatat pada sesi absensi ini.
            </div>
          </div>

          <div class="flex justify-end pt-2 border-t border-line">
            <button class="btn btn-ghost text-xs sm:text-sm" @click="attendancePhotoModal = null">Tutup</button>
          </div>
        </div>
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import api from '../../lib/api';
import { localDateISO, formatDate } from '../../lib/date';

const dayNames = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
const monthShort = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];

function mondayOf(date) {
  const d = new Date(date);
  const day = d.getDay();
  const diff = day === 0 ? -6 : 1 - day;
  d.setDate(d.getDate() + diff);
  d.setHours(0, 0, 0, 0);
  return d;
}
function iso(d) { return localDateISO(d); }
function addDays(d, n) { const r = new Date(d); r.setDate(r.getDate() + n); return r; }

const weekStart = ref(iso(mondayOf(new Date())));
const data = ref(null);
const shiftTemplates = ref([]);
const loading = ref(true);
const loadError = ref(null);
const assignModal = ref(null);
const attendancePhotoModal = ref(null);
const lastUpdated = ref(null);
let refreshTimer = null;

const days = computed(() => {
  const start = new Date(weekStart.value);
  return Array.from({ length: 7 }, (_, i) => addDays(start, i));
});

const weekLabel = computed(() => {
  if (!days.value.length) return '';
  const a = days.value[0], b = days.value[6];
  return `${formatDay(a)} – ${formatDay(b)}`;
});

const attendanceSummary = computed(() => {
  if (!data.value) return { clockedIn: 0, notClockedIn: 0 };

  const today = iso(new Date());
  const scheduledToday = data.value.schedules.filter(schedule => schedule.date === today);
  const clockedIn = data.value.attendances.filter(attendance => attendance.date === today && attendance.clock_in).length;
  const notClockedIn = scheduledToday.filter(schedule => !data.value.leaves.some(leave => leave.user_id === schedule.user_id
    && today >= leave.start_date && today <= leave.end_date)
    && !data.value.attendances.some(attendance => attendance.user_id === schedule.user_id
      && attendance.date === today && attendance.clock_in)).length;

  return { clockedIn, notClockedIn };
});

const lastUpdatedLabel = computed(() => lastUpdated.value
  ? lastUpdated.value.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
  : '—');

function formatDay(d) {
  const day = String(d.getDate()).padStart(2, '0');
  const month = String(d.getMonth() + 1).padStart(2, '0');
  const year = d.getFullYear();
  return `${day}-${month}-${year}`;
}

async function load({ silent = false } = {}) {
  if (!silent) {
    loading.value = true;
    loadError.value = null;
  }
  try {
    const [{ data: weekData }, { data: shifts }] = await Promise.all([
      api.get('/schedule/week', { params: { week_start: weekStart.value } }),
      api.get('/shift-templates'),
    ]);
    data.value = weekData;
    shiftTemplates.value = shifts;
    lastUpdated.value = new Date();
  } catch (e) {
    if (!silent) {
      loadError.value = e.response?.data?.message || e.message || 'Terjadi kesalahan saat memuat jadwal.';
    }
  } finally {
    if (!silent) loading.value = false;
  }
}

onMounted(() => {
  load();
  refreshTimer = window.setInterval(() => load({ silent: true }), 20000);
});
onUnmounted(() => {
  if (refreshTimer) window.clearInterval(refreshTimer);
});
watch(weekStart, () => load());

function shiftWeek(n) { weekStart.value = iso(addDays(new Date(weekStart.value), n)); }
function goToday() { weekStart.value = iso(mondayOf(new Date())); }

function scheduleOn(empId, date) {
  const d = iso(date);
  return data.value.schedules.find(s => s.user_id === empId && s.date === d);
}
function leaveOn(empId, date) {
  const d = iso(date);
  return data.value.leaves.find(l => l.user_id === empId && d >= l.start_date && d <= l.end_date);
}
function scheduledHours(empId) {
  return data.value.schedules
    .filter(s => s.user_id === empId)
    .reduce((sum, s) => sum + durationHours(s.shift_template), 0);
}
function durationHours(shift) {
  if (!shift?.start_time || !shift?.end_time) return 0;
  const [sh, sm] = shift.start_time.split(':').map(Number);
  const [eh, em] = shift.end_time.split(':').map(Number);
  let mins = (eh * 60 + em) - (sh * 60 + sm);
  if (mins <= 0) mins += 24 * 60;
  return mins / 60;
}
function attendanceInfo(empId, date) {
  const d = iso(date);
  const today = iso(new Date());
  const sched = scheduleOn(empId, date);
  if (!sched || d > today) return null;
  const att = data.value.attendances.find(a => a.user_id === empId && a.date === d);
  if (att && (att.clock_in || att.clock_out)) {
    const inT = att.clock_in ? new Date(att.clock_in).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) : '—';
    const outT = att.clock_out ? new Date(att.clock_out).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) : '—';
    const photos = [
      att.clock_in_photo_url && {
        label: 'Masuk',
        url: att.clock_in_photo_url,
        time: inT,
        lat: att.clock_in_lat,
        lng: att.clock_in_lng,
        desa: att.clock_in_desa,
        kecamatan: att.clock_in_kecamatan,
        kabupaten: att.clock_in_kabupaten,
        address: att.clock_in_address,
        location_name: att.clock_in_location_name,
        maps_url: att.clock_in_maps_url || (att.clock_in_lat ? `https://www.google.com/maps?q=${att.clock_in_lat},${att.clock_in_lng}` : null),
      },
      att.clock_out_photo_url && {
        label: 'Pulang',
        url: att.clock_out_photo_url,
        time: outT,
        lat: att.clock_out_lat,
        lng: att.clock_out_lng,
        desa: att.clock_out_desa,
        kecamatan: att.clock_out_kecamatan,
        kabupaten: att.clock_out_kabupaten,
        address: att.clock_out_address,
        location_name: att.clock_out_location_name,
        maps_url: att.clock_out_maps_url || (att.clock_out_lat ? `https://www.google.com/maps?q=${att.clock_out_lat},${att.clock_out_lng}` : null),
      },
    ].filter(Boolean);

    return { text: `Masuk ${inT} · Pulang ${outT}`, warn: false, photos };
  }
  return { text: 'Belum absen', warn: true, photos: [] };
}

function chipClass(color) {
  return {
    amber: 'bg-[#FBE3B8] text-amberink',
    teal: 'bg-[#CDEAE2] text-tealink',
    plum: 'bg-[#E4D9F7] text-plumink',
  }[color] || 'bg-[#FBE3B8] text-amberink';
}
function dotClass(color) {
  return { amber: 'bg-amber', teal: 'bg-teal', plum: 'bg-plum' }[color] || 'bg-amber';
}

function openAssign(emp, date) {
  assignModal.value = { emp, date: iso(date) };
}
function openAttendancePhoto(photo, employee, date) {
  attendancePhotoModal.value = { ...photo, employee, date };
}
async function assignShift(shiftId) {
  const { emp, date } = assignModal.value;
  try {
    await api.post('/schedule/assign', { user_id: emp.id, date, shift_template_id: shiftId });
    assignModal.value = null;
    await load();
  } catch (e) {
    alert(e.response?.data?.message || 'Gagal menyimpan jadwal.');
  }
}

async function publish() {
  await api.post('/schedule/publish', { week_start: weekStart.value });
  await load();
}
async function unpublish() {
  await api.post('/schedule/unpublish', { week_start: weekStart.value });
  await load();
}
async function exportExcel() {
  const response = await api.get('/schedule/export-excel', {
    params: { week_start: weekStart.value },
    responseType: 'blob',
  });
  const url = URL.createObjectURL(response.data);
  const a = document.createElement('a');
  a.href = url;
  a.download = `jadwal_absensi_${weekStart.value}.xlsx`;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
}
</script>
