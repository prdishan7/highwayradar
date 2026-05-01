import { fetchStatus } from './status';
import { fetchLatestIncident } from './incidents';
import { listenSos } from './rtdb';
import { initPush } from './push';

let pollTimer = null;
let lastIncidentId = null;
let lastStatus = null;
let sosUnsub = null;

function sendNotification(title, body) {
  if (typeof Notification === 'undefined') return;
  if (Notification.permission === 'granted') {
    new Notification(title, { body });
  }
}

export function startAlerting() {
  initPush(); // Initialize background push service

  if (typeof Notification !== 'undefined' && Notification.permission === 'default') {
    Notification.requestPermission();
  }

  if (!pollTimer) {
    pollTimer = setInterval(async () => {
      try {
        const [statusRes, incidentRes] = await Promise.all([fetchStatus(), fetchLatestIncident()]);
        const statusText = (statusRes.status_text || '').toLowerCase();
        if (statusText === 'closed' && lastStatus !== 'closed') {
          sendNotification('Highway closed', 'High risk detected. Avoid the corridor.');
        }
        lastStatus = statusText;

        const latest = incidentRes.data?.[0];
        if (latest && latest.id !== lastIncidentId && latest.severity === 'high') {
          sendNotification('High severity incident', latest.description || 'New incident reported.');
        }
        if (latest) {
          lastIncidentId = latest.id;
        }
      } catch (e) {
        // ignore transient errors
      }
    }, 30000);
  }

  if (!sosUnsub) {
    sosUnsub = listenSos((val) => {
      if (val?.active) {
        sendNotification('SOS triggered', `Level ${val.level || 'high'} alert received.`);
      }
    });
  }
}

export function stopAlerting() {
  if (pollTimer) {
    clearInterval(pollTimer);
    pollTimer = null;
  }
  if (sosUnsub) {
    sosUnsub();
    sosUnsub = null;
  }
}
