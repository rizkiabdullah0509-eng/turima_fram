<template>
  <div>
    <div class="grid sm:grid-cols-2 gap-3 mb-4">
      <div class="card p-4"><div class="font-mono text-2xl font-semibold">{{ pendingLeaves.length }}</div><div class="text-inkmuted text-xs">Cuti menunggu</div></div>
      <div class="card p-4"><div class="font-mono text-2xl font-semibold">{{ pendingSwaps.length }}</div><div class="text-inkmuted text-xs">Tukar shift menunggu</div></div>
    </div>

    <div class="grid lg:grid-cols-2 gap-4 items-start">
      <div class="card p-4">
        <div class="font-display font-bold text-base mb-3">Permintaan Cuti</div>
        <div v-if="pendingLeaves.length === 0" class="text-inkfaint text-center py-6">Tidak ada permintaan cuti yang menunggu.</div>
        <div v-for="l in pendingLeaves" :key="l.id" class="flex justify-between items-start py-2.5 border-b border-line">
          <div>
            <strong>{{ l.user.name }}</strong>
            <div class="font-mono text-inkmuted text-xs">{{ l.start_date }} s/d {{ l.end_date }}</div>
            <div class="text-inkmuted text-xs mt-0.5">"{{ l.reason }}"</div>
          </div>
          <div class="flex gap-1.5 shrink-0">
            <button class="btn btn-teal btn-sm" @click="respondLeave(l, 'approve')">Setujui</button>
            <button class="btn btn-coral btn-sm" @click="respondLeave(l, 'reject')">Tolak</button>
          </div>
        </div>

        <div v-if="decidedLeaves.length" class="text-[11px] uppercase font-bold text-inkfaint mt-4 mb-1">Riwayat</div>
        <div v-for="l in decidedLeaves" :key="l.id" class="flex justify-between items-center py-2 border-b border-line last:border-0 text-sm">
          <div>{{ l.user.name }} · {{ l.start_date }}–{{ l.end_date }}</div>
          <span class="tag" :class="l.status === 'approved' ? 'tag-approved' : 'tag-rejected'">{{ l.status === 'approved' ? 'Disetujui' : 'Ditolak' }}</span>
        </div>
      </div>

      <div class="card p-4">
        <div class="font-display font-bold text-base mb-3">Permintaan Tukar Shift</div>
        <div v-if="pendingSwaps.length === 0" class="text-inkfaint text-center py-6">Tidak ada tukar shift yang menunggu persetujuan Anda.</div>
        <div v-for="s in pendingSwaps" :key="s.id" class="flex justify-between items-start py-2.5 border-b border-line text-sm">
          <div>
            <strong>{{ s.from_user.name }}</strong> → <strong>{{ s.to_user.name }}</strong>
            <div class="font-mono text-inkmuted text-xs">{{ s.date }} · {{ s.shift_template.name }}</div>
            <div class="text-inkmuted text-xs">Sudah disetujui rekan kerja, menunggu Anda.</div>
          </div>
          <div class="flex gap-1.5 shrink-0">
            <button class="btn btn-teal btn-sm" @click="respondSwap(s, true)">Setujui</button>
            <button class="btn btn-coral btn-sm" @click="respondSwap(s, false)">Tolak</button>
          </div>
        </div>

        <div v-if="otherSwaps.length" class="text-[11px] uppercase font-bold text-inkfaint mt-4 mb-1">Riwayat</div>
        <div v-for="s in otherSwaps" :key="s.id" class="flex justify-between items-center py-2 border-b border-line last:border-0 text-sm">
          <div>{{ s.from_user.name }} → {{ s.to_user.name }} · {{ s.date }}</div>
          <span class="tag" :class="swapTagClass(s.status)">{{ swapLabel(s.status) }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import api from '../../lib/api';

const leaves = ref([]);
const swaps = ref([]);

async function loadAll() {
  const [{ data: l }, { data: s }] = await Promise.all([api.get('/leave-requests'), api.get('/swap-requests')]);
  leaves.value = l;
  swaps.value = s;
}
onMounted(loadAll);

const pendingLeaves = computed(() => leaves.value.filter(l => l.status === 'pending'));
const decidedLeaves = computed(() => leaves.value.filter(l => l.status !== 'pending').slice(0, 8));
const pendingSwaps = computed(() => swaps.value.filter(s => s.status === 'pending_manager'));
const otherSwaps = computed(() => swaps.value.filter(s => s.status !== 'pending_manager').slice(0, 8));

function swapLabel(status) {
  return {
    pending_peer: 'Menunggu rekan',
    pending_manager: 'Menunggu manajer',
    approved: 'Disetujui',
    rejected_peer: 'Ditolak rekan',
    rejected_manager: 'Ditolak manajer',
  }[status] || status;
}
function swapTagClass(status) {
  if (status === 'approved') return 'tag-approved';
  if (status.startsWith('rejected')) return 'tag-rejected';
  return 'tag-pending';
}

async function respondLeave(l, action) {
  await api.post(`/leave-requests/${l.id}/${action}`);
  await loadAll();
}
async function respondSwap(s, approve) {
  await api.post(`/swap-requests/${s.id}/manager-respond`, { approve });
  await loadAll();
}
</script>
