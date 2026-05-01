<template>
  <div class="fade-up container">
    <div class="page-head">
      <div class="eyebrow">USER ACCOUNT</div>
      <h1 class="page-title">Your Profile</h1>
      <p class="page-subtitle">Manage your session and identification details.</p>
    </div>

    <div class="row g-4 justify-content-center">
      <div class="col-12 col-md-8 col-lg-6">
        <div class="surface surface-body text-center p-5">
          <div class="profile-avatar-large mx-auto mb-4">
             <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
          </div>
          
          <h3 class="fw-bold font-display mb-1 text-capitalize">{{ currentUser?.email?.split('@')[0] || 'User' }}</h3>
          <p class="text-muted small mb-4">{{ currentUser?.email }}</p>
          
          <div class="pill bg-primary text-white text-uppercase px-3 py-2 mb-5">
            Role: {{ currentRole }}
          </div>

          <div class="text-start border-top pt-4">
             <div class="mb-4">
               <label class="eyebrow small mb-2 d-block">Account Status</label>
               <div class="p-3 bg-light rounded-3 d-flex align-items-center justify-content-between">
                  <span class="small fw-bold">Verified Professional</span>
                  <span class="status-indicator risk-low"></span>
               </div>
             </div>
             
             <div class="mb-5">
                <label class="eyebrow small mb-2 d-block">Operational Sector</label>
                <div class="p-3 bg-light rounded-3">
                   <span class="small fw-bold">Corridor 01 - Main Segment</span>
                </div>
             </div>
          </div>

          <button class="btn btn-primary w-100 py-3 rounded-pill" @click="handleLogout">
            Sign Out
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useRouter } from 'vue-router';
import { logout } from '../services/auth';
import { currentUser, currentRole } from '../services/session';

const router = useRouter();

async function handleLogout() {
  await logout();
  router.replace('/login');
}
</script>

<style scoped>
.profile-avatar-large {
  width: 100px;
  height: 100px;
  background: var(--ink);
  color: white;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}
.status-indicator {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  display: inline-block;
  background: var(--success);
  box-shadow: 0 0 0 3px var(--success-soft);
}
</style>
