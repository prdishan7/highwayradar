import { ref } from 'vue';
import { fetchStatus } from '../services/status';
import { listenLiveSensor, listenSos } from '../services/rtdb';

const status = ref({ status_text: 'Loading...', source: 'auto' });
const latestIncident = ref(null);
const sensor = ref({});
const sos = ref({});
const latestIncidents = ref([]);

let started = false;
let timer = null;

async function refreshStatus() {
  try {
    const res = await fetchStatus();
    status.value = res;
    latestIncident.value = res.latest_incident || null;
    latestIncidents.value = res.latest_incidents || (res.latest_incident ? [res.latest_incident] : []);
    mergeSensor(res.sensor);
    mergeSos(res.sos);
  } catch (e) {
    // Log error for debugging, but don't break the UI
    console.error('Failed to refresh status:', e.message);
    // Only update status if it's still in loading state
    if (status.value.status_text === 'Loading...') {
      status.value = { 
        status_text: 'Error loading status', 
        source: 'error',
        error: e.message 
      };
    }
  }
}

function mergeSensor(val) {
  if (val && typeof val === 'object' && Object.keys(val).length) {
    sensor.value = { ...sensor.value, ...val };
  }
}

function mergeSos(val) {
  if (val && typeof val === 'object' && Object.keys(val).length) {
    sos.value = { ...sos.value, ...val };
  }
}

export function useStatusFeed() {
  if (!started) {
    started = true;
    refreshStatus();
    // Poll every 5 seconds instead of 1 second to reduce server load
    timer = setInterval(refreshStatus, 5000);
    listenLiveSensor((val) => {
      mergeSensor(val);
    });
    listenSos((val) => {
      mergeSos(val);
    });
  }

  return { status, latestIncident, latestIncidents, sensor, sos, refreshStatus };
}
