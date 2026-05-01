import { Capacitor, CapacitorHttp } from '@capacitor/core';

const RAW_API_BASE = import.meta.env.VITE_API_BASE || '/api';
const API_BASE = RAW_API_BASE.replace(/\/+$/, '');
const IS_NATIVE = Capacitor.isNativePlatform();
const IS_CAPACITOR_LOCAL = typeof window !== 'undefined' &&
  (window.location.protocol === 'capacitor:' || window.location.hostname === 'localhost');

async function parseJson(response) {
  const text = await response.text();
  try {
    return text ? JSON.parse(text) : {};
  } catch (e) {
    if (typeof text === 'string' && text.includes('aes.js')) {
      return { error: 'Hosting blocked API request (InfinityFree browser challenge).' };
    }
    return { error: 'Invalid JSON response' };
  }
}

export async function apiRequest(path, { method = 'GET', body, auth = true } = {}) {
  const headers = {};
  if (body !== undefined) {
    headers['Content-Type'] = 'application/json';
  }
  const token = localStorage.getItem('hg_token');
  if (auth && token) {
    headers.Authorization = `Bearer ${token}`;
    console.log(`[API] Token present: ${token.substring(0, 20)}...`);
  } else if (auth) {
    console.warn(`[API] No token found for authenticated request to ${path}`);
  }

  const endpoint = path.startsWith('/') ? path : `/${path}`;
  const url = `${API_BASE}${endpoint}`;
  console.log(`[API] ${method} ${url}`, body ? { body } : '');

  try {
    // Use native HTTP BRIDGE ONLY for standalone local builds.
    // When app is loaded from hosted URL (Remote Mode), we MUST use browser fetch 
    // to ensure cPanel security/cookies/headers are handled correctly.
    if (IS_NATIVE && IS_CAPACITOR_LOCAL) {
      console.log(`[API] Standalone Mode: Using CapacitorHttp bridge for ${url}`);

      const response = await CapacitorHttp.request({
        url,
        method,
        headers,
        data: body,
        connectTimeout: 10000,
        readTimeout: 10000
      });

      let data = response.data;

      // If the response is a string, check if it's an HTML error page
      if (typeof data === 'string' && (data.includes('<!DOCTYPE html>') || data.includes('<html'))) {
        const snippet = data.substring(0, 150).replace(/<[^>]*>/g, '').trim();
        console.error(`[API] Received HTML instead of JSON. Snippet: ${snippet}`);
        throw new Error(`Server Error: ${snippet || 'Received HTML error page instead of JSON'}. Check your cPanel settings.`);
      }

      if (typeof data === 'string') {
        try {
          data = JSON.parse(data);
        } catch (e) {
          // Keep as string if it's not JSON
        }
      }

      if (response.status < 200 || response.status >= 300) {
        throw new Error(data?.error || data?.message || `Server error (${response.status})`);
      }
      return data;
    }

    const res = await fetch(url, {
      method,
      headers,
      body: body ? JSON.stringify(body) : undefined
    });

    console.log(`[API] Response:`, res.status, res.statusText);

    const data = await parseJson(res);
    if (!res.ok) {
      console.error(`[API] Error response:`, data);
      throw new Error(data.error || data.message || 'Request failed');
    }
    return data;
  } catch (error) {
    console.error(`[API] Request failed:`, error);
    // Handle network errors (failed to fetch)
    if (error instanceof TypeError && (error.message.includes('fetch') || error.message.includes('Failed'))) {
      throw new Error('Failed to connect to server. Ensure your internet/WAMP is active and API_BASE matches your server.');
    }
    throw error;
  }
}
