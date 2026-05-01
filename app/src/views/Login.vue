<template>
  <div class="row justify-content-center align-items-center" style="min-height: 70vh;">
    <div class="col-12 col-md-8 col-lg-5">
      <div class="page-head text-center fade-up">
        <div class="eyebrow">Terminal Access</div>
        <div class="page-title">Identity Verification</div>
        <p class="page-subtitle">Standard protocol for corridor personnel and authorized responders.</p>
      </div>
      
      <div class="surface surface-body fade-up delay-1">
        <div class="mb-4">
          <label class="eyebrow mb-2 d-block">Personnel Email</label>
          <input v-model="email" type="email" class="form-control" placeholder="me@example.com" />
        </div>
        <div class="mb-4">
          <label class="eyebrow mb-2 d-block">Security Key</label>
          <input v-model="password" type="password" class="form-control" placeholder="password" @keyup.enter="handleLogin" />
        </div>
        
        <button class="btn btn-primary w-100 py-3 mt-2" @click="handleLogin">
          <span>Authorize Session</span>
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><polyline points="12 5 19 12 12 19"></polyline></svg>
        </button>
        
        <div class="text-center mt-4">
          <span class="text-muted small">New personnel?</span>
          <button class="btn btn-link btn-sm text-accent-strong fw-bold" @click="$router.push('/register')">Request Registration</button>
        </div>
        
        <div v-if="error" class="risk-high p-3 rounded-3 mt-4 small text-center">
          <strong>Access Denied:</strong> {{ error }}
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { login } from '../services/auth';

const email = ref('');
const password = ref('');
const error = ref('');
const router = useRouter();
const route = useRoute();

async function handleLogin() {
  error.value = '';
  try {
    const user = await login(email.value, password.value);
    const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : '';
    if (redirect.startsWith('/')) {
      router.replace(redirect);
      return;
    }
    if (user.role === 'admin') router.replace('/admin');
    else if (user.role === 'superadmin') router.replace('/superadmin');
    else router.replace('/community');
  } catch (e) {
    error.value = e.message || 'Login failed';
  }
}
</script>
