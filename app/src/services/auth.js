import { apiRequest } from './api';
import { currentRole, currentUser, sessionToken } from './session';

function persistSession(token, user) {
  sessionToken.value = token;
  currentUser.value = user;
  currentRole.value = user?.role || null;
  localStorage.setItem('hg_token', token);
  localStorage.setItem('hg_user', JSON.stringify(user));
}

export async function login(email, password) {
  const res = await apiRequest('/auth/login', {
    method: 'POST',
    body: { email, password },
    auth: false
  });
  persistSession(res.token, res.user);
  return res.user;
}

export async function register(email, password, role) {
  const res = await apiRequest('/auth/register', {
    method: 'POST',
    body: { email, password, role },
    auth: false
  });
  persistSession(res.token, res.user);
  return res.user;
}

export async function createAdminAccount(email, password) {
  return await apiRequest('/auth/register', {
    method: 'POST',
    body: { email, password, role: 'admin' },
    auth: false
  });
}

export function logout() {
  sessionToken.value = '';
  currentUser.value = null;
  currentRole.value = null;
  localStorage.removeItem('hg_token');
  localStorage.removeItem('hg_user');
}
