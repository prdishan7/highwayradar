<template>
  <div class="fade-up">
    <div class="page-head">
      <div class="eyebrow">DRIVER BRIEFING</div>
      <h1 class="page-title">Highway Status</h1>
      <p class="page-subtitle">Live road conditions and guidance for travelers.</p>
    </div>

    <!-- Main Status Card -->
    <div class="surface hero-card mb-4">
      <div class="hero-grid">
        <div>
          <div class="eyebrow mb-1">Live Sensors</div>
          <h2 class="hero-title">Surface Risk Glance</h2>
          <p class="page-subtitle mb-4">Moisture and rainfall sensors update in real time via roadside telemetry nodes.</p>
          
          <div class="row g-3">
            <div class="col-6 col-md-4">
              <div class="stat-tile bg-white bg-opacity-50">
                <div class="stat-label">Soil (ADC)</div>
                <div class="stat-value">{{ display(sensor.soil) }}</div>
              </div>
            </div>
            <div class="col-6 col-md-4">
              <div class="stat-tile bg-white bg-opacity-50">
                <div class="stat-label">Rain (ADC)</div>
                <div class="stat-value">{{ display(sensor.rain) }}</div>
              </div>
            </div>
            <div class="col-12 col-md-4">
              <div class="stat-tile bg-white bg-opacity-50">
                <div class="stat-label">Updated</div>
                <div class="stat-value small">{{ sensorTime }}</div>
              </div>
            </div>
          </div>
        </div>
        
        <div class="risk-meter h-100 d-flex flex-column justify-content-center" :class="riskTone">
          <div class="eyebrow">Risk Index</div>
          <div class="risk-score mt-1 mb-2">{{ display(sensor.riskIndex) }}</div>
          <div class="pill mx-auto" :class="riskTone">{{ sensor.riskLevel ?? 'N/A' }}</div>
        </div>
      </div>
    </div>

    <div class="row g-4 mb-4">
      <!-- Latest Incident -->
      <div class="col-12 col-lg-7">
        <div class="surface surface-body h-100">
          <div class="d-flex align-items-center justify-content-between mb-4">
             <h5 class="m-0 font-display fw-bold">Live Incident Broadcast</h5>
             <span class="status-indicator risk-low"></span>
          </div>
          
          <div v-if="latestIncident" class="stat-tile border-start border-4 border-primary p-4">
            <div class="d-flex justify-content-between mb-2">
               <span class="pill risk-low small">{{ latestIncident.category }}</span>
               <small class="text-muted">{{ formatTime(latestIncident.created_at) }}</small>
            </div>
            <div class="stat-value mb-3" style="font-size: 1.25rem;">{{ latestIncident.description || 'No description' }}</div>
            <div class="d-flex gap-3 align-items-center">
               <div class="text-muted small fw-bold text-uppercase">Severity: {{ latestIncident.severity }}</div>
               <div v-if="latestIncident.status" class="text-muted small fw-bold text-uppercase">Status: {{ latestIncident.status }}</div>
            </div>
            <button
              v-if="latestIncident.image_base64"
              class="btn btn-outline-primary w-100 mt-4"
              @click="openPreview(latestIncident.image_base64)"
            >
              View Visual Proof
            </button>
          </div>
          <div v-else class="p-5 text-center bg-light rounded-4">
             <p class="text-muted m-0">No incidents reported recently. Enjoy your drive.</p>
          </div>
        </div>
      </div>

      <!-- SOS Status -->
      <div class="col-12 col-lg-5">
        <div class="surface surface-body h-100">
          <h5 class="mb-4 font-display fw-bold">Emergency SOS</h5>
          <div class="stat-tile d-flex align-items-center justify-content-between p-4 mb-4" :class="sos.active ? 'bg-danger bg-opacity-10' : 'bg-light'">
            <div>
              <div class="stat-label">System State</div>
              <div class="stat-value">{{ sos.active ? 'EMERGENCY' : 'STABLE' }}</div>
            </div>
            <div class="pill" :class="sos.active ? 'risk-high' : 'risk-low'">
              {{ sos.active ? 'Active' : 'Normal' }}
            </div>
          </div>
          <p class="small text-muted">SOS signals are prioritized by the central broadcast system. If active, please follow emergency directives immediately.</p>
          <div class="stat-label mt-3">Last Ping: {{ sosTime }}</div>
        </div>
      </div>
    </div>

    <!-- Recommendations Grid -->
    <div class="surface surface-body mb-5">
      <h5 class="mb-4 font-display fw-bold">Protocol Guidelines</h5>
      <div class="row g-3">
        <div class="col-12 col-md-4">
          <div class="p-4 rounded-4 bg-success bg-opacity-10 border border-success border-opacity-20 h-100">
            <div class="fw-bold text-success mb-2">LOW RISK</div>
            <p class="small m-0 text-success text-opacity-75">Proceed normally. Maintain standard highway speeds and keep a safe distance from other vehicles.</p>
          </div>
        </div>
        <div class="col-12 col-md-4">
          <div class="p-4 rounded-4 bg-warning bg-opacity-10 border border-warning border-opacity-20 h-100">
            <div class="fw-bold text-warning mb-2">MEDIUM RISK</div>
            <p class="small m-0 text-warning text-opacity-75">Reduce speed. Avoid hard braking. Be prepared for sudden stops or surface hazards.</p>
          </div>
        </div>
        <div class="col-12 col-md-4">
          <div class="p-4 rounded-4 bg-danger bg-opacity-10 border border-danger border-opacity-20 h-100">
            <div class="fw-bold text-danger mb-2">HIGH RISK</div>
            <p class="small m-0 text-danger text-opacity-75">Extreme caution. Consider alternate routes if available. Report any new hazards immediately.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, onMounted, onBeforeUnmount, inject } from 'vue';
import { useStatusFeed } from '../composables/useStatusFeed';
import { formatNepal, formatNepalFromMs } from '../utils/time';

const { sensor, sos, latestIncident, refreshStatus } = useStatusFeed();
const openPreview = inject('openGlobalPreview');
const nowTick = ref(Date.now());
let tickTimer = null;
const sensorTime = computed(() => formatNepalFromMs(sensor.value.timestampMs, nowTick.value));
const sosTime = computed(() => formatNepalFromMs(sos.value.timestampMs, nowTick.value));
const display = (v) => (v === null || v === undefined ? '-' : v);

const riskTone = computed(() => {
  const level = (sensor.value.riskLevel || '').toUpperCase();
  if (level === 'HIGH') return 'risk-high';
  if (level === 'MEDIUM') return 'risk-medium';
  return 'risk-low';
});

function formatTime(ts) {
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
.status-indicator {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  display: inline-block;
}
.status-indicator.risk-low {
  background: var(--success);
  box-shadow: 0 0 0 4px var(--success-soft);
}
</style>
