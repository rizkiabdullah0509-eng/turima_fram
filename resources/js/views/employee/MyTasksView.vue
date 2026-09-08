<template>
  <div>
    <div class="flex items-center gap-2 mb-4 flex-wrap">
      <button class="btn btn-ghost btn-sm" @click="shiftDay(-1)">← Sebelumnya</button>
      <div class="font-mono font-semibold text-sm bg-surfacealt px-3 py-1.5 rounded-lg">{{ formatDate(date) }}</div>
      <button class="btn btn-ghost btn-sm" @click="shiftDay(1)">Selanjutnya →</button>
      <button class="btn btn-ghost btn-sm" @click="goToday">Hari ini</button>
    </div>

    <div class="card p-4">
      <div class="font-display font-bold text-base mb-1">Tugas Saya — {{ formatDate(date) }}</div>
      <p class="text-inkmuted text-xs mb-3">Anda bebas memilih tugas mana pun untuk diselesaikan. Setiap tugas tetap memerlukan foto bukti.</p>

      <div v-if="tasks.length === 0" class="text-inkfaint text-center py-8">Tidak ada tugas untuk tanggal ini.</div>

      <div v-for="(t, i) in tasks" :key="t.id" class="flex gap-3 py-3.5 border-b border-line last:border-0">
        <div class="w-7 h-7 rounded-full flex items-center justify-center font-mono text-xs font-bold shrink-0 mt-0.5"
             :class="{
               'bg-teal text-white': t.status === 'done',
               'bg-blue-500 text-white': t.status === 'in_progress',
               'bg-amber text-amberink': t.status !== 'done' && t.status !== 'in_progress',
             }">
          {{ t.status === 'done' ? '✓' : (t.status === 'in_progress' ? '⏳' : i + 1) }}
        </div>
        <div class="flex-1 flex justify-between items-start flex-wrap gap-2">
          <div class="flex gap-2.5 items-start">
            <img v-if="t.photo_url" :src="t.photo_url" class="w-11 h-11 rounded-lg object-cover cursor-pointer" @click="photoModal = t.photo_url">
            <div>
              <div class="flex items-center gap-1.5 flex-wrap">
                <span class="font-bold text-sm">{{ t.title }}</span>
                <span v-if="t.source === 'whatsapp'" class="inline-flex items-center text-[10px] bg-emerald-100 text-emerald-800 font-semibold px-1.5 py-0.5 rounded border border-emerald-300" title="Diinput melalui WhatsApp Bot">
                  📱 WA
                </span>
              </div>
              <div v-if="t.description" class="text-xs text-inkmuted mt-0.5 whitespace-pre-line">{{ t.description }}</div>
              <div v-if="t.status === 'done'" class="text-[11px] text-inkfaint mt-0.5">Selesai {{ formatDateTime(t.completed_at) }}</div>
              <div v-else-if="t.status === 'in_progress'" class="text-[11px] text-blue-600 font-medium mt-0.5">Sedang dikerjakan</div>
              <div v-else class="text-[11px] text-inkfaint mt-0.5">Siap dikerjakan</div>
            </div>
          </div>
          <div>
            <button v-if="t.status === 'done'" class="btn btn-ghost btn-sm" @click="undo(t)">Batalkan</button>
            <button v-else class="btn btn-teal btn-sm" @click="openComplete(t)">Tandai Selesai + Foto</button>
          </div>
        </div>
      </div>
    </div>

    <PhotoUploadModal
      :show="!!completeTarget"
      title="Tandai Selesai"
      :subtitle="(completeTarget?.title || '') + ' · unggah foto sebagai bukti pengerjaan.'"
      confirm-label="Simpan & Tandai Selesai"
      confirm-class="btn-primary"
      @cancel="completeTarget = null"
      @confirm="submitComplete"
    />

    <div v-if="photoModal" class="fixed inset-0 bg-black/45 flex items-center justify-center z-[100] p-5" @click.self="photoModal = null">
      <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6">
        <h3 class="font-display font-bold text-base mb-3">Foto Bukti</h3>
        <img :src="photoModal" class="w-full rounded-lg">
        <div class="flex justify-end mt-4"><button class="btn btn-ghost" @click="photoModal = null">Tutup</button></div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue';
import api from '../../lib/api';
import PhotoUploadModal from '../../components/PhotoUploadModal.vue';
import { localDateISO, formatDate } from '../../lib/date';

function iso(d) { return localDateISO(d); }
function addDays(d, n) { const r = new Date(d); r.setDate(r.getDate() + n); return r; }

const date = ref(iso(new Date()));
const tasks = ref([]);
const completeTarget = ref(null);
const photoModal = ref(null);

async function load() {
  const { data } = await api.get('/tasks', { params: { date: date.value } });
  tasks.value = data.sort((a, b) => a.sort_order - b.sort_order);
}
onMounted(load);
watch(date, load);

function shiftDay(n) { date.value = iso(addDays(new Date(date.value), n)); }
function goToday() { date.value = iso(new Date()); }

function openComplete(t) { completeTarget.value = t; }
async function submitComplete(file) {
  const form = new FormData();
  form.append('photo', file);
  try {
    await api.post(`/tasks/${completeTarget.value.id}/complete`, form, { headers: { 'Content-Type': 'multipart/form-data' } });
    completeTarget.value = null;
    await load();
  } catch (e) {
    alert(e.response?.data?.message || 'Gagal menyimpan tugas.');
  }
}
async function undo(t) {
  await api.post(`/tasks/${t.id}/undo`);
  await load();
}
function formatDateTime(dt) { return dt ? new Date(dt).toLocaleString('id-ID') : ''; }
</script>
