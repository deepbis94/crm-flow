<script setup>
import Workspace from '../../Layouts/Workspace.vue';
import { router } from '@inertiajs/vue3';

defineProps({
  heatmap: { type: Array, default: () => [] },
  breaches: { type: Array, default: () => [] },
  agents: { type: Array, default: () => [] },
  deadLetters: { type: Array, default: () => [] },
});

const states = ['new', 'queued', 'claimed', 'working', 'qualified', 'disqualified', 'lost'];
const retry = (eventId) => router.post('/workspace/dead-letter/retry', { event_id: eventId });
</script>

<template>
  <Workspace>
    <h1>Supervisor dashboard</h1>
    <section class="card">
      <h2>Queue load heatmap</h2>
      <table class="heat">
        <thead>
          <tr>
            <th>Queue</th>
            <th v-for="s in states" :key="s">{{ s }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in heatmap" :key="row.id">
            <td>{{ row.queue }}</td>
            <td v-for="s in states" :key="s">{{ row[s] }}</td>
          </tr>
        </tbody>
      </table>
    </section>
    <div class="grid">
      <section class="card">
        <h2>SLA breaches</h2>
        <div class="row" v-for="b in breaches" :key="b.id">
          <div>{{ b.type }} · {{ b.lead_id }}</div>
          <div class="muted">{{ b.escalated_at }}</div>
        </div>
        <p v-if="!breaches.length" class="muted">None.</p>
      </section>
      <section class="card">
        <h2>Agent utilization</h2>
        <div class="row" v-for="a in agents" :key="a.id">
          <div>{{ a.name }}</div>
          <div>{{ a.open }}/{{ a.cap }} ({{ Math.round(a.utilization * 100) }}%)</div>
        </div>
      </section>
    </div>
    <section class="card">
      <h2>Dead-letter queue</h2>
      <div class="row" v-for="e in deadLetters" :key="e.id">
        <div>{{ e.event_type }} · {{ e.event_id }}</div>
        <button class="btn" @click="retry(e.event_id)">Retry</button>
      </div>
      <p v-if="!deadLetters.length" class="muted">Empty.</p>
    </section>
  </Workspace>
</template>
