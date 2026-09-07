<template>
  <div class="card p-4">
    <div class="font-display font-bold text-base mb-3">Riwayat Absensi</div>
    <div v-if="records.length === 0" class="text-inkfaint text-center py-8">Belum ada catatan absensi.</div>
    <table v-else class="w-full text-sm">
      <thead>
        <tr class="text-[11px] uppercase text-inkmuted border-b-2 border-line">
          <th class="text-left py-2">Tanggal</th>
          <th class="text-left py-2">Absen Masuk</th>
          <th class="text-left py-2">Absen Pulang</th>
          <th class="text-left py-2">Durasi</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="r in records" :key="r.id" class="border-b border-line">
          <td class="py-2">{{ r.date }}</td>
          <td class="py-2 font-mono">
            {{ formatTime(r.clock_in) }}
            <img v-if="r.clock_in_photo_url" :src="r.clock_in_photo_url" class="inline w-5 h-5 rounded object-cover cursor-pointer ml-1.5 align-middle shadow-2xs hover:opacity-80" title="Lihat foto & peta" @click="openPhoto(r, 'Masuk')">
          </td>
          <td class="py-2 font-mono">
            {{ formatTime(r.clock_out) }}
            <img v-if="r.clock_out_photo_url" :src="r.clock_out_photo_url" class="inline w-5 h-5 rounded object-cover cursor-pointer ml-1.5 align-middle shadow-2xs hover:opacity-80" title="Lihat foto & peta" @click="openPhoto(r, 'Pulang')">
          </td>
          <td class="py-2 font-mono">{{ duration(r) }}</td>
        </tr>
      </tbody>
    </table>

    <div v-if="photoModal" class="fixed inset-0 bg-black/50 backdrop-blur-xs flex items-center justify-center z-[100] p-4 sm:p-5" @click.self="photoModal = null">
      <div class="app-modal-card bg-white rounded-xl shadow-2xl w-full max-w-lg p-5 sm:p-6 max-h-[92vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-2">
          <div>
            <h3 class="font-display font-bold text-base text-ink">Foto Bukti {{ photoModal.label }}</h3>
            <p class="text-inkmuted text-xs">{{ photoModal.date }} <span v-if="photoModal.time">· {{ photoModal.time }}</span></p>
          </div>
          <button type="button" class="text-inkfaint hover:text-ink text-base leading-none p-1 rounded-md transition" @click="photoModal = null" aria-label="Tutup">
            ✕
          </button>
        </div>

        <div class="rounded-xl overflow-hidden border border-line bg-surfacealt max-h-[42vh] flex items-center justify-center mb-3">
          <img :src="photoModal.url" class="w-full h-full max-h-[42vh] object-contain">
        </div>

        <!-- Peta Posisi Kerja Real -->
        <div class="rounded-xl border border-line bg-surfacealt/70 p-3 mb-3 text-xs">
          <div class="flex items-center justify-between mb-1.5">
            <span class="font-bold flex items-center gap-1.5 text-ink">
              <span>📍</span> Lokasi Posisi Kerja (Maps Real)
            </span>
            <a
              v-if="photoModal.lat && photoModal.lng"
              :href="photoModal.maps_url || ('https://www.google.com/maps?q=' + photoModal.lat + ',' + photoModal.lng)"
              target="_blank"
              rel="noopener"
              class="btn btn-teal btn-sm text-[11px] py-1 px-2.5 shadow-xs"
            >
              🗺️ Buka di Google Maps ↗
            </a>
          </div>

          <div v-if="photoModal.lat && photoModal.lng" class="space-y-2">
            <div class="text-ink font-medium leading-snug">
              {{ photoModal.location_name || 'Koordinat GPS posisi kerja terverifikasi' }}
            </div>
            <div class="flex items-center gap-3 text-[11px] font-mono text-inkmuted">
              <span>Latitude: {{ photoModal.lat }}</span>
              <span>Longitude: {{ photoModal.lng }}</span>
            </div>

            <div class="rounded-lg overflow-hidden border border-line h-36 w-full shadow-2xs">
              <iframe
                :src="'https://www.openstreetmap.org/export/embed.html?bbox=' + (photoModal.lng - 0.004) + '%2C' + (photoModal.lat - 0.003) + '%2C' + (photoModal.lng + 0.004) + '%2C' + (photoModal.lat + 0.003) + '&layer=mapnik&marker=' + photoModal.lat + '%2C' + photoModal.lng"
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
          <button class="btn btn-ghost text-xs sm:text-sm" @click="photoModal = null">Tutup</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '../../lib/api';

const records = ref([]);
const photoModal = ref(null);

onMounted(async () => {
  const { data } = await api.get('/attendance');
  records.value = data.sort((a, b) => b.date.localeCompare(a.date));
});

function openPhoto(record, type) {
  const isIn = type === 'Masuk';
  photoModal.value = {
    label: isIn ? 'Absen Masuk' : 'Absen Pulang',
    url: isIn ? record.clock_in_photo_url : record.clock_out_photo_url,
    date: record.date,
    time: formatTime(isIn ? record.clock_in : record.clock_out),
    lat: isIn ? record.clock_in_lat : record.clock_out_lat,
    lng: isIn ? record.clock_in_lng : record.clock_out_lng,
    location_name: isIn ? record.clock_in_location_name : record.clock_out_location_name,
    maps_url: isIn ? record.clock_in_maps_url : record.clock_out_maps_url,
  };
}

function formatTime(dt) { return dt ? new Date(dt).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) : '—'; }
function duration(r) {
  if (!r.clock_in || !r.clock_out) return '—';
  const hrs = (new Date(r.clock_out) - new Date(r.clock_in)) / 3600000;
  return `${hrs.toFixed(1)} jam`;
}
</script>
