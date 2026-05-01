<template>
  <div class="row justify-content-center">
    <div class="col-12 col-md-7 col-lg-5">
      <div class="page-head text-center fade-up">
        <div class="eyebrow">New operator</div>
        <div class="page-title">Create account</div>
        <div class="page-subtitle">Drivers and residents can self-enroll.</div>
      </div>
      <div class="surface surface-body auth-card fade-up delay-1">
        <div class="mb-3">
          <label class="form-label">Email</label>
          <input v-model="email" type="email" class="form-control" placeholder="name@example.com" />
        </div>
        <div class="mb-3">
          <label class="form-label">Password</label>
          <input v-model="password" type="password" class="form-control" placeholder="********" />
        </div>
        <div class="mb-3">
          <label class="form-label">Confirm Password</label>
          <input v-model="confirm" type="password" class="form-control" placeholder="********" />
        </div>
        <div class="mb-3">
          <label class="form-label">Role</label>
          <select v-model="role" class="form-control">
            <option value="driver">Highway Driver</option>
            <option value="local">Local Resident</option>
          </select>
        </div>
        <button class="btn btn-primary w-100 action-button" @click="handleRegister">Create Account</button>
        <button class="btn btn-link w-100 mt-2" @click="$router.push('/login')">Back to Login</button>
        <div v-if="error" class="alert alert-danger mt-3">{{ error }}</div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { register } from '../services/auth';

const email = ref('');
const password = ref('');
const confirm = ref('');
const role = ref('driver');
const error = ref('');
const router = useRouter();

function homeForRole() {
  return '/community';
}

async function handleRegister() {
  error.value = '';
  if (password.value !== confirm.value) {
    error.value = 'Passwords do not match';
    return;
  }
  try {
    const user = await register(email.value, password.value, role.value);
    router.replace(homeForRole(user.role));
  } catch (e) {
    error.value = e.message || 'Registration failed';
  }
}
</script>
