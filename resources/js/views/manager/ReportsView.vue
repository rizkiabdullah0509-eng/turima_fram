<template>
  <div>
    <div class="flex items-center gap-2 mb-4 flex-wrap">
      <button class="btn btn-ghost btn-sm" @click="shiftWeek(-7)">← Sebelumnya</button>
      <div class="font-mono font-semibold text-sm bg-surfacealt px-3 py-1.5 rounded-lg">{{ weekStart }} – {{ report?.week_end }}</div>
      <button class="btn btn-ghost btn-sm" @click="shiftWeek(7)">Selanjutnya →</button>
      <button class="btn btn-ghost btn-sm" @click="goToday">Minggu ini</button>
    </div>

    <div class="card p-4">
      <div class="flex justify-between items-start mb-3">
        <div>
          <div class="font-display font-bold text-base">Rekap Jam Kerja</div>
          <div class="text-inkmuted text-xs">Jadwal vs. jam aktual (berdasarkan absen) untuk minggu terpilih.</div>
        </div>
        <button class="btn btn-ghost btn-sm" @click="exportCsv">⬇ Ekspor CSV</button>
      </div>

      <div v-if="loadError" class="text-coral text-sm py-4 text-center">
        {{ loadError }} — <button class="underline" @click="load">Coba lagi</button>
      </div>
      <div v-else-if="!report" class="text-inkfaint text-sm py-4 text-center">Memuat laporan...</div>
      <table class="w-full text-sm">
        <thead>
          <tr class="text-[11px] uppercase text-inkmuted border-b-2 border-line">
            <th class="text-left py-2">Karyawan</th>
            <th class="text-left py-2">Posisi</th>
            <th class="text-left py-2">Jam Terjadwal</th>
            <th class="text-left py-2">Jam Aktual</th>
            <th class="text-left py-2">Batas Mingguan</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in report?.rows" :key="row.employee.id" class="border-b border-line">
            <td class="py-2 font-bold">{{ row.employee.name }}</td>
            <td class="py-2">{{ row.employee.position }}</td>
            <td class="py-2 font-mono">{{ row.scheduled_hours.toFixed(1) }} jam</td>
            <td class="py-2 font-mono">{{ row.actual_hours.toFixed(1) }} jam</td>
            <td class="py-2 font-mono" :class="row.scheduled_hours > row.employee.max_hours ? 'text-coral font-bold' : ''">
              {{ row.employee.max_hours }} jam
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue';
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

async function load() {
  loadError.value = null;
  try {
    const { data } = await api.get('/reports/weekly-hours', { params: { week_start: weekStart.value } });
    report.value = data;
  } catch (e) {
    loadError.value = e.response?.data?.message || e.message || 'Gagal memuat laporan.';
  }
}
onMounted(load);
watch(weekStart, load);

function shiftWeek(n) { weekStart.value = iso(addDays(new Date(weekStart.value), n)); }
function goToday() { weekStart.value = iso(mondayOf(new Date())); }

async function exportCsv() {
  const response = await api.get('/schedule/export-csv', { params: { week_start: weekStart.value }, responseType: 'blob' });
  const url = URL.createObjectURL(response.data);
  const a = document.createElement('a');
  a.href = url;
  a.download = `jadwal_${weekStart.value}.csv`;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
}
</script>
