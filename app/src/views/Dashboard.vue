<template>
  <div class="fade-up">
    <div class="page-head">
      <div class="eyebrow">SAFETY HUB</div>
      <h1 class="page-title">GBBS Highway Corridors</h1>
      <p class="page-subtitle">SAFETY & MONITORING</p>
    </div>

    <div class="row g-4 mb-5">
      <div class="col-12 col-md-4">
        <div class="surface surface-body text-center">
          <div class="stat-label">Active Hazards</div>
          <div class="stat-value text-danger">{{ latestIncidents?.length || 0 }}</div>
        </div>
      </div>
      <div class="col-12 col-md-4">
        <div class="surface surface-body text-center">
          <div class="stat-label">Corridor Status</div>
          <div class="stat-value" :class="riskTone">{{ sensor.riskLevel || 'ANALYTICAL' }}</div>
        </div>
      </div>
      <div class="col-12 col-md-4">
        <div class="surface surface-body text-center">
          <div class="stat-label">Last Updated</div>
          <div class="stat-value" style="font-size: 0.9rem;">{{ sensorTime }}</div>
        </div>
      </div>
    </div>

    <!-- Live Map View Section -->
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h5 class="m-0 font-display fw-bold">Live Highway Map</h5>
      <div class="small text-muted d-flex align-items-center gap-1">
        <span class="status-indicator risk-low"></span>
        Real-time
      </div>
    </div>
    
    <div class="surface mb-5 p-0 overflow-hidden position-relative map-container">
      <div id="dashboard-map" class="dashboard-map"></div>
    </div>

    <!-- Log Submission Section -->
    <div class="log-submission-section surface surface-body mb-5 text-center p-5">
      <div class="row align-items-center justify-content-center">
        <div class="col-12 col-md-8">
          <div class="mb-3">
             <div class="submission-icon-circle bg-primary text-white mx-auto mb-3">
               <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
             </div>
             <h3 class="fw-bold text-asphalt mb-2">Help Others Stay Safe</h3>
             <p class="text-muted mb-4">See a hazard or emergency on the highway? Your report can help others avoid danger.</p>
          </div>
          
          <div class="d-flex justify-content-center">
            <template v-if="currentUser">
              <router-link to="/report" class="btn btn-primary px-5 py-3 rounded-pill fw-bold">
                Post an Alert Now
              </router-link>
            </template>
            <template v-else>
               <router-link to="/login" class="btn btn-outline-primary px-5 py-3 rounded-pill fw-bold">
                 Login to Report
               </router-link>
            </template>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-4">
      <div class="col-12">
        <div class="surface surface-body h-100">
          <div class="d-flex align-items-center justify-content-between mb-4">
            <h5 class="m-0 font-display fw-bold">Recent Reports</h5>
            <router-link to="/community" class="text-decoration-none small fw-bold">Community Feed</router-link>
          </div>
          <div v-if="latestIncidents && latestIncidents.length" class="surface-list">
            <div class="row">
               <div
                  v-for="item in latestIncidents.slice(0, 4)"
                  :key="item.id"
                  class="col-12 col-md-6 mb-3"
                >
                  <div class="stat-tile h-100 border-start border-4 border-warning">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                       <span class="pill risk-low">{{ item.category }}</span>
                       <small class="text-muted">{{ formatTime(item.created_at) }}</small>
                    </div>
                    <div class="stat-value small mb-1">{{ item.description || 'Routine observation' }}</div>
                    <button v-if="item.image_base64" class="btn btn-outline-primary btn-sm w-100 mt-2" @click="openPreview(item.image_base64)">
                      View Image
                    </button>
                  </div>
               </div>
            </div>
          </div>
          <div v-else class="text-center py-5">
            <p class="text-muted small">No active reports at the moment. Drive safe!</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, onMounted, onBeforeUnmount, nextTick, watch, inject } from 'vue';
import { useStatusFeed } from '../composables/useStatusFeed';
import { formatNepal, formatNepalFromMs } from '../utils/time';
import { currentUser } from '../services/session';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const { sensor, latestIncidents, refreshStatus } = useStatusFeed();
const openPreview = inject('openGlobalPreview');
const nowTick = ref(Date.now());
let tickTimer = null;
let dashboardMap = null;

const sensorTime = computed(() => formatNepalFromMs(sensor.value.timestampMs, nowTick.value));

const riskTone = computed(() => {
  const level = (sensor.value.riskLevel || '').toUpperCase();
  if (level === 'HIGH') return 'risk-high';
  if (level === 'MEDIUM') return 'risk-medium';
  return 'risk-low';
});

function formatTime(ts) {
  return formatNepal(ts);
}

function initMap() {
  if (dashboardMap) return;
  const kathmandu = [27.7172, 85.3240];
  dashboardMap = L.map('dashboard-map', {
    zoomControl: false,
    attributionControl: false
  }).setView(kathmandu, 12);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(dashboardMap);

  updateMarkers();
}

function updateMarkers() {
  if (!dashboardMap) return;
  if (latestIncidents.value) {
    latestIncidents.value.forEach(incident => {
      if (incident.latitude && incident.longitude) {
        L.circle([incident.latitude, incident.longitude], {
          radius: 500,
          color: '#ef4444',
          fillColor: '#ef4444',
          fillOpacity: 0.4
        }).addTo(dashboardMap);
      }
    });
  }
}

onMounted(() => {
  tickTimer = setInterval(() => {
    nowTick.value = Date.now();
  }, 1000);

  nextTick(() => {
    initMap();
  });
});

watch(latestIncidents, () => {
  updateMarkers();
});

onBeforeUnmount(() => {
  if (tickTimer) clearInterval(tickTimer);
  if (dashboardMap) {
    dashboardMap.remove();
    dashboardMap = null;
  }
});
</script>

<style scoped>
.map-container {
  height: 400px;
  border-radius: 24px;
}

.dashboard-map {
  width: 100%;
  height: 100%;
}

.log-submission-section {
  background: linear-gradient(135deg, #f0f9ff 0%, #e0e7ff 100%);
  border: 1px solid rgba(255, 255, 255, 0.5);
}

.submission-icon-circle {
  width: 56px;
  height: 56px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
}

.status-indicator {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  display: inline-block;
}

.status-indicator.risk-low {
  background: var(--success);
  box-shadow: 0 0 0 3px var(--success-soft);
}
</style>
