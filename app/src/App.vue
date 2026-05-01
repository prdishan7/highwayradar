<template>
  <div class="app-shell" :class="{ 'red-alert-mode': redThemeActive }">
    <div class="app-bg"></div>
    
    <nav class="topbar">
      <div class="container d-flex justify-content-between align-items-center">
        <router-link class="brand" :to="homeLink">
          <div class="brand-mark"></div>
          <div>
            <div class="brand-title">GBBS Highway</div>
            <div class="brand-subtitle">SAFETY & MONITORING</div>
          </div>
        </router-link>
        <div class="d-flex align-items-center gap-3">
          <div class="topbar-actions d-none d-md-flex align-items-center gap-3">
            <div class="nav-links">
              <router-link
                v-for="item in navItems"
                :key="item.key"
                :to="item.to"
                class="nav-link"
                @click="onNavClick($event, item)"
              >
                {{ item.label }}
              </router-link>
            </div>
          </div>
          
          <router-link v-if="currentUser" class="profile-circle" to="/profile" title="My Profile">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
          </router-link>
          
          <div v-else class="d-md-flex d-none">
            <router-link to="/register" class="btn btn-primary btn-sm">Get Started</router-link>
          </div>
        </div>
      </div>
    </nav>
    
    <main class="container main-pad">
      <router-view v-slot="{ Component }">
        <transition name="slide-fade" mode="out-in">
          <component :is="Component" />
        </transition>
      </router-view>
    </main>

    <nav class="mobile-tabbar">
      <router-link
        v-for="item in tabItems"
        :key="item.to"
        class="tab-link"
        :to="item.to"
        @click="onNavClick($event, item)"
      >
        <component :is="getIcon(item.key)" />
        <span>{{ item.mobileLabel || item.label }}</span>
      </router-link>
    </nav>

    <div v-if="previewImage" class="image-modal" @click="closePreview">
      <div class="image-modal-body">
        <img :src="previewImage" alt="Incident evidence" />
        <button class="btn btn-primary mt-3 w-100" @click.stop="closePreview">Dimiss Preview</button>
      </div>
    </div>

    <div v-if="loginPrompt.visible" class="auth-modal-overlay" @click="handleNotNow">
      <div class="auth-modal-panel" @click.stop>
        <div class="auth-modal-badge">Guest mode</div>
        <h5 class="auth-modal-title">Login Required</h5>
        <p class="auth-modal-text">
          To use <strong>{{ loginPrompt.featureLabel }}</strong>, please sign in.
        </p>
        <div class="auth-modal-actions">
          <button class="btn auth-btn-secondary w-100" @click="handleNotNow">Not now</button>
          <button class="btn auth-btn-primary w-100" @click="continueToLogin">Login</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch, provide } from 'vue';
import { useRouter } from 'vue-router';
import { logout } from './services/auth';
import { currentUser, currentRole } from './services/session';
import { useStatusFeed } from './composables/useStatusFeed';
import { startAlerting } from './services/notifications';
import { formatNepal } from './utils/time';

const router = useRouter();

const { 
  status,
  sensor, 
  sos, 
  latestIncident, 
  refreshStatus 
} = useStatusFeed();

const previewImage = ref(null);
const loginPrompt = ref({
  visible: false,
  featureLabel: 'this feature',
  destination: '/login'
});
const redThemeActive = ref(false);
let audioCtx = null;
let buzzerTimer = null;
let buzzerStopTimer = null;
let removeAudioUnlockListeners = null;

function openPreview(img) {
  previewImage.value = img;
}
function closePreview() {
  previewImage.value = null;
}

provide('openGlobalPreview', openPreview);

const navItems = computed(() => {
  const baseItems = [
    { key: 'community', label: 'Community', to: '/community' },
    { key: 'planner', label: 'Trip Planner', to: '/planner' },
    { key: 'report', label: 'Report', to: '/report' }
  ];

  if (currentUser.value) {
    baseItems.push({ key: 'profile', label: 'Profile', to: '/profile' });
  }

  if (!currentUser.value) {
    return [
      ...baseItems,
      { key: 'login', label: 'Login', to: '/login' }
    ];
  }

  const role = currentRole.value || 'driver';
  if (role === 'admin' || role === 'superadmin') {
    const adminItems = [
      ...baseItems, // Now includes Profile
      { key: 'admin', label: 'Verification Console', to: '/admin' }
    ];
    if (role === 'superadmin') {
      adminItems.push({ key: 'superadmin', label: 'System Admin', to: '/superadmin' });
    }
    return adminItems;
  }

  return baseItems;
});

const tabItems = computed(() => {
  const baseItems = [
    { key: 'community', label: 'Community', mobileLabel: 'Radar', to: '/community' },
    { key: 'planner', label: 'Trip Planner', mobileLabel: 'Plan', to: '/planner' },
    { key: 'report', label: 'Report', mobileLabel: 'Report', to: '/report' }
  ];

  if (!currentUser.value) {
    return [
      ...baseItems,
      { key: 'login', label: 'Login', mobileLabel: 'Login', to: '/login' }
    ];
  }

  const role = currentRole.value || 'driver';
  const finalItems = [...baseItems];

  if (role === 'admin' || role === 'superadmin') {
    finalItems.push({ key: 'admin', label: 'Verification', mobileLabel: 'Verify', to: '/admin' });
  }
  
  if (role === 'superadmin') {
    finalItems.push({ key: 'superadmin', label: 'System', mobileLabel: 'System', to: '/superadmin' });
  }

  return finalItems;
});

const homeLink = computed(() => {
  if (!currentUser.value) return '/community'; // Guest goes to Radar
  const role = currentRole.value || 'driver';
  if (role === 'superadmin') return '/superadmin';
  if (role === 'admin') return '/admin';
  return '/community';
});

async function handleLogout() {
  await logout();
  router.replace('/login');
}

const statusTone = computed(() => {
  const text = (status.value?.status_text || '').toLowerCase();
  if (text.includes('close')) return 'risk-high';
  if (text.includes('warn')) return 'risk-medium';
  return 'risk-low';
});

const isRedAlert = computed(() => {
  const statusText = (status.value?.status_text || '').toLowerCase();
  const risk = (sensor.value?.riskLevel || '').toUpperCase();
  const sosActive = !!sos.value?.active;
  return sosActive || statusText.includes('close') || risk === 'HIGH';
});

function ensureAudioContext() {
  if (typeof window === 'undefined') return null;
  if (!audioCtx) {
    const AudioCtx = window.AudioContext || window.webkitAudioContext;
    if (!AudioCtx) return null;
    audioCtx = new AudioCtx();
  }
  return audioCtx;
}

function stopWebBuzzer() {
  if (buzzerTimer) {
    clearInterval(buzzerTimer);
    buzzerTimer = null;
  }
  if (buzzerStopTimer) {
    clearTimeout(buzzerStopTimer);
    buzzerStopTimer = null;
  }
  redThemeActive.value = false;
}

async function playWebBuzzerFiveSeconds() {
  const ctx = ensureAudioContext();
  if (!ctx) return;

  if (ctx.state === 'suspended') {
    try {
      await ctx.resume();
    } catch (e) {
      return;
    }
  }

  stopWebBuzzer();
  redThemeActive.value = true;
  const endAt = Date.now() + 5000;
  let highTone = true;
  buzzerStopTimer = setTimeout(() => {
    redThemeActive.value = false;
    buzzerStopTimer = null;
  }, 5000);

  buzzerTimer = setInterval(() => {
    if (Date.now() >= endAt) {
      stopWebBuzzer();
      return;
    }

    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    osc.type = 'square';
    osc.frequency.value = highTone ? 1600 : 1200;
    gain.gain.value = 0.25;
    osc.connect(gain);
    gain.connect(ctx.destination);
    osc.start();
    osc.stop(ctx.currentTime + 0.22);
    highTone = !highTone;
  }, 250);
}

const liveSource = computed(() => {
  if (status.value?.overridden_by_admin) return 'admin';
  if (sos.value?.active) return 'sos';
  const incidentStatus = (latestIncident.value?.status || '').toLowerCase();
  if (latestIncident.value && !['resolved', 'false'].includes(incidentStatus)) return 'incident';
  const risk = (sensor.value?.riskLevel || '').toUpperCase();
  if (risk === 'HIGH' || risk === 'MEDIUM') return 'sensor';
  return 'none';
});

const liveFeedLabel = computed(() => {
  switch (liveSource.value) {
    case 'admin':
      return 'Admin Override';
    case 'sos':
      return 'SOS System';
    case 'incident':
      return 'Log Submission';
    case 'sensor':
      return 'Sensor Based';
    case 'none':
      return 'No Status';
    default:
      return 'Live Feed';
  }
});

// Icon Components
const IconDashboard = {
  template: `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>`
};
const IconAdmin = {
  template: `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>`
};
const IconUser = {
  template: `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>`
};
const IconCommunity = {
  template: `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>`
};
const IconPlanner = {
  template: `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"></polygon><line x1="8" y1="2" x2="8" y2="18"></line><line x1="16" y1="6" x2="16" y2="22"></line></svg>`
};
const IconReport = {
  template: `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>`
};

function getIcon(key) {
  if (key === 'dashboard') return IconDashboard;
  if (key === 'admin' || key === 'superadmin') return IconAdmin;
  if (key === 'community') return IconCommunity;
  if (key === 'planner') return IconPlanner;
  if (key === 'driver' || key === 'login' || key === 'register' || key === 'profile') return IconUser;
  if (key === 'report') return IconReport;
  return IconDashboard;
}

function requiresAuthPrompt(item) {
  if (currentUser.value) return false;
  return item?.key === 'planner' || item?.key === 'report';
}

function onNavClick(event, item) {
  if (!requiresAuthPrompt(item)) return;
  event.preventDefault();
  loginPrompt.value = {
    visible: true,
    featureLabel: item.label,
    destination: item.to
  };
}

function closeLoginPrompt() {
  loginPrompt.value.visible = false;
}

function handleNotNow() {
  closeLoginPrompt();
  router.push('/dashboard');
}

function continueToLogin() {
  const redirect = loginPrompt.value.destination || '/dashboard';
  closeLoginPrompt();
  router.push({ path: '/login', query: { redirect } });
}


onMounted(() => {
  startAlerting();

  const unlockAudio = () => {
    const ctx = ensureAudioContext();
    if (ctx && ctx.state === 'suspended') {
      ctx.resume().catch(() => {});
    }
  };
  window.addEventListener('click', unlockAudio, { passive: true });
  window.addEventListener('touchstart', unlockAudio, { passive: true });
  removeAudioUnlockListeners = () => {
    window.removeEventListener('click', unlockAudio);
    window.removeEventListener('touchstart', unlockAudio);
  };
});

watch(isRedAlert, (current, prev) => {
  if (current && !prev) {
    playWebBuzzerFiveSeconds();
  }
});

onBeforeUnmount(() => {
  if (removeAudioUnlockListeners) {
    removeAudioUnlockListeners();
    removeAudioUnlockListeners = null;
  }
  stopWebBuzzer();
  if (audioCtx && audioCtx.state !== 'closed') {
    audioCtx.close().catch(() => {});
  }
});
</script>

<style scoped>
.image-modal {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.75);
  display: grid;
  place-items: center;
  z-index: 999;
  padding: 20px;
}
.image-modal-body {
  background: #0f1624;
  padding: 16px;
  border-radius: 12px;
  max-width: 90vw;
  max-height: 90vh;
  display: grid;
  gap: 12px;
}
.image-modal img {
  max-width: 80vw;
  max-height: 70vh;
  object-fit: contain;
  border-radius: 8px;
}
.auth-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.56);
  backdrop-filter: blur(4px);
  display: grid;
  place-items: center;
  z-index: 1000;
  padding: 16px;
}

.auth-modal-panel {
  width: min(440px, 96vw);
  border-radius: 18px;
  background: #ffffff;
  color: #0f172a;
  padding: 18px;
  border: 1px solid rgba(15, 23, 42, 0.08);
  box-shadow: 0 14px 30px rgba(15, 23, 42, 0.28);
}

.auth-modal-badge {
  display: inline-flex;
  align-items: center;
  font-size: 0.72rem;
  font-weight: 700;
  color: #475569;
  background: #f1f5f9;
  border-radius: 999px;
  padding: 4px 10px;
  margin-bottom: 10px;
}

.auth-modal-title {
  margin: 0;
  font-size: 1.65rem;
  line-height: 1.1;
  color: #0f172a;
}

.auth-modal-text {
  margin: 10px 0 16px 0;
  color: #334155;
  font-size: 1rem;
}

.auth-modal-actions {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}

.auth-btn-secondary {
  background: #ffffff;
  color: #475569;
  border: 1.5px solid #cbd5e1;
}

.auth-btn-secondary:hover {
  background: #f8fafc;
  color: #0f172a;
}

.auth-btn-primary {
  background: #0b1738;
  color: #ffffff;
  border: 1px solid #0b1738;
}

.auth-btn-primary:hover {
  background: #111f46;
}

@media (max-width: 480px) {
  .auth-modal-panel {
    padding: 16px;
    border-radius: 16px;
  }

  .auth-modal-title {
    font-size: 1.4rem;
  }

  .auth-modal-actions {
    grid-template-columns: 1fr;
  }
}

.topbar-actions {
  display: flex;
  align-items: center;
  gap: 16px;
}

.profile-circle {
  width: 36px;
  height: 36px;
  background: #0f172a;
  color: white;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: transform 0.2s;
}

.profile-circle:active {
  transform: scale(0.9);
}

@media (max-width: 768px) {
  .nav-links {
    display: none;
  }
}

.tab-link-exit {
  margin-left: 0;
}
</style>

