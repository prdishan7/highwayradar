import { apiRequest } from './api';

export const INCIDENT_CATEGORIES = [
  { value: 'collision', label: 'Collision' },
  { value: 'landslide', label: 'Landslide' },
  { value: 'flooding', label: 'Flooding' },
  { value: 'obstacle', label: 'Road Obstruction' },
  { value: 'pothole', label: 'Pothole / Surface' },
  { value: 'fire', label: 'Fire / Smoke' },
  { value: 'sos', label: 'SOS / Distress' },
  { value: 'other', label: 'Other' }
];

export const INCIDENT_SEVERITIES = [
  { value: 'low', label: 'Low' },
  { value: 'medium', label: 'Medium' },
  { value: 'high', label: 'High' }
];

export function fetchIncidents(limit = 20, verifiedOnly = false) {
  const params = new URLSearchParams({ limit });
  if (verifiedOnly) params.append('verified_only', '1');
  return apiRequest(`/incidents?${params.toString()}`);
}

export function fetchLatestIncident(verifiedOnly = false) {
  const params = new URLSearchParams({ latest: '1' });
  if (verifiedOnly) params.append('verified_only', '1');
  return apiRequest(`/incidents?${params.toString()}`);
}

export function createIncident(payload) {
  return apiRequest('/incidents', { method: 'POST', body: payload });
}

export function updateIncident(id, payload) {
  return apiRequest(`/incidents/${id}`, { method: 'PATCH', body: payload });
}

export function deleteIncident(id) {
  return apiRequest(`/incidents/${id}`, { method: 'DELETE' });
}

export function clearIncidentQueue() {
  return apiRequest('/incidents', { method: 'DELETE' });
}
