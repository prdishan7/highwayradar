<template>
  <div class="community-page">
    <!-- Header -->
    <div class="page-head fade-up">
      <div class="d-flex justify-content-between align-items-end px-1">
        <div>
          <div class="eyebrow">HIGHWAY INTELLIGENCE</div>
          <h1 class="page-title h2">Community Hub</h1>
        </div>
        <div class="d-none d-md-block text-end">
          <div class="eyebrow">Local Time</div>
          <div class="fw-bold small">{{ nepalTime }}</div>
        </div>
      </div>
    </div>

    <!-- Map Primary Focus -->
    <div class="surface map-container fade-up delay-1">
      <div id="community-map" class="community-map"></div>
      
      <!-- Floating Status Overlay -->
      <div class="map-overlay">
        <div class="overlay-card glass shadow-sm">
          <div class="stat-group">
            <div class="stat-mini">
              <span class="label">Hazard Status</span>
              <div class="value d-flex align-items-center gap-2">
                <span class="status-pulse" :class="riskTone"></span>
                <span :class="riskTone + '-text'">{{ sensor.riskLevel ?? 'Scanning...' }}</span>
              </div>
            </div>
            <div class="stat-divider"></div>
            <div class="stat-mini text-center">
              <span class="label">Live Logs</span>
              <div class="value">{{ incidents.length }}</div>
            </div>
            <div class="stat-divider d-none d-sm-block"></div>
            <div class="stat-mini d-none d-sm-block text-end">
              <span class="label">Last Pulse</span>
              <div class="value font-monospace smaller">{{ sensorShortTime }}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Map Legend -->
      <div class="map-controls">
        <button class="btn-geo shadow-sm" @click="setCurrentLocationFromDevice" title="My Location">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
        </button>
      </div>
    </div>

    <div class="row g-3 mt-1 fade-up delay-2">
      <!-- Slope Analytics -->
      <div class="col-12 col-md-4">
        <div class="surface surface-body p-3 h-100 border-accent">
          <div class="d-flex justify-content-between align-items-start mb-2">
            <h6 class="eyebrow m-0">Slope Analytics</h6>
            <div class="status-dot pulsing" :class="riskTone"></div>
          </div>
          <div class="d-flex align-items-center gap-4">
             <div class="sensor-reading">
               <span class="smallest text-muted d-block uppercase-eyebrow">Soil Stability</span>
               <span class="fw-bold fs-4 m-0 letter-tight">{{ display(sensor.soil) }}</span>
             </div>
             <div class="sensor-reading">
               <span class="smallest text-muted d-block uppercase-eyebrow">Rainfall</span>
               <span class="fw-bold fs-4 m-0 letter-tight">{{ display(sensor.rain) }}</span>
             </div>
          </div>
          <p class="smallest text-muted mt-2 mb-0 italic">{{ riskAdvice }}</p>
        </div>
      </div>

      <!-- Live Logs and Handshake -->
      <div class="col-12 col-md-5">
        <div class="surface surface-body p-3 h-100">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="eyebrow m-0">Field Intelligence</h6>
            <div class="badge-live" :class="riskTone">Active Data</div>
          </div>
          <div v-if="latestIncident" class="d-flex flex-column flex-sm-row gap-3 align-items-sm-center">
             <div class="flex-grow-1">
               <div class="d-flex align-items-center gap-2 mb-1">
                 <span class="pill pill-xs" :class="riskTone">{{ latestIncident.category }}</span>
                 <span class="text-muted smallest">{{ formatTime(latestIncident.created_at) }}</span>
               </div>
               <p class="smallest m-0 fw-medium line-clamp-1">{{ latestIncident.description || 'Verified anomaly detected.' }}</p>
             </div>
             <button v-if="latestIncident.image_base64" class="btn btn-primary btn-sm py-1 px-3 smallest" @click="openPreview(latestIncident.image_base64)">
                View Intel
             </button>
          </div>
          <div v-else class="py-1 text-muted smallest italic">
            Monitoring field sensors for displacement...
          </div>
        </div>
      </div>

      <!-- Restored Last Updated Card -->
      <div class="col-12 col-md-3">
        <div class="surface surface-body p-3 h-100 d-flex flex-column justify-content-center bg-soft-blue">
          <div class="eyebrow mb-1">Last Update</div>
          <div class="fw-bold fs-6 font-monospace mb-1">{{ sensorShortTime }}</div>
          <div class="smallest text-muted">Corridor Handshake Status: <span class="text-success fw-bold">Active</span></div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, onMounted, onBeforeUnmount, inject, nextTick, watch } from 'vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { useStatusFeed } from '../composables/useStatusFeed';
import { fetchIncidents } from '../services/incidents';
import { formatNepal, formatNepalFromMs } from '../utils/time';

const { sensor, sos, latestIncident } = useStatusFeed();
const openPreview = inject('openGlobalPreview');
const nowTick = ref(Date.now());
const communityCenter = [27.7172, 85.3240];
let tickTimer = null;
let communityMap = null;
let latestMarker = null;
let currentLocationMarker = null;
let incidentLayer = null;
const incidents = ref([]);
const currentPosition = ref(null);
const NEARBY_RADIUS_METERS = 5000;

const sensorShortTime = computed(() => {
  if (!sensor.value.timestampMs) return 'N/A';
  const date = new Date(sensor.value.timestampMs);
  return date.toLocaleTimeString('en-US', { hour12: true, hour: 'numeric', minute: '2-digit', second: '2-digit' });
});

const nepalTime = computed(() => {
  return new Intl.DateTimeFormat('en-US', {
    timeZone: 'Asia/Kathmandu',
    hour: 'numeric',
    minute: 'numeric',
    second: 'numeric',
    hour12: true
  }).format(new Date(nowTick.value));
});

const display = (v) => (v === null || v === undefined ? '-' : v);

const riskTone = computed(() => {
  const level = (sensor.value.riskLevel || '').toUpperCase();
  if (level === 'HIGH') return 'risk-high';
  if (level === 'MEDIUM') return 'risk-medium';
  return 'risk-low';
});

const riskAdvice = computed(() => {
  const level = (sensor.value.riskLevel || '').toUpperCase();
  if (level === 'HIGH') return 'Avoid travel. High landslide risk detected.';
  if (level === 'MEDIUM') return 'Proceed with caution. Unstable slopes reported.';
  return 'Clear conditions. Monitor for sudden updates.';
});

function formatTime(ts) {
  return formatNepal(ts);
}

function getIncidentCoords(incident) {
  const lat = Number(incident?.latitude);
  const lng = Number(incident?.longitude);
  if (!Number.isFinite(lat) || !Number.isFinite(lng)) return null;
  return [lat, lng];
}

function renderLatestIncidentMarker() {
  if (!communityMap) return;
  if (latestMarker) {
    communityMap.removeLayer(latestMarker);
    latestMarker = null;
  }

  const coords = getIncidentCoords(latestIncident.value);
  if (!coords) return;

  latestMarker = L.circleMarker(coords, {
    radius: 9,
    fillColor: '#ef4444',
    color: '#ffffff',
    weight: 2,
    opacity: 1,
    fillOpacity: 0.9
  })
    .bindPopup('Latest incident location')
    .addTo(communityMap);

  if (!currentPosition.value) {
    const bounds = L.latLngBounds([communityCenter, coords]).pad(0.25);
    communityMap.fitBounds(bounds);
  }
}

function renderIncidentDots() {
  if (!communityMap) return;
  if (incidentLayer) {
    communityMap.removeLayer(incidentLayer);
  }
  incidentLayer = L.layerGroup();

  incidents.value.forEach((incident) => {
    const coords = getIncidentCoords(incident);
    if (!coords) return;
    let distance = null;
    if (currentPosition.value) {
      distance = distanceMeters(
        { lat: currentPosition.value.lat, lng: currentPosition.value.lng },
        { lat: coords[0], lng: coords[1] }
      );
    }
    const isNearby = Number.isFinite(distance) && distance <= NEARBY_RADIUS_METERS;
    const popupText = isNearby
      ? `Nearby incident (${Math.round(distance)} m)<br>${incident.description || 'Reported incident'}`
      : (incident.description || 'Reported incident');
    L.circleMarker(coords, {
      radius: isNearby ? 7 : 6,
      fillColor: '#ef4444',
      color: '#ffffff',
      weight: 1.5,
      opacity: 1,
      fillOpacity: 0.85
    })
      .bindPopup(popupText)
      .addTo(incidentLayer);
  });

  incidentLayer.addTo(communityMap);
}

async function loadIncidents() {
  try {
    const res = await fetchIncidents(100, true);
    incidents.value = res.data || [];
    renderIncidentDots();
  } catch (e) {
    incidents.value = [];
  }
}

function setCurrentLocation(lat, lng) {
  if (!communityMap) return;
  currentPosition.value = { lat, lng };
  const arrowHtml = `
    <div class="map-arrow-wrap">
      <div class="map-arrow"></div>
      <div class="map-arrow-pulse"></div>
    </div>
  `;
  if (currentLocationMarker) {
    currentLocationMarker.setLatLng([lat, lng]);
  } else {
    currentLocationMarker = L.marker([lat, lng], {
      icon: L.divIcon({
        className: 'map-arrow-icon',
        html: arrowHtml,
        iconSize: [26, 26],
        iconAnchor: [13, 13]
      })
    });
    if (communityMap) {
       currentLocationMarker.bindPopup('Your current location').addTo(communityMap);
    }
  }
  if (communityMap) {
    communityMap.setView([lat, lng], 13);
  }
  renderIncidentDots();
}

function distanceMeters(a, b) {
  const toRad = (deg) => (deg * Math.PI) / 180;
  const R = 6371000;
  const dLat = toRad(b.lat - a.lat);
  const dLng = toRad(b.lng - a.lng);
  const lat1 = toRad(a.lat);
  const lat2 = toRad(b.lat);
  const h =
    Math.sin(dLat / 2) * Math.sin(dLat / 2) +
    Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLng / 2) * Math.sin(dLng / 2);
  return 2 * R * Math.atan2(Math.sqrt(h), Math.sqrt(1 - h));
}

function setCurrentLocationFromDevice() {
  if (!navigator.geolocation) return;
  navigator.geolocation.getCurrentPosition(
    (position) => {
      setCurrentLocation(position.coords.latitude, position.coords.longitude);
    },
    () => {},
    { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
  );
}

function initCommunityMap() {
  if (communityMap) return;

  // Center on user if already found, otherwise use default
  const center = currentPosition.value 
    ? [currentPosition.value.lat, currentPosition.value.lng] 
    : communityCenter;
    
  communityMap = L.map('community-map', { zoomControl: false }).setView(center, currentPosition.value ? 14 : 13);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OSM contributors'
  }).addTo(communityMap);

  L.circle(communityCenter, {
    radius: 700,
    color: '#10b981',
    fillColor: '#10b981',
    fillOpacity: 0.12,
    weight: 2
  }).addTo(communityMap);

  renderLatestIncidentMarker();
  renderIncidentDots();
  setTimeout(() => {
    if (communityMap) communityMap.invalidateSize();
  }, 80);
}

watch(
  () => latestIncident.value,
  () => {
    renderLatestIncidentMarker();
    loadIncidents();
  }
);

onMounted(() => {
  // 1. Start Geolocation ASAP
  setCurrentLocationFromDevice();

  nextTick(() => {
    // 2. Initialize Map immediately
    initCommunityMap();
    loadIncidents();
  });

  tickTimer = setInterval(() => {
    nowTick.value = Date.now();
  }, 1000);
});

onBeforeUnmount(() => {
  if (tickTimer) clearInterval(tickTimer);
  if (communityMap) {
    communityMap.remove();
    communityMap = null;
  }
});
</script>

<style scoped>
.community-page {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.map-container {
  position: relative;
  height: 480px;
  border-radius: 28px !important;
  overflow: hidden;
  box-shadow: 0 12px 40px rgba(0, 0, 0, 0.12);
}

@media (min-width: 992px) {
  .map-container {
    height: 600px;
  }
  .map-overlay {
    width: auto;
    max-width: 480px;
    right: auto;
  }
}

@media (max-width: 768px) {
  .map-container {
    height: 65vh;
  }
}
.community-map {
  width: 100% !important;
  height: 100% !important;
  z-index: 1;
}
.map-overlay {
  position: absolute;
  top: 16px;
  left: 16px;
  right: 16px;
  z-index: 1000;
  pointer-events: none;
}
.overlay-card {
  pointer-events: auto;
  padding: 12px 20px;
  border-radius: 18px;
  border: 1px solid rgba(255, 255, 255, 0.2);
}
.glass {
  background: rgba(255, 255, 255, 0.85);
  backdrop-filter: blur(12px) saturate(180%);
}
.stat-group {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}
.stat-mini {
  display: flex;
  flex-direction: column;
}
.stat-mini .label {
  font-size: 0.62rem;
  text-transform: uppercase;
  font-weight: 700;
  color: var(--muted);
  letter-spacing: 0.04em;
}
.stat-mini .value {
  font-size: 0.95rem;
  font-weight: 700;
  color: var(--ink);
}
.stat-divider {
  width: 1px;
  height: 24px;
  background: rgba(15, 23, 42, 0.1);
}
.map-controls {
  position: absolute;
  bottom: 20px;
  right: 20px;
  z-index: 1000;
}
.btn-geo {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  background: white;
  border: none;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--ink);
  cursor: pointer;
  transition: all 0.2s;
}
.btn-geo:active {
  transform: scale(0.9);
}
.status-pulse {
  width: 8px;
  height: 8px;
  border-radius: 50%;
}
.status-pulse.risk-low {
  background: var(--success);
  box-shadow: 0 0 0 3px var(--success-soft);
  animation: pulse-low 2s infinite;
}
.status-pulse.risk-high {
  background: var(--danger);
  box-shadow: 0 0 0 3px var(--danger-soft);
  animation: pulse-high 2s infinite;
}
.badge-live {
  font-size: 0.6rem;
  font-weight: 800;
  text-transform: uppercase;
  padding: 2px 8px;
  border-radius: 6px;
  background: rgba(15, 23, 42, 0.05);
}
.badge-live.risk-low { color: var(--success); }
.badge-live.risk-high { color: var(--danger); }

.smallest { font-size: 0.7rem; }
.uppercase-eyebrow { text-transform: uppercase; letter-spacing: 0.05em; font-weight: 700; }
.italic { font-style: italic; }

@keyframes pulse-low {
  0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.4); }
  70% { box-shadow: 0 0 0 8px rgba(16, 185, 129, 0); }
  100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
}
@keyframes pulse-high {
  0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4); }
  70% { box-shadow: 0 0 0 8px rgba(239, 68, 68, 0); }
  100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
}

:deep(.map-arrow-icon) { background: transparent; border: 0; }
:deep(.map-arrow-wrap) { position: relative; width: 26px; height: 26px; }
:deep(.map-arrow) {
  position: absolute; left: 8px; top: 4px; width: 0; height: 0;
  border-left: 5px solid transparent; border-right: 5px solid transparent;
  border-bottom: 15px solid #2563eb;
}
:deep(.map-arrow-pulse) {
  position: absolute; inset: 0; border-radius: 999px;
  border: 2px solid rgba(37, 99, 235, 0.35);
}

.risk-low-text { color: var(--success); }
.risk-high-text { color: var(--danger); }
.risk-medium-text { color: var(--warning); }

.bg-soft-blue {
  background: linear-gradient(135deg, rgba(59, 130, 246, 0.04) 0%, rgba(255, 255, 255, 1) 100%);
}

.letter-tight { letter-spacing: -0.02em; }

.status-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
}
.status-dot.risk-low { background: var(--success); }
.status-dot.risk-high { background: var(--danger); }

.pulsing {
  box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.4);
  animation: pulse-dot 2s infinite;
}

@keyframes pulse-dot {
  0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.4); }
  70% { box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
  100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
}

.line-clamp-1 {
  display: -webkit-box;
  -webkit-line-clamp: 1;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

@media (max-width: 480px) {
  .map-container {
    height: 55vh;
  }
  .overlay-card {
    padding: 10px 14px;
  }
  .stat-mini .value {
    font-size: 0.85rem;
  }
}
</style>
