<template>
  <div class="fade-up container pb-5">
    <div class="page-head text-center text-md-start mb-5">
      <div class="eyebrow">SAFETY & MONITORING</div>
      <h1 class="page-title">Submit Report</h1>
      <p class="page-subtitle">Description of the incident or road hazard.</p>
    </div>

    <div class="row justify-content-center">
      <div class="col-12 col-xl-8">
        <div class="surface surface-body p-4 p-md-5">
          <incident-form :role="currentRole || 'driver'" :enable-location-map="true" @submitted="onSubmitted" />
          
          <div class="mt-5 border-top pt-4 text-center">
            <h6 class="font-display fw-bold mb-3">Guidelines for submission</h6>
            <div class="row g-3">
              <div class="col-12 col-md-4">
                <div class="p-3 bg-light rounded-3 h-100">
                  <div class="fw-bold small mb-1">Be Accurate</div>
                  <div class="text-muted" style="font-size: 0.75rem;">Only report hazards you have directly observed.</div>
                </div>
              </div>
              <div class="col-12 col-md-4">
                <div class="p-3 bg-light rounded-3 h-100">
                  <div class="fw-bold small mb-1">Add Location</div>
                  <div class="text-muted" style="font-size: 0.75rem;">Enable GPS for precise hazard placement on the map.</div>
                </div>
              </div>
              <div class="col-12 col-md-4">
                <div class="p-3 bg-light rounded-3 h-100">
                  <div class="fw-bold small mb-1">Clear Photo</div>
                  <div class="text-muted" style="font-size: 0.75rem;">A clear image helps others understand the risk better.</div>
                </div>
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useRouter } from 'vue-router';
import { currentRole } from '../services/session';
import IncidentForm from '../components/IncidentForm.vue';

const router = useRouter();
function onSubmitted() {
  setTimeout(() => {
    router.push('/community');
  }, 1500);
}
</script>

<style scoped>
.page-head {
  max-width: 600px;
}
</style>
