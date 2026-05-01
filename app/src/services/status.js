import { apiRequest } from './api';

export function fetchStatus() {
  return apiRequest('/status');
}

export function overrideStatus(status_text) {
  return apiRequest('/status', { method: 'POST', body: { status_text } });
}

export function clearStatusOverride() {
  return apiRequest('/status', { method: 'POST', body: { clear_override: true } });
}
