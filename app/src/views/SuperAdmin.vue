<template>
  <div class="fade-up container">
    <div class="page-head">
      <div class="eyebrow">SYSTEM MANAGEMENT</div>
      <h1 class="page-title">SuperAdmin Console</h1>
      <p class="page-subtitle">Manage administrative privileges and create new admin profiles.</p>
    </div>

    <div class="row g-4">
      <!-- Create Admin Card -->
      <div class="col-12 col-md-5">
        <div class="surface surface-body h-100">
          <h4 class="fw-bold font-display mb-4">Create Admin Account</h4>
          <p class="text-muted small mb-4">Admins can verify incident reports and manage highway status overrides.</p>
          
          <form @submit.prevent="handleCreateAdmin">
            <div class="mb-3">
              <label class="eyebrow small mb-2 d-block">Email Address</label>
              <input 
                v-model="form.email" 
                type="email" 
                class="form-control" 
                placeholder="admin@highway.gov" 
                required
              />
            </div>
            
            <div class="mb-4">
              <label class="eyebrow small mb-2 d-block">Password</label>
              <input 
                v-model="form.password" 
                type="password" 
                class="form-control" 
                placeholder="••••••••" 
                required
              />
            </div>
            
            <button 
              type="submit" 
              class="btn btn-primary w-100 py-3" 
              :disabled="loading"
            >
              <span v-if="loading">Provisioning Account...</span>
              <span v-else>Create Admin Profile</span>
            </button>
          </form>

          <div v-if="error" class="risk-high p-3 rounded-3 mt-3 small text-center">{{ error }}</div>
          <div v-if="success" class="risk-low p-3 rounded-3 mt-3 small text-center">Account successfully provisioned.</div>
        </div>
      </div>

      <!-- Admin List -->
      <div class="col-12 col-md-7">
        <div class="surface surface-body h-100">
          <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold font-display m-0">Admin Directory</h4>
            <button class="btn btn-sm btn-outline-secondary" @click="loadAdmins" :disabled="loadingAdmins">
              Refresh
            </button>
          </div>
          
          <div v-if="loadingAdmins" class="text-center py-5">
            <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
            <div class="small text-muted mt-2">Loading admins...</div>
          </div>
          
          <div v-else-if="admins.length === 0" class="text-center py-5 border rounded-3 border-dashed">
            <div class="text-muted small">No admin accounts found in the system.</div>
          </div>
          
          <div v-else class="table-responsive">
            <table class="table table-hover align-middle">
              <thead class="bg-light">
                <tr>
                  <th class="small eyebrow">Email</th>
                  <th class="small eyebrow">Joined</th>
                  <th class="small eyebrow text-end">Action</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="admin in admins" :key="admin.id">
                  <td data-label="Email" class="small fw-semibold">{{ admin.email }}</td>
                  <td data-label="Joined" class="small text-muted">{{ formatDate(admin.created_at) }}</td>
                  <td data-label="Action" class="text-end">
                    <button 
                      class="btn btn-sm btn-outline-danger" 
                      @click="removeAdmin(admin)"
                      :disabled="deletingId === admin.id"
                    >
                      {{ deletingId === admin.id ? '...' : 'Remove' }}
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
    <div class="row g-4 mt-2">
      <!-- Incident Management Section -->
      <div class="col-12">
        <div class="surface surface-body">
          <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold font-display m-0">Global Incident Queue</h4>
            <div class="d-flex gap-2">
              <button class="btn btn-sm btn-outline-secondary" @click="loadIncidents" :disabled="loadingIncidents">
                {{ loadingIncidents ? 'Refreshing...' : 'Refresh Queue' }}
              </button>
            </div>
          </div>

          <div v-if="loadingIncidents" class="text-center py-5">
            <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
            <div class="small text-muted mt-2">Connecting to incident server...</div>
          </div>

          <div v-else-if="incidents.length === 0" class="text-center py-5 border rounded-3 border-dashed">
            <div class="text-muted small">No pending or active incidents reported.</div>
          </div>

          <div v-else class="table-responsive">
            <table class="table table-hover align-middle">
              <thead>
                <tr class="bg-light">
                  <th class="small eyebrow">ID</th>
                  <th class="small eyebrow">Category</th>
                  <th class="small eyebrow">Severity</th>
                  <th class="small eyebrow">Status</th>
                  <th class="small eyebrow text-end">Management</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in incidents" :key="item.id">
                  <td data-label="ID" class="small text-muted font-monospace">#{{ item.id }}</td>
                  <td data-label="Category" class="small fw-semibold text-capitalize">{{ item.category }}</td>
                  <td data-label="Severity">
                    <span class="pill pill-sm" :class="severityClass(item.severity)">{{ item.severity }}</span>
                  </td>
                  <td data-label="Status">
                    <span class="pill pill-sm" :class="statusClass(item.status)">{{ item.status }}</span>
                  </td>
                  <td data-label="Action" class="text-end">
                    <div class="d-flex justify-content-end gap-2">
                      <button 
                        class="btn btn-sm btn-primary" 
                        @click="verifyIncident(item)"
                        :disabled="actionId === item.id || isVerified(item)"
                      >
                         {{ isVerified(item) ? 'Verified' : 'Verify' }}
                      </button>
                      <button 
                        class="btn btn-sm btn-outline-danger" 
                        @click="removeIncident(item)"
                        :disabled="actionId === item.id"
                      >
                        Delete
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { fetchAdmins, createAdmin, deleteAdmin } from '../services/admin';
import { fetchIncidents, updateIncident, deleteIncident } from '../services/incidents';
import { useStatusFeed } from '../composables/useStatusFeed';

const form = ref({
  email: '',
  password: ''
});

const admins = ref([]);
const incidents = ref([]);
const loading = ref(false);
const loadingAdmins = ref(false);
const loadingIncidents = ref(false);
const deletingId = ref(null);
const actionId = ref(null);
const error = ref('');
const success = ref(false);

const { refreshStatus } = useStatusFeed();

async function loadAdmins() {
  loadingAdmins.value = true;
  try {
    const res = await fetchAdmins();
    admins.value = res.data || [];
  } catch (e) {
    console.error('Failed to load admins', e);
  } finally {
    loadingAdmins.value = false;
  }
}

async function handleCreateAdmin() {
  loading.value = true;
  error.value = '';
  success.value = false;
  
  try {
    await createAdmin(form.value.email, form.value.password);
    success.value = true;
    form.value.email = '';
    form.value.password = '';
    await loadAdmins();
  } catch (e) {
    error.value = e.message || 'Failed to create admin account.';
  } finally {
    loading.value = false;
  }
}

async function removeAdmin(admin) {
  if (!confirm(`Are you sure you want to remove ${admin.email}?`)) return;
  
  deletingId.value = admin.id;
  try {
    await deleteAdmin(admin.id);
    await loadAdmins();
  } catch (e) {
    alert('Failed to remove admin: ' + e.message);
  } finally {
    deletingId.value = null;
  }
}

// Incident Management Logic
async function loadIncidents() {
  loadingIncidents.value = true;
  try {
    const res = await fetchIncidents(50);
    incidents.value = res.data || [];
  } catch (e) {
    console.error('Failed to load incidents', e);
  } finally {
    loadingIncidents.value = false;
  }
}

async function verifyIncident(item) {
  actionId.value = item.id;
  try {
    await updateIncident(item.id, { status: 'verified' });
    await refreshStatus();
    await loadIncidents();
  } catch (e) {
    alert('Failed to verify incident: ' + e.message);
  } finally {
    actionId.value = null;
  }
}

async function removeIncident(item) {
  if (!confirm(`Delete incident #${item.id}?`)) return;
  actionId.value = item.id;
  try {
    await deleteIncident(item.id);
    await refreshStatus();
    await loadIncidents();
  } catch (e) {
    alert('Failed to delete incident: ' + e.message);
  } finally {
    actionId.value = null;
  }
}

function severityClass(severity) {
  const s = (severity || 'low').toLowerCase();
  if (s === 'high') return 'risk-high';
  if (s === 'medium') return 'risk-medium';
  return 'risk-low';
}

function statusClass(status) {
  const s = (status || 'new').toLowerCase();
  if (s === 'verified') return 'risk-low';
  if (s === 'false' || s === 'resolved') return 'risk-medium';
  return 'risk-high';
}

function isVerified(item) {
  return (item.status || '').toLowerCase() === 'verified';
}

function formatDate(dateStr) {
  if (!dateStr) return 'N/A';
  return new Date(dateStr).toLocaleDateString();
}

onMounted(() => {
  loadAdmins();
  loadIncidents();
});
</script>

<style scoped>
@media (max-width: 768px) {
  .table-responsive {
    overflow-x: visible !important;
    border: none !important;
  }
  
  table, thead, tbody, th, td, tr {
    display: block;
  }
  
  table thead {
    display: none;
  }
  
  table tr {
    margin-bottom: 20px;
    padding: 20px;
    border: 1px solid rgba(15, 23, 42, 0.08);
    border-radius: 20px;
    background: #ffffff;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
  }
  
  table td {
    padding: 8px 0 !important;
    border: none !important;
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 4px;
  }
  
  table td::before {
    content: attr(data-label);
    font-size: 0.65rem;
    font-weight: 800;
    text-transform: uppercase;
    color: var(--muted);
    letter-spacing: 0.05em;
    opacity: 0.8;
  }

  /* Specific value styling for mobile */
  table td[data-label="Email"], 
  table td[data-label="Category"] {
    font-size: 1.1rem !important;
    color: var(--ink);
    font-weight: 700 !important;
    margin-bottom: 4px;
  }

  table td[data-label="ID"] {
    position: absolute;
    top: -10px;
    right: 0;
    opacity: 0.5;
  }

  table td[data-label="Severity"],
  table td[data-label="Status"],
  table td[data-label="Joined"] {
    flex-direction: row;
    justify-content: space-between;
    width: 100%;
    align-items: center;
    border-bottom: 1px dashed rgba(0,0,0,0.05) !important;
    padding-bottom: 12px !important;
  }

  table td[data-label="Action"] {
    margin-top: 12px;
    padding-top: 16px !important;
    align-items: flex-stretch;
    width: 100%;
  }

  table td[data-label="Action"]::before {
    display: none;
  }

  .d-flex.justify-content-end {
    justify-content: center !important;
    gap: 12px !important;
  }

  .btn-sm {
    padding: 10px 20px;
    font-size: 0.85rem;
    font-weight: 700;
    border-radius: 12px;
  }
}
</style>
