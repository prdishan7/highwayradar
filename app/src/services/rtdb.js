import { ref, onValue, update } from 'firebase/database';
import { db } from './firebase';

export function listenLiveSensor(cb) {
  const r = ref(db, 'live/sensor');
  return onValue(r, (snap) => cb(snap.val() || {}));
}

export function listenSos(cb) {
  const r = ref(db, 'alerts/sos');
  return onValue(r, (snap) => cb(snap.val() || {}));
}

export async function clearSosStatus() {
  const r = ref(db, 'alerts/sos');
  await update(r, {
    active: false,
    level: 'DEACTIVATED',
    timestampMs: Date.now()
  });
}
