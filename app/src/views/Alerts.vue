<template>
  <div class="fade-up">
    <div class="page-head">
      <div class="eyebrow">EMERGENCY OPERATIONS</div>
      <h1 class="page-title">Alert Stream</h1>
      <p class="page-subtitle">Historical SOS data and active escalation notes for the highway corridor.</p>
    </div>

    <div class="row g-4">
      <div class="col-12 col-lg-7">
        <div class="surface surface-body h-100">
          <div class="d-flex align-items-center justify-content-between mb-4">
            <h5 class="m-0 font-display fw-bold">SOS System Status</h5>
            <span class="pill" :class="sos.active ? 'risk-high' : 'risk-low'">
              {{ sos.active ? 'Signal Active' : 'System Stable' }}
            </span>
          </div>
          
          <div class="row g-3 mb-4">
            <div class="col-6">
              <div class="stat-tile bg-light border-0">
                <div class="stat-label">Level</div>
                <div class="stat-value">{{ sos.level || 'DEACTIVATED' }}</div>
              </div>
            </div>
            <div class="col-6">
              <div class="stat-tile bg-light border-0">
                <div class="stat-label">Last Transmission</div>
                <div class="stat-value small">{{ sosTime }}</div>
              </div>
            </div>
          </div>
          
          <div class="p-3 rounded-3 bg-light border border-dashed text-muted small">
            <div class="d-flex gap-2 align-items-center mb-1">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
               <span class="fw-bold">Operational Note</span>
            </div>
            Roadside emergency buttons require a sustained 3-second press to prevent accidental triggers. Signals are verified by AI before human dispatch.
          </div>
        </div>
      </div>
      
      <div class="col-12 col-lg-5">
        <div class="surface surface-body h-100">
          <h5 class="mb-4 font-display fw-bold">Recent Event Log</h5>
          <div v-if="latestIncidents && latestIncidents.length" class="surface-list">
            <div
              v-for="item in latestIncidents.slice(0, 5)"
              :key="item.id"
              class="stat-tile mb-3 border-start border-4"
              :class="item.severity === 'high' ? 'border-danger' : 'border-warning'"
            >
              <div class="d-flex justify-content-between mb-1">
                <span class="pill risk-low small">{{ item.category }}</span>
                <small class="text-muted">{{ formatTime(item.created_at) }}</small>
              </div>
              <div class="stat-value small mb-2">{{ item.description || 'System Log' }}</div>
              <button
                v-if="item.image_base64"
                class="btn btn-outline-primary btn-sm w-100"
                @click="openPreview(item.image_base64)"
              >
                Inspect Event
              </button>
            </div>
          </div>
          <div v-else class="text-center py-5">
            <p class="text-muted small">No logged events in the current session.</p>
          </div>
        </div>
      </div>
    </div>

    <!-- The global preview is in App.vue, but we use this as fallback if not communicating well -->
  </div>
</template>

<script setup>
import { useStatusFeed } from '../composables/useStatusFeed';
import { ref, computed, onMounted, onBeforeUnmount, inject } from 'vue';
import { formatNepal, formatNepalFromMs } from '../utils/time';

const { sos, latestIncidents } = useStatusFeed();
const openPreview = inject('openGlobalPreview');
const nowTick = ref(Date.now());
let tickTimer = null;
const sosTime = computed(() => formatNepalFromMs(sos.value.timestampMs, nowTick.value));

function formatTime(ts) {
  if (!ts) return '';
  return formatNepal(ts);
}

onMounted(() => {
  tickTimer = setInterval(() => {
    nowTick.value = Date.now();
  }, 1000);
});

onBeforeUnmount(() => {
  if (tickTimer) clearInterval(tickTimer);
});
</script>

<style scoped>
/* Scoped styles for Alerts view */
</style>
