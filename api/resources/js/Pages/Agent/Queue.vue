<script setup>
import Workspace from '../../Layouts/Workspace.vue';
import { Link, router } from '@inertiajs/vue3';

defineProps({
  mine: { type: Array, default: () => [] },
  available: { type: Array, default: () => [] },
});

const claim = (id) => router.post(`/workspace/leads/${id}/claim`);
</script>

<template>
  <Workspace>
    <h1>My queue</h1>
    <div class="grid">
      <section class="card">
        <h2>Working</h2>
        <div v-if="!mine.length" class="muted">No claimed leads.</div>
        <div class="row" v-for="lead in mine" :key="lead.id">
          <div>
            <Link :href="`/workspace/leads/${lead.id}`">{{ lead.name }}</Link>
            <div class="muted">{{ lead.email }} · {{ lead.state }}</div>
          </div>
          <span class="badge">{{ lead.queue?.name }}</span>
        </div>
      </section>
      <section class="card">
        <h2>Available</h2>
        <div v-if="!available.length" class="muted">Queue is empty.</div>
        <div class="row" v-for="lead in available" :key="lead.id">
          <div>
            <div>{{ lead.name }}</div>
            <div class="muted">{{ lead.email }} · p{{ lead.priority }}</div>
          </div>
          <button class="btn" @click="claim(lead.id)">Claim</button>
        </div>
      </section>
    </div>
  </Workspace>
</template>
