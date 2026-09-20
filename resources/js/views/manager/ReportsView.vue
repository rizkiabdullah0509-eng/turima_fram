<template>
  <div class="space-y-4 sm:space-y-6">
    <!-- Navigation Bar & Minggu Selector -->
    <div class="card p-3.5 sm:p-4 bg-white shadow-sm border border-line">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <!-- Date Display Range -->
        <div class="flex items-center gap-2.5 min-w-0">
          <div class="w-10 h-10 rounded-xl bg-surfacealt text-branddark flex items-center justify-center shrink-0 border border-line shadow-xs">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
          </div>
          <div class="min-w-0">
            <div class="text-[11px] font-bold uppercase tracking-wider text-inkmuted">Periode Minggu</div>
            <div class="font-display font-bold text-sm sm:text-base text-ink truncate">
              {{ formatDateIndo(weekStart) }} – {{ formatDateIndo(report?.week_end) }}
            </div>
          </div>
        </div>

        <!-- Navigation Buttons -->
        <div class="flex items-center gap-1.5 sm:gap-2 self-stretch sm:self-auto justify-between sm:justify-start">
          <div class="flex items-center gap-1 bg-surfacealt p-1 rounded-lg border border-line flex-1 sm:flex-none justify-center">
            <button
              class="btn btn-ghost btn-sm px-2.5 py-1.5 text-xs rounded-md"
              title="Minggu Sebelumnya"
              @click="shiftWeek(-7)"
            >
              ← <span class="hidden sm:inline">Sebelumnya</span>
            </button>
            <button
              class="btn btn-ghost btn-sm px-2.5 py-1.5 text-xs rounded-md"
              title="Minggu Selanjutnya"
              @click="shiftWeek(7)"
            >
              <span class="hidden sm:inline">Selanjutnya</span> →
            </button>
          </div>

          <button
            class="btn btn-sm font-semibold transition-all"
            :class="isCurrentWeek ? 'btn-primary shadow-xs' : 'btn-ghost'"
            @click="goToday"
          >
            Minggu Ini
          </button>
        </div>
      </div>
    </div>

    <!-- Summary KPI Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 sm:gap-4" v-if="report?.rows">
      <!-- Total Staf -->
      <div class="card p-3.5 sm:p-4 bg-white border border-line flex flex-col justify-between">
        <div class="flex items-center justify-between text-inkmuted mb-1">
          <span class="text-xs font-semibold">Total Karyawan</span>
          <span class="w-7 h-7 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center text-xs font-bold">👥</span>
        </div>
        <div class="font-display font-bold text-xl sm:text-2xl text-ink">
          {{ totalEmployees }} <span class="text-xs font-normal text-inkmuted">staf</span>
        </div>
      </div>

      <!-- Total Scheduled -->
      <div class="card p-3.5 sm:p-4 bg-white border border-line flex flex-col justify-between">
        <div class="flex items-center justify-between text-inkmuted mb-1">
          <span class="text-xs font-semibold">Jam Terjadwal</span>
          <span class="w-7 h-7 rounded-lg bg-blue-50 text-blue-700 flex items-center justify-center text-xs font-bold">📋</span>
        </div>
        <div class="font-display font-bold text-xl sm:text-2xl text-ink font-mono">
          {{ totalScheduledHours.toFixed(1) }} <span class="text-xs font-sans font-normal text-inkmuted">jam</span>
        </div>
      </div>

      <!-- Late Count / Keterlambatan -->
      <div class="card p-3.5 sm:p-4 bg-white border border-line flex flex-col justify-between col-span-2 sm:col-span-1" :class="totalLateCount > 0 ? 'border-amber-400/40 bg-amber-50/30' : ''">
        <div class="flex items-center justify-between text-inkmuted mb-1">
          <span class="text-xs font-semibold">Keterlambatan</span>
          <span class="w-7 h-7 rounded-lg flex items-center justify-center text-xs font-bold" :class="totalLateCount > 0 ? 'bg-amber-500 text-white' : 'bg-gray-100 text-gray-600'">🕐</span>
        </div>
        <div class="font-display font-bold text-xl sm:text-2xl font-mono" :class="totalLateCount > 0 ? 'text-amber-600' : 'text-ink'">
          {{ totalLateCount }} <span class="text-xs font-sans font-normal text-inkmuted">kali</span>
        </div>
      </div>
    </div>

    <!-- Main Report Container -->
    <div class="card p-4 sm:p-5 bg-white border border-line">
      <!-- Title & Export Header -->
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between pb-4 mb-4 border-b border-line">
        <div>
          <h2 class="font-display font-bold text-lg sm:text-xl text-ink">Rekap Jam Kerja Tim</h2>
          <p class="text-inkmuted text-xs sm:text-sm mt-0.5">
            Perbandingan jam shift terjadwal vs. jam kerja aktual berdasarkan absensi karyawan.
          </p>
        </div>

        <button
          class="btn btn-ghost btn-sm shrink-0 self-start sm:self-auto flex items-center justify-center gap-1.5 border-line hover:bg-surfacealt text-xs font-semibold w-full sm:w-auto py-2 px-3.5"
          @click="exportCsv"
        >
          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
          </svg>
          <span>Ekspor Jadwal (CSV)</span>
        </button>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="py-12 text-center">
        <div class="inline-block animate-spin w-6 h-6 border-2 border-brand border-t-transparent rounded-full mb-2"></div>
        <div class="text-inkmuted text-sm font-medium">Memuat data laporan...</div>
      </div>

      <!-- Error State -->
      <div v-else-if="loadError" class="card bg-red-50/60 border border-coral/30 p-6 text-center">
        <div class="text-coral text-sm font-semibold mb-2">{{ loadError }}</div>
        <button class="btn btn-coral btn-sm inline-flex items-center gap-1" @click="load">
          🔄 Coba Lagi
        </button>
      </div>

      <!-- Empty State -->
      <div v-else-if="!report?.rows || report.rows.length === 0" class="py-12 text-center text-inkmuted">
        <div class="text-3xl mb-2">📊</div>
        <div class="text-sm font-semibold">Tidak ada data jam kerja untuk minggu ini.</div>
      </div>

      <template v-else>
        <!-- MOBILE CARD VIEW (Visibel pada Layar HP < 640px) -->
        <div class="block sm:hidden space-y-3">
          <div
            v-for="row in report.rows"
            :key="'mobile-' + row.employee.id"
            class="p-3.5 rounded-xl border transition-all bg-surfacealt/30 border-line hover:border-brand/30"
          >
            <!-- Card Header: Nama & Posisi -->
            <div class="flex items-center justify-between mb-3">
              <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-full bg-branddark text-white font-bold text-xs flex items-center justify-center shrink-0 shadow-xs">
                  {{ getInitials(row.employee.name) }}
                </div>
                <div class="min-w-0">
                  <div class="font-bold text-sm text-ink truncate">{{ row.employee.name }}</div>
                  <div class="text-[11px] text-inkmuted font-medium">{{ row.employee.position || 'Staf' }}</div>
                </div>
              </div>
            </div>

            <!-- Stats Grid -->
            <div class="bg-white p-3 rounded-lg border border-line/80">
              <div class="grid grid-cols-2 gap-2 text-center">
                <div class="p-1.5 rounded bg-surfacealt/50">
                  <div class="text-[10px] text-inkmuted font-medium">Terjadwal</div>
                  <div class="font-mono text-xs font-bold text-ink mt-0.5">{{ row.scheduled_hours.toFixed(1) }}j</div>
                </div>
                <div class="p-1.5 rounded" :class="row.total_late_minutes > 0 ? 'bg-amber-100/70' : 'bg-gray-100'">
                  <div class="text-[10px] font-medium" :class="row.total_late_minutes > 0 ? 'text-amber-800' : 'text-inkmuted'">Terlambat</div>
                  <div class="font-mono text-xs font-bold mt-0.5" :class="row.total_late_minutes > 0 ? 'text-amber-600' : 'text-inkmuted'">
                    {{ row.total_late_minutes || 0 }} <span class="text-[9px] font-normal">mnt</span>
                    <span v-if="row.late_count > 0" class="text-[9px] font-normal">({{ row.late_count }}x)</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- DESKTOP TABLE VIEW (Visibel pada Layar Tablet & Desktop >= 640px) -->
        <div class="hidden sm:block overflow-x-auto rounded-xl border border-line">
          <table class="w-full text-sm text-left">
            <thead>
              <tr class="bg-surfacealt/60 text-[11px] uppercase font-bold text-inkmuted border-b border-line">
                <th class="py-3 px-4">Karyawan</th>
                <th class="py-3 px-3">Posisi</th>
                <th class="py-3 px-3 text-right">Jam Terjadwal</th>
                <th class="py-3 px-3 text-right">Terlambat</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-line bg-white">
              <tr
                v-for="row in report.rows"
                :key="'table-' + row.employee.id"
                class="hover:bg-surfacealt/20 transition-colors"
              >
                <td class="py-3 px-4">
                  <div class="flex items-center gap-2.5">
                    <div class="w-7 h-7 rounded-full bg-branddark text-white font-bold text-[11px] flex items-center justify-center shrink-0">
                      {{ getInitials(row.employee.name) }}
                    </div>
                    <span class="font-bold text-ink">{{ row.employee.name }}</span>
                  </div>
                </td>
                <td class="py-3 px-3 text-inkmuted text-xs font-medium">
                  {{ row.employee.position || 'Staf' }}
                </td>
                <td class="py-3 px-3 text-right font-mono font-bold text-ink">
                  {{ row.scheduled_hours.toFixed(1) }} jam
                </td>
                <td class="py-3 px-3 text-right">
                  <div v-if="row.total_late_minutes > 0" class="flex flex-col items-end">
                    <span class="font-mono font-bold text-amber-600">{{ row.total_late_minutes }} menit</span>
                    <span class="text-[10px] text-amber-500 font-medium">{{ row.late_count }}x terlambat</span>
                  </div>
                  <span v-else class="text-[11px] text-inkmuted font-medium">— Tepat waktu</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import api from '../../lib/api';
import { localDateISO } from '../../lib/date';

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
const report = ref(null);
const loadError = ref(null);
const loading = ref(false);

async function load() {
  loadError.value = null;
  loading.value = true;
  try {
    const { data } = await api.get('/reports/weekly-hours', { params: { week_start: weekStart.value } });
    report.value = data;
  } catch (e) {
    loadError.value = e.response?.data?.message || e.message || 'Gagal memuat laporan.';
  } finally {
    loading.value = false;
  }
}
onMounted(load);
watch(weekStart, load);

function shiftWeek(n) { weekStart.value = iso(addDays(new Date(weekStart.value), n)); }
function goToday() { weekStart.value = iso(mondayOf(new Date())); }

// Format tanggal Indonesia
function formatDateIndo(dateStr) {
  if (!dateStr) return '';
  const parts = dateStr.split('-');
  if (parts.length !== 3) return dateStr;
  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
  const day = parseInt(parts[2], 10);
  const month = months[parseInt(parts[1], 10) - 1] || parts[1];
  const year = parts[0];
  return `${day} ${month} ${year}`;
}

// Inisial Nama
function getInitials(name) {
  if (!name) return 'K';
  return name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
}

// Stat ringkasan
const totalEmployees = computed(() => report.value?.rows?.length || 0);
const totalScheduledHours = computed(() => {
  if (!report.value?.rows) return 0;
  return report.value.rows.reduce((sum, r) => sum + (r.scheduled_hours || 0), 0);
});
const totalActualHours = computed(() => {
  if (!report.value?.rows) return 0;
  return report.value.rows.reduce((sum, r) => sum + (r.actual_hours || 0), 0);
});
const exceededLimitCount = computed(() => {
  if (!report.value?.rows) return 0;
  return report.value.rows.filter(r => (r.scheduled_hours || 0) > (r.employee?.max_hours || 0)).length;
});
const totalLateCount = computed(() => {
  if (!report.value?.rows) return 0;
  return report.value.rows.reduce((sum, r) => sum + (r.late_count || 0), 0);
});

const isCurrentWeek = computed(() => {
  return weekStart.value === iso(mondayOf(new Date()));
});

async function exportCsv() {
  try {
    const response = await api.get('/schedule/export-csv', { params: { week_start: weekStart.value }, responseType: 'blob' });
    const url = URL.createObjectURL(response.data);
    const a = document.createElement('a');
    a.href = url;
    a.download = `jadwal_${weekStart.value}.csv`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  } catch (e) {
    alert('Gagal mengunduh CSV.');
  }
}
</script>
