import { ref as vueRef } from 'vue';
import { apiRequest } from './api';

function safeParseJson(value) {
  if (!value || value === 'undefined' || value === 'null') return null;
  try {
    return JSON.parse(value);
  } catch {
    return null;
  }
}

export const sessionToken = vueRef(localStorage.getItem('hg_token') || '');
export const currentUser = vueRef(safeParseJson(localStorage.getItem('hg_user')));
export const currentRole = vueRef(currentUser.value?.role || null);

export async function refreshProfile() {
  if (!sessionToken.value) return null;
  try {
    const res = await apiRequest('/me');
    currentUser.value = res.user;
    currentRole.value = res.user?.role || null;
    localStorage.setItem('hg_user', JSON.stringify(res.user));
    return res.user;
  } catch (e) {
    sessionToken.value = '';
    currentUser.value = null;
    currentRole.value = null;
    localStorage.removeItem('hg_user');
    localStorage.removeItem('hg_token');
    throw e;
  }
}
