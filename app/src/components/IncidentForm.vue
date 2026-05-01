<template>
  <div class="incident-form-compact">
    <div class="row g-3">
      <div class="col-12 col-md-6">
        <label class="eyebrow mb-2 d-block">Incident Type</label>
        <select v-model="form.category" class="form-control">
          <option v-for="item in categories" :key="item.value" :value="item.value">
            {{ item.label }}
          </option>
        </select>
      </div>
      <div class="col-12 col-md-6">
        <label class="eyebrow mb-2 d-block">Urgency Level</label>
        <select v-model="form.severity" class="form-control">
          <option v-for="item in severities" :key="item.value" :value="item.value">
            {{ item.label }}
          </option>
        </select>
      </div>
      <div class="col-12">
        <label class="eyebrow mb-2 d-block">Situational Details</label>
        <textarea v-model="form.description" class="form-control" rows="2" placeholder="Describe the observation..."></textarea>
      </div>
      <div class="col-12">
        <label class="eyebrow mb-2 d-block">Visual Evidence (Mandatory)</label>
        <div class="custom-file-input">
          <input type="file" accept="image/*" class="form-control" @change="handleFile" />
          <div v-if="preview" class="mt-3">
            <img :src="preview" alt="preview" class="preview-img" />
          </div>
        </div>
      </div>

      <div v-if="enableLocationMap" class="col-12">
        <label class="eyebrow mb-2 d-block">Location Preview (Mandatory)</label>
        <div id="incident-location-map" class="location-map"></div>
        <div class="text-muted small mt-2">{{ selectedPointText }}</div>
        <button class="btn btn-outline-primary w-100 mt-3" :disabled="findingLocation" @click="useCurrentLocation">
          {{ findingLocation ? 'Locating...' : 'Use Current Location' }}
        </button>
      </div>
    </div>
    
    <button class="btn btn-primary w-100 mt-4 py-3" :disabled="loading" @click="submit">
      <span v-if="loading">Processing...</span>
      <span v-else>Transmit Report</span>
    </button>
    
    <div v-if="error" class="risk-high p-2 rounded-3 mt-3 small text-center">{{ error }}</div>
    <div v-if="success" class="risk-low p-2 rounded-3 mt-3 small text-center">Transmission successful.</div>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { createIncident, fetchIncidents, INCIDENT_CATEGORIES, INCIDENT_SEVERITIES } from '../services/incidents';
import { compressWithWatermark } from '../services/image';
import { currentUser } from '../services/session';

const props = defineProps({
  role: { type: String, default: 'user' },
  enableLocationMap: { type: Boolean, default: false }
});

const form = reactive({
  category: 'collision',
  severity: 'low',
  description: '',
  image_base64: null
});

const loading = ref(false);
const error = ref('');
const success = ref(false);
const preview = ref(null);
const findingLocation = ref(false);
const selectedPoint = ref(null);
const incidents = ref([]);

const categories = INCIDENT_CATEGORIES;
const severities = INCIDENT_SEVERITIES;
const selectedPointText = computed(() => {
  if (!selectedPoint.value) return 'Tap map or use current location to set point.';
  return `Selected point: ${selectedPoint.value.lat.toFixed(5)}, ${selectedPoint.value.lng.toFixed(5)}`;
});

let locationMap = null;
let selectedMarker = null;
let currentLocationMarker = null;
let incidentLayer = null;
const defaultCenter = [27.7172, 85.3240];

function getIncidentCoords(incident) {
  const lat = Number(incident?.latitude);
  const lng = Number(incident?.longitude);
  if (!Number.isFinite(lat) || !Number.isFinite(lng)) return null;
  return [lat, lng];
}

function setSelectedMarker(lat, lng) {
  selectedPoint.value = { lat, lng };
  if (selectedMarker) {
    selectedMarker.setLatLng([lat, lng]);
    return;
  }
  selectedMarker = L.circleMarker([lat, lng], {
    radius: 8,
    fillColor: '#2563eb',
    color: '#ffffff',
    weight: 2,
    opacity: 1,
    fillOpacity: 0.9
  })
    .bindPopup('Selected report location')
    .addTo(locationMap);
}

function setCurrentLocationMarker(lat, lng) {
  if (currentLocationMarker) {
    currentLocationMarker.setLatLng([lat, lng]);
    return;
  }
  currentLocationMarker = L.circleMarker([lat, lng], {
    radius: 7,
    fillColor: '#0f172a',
    color: '#ffffff',
    weight: 2,
    opacity: 1,
    fillOpacity: 0.85
  });
  if (locationMap) {
    currentLocationMarker.bindPopup('Your current location').addTo(locationMap);
  }
}

function renderIncidents() {
  if (!locationMap) return;
  if (incidentLayer) {
    locationMap.removeLayer(incidentLayer);
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
      .bindPopup(incident.description || 'Reported incident')
      .addTo(incidentLayer);
  });
  incidentLayer.addTo(locationMap);
}

async function loadIncidents() {
  try {
    const res = await fetchIncidents(100, true);
    incidents.value = res.data || [];
    renderIncidents();
  } catch (e) {
    incidents.value = [];
  }
}

function useCurrentLocation() {
  if (!navigator.geolocation) {
    error.value = 'Geolocation is not supported on this device.';
    return;
  }
  findingLocation.value = true;
  error.value = '';
  navigator.geolocation.getCurrentPosition(
    (position) => {
      const lat = position.coords.latitude;
      const lng = position.coords.longitude;
      setCurrentLocationMarker(lat, lng);
      setSelectedMarker(lat, lng);
      if (locationMap) locationMap.setView([lat, lng], 14);
      findingLocation.value = false;
    },
    (e) => {
      error.value = e.message || 'Could not get current location.';
      findingLocation.value = false;
    },
    { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
  );
}

function initLocationMap() {
  if (!props.enableLocationMap || locationMap) return;
  const center = selectedPoint.value 
    ? [selectedPoint.value.lat, selectedPoint.value.lng] 
    : defaultCenter;

  locationMap = L.map('incident-location-map', { zoomControl: true }).setView(center, selectedPoint.value ? 14 : 12);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors'
  }).addTo(locationMap);
  locationMap.on('click', (event) => {
    setSelectedMarker(event.latlng.lat, event.latlng.lng);
  });
  renderIncidents();
  setTimeout(() => {
    if (locationMap) locationMap.invalidateSize();
  }, 80);
}

async function handleFile(event) {
  const file = event.target.files[0];
  if (!file) return;
  error.value = '';
  success.value = false;
  try {
    loading.value = true;
    const compressed = await compressWithWatermark(file, {
      role: props.role || currentUser.value?.role || 'user',
      email: currentUser.value?.email || 'unknown',
      time: new Date().toISOString()
    });
    form.image_base64 = compressed;
    preview.value = compressed;
  } catch (e) {
    form.image_base64 = null;
    preview.value = null;
    error.value = e.message;
  } finally {
    loading.value = false;
  }
}

async function submit() {
  error.value = '';
  success.value = false;
  const missingFields = [];
  if (!form.image_base64) {
    missingFields.push('image');
  }
  if (props.enableLocationMap && !selectedPoint.value) {
    missingFields.push('location');
  }
  if (missingFields.length > 0) {
    if (missingFields.length === 2) {
      error.value = 'Image and location are mandatory. Add a photo and select a map location.';
    } else if (missingFields[0] === 'image') {
      error.value = 'Image is mandatory. Please upload a clear photo.';
    } else {
      error.value = 'Location is mandatory. Select location on map or use current location.';
    }
    return;
  }
  loading.value = true;
  try {
    const payload = {
      ...form,
      latitude: selectedPoint.value ? Number(selectedPoint.value.lat) : null,
      longitude: selectedPoint.value ? Number(selectedPoint.value.lng) : null
    };
    await createIncident(payload);
    success.value = true;
    form.description = '';
    form.image_base64 = null;
    preview.value = null;
    selectedPoint.value = null;
    if (selectedMarker && locationMap) {
      locationMap.removeLayer(selectedMarker);
      selectedMarker = null;
    }
    await loadIncidents();
    emit('submitted');
  } catch (e) {
    error.value = e.message || 'Failed to submit';
  } finally {
    loading.value = false;
  }
}

const emit = defineEmits(['submitted']);

onMounted(() => {
  if (!props.enableLocationMap) return;
  
  // 1. Start Geolocation ASAP
  useCurrentLocation();

  nextTick(async () => {
    // 2. Initialize Map immediately
    initLocationMap();
    await loadIncidents();
  });
});

onBeforeUnmount(() => {
  if (locationMap) {
    locationMap.remove();
    locationMap = null;
  }
});
</script>

<style scoped>
.preview-img {
  width: 100%;
  max-height: 240px;
  object-fit: cover;
  border-radius: 12px;
}

.location-map {
  width: 100%;
  height: 280px;
  border-radius: 14px;
  overflow: hidden;
}
</style>
