<template>
  <div class="planner-layout">
    <div class="search-overlay">
      <div class="search-card">
        <div class="card-header">
          <span class="indicator-dot"></span>
          <span class="card-title">Set Destination</span>
        </div>

        <div class="geocoder-wrapper">
          <div class="search-box">
            <svg xmlns="http://www.w3.org/2000/svg" class="search-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="11" cy="11" r="8"></circle>
              <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
            <input
              type="text"
              class="search-input"
              placeholder="Search destination and press Enter"
              v-model="searchQuery"
              @keyup.enter="searchDestination"
            />
          </div>
        </div>

        <div v-if="shortestKmText || safestKmText" class="planner-status">
          <p class="status-text">{{ plannerMessage }}</p>
          <div class="status-metrics-row">
            <div v-if="shortestKmText" class="route-pill shortest">
              <span class="pill-label">SHORTEST</span>
              <span class="pill-value">{{ shortestKmText }}</span>
            </div>
            <div v-if="safestKmText" class="route-pill safest">
              <span class="pill-label">SAFEST</span>
              <span class="pill-value" :class="{ 'highlight': safestKmText !== shortestKmText }">{{ safestKmText }}</span>
            </div>
          </div>
        </div>

        <div class="travel-tip-footer">
          Tap map to set destination. Red dots mark incident locations.
        </div>
      </div>
    </div>

    <div id="map" class="background-map"></div>

    <button class="voice-nav-fab" @click="toggleVoice" :title="voiceEnabled ? 'Stop voice guidance' : 'Start voice guidance'">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z"></path><path d="M19 10v2a7 7 0 0 1-14 0v-2"></path><line x1="12" y1="19" x2="12" y2="22"></line></svg>
    </button>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import iconRetinaUrl from 'leaflet/dist/images/marker-icon-2x.png';
import iconUrl from 'leaflet/dist/images/marker-icon.png';
import shadowUrl from 'leaflet/dist/images/marker-shadow.png';
import { fetchIncidents } from '../services/incidents';

delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({
  iconRetinaUrl,
  iconUrl,
  shadowUrl,
});

const searchQuery = ref('');
const findingLocation = ref(false);
const plannerMessage = ref('Search or tap on the map to set your destination.');
const voiceEnabled = ref(false);
const currentPosition = ref(null);
const destination = ref(null);
const incidents = ref([]);
const shortestKmText = ref('');
const safestKmText = ref('');
const defaultCenter = [27.7172, 85.3240];

let map = null;
let currentMarker = null;
let destinationMarker = null;
let shortestRouteLine = null;
let safestRouteLine = null;
let incidentLayer = null;
let watchId = null;
let voiceTimer = null;
let routeTimer = null;
let routeSeq = 0;
const activeSteps = ref([]);
const nextStepIndex = ref(0);
const lastSpokenStepIndex = ref(-1);

const canGuide = computed(() => !!currentPosition.value && !!destination.value);

function getIncidentCoords(incident) {
  const lat = Number(incident?.latitude);
  const lng = Number(incident?.longitude);
  if (!Number.isFinite(lat) || !Number.isFinite(lng)) return null;
  return [lat, lng];
}

function setCurrentMarker(lat, lng, heading = null) {
  currentPosition.value = { lat, lng };
  
  const arrowHtml = `
    <div class="nav-arrow-wrapper" style="transform: rotate(${heading || 0}deg)">
      <div class="nav-arrow shadow"></div>
    </div>
  `;

  if (currentMarker) {
    currentMarker.setLatLng([lat, lng]);
    if (heading !== null) {
      currentMarker.setIcon(L.divIcon({
        className: 'nav-position-icon',
        html: arrowHtml,
        iconSize: [32, 32],
        iconAnchor: [16, 16]
      }));
    }
  } else {
    currentMarker = L.marker([lat, lng], {
      icon: L.divIcon({
        className: 'nav-position-icon',
        html: arrowHtml,
        iconSize: [32, 32],
        iconAnchor: [16, 16]
      })
    }).addTo(map).bindPopup('Your location');
  }
  
  if (voiceEnabled.value && map) {
    map.panTo([lat, lng]);
  }
  
  checkNavigationProgress(lat, lng);
  scheduleRouteRefresh();
}

function checkNavigationProgress(lat, lng) {
  if (!voiceEnabled.value || activeSteps.value.length === 0) return;

  const currentStep = activeSteps.value[nextStepIndex.value];
  if (!currentStep) return;

  const [stepLng, stepLat] = currentStep.location;
  const distToTurn = haversineMeters({ lat, lng }, { lat: stepLat, lng: stepLng });

  // If we are close to the maneuver point, prepare for the next step
  if (distToTurn < 25) {
    nextStepIndex.value++;
    if (nextStepIndex.value < activeSteps.value.length) {
      const nextStep = activeSteps.value[nextStepIndex.value];
      speak(`Now, ${nextStep.maneuver.instruction}`);
      lastSpokenStepIndex.value = nextStepIndex.value;
    } else {
      speak("You have arrived at your destination.");
      stopVoiceGuidance();
    }
  } else if (nextStepIndex.value !== lastSpokenStepIndex.value) {
    // Speak first instruction or reinforcement
    speak(currentStep.maneuver.instruction);
    lastSpokenStepIndex.value = nextStepIndex.value;
  }
}

function setDestination(lat, lng, label = 'Destination') {
  destination.value = { lat, lng, label };
  searchQuery.value = label;
  if (destinationMarker) {
    destinationMarker.setLatLng([lat, lng]).bindPopup(label);
  } else {
    destinationMarker = L.marker([lat, lng]).addTo(map).bindPopup(label);
  }
  plannerMessage.value = `Destination set: ${lat.toFixed(5)}, ${lng.toFixed(5)}`;
  scheduleRouteRefresh();
}

function clearRoutes() {
  if (!map) return;
  if (shortestRouteLine) {
    map.removeLayer(shortestRouteLine);
    shortestRouteLine = null;
  }
  if (safestRouteLine) {
    map.removeLayer(safestRouteLine);
    safestRouteLine = null;
  }
}

function scheduleRouteRefresh() {
  if (routeTimer) clearTimeout(routeTimer);
  routeTimer = setTimeout(() => {
    computeRoutes();
  }, 350);
}

function haversineMeters(a, b) {
  const toRad = (deg) => (deg * Math.PI) / 180;
  const R = 6371000;
  const dLat = toRad(b.lat - a.lat);
  const dLng = toRad(b.lng - a.lng);
  const lat1 = toRad(a.lat);
  const lat2 = toRad(b.lat);
  const h = Math.sin(dLat / 2) ** 2 + Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLng / 2) ** 2;
  return 2 * R * Math.asin(Math.sqrt(h));
}

function routeRiskScore(routeCoords, thresholdMeters = 140) {
  if (!routeCoords?.length) return { score: 9999, nearCount: 0 };
  const sampled = routeCoords.filter((_, idx) => idx % 8 === 0 || idx === routeCoords.length - 1);
  let nearCount = 0;
  incidents.value.forEach((incident) => {
    const coord = getIncidentCoords(incident);
    if (!coord) return;
    const incidentPoint = { lat: coord[0], lng: coord[1] };
    let near = false;
    for (const p of sampled) {
      const d = haversineMeters({ lat: p[1], lng: p[0] }, incidentPoint);
      if (d <= thresholdMeters) {
        near = true;
        break;
      }
    }
    if (near) nearCount += 1;
  });
  return { score: nearCount * 1000 + sampled.length, nearCount };
}

function closestIncidentToRoute(routeCoords) {
  if (!routeCoords?.length) return null;
  const sampled = routeCoords.filter((_, idx) => idx % 8 === 0 || idx === routeCoords.length - 1);
  let best = null;
  incidents.value.forEach((inc) => {
    const coords = getIncidentCoords(inc);
    if (!coords) return;
    const incidentPoint = { lat: coords[0], lng: coords[1] };
    sampled.forEach((p) => {
      const d = haversineMeters({ lat: p[1], lng: p[0] }, incidentPoint);
      if (!best || d < best.distance) {
        best = { point: incidentPoint, distance: d };
      }
    });
  });
  return best;
}

async function fetchOsrmRoute(points) {
  const coords = points.map((p) => `${p.lng},${p.lat}`).join(';');
  const url = `https://router.project-osrm.org/route/v1/driving/${coords}?overview=full&geometries=geojson&steps=true&alternatives=false`;
  const res = await fetch(url);
  if (!res.ok) throw new Error('Route service unavailable');
  const data = await res.json();
  const route = data?.routes?.[0];
  if (!route?.geometry?.coordinates?.length) throw new Error('No route found');
  
  // Extract and flatten steps for easier turn-by-turn
  const steps = [];
  route.legs.forEach(leg => {
    leg.steps.forEach(step => steps.push(step));
  });
  route._processedSteps = steps;
  
  return route;
}

function buildDetourWaypoint(start, end, incidentPoint, direction = 1, meters = 900) {
  const routeVec = { lat: end.lat - start.lat, lng: end.lng - start.lng };
  let perp = { lat: -routeVec.lng, lng: routeVec.lat };
  const len = Math.hypot(perp.lat, perp.lng) || 1;
  perp = { lat: (perp.lat / len) * direction, lng: (perp.lng / len) * direction };
  const deg = meters / 111320;
  return {
    lat: incidentPoint.lat + perp.lat * deg,
    lng: incidentPoint.lng + perp.lng * deg
  };
}

async function computeRoutes() {
  if (!map) return;
  clearRoutes();
  shortestKmText.value = '';
  safestKmText.value = '';
  if (!currentPosition.value || !destination.value) return;

  const seq = ++routeSeq;
  const start = { lat: currentPosition.value.lat, lng: currentPosition.value.lng };
  const end = { lat: destination.value.lat, lng: destination.value.lng };

  try {
    const shortest = await fetchOsrmRoute([start, end]);
    if (seq !== routeSeq) return;

    const shortestRisk = routeRiskScore(shortest.geometry.coordinates);
    let safest = shortest;
    let safestRisk = shortestRisk;

    if (shortestRisk.nearCount > 0 && incidents.value.length) {
      const closest = closestIncidentToRoute(shortest.geometry.coordinates);
      if (closest && closest.distance <= 250) {
        const incidentPoint = closest.point;
        const wpA = buildDetourWaypoint(start, end, incidentPoint, 1);
        const wpB = buildDetourWaypoint(start, end, incidentPoint, -1);

        const candidates = await Promise.allSettled([
          fetchOsrmRoute([start, wpA, end]),
          fetchOsrmRoute([start, wpB, end])
        ]);
        if (seq !== routeSeq) return;

        candidates.forEach((res) => {
          if (res.status !== 'fulfilled') return;
          const risk = routeRiskScore(res.value.geometry.coordinates);
          const betterRisk = risk.score < safestRisk.score;
          const equalRiskShorter = risk.score === safestRisk.score && res.value.distance < safest.distance;
          if (betterRisk || equalRiskShorter) {
            safest = res.value;
            safestRisk = risk;
          }
        });
      }
    }

    shortestRouteLine = L.polyline(
      shortest.geometry.coordinates.map((c) => [c[1], c[0]]),
      { color: '#2563eb', weight: 4, opacity: 0.7, dashArray: '7,7' }
    ).addTo(map);

    safestRouteLine = L.polyline(
      safest.geometry.coordinates.map((c) => [c[1], c[0]]),
      { color: '#16a34a', weight: 5, opacity: 0.9 }
    ).addTo(map);

    activeSteps.value = safest._processedSteps || [];
    nextStepIndex.value = 0;
    lastSpokenStepIndex.value = -1;

    const bounds = L.latLngBounds(safestRouteLine.getLatLngs());
    map.fitBounds(bounds.pad(0.15));

    shortestKmText.value = `${(shortest.distance / 1000).toFixed(2)} km`;
    safestKmText.value = `${(safest.distance / 1000).toFixed(2)} km`;
    plannerMessage.value = safestRisk.nearCount === 0
      ? 'Safest route avoids known incident points.'
      : 'Safest available route selected with lower incident exposure.';
  } catch (e) {
    if (seq !== routeSeq) return;
    plannerMessage.value = 'Could not calculate route right now.';
  }
}

function renderIncidents() {
  if (!map) return;
  if (incidentLayer) {
    map.removeLayer(incidentLayer);
  }
  incidentLayer = L.layerGroup();
  incidents.value.forEach((incident) => {
    const coords = getIncidentCoords(incident);
    if (!coords) return;
    L.circleMarker(coords, {
      radius: 6,
      fillColor: '#ef4444',
      color: '#ffffff',
      weight: 1.5,
      opacity: 1,
      fillOpacity: 0.85
    })
      .bindPopup(incident.description || 'Incident')
      .addTo(incidentLayer);
  });
  incidentLayer.addTo(map);
}

async function loadIncidents() {
  try {
    const res = await fetchIncidents(100, true);
    incidents.value = res.data || [];
    renderIncidents();
    scheduleRouteRefresh();
  } catch (e) {
    incidents.value = [];
  }
}

function useCurrentLocation() {
  if (!navigator.geolocation) {
    plannerMessage.value = 'Geolocation is not supported on this device.';
    return;
  }
  findingLocation.value = true;
  navigator.geolocation.getCurrentPosition(
    (position) => {
      const lat = position.coords.latitude;
      const lng = position.coords.longitude;
      setCurrentMarker(lat, lng);
      if (map) map.setView([lat, lng], 13);
      plannerMessage.value = 'Current location updated.';
      findingLocation.value = false;
    },
    (err) => {
      plannerMessage.value = err.message || 'Could not get current location.';
      findingLocation.value = false;
    },
    { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
  );
}

function startLocationWatch() {
  if (!navigator.geolocation) return;
  watchId = navigator.geolocation.watchPosition(
    (position) => {
      const { latitude, longitude, heading } = position.coords;
      setCurrentMarker(latitude, longitude, heading);
    },
    () => {},
    { enableHighAccuracy: true, maximumAge: 0, timeout: 10000 }
  );
}

function stopLocationWatch() {
  if (watchId !== null && navigator.geolocation) {
    navigator.geolocation.clearWatch(watchId);
    watchId = null;
  }
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

function bearingDegrees(a, b) {
  const toRad = (deg) => (deg * Math.PI) / 180;
  const toDeg = (rad) => (rad * 180) / Math.PI;
  const lat1 = toRad(a.lat);
  const lat2 = toRad(b.lat);
  const dLng = toRad(b.lng - a.lng);
  const y = Math.sin(dLng) * Math.cos(lat2);
  const x = Math.cos(lat1) * Math.sin(lat2) - Math.sin(lat1) * Math.cos(lat2) * Math.cos(dLng);
  return (toDeg(Math.atan2(y, x)) + 360) % 360;
}

function cardinalFromBearing(deg) {
  const dirs = ['north', 'north-east', 'east', 'south-east', 'south', 'south-west', 'west', 'north-west'];
  return dirs[Math.round(deg / 45) % 8];
}

function speak(text) {
  if (!('speechSynthesis' in window)) {
    plannerMessage.value = 'Voice guidance is not supported in this browser.';
    return;
  }
  window.speechSynthesis.cancel();
  const utter = new SpeechSynthesisUtterance(text);
  utter.rate = 1;
  utter.pitch = 1;
  window.speechSynthesis.speak(utter);
}

function speakGuidance() {
  if (!activeSteps.value.length) {
    speak('Calculating your route. Please wait.');
    return;
  }
  
  const step = activeSteps.value[nextStepIndex.value];
  if (step) {
    speak(step.maneuver.instruction);
    lastSpokenStepIndex.value = nextStepIndex.value;
  }
}

function stopVoiceGuidance() {
  voiceEnabled.value = false;
  if (voiceTimer) {
    clearInterval(voiceTimer);
    voiceTimer = null;
  }
  if ('speechSynthesis' in window) {
    window.speechSynthesis.cancel();
  }
}

function toggleVoice() {
  if (voiceEnabled.value) {
    stopVoiceGuidance();
    plannerMessage.value = 'Voice guidance stopped.';
    return;
  }
  voiceEnabled.value = true;
  plannerMessage.value = 'Voice navigation active.';
  speakGuidance();
  // Instead of a fixed interval, we rely on checkNavigationProgress
  // But we can keep a long-term reminder if they get stuck
  voiceTimer = setInterval(() => {
    if (voiceEnabled.value) speakGuidance();
  }, 45000);
}

async function searchDestination() {
  const query = searchQuery.value.trim();
  if (!query) return;
  
  plannerMessage.value = `Searching for "${query}"...`;
  
  try {
    // Simplest possible fetch to minimize CORS preflight issues
    const url = `https://nominatim.openstreetmap.org/search?format=json&limit=1&q=${encodeURIComponent(query)}`;
    
    // We remove custom headers to keep it a "simple request" in CORS terms
    const res = await fetch(url);

    if (!res.ok) {
      throw new Error(`Search failed (Status ${res.status})`);
    }

    const data = await res.json();
    
    if (!Array.isArray(data) || data.length === 0) {
      plannerMessage.value = `No results found for "${query}". Try a different name.`;
      return;
    }

    const lat = Number(data[0].lat);
    const lng = Number(data[0].lon);

    if (isNaN(lat) || isNaN(lng)) {
      plannerMessage.value = 'Invalid data received from search service.';
      return;
    }

    setDestination(lat, lng, data[0].display_name || 'Destination');
    if (map) {
      map.setView([lat, lng], 14);
    }
  } catch (e) {
    console.error('Search error:', e);
    // Provide a more user-friendly message for "Failed to fetch"
    if (e.message === 'Failed to fetch' || e.name === 'TypeError') {
      plannerMessage.value = 'Connection error. Please check your internet and try again.';
    } else {
      plannerMessage.value = e.message || 'Search service is currently unavailable.';
    }
  }
}

function initMap() {
  const center = currentPosition.value 
    ? [currentPosition.value.lat, currentPosition.value.lng] 
    : defaultCenter;

  map = L.map('map', {
    zoomControl: false,
    attributionControl: false
  }).setView(center, currentPosition.value ? 14 : 13);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
  map.on('click', (event) => {
    setDestination(event.latlng.lat, event.latlng.lng);
  });
}

onMounted(() => {
  nextTick(async () => {
    // 1. Initialize Map first
    initMap();
    await loadIncidents();
    
    // 2. Then start Geolocation
    useCurrentLocation();
    startLocationWatch();
  });
});

onBeforeUnmount(() => {
  stopVoiceGuidance();
  stopLocationWatch();
  if (routeTimer) clearTimeout(routeTimer);
  if (map) {
    map.remove();
    map = null;
  }
});
</script>

<style scoped>
.planner-layout {
  position: fixed;
  left: 0;
  right: 0;
  top: calc(72px + var(--safe-top));
  bottom: 0;
  overflow: hidden;
  background: #f8fafc;
}

.background-map {
  position: absolute;
  inset: 0;
  z-index: 1;
}

.search-overlay {
  position: absolute;
  top: 12px;
  left: 12px;
  right: 12px;
  width: auto;
  max-width: 560px;
  margin: 0 auto;
  z-index: 100;
}

.search-card {
  background: white;
  border-radius: 18px;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.12);
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.card-header {
  padding: 14px 16px 8px;
  display: flex;
  align-items: center;
  gap: 12px;
}

.indicator-dot {
  width: 14px;
  height: 14px;
  border: 2px solid #cbd5e1;
  border-radius: 50%;
}

.card-title {
  font-size: 0.95rem;
  font-weight: 800;
  color: #0f172a;
}

.geocoder-wrapper {
  padding: 0 16px 10px;
}

.search-box {
  position: relative;
  display: flex;
  align-items: center;
}

.search-icon {
  position: absolute;
  left: 16px;
  color: #94a3b8;
}

.search-input {
  width: 100%;
  height: 46px;
  background: #f8fafc;
  border: 1px solid #f1f5f9;
  border-radius: 12px;
  padding: 0 16px 0 48px;
  font-size: 1rem;
  font-weight: 500;
  color: #1e293b;
  transition: all 0.2s;
}

.search-input:focus {
  outline: none;
  background: white;
  border-color: #e2e8f0;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
}

.planner-status {
  padding: 0 16px 10px;
}

.status-text {
  margin: 0;
  font-size: 0.82rem;
  color: #64748b;
}

.status-metrics-row {
  margin-top: 8px;
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.route-pill {
  display: flex;
  flex-direction: column;
  padding: 6px 12px;
  border-radius: 10px;
  min-width: 90px;
}

.route-pill.shortest {
  background: rgba(37, 99, 235, 0.08);
  border: 1px solid rgba(37, 99, 235, 0.2);
}

.route-pill.safest {
  background: rgba(22, 163, 74, 0.08);
  border: 1px solid rgba(22, 163, 74, 0.2);
}

.pill-label {
  font-size: 0.55rem;
  font-weight: 800;
  letter-spacing: 0.05em;
  color: #64748b;
  margin-bottom: 2px;
}

.pill-value {
  font-size: 0.85rem;
  font-weight: 700;
  color: #0f172a;
}

.route-pill.shortest .pill-value { color: #2563eb; }
.route-pill.safest .pill-value { color: #16a34a; }

.pill-value.highlight {
  text-decoration: underline;
  text-underline-offset: 4px;
}

.travel-tip-footer {
  padding: 8px 16px;
  background: #f8fafc;
  color: #94a3b8;
  font-size: 0.65rem;
  font-weight: 600;
  text-align: center;
  letter-spacing: -0.01em;
}

.voice-nav-fab {
  position: absolute;
  bottom: 140px;
  right: 24px;
  width: 56px;
  height: 56px;
  background: #0f172a;
  color: white;
  border: none;
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
  z-index: 100;
  cursor: pointer;
}

@media (min-width: 992px) {
  .search-overlay {
    top: 24px;
    left: 24px;
    right: auto;
    width: 420px;
    margin: 0;
  }
}

@media (max-width: 768px) {
  .planner-layout {
    top: calc(72px + var(--safe-top));
    bottom: 0;
  }

  .search-overlay {
    top: 10px;
    left: 10px;
    right: 10px;
  }
}
</style>
