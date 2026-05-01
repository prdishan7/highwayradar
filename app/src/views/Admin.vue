<template>
  <div>
    <div class="page-head fade-up">
      <div class="eyebrow">Admin</div>
      <div class="page-title">Incident Verification</div>
      <div class="page-subtitle">Review new reports and verify valid incidents.</div>
    </div>

    <div class="surface surface-body fade-up delay-1">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <h5 class="m-0">Incident queue</h5>
        <button class="btn btn-sm btn-outline-secondary" @click="loadIncidents" :disabled="loadingIncidents">
          {{ loadingIncidents ? 'Refreshing...' : 'Refresh' }}
        </button>
      </div>

      <div v-if="statusError" class="alert alert-danger mb-3">{{ statusError }}</div>
      <div v-if="loadingIncidents" class="alert-soft">Loading incidents...</div>
      <div v-else-if="incidents.length === 0" class="alert-soft">No incidents reported.</div>

      <div v-else class="table-responsive">
        <table class="table align-middle">
          <thead>
            <tr>
              <th>ID</th>
              <th>Category</th>
              <th>Severity</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in incidents" :key="item.id">
              <td data-label="ID">{{ item.id }}</td>
              <td data-label="Category">{{ item.category || 'unknown' }}</td>
              <td data-label="Severity">
                <span class="pill" :class="severityClass(item.severity)">{{ item.severity || 'low' }}</span>
              </td>
              <td data-label="Status">
                <span class="pill" :class="statusClass(item.status)">{{ (item.status || 'new').toLowerCase() }}</span>
              </td>
              <td data-label="Actions" class="d-flex gap-2">
                <button
                  class="btn btn-sm btn-primary"
                  @click="verifyIncident(item)"
                  :disabled="verifyingIncidentId === item.id || isVerified(item)"
                >
                  {{ verifyingIncidentId === item.id ? 'Verifying...' : (isVerified(item) ? 'Verified' : 'Verify') }}
                </button>
                <button
                  class="btn btn-sm btn-outline-danger"
                  @click="removeIncident(item)"
                  :disabled="deletingIncidentId === item.id"
                >
                  {{ deletingIncidentId === item.id ? 'Deleting...' : 'Delete' }}
                </button>
                <button
                  v-if="item.image_base64"
                  class="btn btn-sm btn-outline-secondary"
                  @click="openPreview(item.image_base64)"
                >
                  View image
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <teleport to="body">
      <div v-if="previewImage" class="image-modal" @click="closePreview">
        <div class="image-modal-body">
          <img :src="previewImage" alt="Incident evidence" />
          <button class="btn btn-light mt-2" @click.stop="closePreview">Close</button>
        </div>
      </div>
    </teleport>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useStatusFeed } from '../composables/useStatusFeed';
import { deleteIncident, fetchIncidents, updateIncident } from '../services/incidents';

const { refreshStatus } = useStatusFeed();

const incidents = ref([]);
const loadingIncidents = ref(true);
const statusError = ref('');
const previewImage = ref(null);
const deletingIncidentId = ref(null);
const verifyingIncidentId = ref(null);

function statusClass(status) {
  const s = (status || 'new').toLowerCase();
  if (s === 'verified') return 'risk-low';
  if (s === 'false' || s === 'resolved') return 'risk-medium';
  return 'risk-high';
}

function severityClass(severity) {
  const s = (severity || 'low').toLowerCase();
  if (s === 'high') return 'risk-high';
  if (s === 'medium') return 'risk-medium';
  return 'risk-low';
}

function isVerified(item) {
  return (item.status || '').toLowerCase() === 'verified';
}

async function loadIncidents() {
  loadingIncidents.value = true;
  statusError.value = '';
  try {
    const res = await fetchIncidents(50);
    incidents.value = res.data || [];
  } catch (e) {
    statusError.value = e.message || 'Failed to load incidents';
  } finally {
    loadingIncidents.value = false;
  }
}

async function verifyIncident(item) {
  statusError.value = '';
  verifyingIncidentId.value = item.id;
  try {
    await updateIncident(item.id, { status: 'verified' });
    await refreshStatus();
    await loadIncidents();
  } catch (e) {
    statusError.value = e.message || 'Failed to verify incident';
  } finally {
    verifyingIncidentId.value = null;
  }
}

async function removeIncident(item) {
  const confirmed = window.confirm(`Delete incident #${item.id}? This cannot be undone.`);
  if (!confirmed) return;

  statusError.value = '';
  deletingIncidentId.value = item.id;
  try {
    await deleteIncident(item.id);
    await refreshStatus();
    await loadIncidents();
  } catch (e) {
    statusError.value = e.message || 'Failed to delete incident';
  } finally {
    deletingIncidentId.value = null;
  }
}

function openPreview(img) {
  previewImage.value = img;
}

function closePreview() {
  previewImage.value = null;
}

onMounted(() => {
  loadIncidents();
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
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
}
.image-modal img {
  display: block;
  margin: 0 auto;
  max-width: 80vw;
  max-height: 70vh;
  object-fit: contain;
  border-radius: 8px;
}
.image-modal-body .btn {
  position: absolute;
  top: 12px;
  right: 12px;
  margin-top: 0 !important;
}

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
    text-align: left !important;
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

  table td[data-label="ID"] {
    position: absolute;
    top: -10px;
    right: 0;
    opacity: 0.5;
  }

  table td[data-label="Category"] {
    font-size: 1.1rem !important;
    color: var(--ink);
    font-weight: 700 !important;
    margin-bottom: 4px;
  }

  table td[data-label="Severity"],
  table td[data-label="Status"] {
    flex-direction: row;
    justify-content: space-between;
    width: 100%;
    align-items: center;
    border-bottom: 1px dashed rgba(0,0,0,0.05) !important;
    padding-bottom: 12px !important;
  }

  table td[data-label="Actions"] {
    margin-top: 12px;
    padding-top: 16px !important;
    align-items: flex-stretch;
    width: 100%;
    flex-direction: row;
    flex-wrap: wrap;
    justify-content: center !important;
    gap: 12px !important;
  }

  table td[data-label="Actions"]::before {
    display: none;
  }

  .btn-sm {
    padding: 10px 16px;
    font-size: 0.85rem;
    font-weight: 700;
    border-radius: 12px;
    flex: 1 0 auto;
    min-width: 100px;
  }
}
</style>
