<template>
  <div>
    <div class="flex items-center gap-2 mb-4 flex-wrap">
      <button class="btn btn-ghost btn-sm" @click="shiftWeek(-7)">← Sebelumnya</button>
      <div class="font-mono font-semibold text-sm bg-surfacealt px-3 py-1.5 rounded-lg">{{ weekLabel }}</div>
      <button class="btn btn-ghost btn-sm" @click="shiftWeek(7)">Selanjutnya →</button>
      <button class="btn btn-ghost btn-sm" @click="goToday">Minggu ini</button>
      <span class="tag" :class="data?.published ? 'tag-approved' : 'tag-pending'">{{ data?.published ? 'Terpublikasi' : 'Draf' }}</span>
    </div>

    <div v-if="loading" class="card p-10 text-center text-inkfaint">Memuat jadwal...</div>

    <div v-else-if="loadError" class="card p-10 text-center text-coral">
      <div class="font-bold mb-2">Gagal memuat jadwal</div>
      <div class="text-sm mb-4">{{ loadError }}</div>
      <button class="btn btn-primary btn-sm" @click="load">Coba Lagi</button>
    </div>

    <div v-else-if="!data.published" class="card p-10 text-center text-inkfaint">
      <div class="font-display text-inkmuted mb-1">Jadwal minggu ini belum dipublikasikan</div>
      Cek kembali nanti, atau lihat minggu lain menggunakan tombol panah di atas.
    </div>

    <div v-else class="card overflow-x-auto">
      <table class="w-full border-collapse min-w-[700px]">
        <thead>
          <tr class="bg-surfacealt text-[11px] uppercase text-inkmuted">
            <th v-for="(d, i) in days" :key="i" class="p-2.5 text-center min-w-[100px]">
              <div class="font-display text-ink text-[13px] normal-case">{{ dayNames[i] }}</div>
              <div class="font-mono text-inkfaint text-[11px]">{{ formatDay(d) }}</div>
            </th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td v-for="(d, i) in days" :key="i" class="p-2 align-top border-l border-line">
              <div v-if="leaveOn(d)" class="bg-[#F6D9D3] text-coralink text-[11px] font-bold rounded-lg px-2 py-1.5 text-center">🏖 Cuti</div>
              <template v-else>
                <div v-if="scheduleOn(d)" class="rounded-md px-2 py-1.5 text-[11px] font-semibold shadow-sm" :class="chipClass(scheduleOn(d).shift_template.color)">
                  {{ scheduleOn(d).shift_template.name }}
                  <div class="font-mono text-[10px] opacity-80">{{ scheduleOn(d).shift_template.start_time.slice(0,5) }}–{{ scheduleOn(d).shift_template.end_time.slice(0,5) }}</div>
                </div>
                <div v-else class="text-inkfaint text-[11px] text-center py-2">Libur</div>

                <!-- Bukti Absen & Akses Foto + Maps Real -->
                <div v-if="attOn(d)" class="mt-1.5 p-1.5 rounded bg-surfacealt text-[10px] space-y-1">
                  <!-- Absen Masuk -->
                  <div v-if="attOn(d).clock_in">
                    <div class="flex items-center justify-between gap-1">
                      <span class="font-mono text-inkmuted font-medium">In: {{ formatTime(attOn(d).clock_in) }}</span>
                      <button
                        v-if="attOn(d).clock_in_photo_url"
                        type="button"
                        class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded border border-line bg-white hover:border-teal cursor-pointer shadow-2xs"
                        title="Akses foto dan peta absen masuk"
                        @click="openAttendancePhoto({
                          url: attOn(d).clock_in_photo_url,
                          time: formatTime(attOn(d).clock_in),
                          lat: attOn(d).clock_in_lat,
                          lng: attOn(d).clock_in_lng,
                          desa: attOn(d).clock_in_desa,
                          kecamatan: attOn(d).clock_in_kecamatan,
                          kabupaten: attOn(d).clock_in_kabupaten,
                          address: attOn(d).clock_in_address,
                          location_name: attOn(d).clock_in_location_name,
                          maps_url: attOn(d).clock_in_maps_url,
                        }, 'Absen Masuk', d)"
                      >
                        <img :src="attOn(d).clock_in_photo_url" alt="Foto Masuk" class="w-3.5 h-3.5 rounded-sm object-cover">
                        <span class="text-[9px] font-semibold text-tealink">Foto & Maps</span>
                      </button>
                    </div>
                    <!-- Info Lokasi Masuk (Desa, Kecamatan, Kabupaten) -->
                    <div
                      v-if="attOn(d).clock_in_desa || attOn(d).clock_in_kecamatan || attOn(d).clock_in_kabupaten"
                      class="text-[9px] text-tealink leading-tight truncate mt-0.5 flex items-center gap-0.5"
                      :title="attOn(d).clock_in_address || [attOn(d).clock_in_desa, attOn(d).clock_in_kecamatan, attOn(d).clock_in_kabupaten].filter(Boolean).join(', ')"
                    >
                      <span>📍</span>
                      <span class="truncate">{{ [attOn(d).clock_in_desa, attOn(d).clock_in_kecamatan].filter(Boolean).join(', ') || attOn(d).clock_in_kabupaten }}</span>
                    </div>
                  </div>

                  <!-- Absen Pulang -->
                  <div v-if="attOn(d).clock_out" class="pt-1 border-t border-line/50">
                    <div class="flex items-center justify-between gap-1">
                      <span class="font-mono text-inkmuted font-medium">Out: {{ formatTime(attOn(d).clock_out) }}</span>
                      <button
                        v-if="attOn(d).clock_out_photo_url"
                        type="button"
                        class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded border border-line bg-white hover:border-coral cursor-pointer shadow-2xs"
                        title="Akses foto dan peta absen pulang"
                        @click="openAttendancePhoto({
                          url: attOn(d).clock_out_photo_url,
                          time: formatTime(attOn(d).clock_out),
                          lat: attOn(d).clock_out_lat,
                          lng: attOn(d).clock_out_lng,
                          desa: attOn(d).clock_out_desa,
                          kecamatan: attOn(d).clock_out_kecamatan,
                          kabupaten: attOn(d).clock_out_kabupaten,
                          address: attOn(d).clock_out_address,
                          location_name: attOn(d).clock_out_location_name,
                          maps_url: attOn(d).clock_out_maps_url,
                        }, 'Absen Pulang', d)"
                      >
                        <img :src="attOn(d).clock_out_photo_url" alt="Foto Pulang" class="w-3.5 h-3.5 rounded-sm object-cover">
                        <span class="text-[9px] font-semibold text-coralink">Foto & Maps</span>
                      </button>
                    </div>
                    <!-- Info Lokasi Pulang (Desa, Kecamatan, Kabupaten) -->
                    <div
                      v-if="attOn(d).clock_out_desa || attOn(d).clock_out_kecamatan || attOn(d).clock_out_kabupaten"
                      class="text-[9px] text-coralink leading-tight truncate mt-0.5 flex items-center gap-0.5"
                      :title="attOn(d).clock_out_address || [attOn(d).clock_out_desa, attOn(d).clock_out_kecamatan, attOn(d).clock_out_kabupaten].filter(Boolean).join(', ')"
                    >
                      <span>📍</span>
                      <span class="truncate">{{ [attOn(d).clock_out_desa, attOn(d).clock_out_kecamatan].filter(Boolean).join(', ') || attOn(d).clock_out_kabupaten }}</span>
                    </div>
                  </div>
                </div>

                <div v-if="scheduleOn(d) && iso(d) === today" class="mt-1.5">
                  <button v-if="!attOn(d)?.clock_in && !isClockInClosed(d)" class="btn btn-teal btn-sm w-full justify-center" @click="openPhoto('in', d)">📷 Absen Masuk</button>
                  <button v-else-if="!attOn(d)?.clock_out" class="btn btn-coral btn-sm w-full justify-center" @click="openPhoto('out', d)">📷 Absen Pulang</button>
                  <div v-else class="font-mono text-[10px] text-inkfaint mt-1 text-center">✓ Selesai</div>
                </div>
              </template>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal Akses Foto untuk Absen Masuk / Pulang -->
    <PhotoUploadModal
      :show="!!photoTarget"
      :title="photoTarget?.mode === 'in' ? 'Akses Foto Absen Masuk' : 'Akses Foto Absen Pulang'"
      subtitle="Ambil foto dan verifikasi posisi kerja langsung via GPS Maps."
      :confirm-label="photoTarget?.mode === 'in' ? 'Konfirmasi Absen Masuk' : 'Konfirmasi Absen Pulang'"
      :confirm-class="photoTarget?.mode === 'in' ? 'btn-teal' : 'btn-coral'"
      @cancel="photoTarget = null"
      @confirm="submitAttendance"
    />

    <!-- Modal Akses & Lihat Foto Bukti Absen + Peta Real -->
    <Transition name="app-modal" appear>
      <div v-if="viewPhotoModal" class="fixed inset-0 bg-black/50 backdrop-blur-xs flex items-center justify-center z-[100] p-4 sm:p-5" @click.self="viewPhotoModal = null">
        <div class="app-modal-card bg-white rounded-xl shadow-2xl w-full max-w-lg p-5 sm:p-6 max-h-[92vh] overflow-y-auto">
          <div class="flex items-center justify-between mb-2">
            <div>
              <h3 class="font-display font-bold text-base text-ink">Bukti {{ viewPhotoModal.label }}</h3>
              <p class="text-inkmuted text-xs">{{ viewPhotoModal.date }} <span v-if="viewPhotoModal.time">· {{ viewPhotoModal.time }}</span></p>
            </div>
            <button type="button" class="text-inkfaint hover:text-ink text-base leading-none p-1 rounded-md transition" @click="viewPhotoModal = null" aria-label="Tutup">
              ✕
            </button>
          </div>

          <!-- Foto Absensi -->
          <div class="rounded-xl overflow-hidden border border-line bg-surfacealt max-h-[42vh] flex items-center justify-center mb-3">
            <img :src="viewPhotoModal.url" :alt="`Foto ${viewPhotoModal.label}`" class="w-full h-full max-h-[42vh] object-contain">
          </div>

          <!-- Peta Posisi Kerja Real & Rincian Alamat Lengkap -->
          <div class="rounded-xl border border-line bg-surfacealt/70 p-3.5 mb-3 text-xs">
            <div class="flex items-center justify-between mb-2">
              <span class="font-bold flex items-center gap-1.5 text-ink">
                <span>📍</span> Posisi Kerja (Maps Real)
              </span>
              <a
                v-if="viewPhotoModal.lat && viewPhotoModal.lng"
                :href="viewPhotoModal.maps_url || ('https://www.google.com/maps?q=' + viewPhotoModal.lat + ',' + viewPhotoModal.lng)"
                target="_blank"
                rel="noopener"
                class="btn btn-teal btn-sm text-[11px] py-1 px-2.5 shadow-xs"
              >
                🗺️ Buka di Google Maps ↗
              </a>
            </div>

            <div v-if="viewPhotoModal.lat && viewPhotoModal.lng" class="space-y-2.5">
              <!-- Rincian Wilayah: Desa/Kelurahan, Kecamatan, Kabupaten/Kota -->
              <div class="grid grid-cols-3 gap-1.5 bg-white p-2.5 rounded-lg border border-line text-center">
                <div class="p-1 bg-surfacealt/40 rounded">
                  <div class="text-[9px] uppercase tracking-wide text-inkfaint font-semibold">Desa / Kel.</div>
                  <div class="text-[11px] font-bold text-ink truncate" :title="viewPhotoModal.desa || '—'">
                    {{ viewPhotoModal.desa || '—' }}
                  </div>
                </div>
                <div class="p-1 bg-surfacealt/40 rounded">
                  <div class="text-[9px] uppercase tracking-wide text-inkfaint font-semibold">Kecamatan</div>
                  <div class="text-[11px] font-bold text-ink truncate" :title="viewPhotoModal.kecamatan || '—'">
                    {{ viewPhotoModal.kecamatan || '—' }}
                  </div>
                </div>
                <div class="p-1 bg-surfacealt/40 rounded">
                  <div class="text-[9px] uppercase tracking-wide text-inkfaint font-semibold">Kabupaten / Kota</div>
                  <div class="text-[11px] font-bold text-ink truncate" :title="viewPhotoModal.kabupaten || '—'">
                    {{ viewPhotoModal.kabupaten || '—' }}
                  </div>
                </div>
              </div>

              <!-- Alamat Lengkap -->
              <div v-if="viewPhotoModal.address" class="bg-white p-2 rounded-lg border border-line text-[11px] text-ink leading-snug">
                <div class="text-[9px] uppercase tracking-wide text-inkfaint font-semibold mb-0.5">Alamat Lengkap:</div>
                <div>{{ viewPhotoModal.address }}</div>
              </div>

              <!-- Koordinat Latitude & Longitude -->
              <div class="flex items-center justify-between text-[11px] font-mono text-inkmuted bg-white/70 px-2 py-1 rounded border border-line">
                <span>Lat: {{ viewPhotoModal.lat }}</span>
                <span>Lng: {{ viewPhotoModal.lng }}</span>
              </div>

              <!-- Peta Interaktif OpenStreetMap Real -->
              <div class="rounded-lg overflow-hidden border border-line h-40 w-full shadow-2xs">
                <iframe
                  :src="'https://www.openstreetmap.org/export/embed.html?bbox=' + (viewPhotoModal.lng - 0.004) + '%2C' + (viewPhotoModal.lat - 0.003) + '%2C' + (viewPhotoModal.lng + 0.004) + '%2C' + (viewPhotoModal.lat + 0.003) + '&layer=mapnik&marker=' + viewPhotoModal.lat + '%2C' + viewPhotoModal.lng"
                  class="w-full h-full border-0"
                  loading="lazy"
                ></iframe>
              </div>
            </div>
            <div v-else class="text-inkfaint text-xs py-1">
              Data koordinat GPS tidak tersimpan pada absensi ini.
            </div>
          </div>

          <div class="flex justify-end pt-2 border-t border-line">
            <button class="btn btn-ghost text-xs sm:text-sm" @click="viewPhotoModal = null">Tutup</button>
          </div>
        </div>
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import api from '../../lib/api';
import PhotoUploadModal from '../../components/PhotoUploadModal.vue';
import { useAuthStore } from '../../stores/auth';
import { localDateISO } from '../../lib/date';

const auth = useAuthStore();

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
const loading = ref(true);
const loadError = ref(null);
const photoTarget = ref(null);
const viewPhotoModal = ref(null);
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
const today = computed(() => data.value?.today || iso(new Date()));
function formatDay(d) {
  const day = String(d.getDate()).padStart(2, '0');
  const month = String(d.getMonth() + 1).padStart(2, '0');
  const year = d.getFullYear();
  return `${day}-${month}-${year}`;
}
function formatTime(dt) { return dt ? new Date(dt).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) : '—'; }

async function load({ silent = false } = {}) {
  if (!silent) {
    loading.value = true;
    loadError.value = null;
  }
  try {
    const { data: weekData } = await api.get('/schedule/week', { params: { week_start: weekStart.value } });
    data.value = weekData;
  } catch (e) {
    if (!silent) loadError.value = e.response?.data?.message || e.message || 'Gagal memuat jadwal.';
  } finally {
    if (!silent) loading.value = false;
  }
}
onMounted(() => {
  load();
  refreshTimer = window.setInterval(() => load({ silent: true }), 10000);
});
onUnmounted(() => {
  if (refreshTimer) window.clearInterval(refreshTimer);
});
watch(weekStart, () => load());

function shiftWeek(n) { weekStart.value = iso(addDays(new Date(weekStart.value), n)); }
function goToday() { weekStart.value = iso(mondayOf(new Date())); }

function scheduleOn(date) {
  if (!auth.user?.id) return null;
  const d = iso(date);
  return data.value.schedules.find(s => s.date === d && s.user_id === auth.user.id);
}
function leaveOn(date) {
  if (!auth.user?.id) return null;
  const d = iso(date);
  return data.value.leaves.find(l => l.user_id === auth.user.id && d >= l.start_date && d <= l.end_date);
}
function attOn(date) {
  if (!auth.user?.id) return null;
  const d = iso(date);
  return data.value.attendances.find(a => a.date === d && a.user_id === auth.user.id);
}
function isClockInClosed(date) {
  const schedule = scheduleOn(date);
  return Boolean(schedule?.clock_in_closed);
}
function chipClass(color) {
  return {
    amber: 'bg-[#FBE3B8] text-amberink',
    teal: 'bg-[#CDEAE2] text-tealink',
    plum: 'bg-[#E4D9F7] text-plumink',
  }[color] || 'bg-[#FBE3B8] text-amberink';
}

function openPhoto(mode, date) { photoTarget.value = { mode, date: iso(date) }; }
function openAttendancePhoto(photoData, label, date) {
  const isObj = typeof photoData === 'object' && photoData !== null;
  viewPhotoModal.value = {
    url: isObj ? photoData.url : photoData,
    label,
    date: formatDay(date),
    time: isObj ? photoData.time : null,
    lat: isObj ? photoData.lat : null,
    lng: isObj ? photoData.lng : null,
    desa: isObj ? photoData.desa : null,
    kecamatan: isObj ? photoData.kecamatan : null,
    kabupaten: isObj ? photoData.kabupaten : null,
    address: isObj ? photoData.address : null,
    location_name: isObj ? photoData.location_name : null,
    maps_url: isObj ? photoData.maps_url : null,
  };
}

async function submitAttendance(file, locData = {}) {
  const form = new FormData();
  form.append('date', photoTarget.value.date);
  form.append('photo', file);
  if (locData.latitude != null) form.append('latitude', locData.latitude);
  if (locData.longitude != null) form.append('longitude', locData.longitude);
  if (locData.desa) form.append('desa', locData.desa);
  if (locData.kecamatan) form.append('kecamatan', locData.kecamatan);
  if (locData.kabupaten) form.append('kabupaten', locData.kabupaten);
  if (locData.address) form.append('address', locData.address);
  if (locData.location_name) form.append('location_name', locData.location_name);

  const endpoint = photoTarget.value.mode === 'in' ? '/attendance/clock-in' : '/attendance/clock-out';
  try {
    await api.post(endpoint, form, { headers: { 'Content-Type': 'multipart/form-data' } });
    photoTarget.value = null;
    await load();
  } catch (e) {
    alert(e.response?.data?.message || 'Gagal menyimpan absen.');
  }
}
</script>
