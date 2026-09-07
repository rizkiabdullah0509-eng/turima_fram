<template>
  <Transition name="app-modal" appear>
    <div v-if="show" class="fixed inset-0 bg-black/50 backdrop-blur-xs flex items-center justify-center z-[100] p-4 sm:p-5" @click.self="handleCancel">
      <div class="app-modal-card bg-white rounded-xl shadow-2xl w-full max-w-lg p-5 sm:p-6 max-h-[92vh] overflow-y-auto">
        <!-- Header Modal -->
        <div class="flex items-start justify-between gap-3 mb-2">
          <div>
            <h3 class="font-display font-bold text-base text-ink">{{ title }}</h3>
            <p v-if="subtitle" class="text-inkmuted text-xs mt-0.5">{{ subtitle }}</p>
          </div>
          <button type="button" class="text-inkfaint hover:text-ink text-base leading-none p-1 rounded-md transition" @click="handleCancel" aria-label="Tutup">
            ✕
          </button>
        </div>

        <!-- Mode Selector: Akses Kamera (Utama) vs Unggah File -->
        <div class="flex items-center gap-1 p-1 bg-surfacealt rounded-lg mb-3 text-xs">
          <button
            type="button"
            class="flex-1 py-1.5 px-2 rounded-md font-semibold transition text-center flex items-center justify-center gap-1.5"
            :class="mode === 'camera' ? 'bg-white shadow-xs text-ink' : 'text-inkmuted hover:text-ink'"
            @click="switchMode('camera')"
          >
            <span>📷</span> Akses Kamera
          </button>
          <button
            type="button"
            class="flex-1 py-1.5 px-2 rounded-md font-semibold transition text-center flex items-center justify-center gap-1.5"
            :class="mode === 'file' ? 'bg-white shadow-xs text-ink' : 'text-inkmuted hover:text-ink'"
            @click="switchMode('file')"
          >
            <span>📁</span> Unggah File
          </button>
        </div>

        <!-- Mode Kamera Langsung -->
        <div v-if="mode === 'camera'">
          <!-- Live Viewfinder (saat belum ada foto yang diambil) -->
          <div v-if="!preview" class="relative rounded-xl overflow-hidden bg-slate-950 aspect-[4/3] flex items-center justify-center mb-3 border border-line">
            <video
              ref="videoRef"
              autoplay
              playsinline
              muted
              class="w-full h-full object-cover"
              :class="{ '-scale-x-100': facingMode === 'user' }"
            ></video>

            <!-- Bingkai Panduan Kamera -->
            <div v-if="isCameraActive" class="pointer-events-none absolute inset-3 border border-white/20 rounded-lg flex flex-col justify-between p-2">
              <div class="flex justify-between">
                <div class="w-4 h-4 border-t-2 border-l-2 border-emerald-400"></div>
                <div class="w-4 h-4 border-t-2 border-r-2 border-emerald-400"></div>
              </div>
              <div class="text-center">
                <span class="text-[11px] font-medium text-white/90 bg-black/50 px-2.5 py-0.5 rounded-full backdrop-blur-xs">
                  Posisikan diri Anda di depan kamera
                </span>
              </div>
              <div class="flex justify-between">
                <div class="w-4 h-4 border-b-2 border-l-2 border-emerald-400"></div>
                <div class="w-4 h-4 border-b-2 border-r-2 border-emerald-400"></div>
              </div>
            </div>

            <!-- Status Memulai Kamera -->
            <div v-if="isStartingCamera" class="absolute inset-0 bg-black/75 flex flex-col items-center justify-center text-white p-4 text-center">
              <div class="w-8 h-8 border-2 border-emerald-400 border-t-transparent rounded-full animate-spin mb-2"></div>
              <span class="text-xs">Mengakses kamera...</span>
            </div>

            <!-- Pesan Error Akses Kamera -->
            <div v-if="cameraError" class="absolute inset-0 bg-black/85 flex flex-col items-center justify-center text-white p-4 text-center">
              <div class="text-coral text-2xl mb-1.5">⚠️</div>
              <p class="text-xs text-white/90 mb-3 max-w-[260px]">{{ cameraError }}</p>
              <div class="flex gap-2">
                <button type="button" class="btn btn-ghost btn-sm bg-white/10 border-white/20 text-white hover:bg-white/20" @click="startCamera">
                  Coba Lagi
                </button>
                <button type="button" class="btn btn-primary btn-sm" @click="switchMode('file')">
                  Gunakan File Foto
                </button>
              </div>
            </div>
          </div>

          <!-- Tombol Aksi Kamera (saat belum ada foto diambil) -->
          <div v-if="!preview" class="flex items-center gap-2 mb-3">
            <button
              type="button"
              class="btn btn-teal flex-1 justify-center py-2.5 shadow-sm text-sm"
              :disabled="!isCameraActive || isStartingCamera"
              @click="takePhoto"
            >
              <span>📸</span> Ambil Foto Sekarang
            </button>
            <button
              type="button"
              class="btn btn-ghost px-3 py-2.5 text-xs text-inkmuted hover:text-ink"
              title="Ganti kamera depan / belakang"
              :disabled="!isCameraActive || isStartingCamera"
              @click="toggleFacingMode"
            >
              🔄 Balik
            </button>
          </div>

          <!-- Preview Hasil Foto yang Telah Diambil -->
          <div v-if="preview" class="mb-3">
            <div class="relative rounded-xl overflow-hidden border border-line bg-slate-900 aspect-[4/3] mb-2">
              <img :src="preview" alt="Hasil Foto" class="w-full h-full object-cover">
              <div class="absolute bottom-2 left-2 right-2 flex items-center justify-between pointer-events-none">
                <span class="bg-black/60 text-white text-[10px] px-2.5 py-0.5 rounded backdrop-blur-xs font-mono">
                  ✓ Foto Terverifikasi
                </span>
              </div>
            </div>
            <button type="button" class="btn btn-ghost btn-sm w-full justify-center text-xs" @click="retake">
              🔄 Ambil Ulang Foto
            </button>
          </div>
        </div>

        <!-- Mode Unggah File (Pilihan Fallback) -->
        <div v-else class="mb-3">
          <div class="field mb-2">
            <label>Pilih File Foto</label>
            <input type="file" accept="image/*" @change="onFile">
          </div>
          <div v-if="preview" class="relative rounded-xl overflow-hidden border border-line aspect-[4/3] max-h-56">
            <img :src="preview" alt="Preview File" class="w-full h-full object-cover">
          </div>
          <div v-else class="border border-dashed border-line rounded-lg text-center text-inkfaint text-xs py-8">
            Pilih file foto dari penyimpanan perangkat
          </div>
        </div>

        <!-- FITUR MAPS REAL & POSISI KERJA (AKURASI TINGGI) -->
        <div class="rounded-xl border border-line bg-surfacealt/70 p-3.5 mb-3 text-xs">
          <div class="flex items-center justify-between mb-2">
            <span class="font-bold flex items-center gap-1.5 text-ink">
              <span>📍</span> Lokasi Posisi Kerja (GPS Real)
            </span>
            <button
              type="button"
              class="text-[11px] text-tealink hover:underline flex items-center gap-1 font-semibold"
              :disabled="location.loading"
              @click="startLocationWatch"
            >
              🔄 Kalibrasi / Perbarui GPS
            </button>
          </div>

          <div v-if="location.loading && !location.lat" class="flex items-center gap-2 py-2 text-inkmuted">
            <div class="w-4 h-4 border-2 border-teal border-t-transparent rounded-full animate-spin"></div>
            <span>Mengunci koordinat GPS akurasi tinggi...</span>
          </div>

          <div v-else-if="location.error" class="text-coral text-[11px] py-2 bg-[#F6D9D3]/50 p-2.5 rounded-lg mb-1">
            ⚠️ {{ location.error }}
            <button type="button" class="underline ml-1 font-semibold" @click="startLocationWatch">Coba Lagi</button>
          </div>

          <div v-if="location.lat != null" class="space-y-2">
            <!-- Badge Akurasi GPS Real -->
            <div class="flex items-center justify-between gap-2 flex-wrap">
              <span v-if="accuracyStatus" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold border" :class="accuracyStatus.color">
                <span class="w-1.5 h-1.5 rounded-full bg-current animate-pulse"></span>
                {{ accuracyStatus.text }}
              </span>
              <span class="font-mono text-[11px] text-inkfaint">
                {{ location.lat.toFixed(6) }}, {{ location.lng.toFixed(6) }}
              </span>
            </div>

            <!-- Tabel Rincian Wilayah: Desa, Kecamatan, Kabupaten -->
            <div class="grid grid-cols-3 gap-1.5 bg-white p-2 rounded-lg border border-line text-center">
              <div>
                <div class="text-[9px] uppercase tracking-wide text-inkfaint font-semibold">Desa / Kel.</div>
                <div class="text-[11px] font-bold text-ink truncate" :title="location.desa || '—'">
                  {{ location.desa || '—' }}
                </div>
              </div>
              <div>
                <div class="text-[9px] uppercase tracking-wide text-inkfaint font-semibold">Kecamatan</div>
                <div class="text-[11px] font-bold text-ink truncate" :title="location.kecamatan || '—'">
                  {{ location.kecamatan || '—' }}
                </div>
              </div>
              <div>
                <div class="text-[9px] uppercase tracking-wide text-inkfaint font-semibold">Kabupaten / Kota</div>
                <div class="text-[11px] font-bold text-ink truncate" :title="location.kabupaten || '—'">
                  {{ location.kabupaten || '—' }}
                </div>
              </div>
            </div>

            <!-- Alamat Lengkap -->
            <div v-if="location.address" class="text-[11px] text-inkmuted leading-tight px-1">
              <span class="font-semibold text-ink">Alamat:</span> {{ location.address }}
            </div>

            <!-- Embed Peta Interaktif OpenStreetMap Real -->
            <div class="relative rounded-lg overflow-hidden border border-line h-32 w-full mt-1 shadow-2xs">
              <iframe
                :src="osmEmbedUrl"
                class="w-full h-full border-0 pointer-events-none"
                loading="lazy"
              ></iframe>
              <a
                :href="googleMapsUrl"
                target="_blank"
                rel="noopener"
                class="absolute bottom-1.5 right-1.5 bg-white/95 backdrop-blur-xs text-[10px] font-semibold text-ink px-2 py-1 rounded shadow-xs hover:bg-white border border-line flex items-center gap-1"
              >
                <span>🗺️</span> Buka Google Maps ↗
              </a>
            </div>
          </div>
        </div>

        <!-- Footer Tombol -->
        <div class="flex justify-end gap-2 pt-2 border-t border-line">
          <button type="button" class="btn btn-ghost text-xs sm:text-sm" @click="handleCancel">Batal</button>
          <button
            type="button"
            class="btn text-xs sm:text-sm"
            :class="confirmClass"
            :disabled="!file"
            @click="confirm"
          >
            {{ file ? confirmLabel : 'Ambil foto dahulu' }}
          </button>
        </div>
      </div>
    </div>
  </Transition>
</template>

<script setup>
import { ref, computed, watch, onUnmounted, nextTick } from 'vue';

const props = defineProps({
  show: Boolean,
  title: { type: String, default: 'Akses Foto' },
  subtitle: { type: String, default: '' },
  confirmLabel: { type: String, default: 'Simpan' },
  confirmClass: { type: String, default: 'btn-primary' },
});
const emit = defineEmits(['confirm', 'cancel']);

const mode = ref('camera'); // 'camera' | 'file'
const file = ref(null);
const preview = ref(null);

const videoRef = ref(null);
const stream = ref(null);
const isStartingCamera = ref(false);
const isCameraActive = ref(false);
const cameraError = ref(null);
const facingMode = ref('user'); // 'user' (kamera depan) | 'environment' (kamera belakang)

// Geolocation GPS Maps Real State (Akurasi Tinggi)
const location = ref({
  lat: null,
  lng: null,
  accuracy: null,
  desa: '',
  kecamatan: '',
  kabupaten: '',
  address: '',
  name: '',
  loading: false,
  error: null,
});

let watchId = null;
let lastGeocodedKey = '';

const accuracyStatus = computed(() => {
  if (location.value.accuracy === null) return null;
  const acc = location.value.accuracy;
  if (acc <= 15) return { text: `Sangat Akurat (±${acc}m)`, color: 'text-emerald-700 bg-emerald-100 border-emerald-300' };
  if (acc <= 35) return { text: `Akurat (±${acc}m)`, color: 'text-teal-800 bg-teal-100 border-teal-300' };
  if (acc <= 70) return { text: `Cukup Akurat (±${acc}m)`, color: 'text-amber-800 bg-amber-100 border-amber-300' };
  return { text: `Mengoptimalkan GPS (±${acc}m)...`, color: 'text-coral bg-[#F6D9D3] border-coral/30' };
});

const osmEmbedUrl = computed(() => {
  if (location.value.lat == null || location.value.lng == null) return '';
  const lat = location.value.lat;
  const lng = location.value.lng;
  const delta = 0.003;
  return `https://www.openstreetmap.org/export/embed.html?bbox=${lng - delta}%2C${lat - delta}%2C${lng + delta}%2C${lat + delta}&layer=mapnik&marker=${lat}%2C${lng}`;
});

const googleMapsUrl = computed(() => {
  if (location.value.lat == null || location.value.lng == null) return '#';
  return `https://www.google.com/maps?q=${location.value.lat},${location.value.lng}`;
});

function handlePosition(pos) {
  const acc = Math.round(pos.coords.accuracy);
  if (location.value.lat === null || acc <= (location.value.accuracy || 9999) + 5) {
    location.value.lat = pos.coords.latitude;
    location.value.lng = pos.coords.longitude;
    location.value.accuracy = acc;
    location.value.loading = false;
    geocodeCoordinates(pos.coords.latitude, pos.coords.longitude);
  }
}

async function geocodeCoordinates(lat, lng) {
  const key = `${lat.toFixed(4)},${lng.toFixed(4)}`;
  if (lastGeocodedKey === key) return;
  lastGeocodedKey = key;

  try {
    const res = await fetch(
      `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`,
      { headers: { 'Accept-Language': 'id' } }
    );
    if (res.ok) {
      const data = await res.json();
      if (data && data.address) {
        const addr = data.address;
        const desa = addr.village || addr.suburb || addr.neighbourhood || addr.quarter || addr.hamlet || '';
        const kecamatan = addr.city_district || addr.district || addr.subdistrict || addr.municipality || '';
        const kabupaten = addr.city || addr.regency || addr.county || addr.town || '';

        location.value.desa = desa;
        location.value.kecamatan = kecamatan;
        location.value.kabupaten = kabupaten;
        location.value.address = data.display_name || '';

        const parts = [
          desa ? `Desa/Kel. ${desa}` : '',
          kecamatan ? `Kec. ${kecamatan}` : '',
          kabupaten,
        ].filter(Boolean);

        location.value.name = parts.join(', ') || data.display_name;
      }
    }
  } catch (e) {
    if (!location.value.name) {
      location.value.name = `Lat: ${lat.toFixed(5)}, Lng: ${lng.toFixed(5)}`;
    }
  }
}

function startLocationWatch() {
  stopLocationWatch();
  if (!navigator.geolocation) {
    location.value.error = 'Browser atau perangkat tidak mendukung GPS geolokasi.';
    return;
  }
  location.value.loading = true;
  location.value.error = null;

  // Permintaan cepat awal
  navigator.geolocation.getCurrentPosition(
    (pos) => handlePosition(pos),
    () => {},
    { enableHighAccuracy: true, timeout: 8000, maximumAge: 0 }
  );

  // Pantau GPS terus-menerus hingga akurasi terbaik terkunci
  watchId = navigator.geolocation.watchPosition(
    (pos) => handlePosition(pos),
    (err) => {
      location.value.loading = false;
      if (err.code === err.PERMISSION_DENIED) {
        location.value.error = 'Izin lokasi (GPS) belum diizinkan. Mohon aktifkan izin lokasi di browser.';
      } else if (err.code === err.POSITION_UNAVAILABLE) {
        location.value.error = 'Sinyal GPS lokasi tidak terdeteksi.';
      } else if (err.code === err.TIMEOUT) {
        location.value.error = 'Waktu deteksi GPS habis. Silakan coba kalibrasi ulang.';
      } else {
        location.value.error = 'Gagal mendeteksi koordinat GPS.';
      }
    },
    { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 }
  );
}

function stopLocationWatch() {
  if (watchId !== null && navigator.geolocation) {
    navigator.geolocation.clearWatch(watchId);
    watchId = null;
  }
}

async function startCamera() {
  stopCamera();
  cameraError.value = null;
  isStartingCamera.value = true;
  isCameraActive.value = false;

  try {
    if (!navigator?.mediaDevices?.getUserMedia) {
      throw new Error('Browser atau perangkat tidak mendukung akses kamera langsung.');
    }

    const constraints = {
      video: {
        facingMode: { ideal: facingMode.value },
        width: { ideal: 1280 },
        height: { ideal: 720 },
      },
      audio: false,
    };

    const mediaStream = await navigator.mediaDevices.getUserMedia(constraints);
    stream.value = mediaStream;

    await nextTick();
    if (videoRef.value) {
      videoRef.value.srcObject = mediaStream;
      await videoRef.value.play();
    }
    isCameraActive.value = true;
  } catch (err) {
    console.error('Camera error:', err);
    if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
      cameraError.value = 'Izin kamera belum diberikan. Mohon izinkan akses kamera di browser Anda.';
    } else if (err.name === 'NotFoundError' || err.name === 'DevicesNotFoundError') {
      cameraError.value = 'Kamera tidak terdeteksi pada perangkat ini.';
    } else if (err.name === 'NotReadableError' || err.name === 'TrackStartError') {
      cameraError.value = 'Kamera sedang digunakan oleh aplikasi lain.';
    } else {
      cameraError.value = err.message || 'Gagal mengakses kamera.';
    }
  } finally {
    isStartingCamera.value = false;
  }
}

function stopCamera() {
  if (stream.value) {
    try {
      stream.value.getTracks().forEach((track) => track.stop());
    } catch (e) {
      // ignore
    }
    stream.value = null;
  }
  if (videoRef.value) {
    videoRef.value.srcObject = null;
  }
  isCameraActive.value = false;
}

function toggleFacingMode() {
  facingMode.value = facingMode.value === 'user' ? 'environment' : 'user';
  startCamera();
}

function drawWatermark(ctx, width, height) {
  const barHeight = Math.max(64, Math.round(height * 0.2));
  ctx.save();
  ctx.fillStyle = 'rgba(15, 23, 42, 0.85)';
  ctx.fillRect(0, height - barHeight, width, barHeight);

  // Judul Watermark
  ctx.fillStyle = '#ffffff';
  ctx.font = `bold ${Math.max(12, Math.round(height * 0.026))}px sans-serif`;
  ctx.fillText('TURIMA FRAM · VERIFIKASI POSISI KERJA', 16, height - barHeight + Math.round(barHeight * 0.25));

  // Waktu & Koordinat GPS Real (Akurasi Tinggi)
  ctx.fillStyle = '#e2e8f0';
  ctx.font = `${Math.max(10, Math.round(height * 0.021))}px monospace`;
  const timeStr = new Date().toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'medium' });
  const gpsStr = location.value.lat != null
    ? `📍 GPS: ${location.value.lat.toFixed(6)}, ${location.value.lng.toFixed(6)} (Akurasi: ±${location.value.accuracy || 0}m)`
    : '📍 GPS: Menunggu sinyal';
  ctx.fillText(`${timeStr} · ${gpsStr}`, 16, height - barHeight + Math.round(barHeight * 0.5));

  // Rincian Desa, Kecamatan, Kabupaten
  ctx.fillStyle = '#6ee7b7';
  ctx.font = `bold ${Math.max(10, Math.round(height * 0.02))}px sans-serif`;
  const locDetail = [
    location.value.desa ? `Desa/Kel. ${location.value.desa}` : '',
    location.value.kecamatan ? `Kec. ${location.value.kecamatan}` : '',
    location.value.kabupaten ? location.value.kabupaten : '',
  ].filter(Boolean).join(' | ');
  ctx.fillText(`🏢 ${locDetail || location.value.name || 'Lokasi Kerja'}`, 16, height - barHeight + Math.round(barHeight * 0.72));

  // Alamat Lengkap
  if (location.value.address) {
    ctx.fillStyle = '#cbd5e1';
    ctx.font = `${Math.max(8, Math.round(height * 0.017))}px sans-serif`;
    const fullTruncated = location.value.address.length > 70 ? location.value.address.slice(0, 67) + '...' : location.value.address;
    ctx.fillText(fullTruncated, 16, height - barHeight + Math.round(barHeight * 0.9));
  }
  ctx.restore();
}

function takePhoto() {
  if (!videoRef.value) return;
  const video = videoRef.value;
  const canvas = document.createElement('canvas');
  const width = video.videoWidth || 640;
  const height = video.videoHeight || 480;
  canvas.width = width;
  canvas.height = height;
  const ctx = canvas.getContext('2d');
  if (!ctx) return;

  // Mirroring horizontal jika kamera depan
  ctx.save();
  if (facingMode.value === 'user') {
    ctx.translate(width, 0);
    ctx.scale(-1, 1);
  }
  ctx.drawImage(video, 0, 0, width, height);
  ctx.restore();

  // Watermark stempel waktu & GPS real
  drawWatermark(ctx, width, height);

  canvas.toBlob((blob) => {
    if (!blob) return;
    const f = new File([blob], `foto_absen_${Date.now()}.jpg`, { type: 'image/jpeg' });
    file.value = f;
    preview.value = URL.createObjectURL(blob);
    stopCamera();
  }, 'image/jpeg', 0.9);
}

function retake() {
  file.value = null;
  if (preview.value) {
    URL.revokeObjectURL(preview.value);
    preview.value = null;
  }
  if (mode.value === 'camera') {
    startCamera();
  }
}

function switchMode(newMode) {
  mode.value = newMode;
  if (newMode === 'camera') {
    if (!preview.value) {
      startCamera();
    }
  } else {
    stopCamera();
  }
}

function onFile(e) {
  const f = e.target.files[0];
  if (!f) return;
  file.value = f;
  if (preview.value) {
    URL.revokeObjectURL(preview.value);
  }
  preview.value = URL.createObjectURL(f);
}

function handleCancel() {
  stopCamera();
  stopLocationWatch();
  emit('cancel');
}

function confirm() {
  if (!file.value) return;
  stopCamera();
  stopLocationWatch();
  emit('confirm', file.value, {
    latitude: location.value.lat,
    longitude: location.value.lng,
    desa: location.value.desa,
    kecamatan: location.value.kecamatan,
    kabupaten: location.value.kabupaten,
    address: location.value.address,
    location_name: location.value.name,
  });
}

watch(() => props.show, async (val) => {
  if (val) {
    file.value = null;
    if (preview.value) {
      URL.revokeObjectURL(preview.value);
      preview.value = null;
    }
    cameraError.value = null;
    mode.value = 'camera';
    startLocationWatch();
    await nextTick();
    startCamera();
  } else {
    stopCamera();
    stopLocationWatch();
    if (preview.value) {
      URL.revokeObjectURL(preview.value);
      preview.value = null;
    }
    file.value = null;
  }
});

onUnmounted(() => {
  stopCamera();
  stopLocationWatch();
  if (preview.value) {
    URL.revokeObjectURL(preview.value);
  }
});
</script>
