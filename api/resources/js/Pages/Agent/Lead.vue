<script setup>
import Workspace from '../../Layouts/Workspace.vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({ lead: { type: Object, required: true } });

const note = useForm({ body: '', type: 'note' });
const convert = useForm({ sku: 'starter-monthly', name: 'Starter Monthly', amount_minor: 4900, currency: 'USD' });

const addNote = () => note.post(`/workspace/leads/${props.lead.id}/notes`, { onSuccess: () => note.reset('body') });
const qualify = () => convert.post(`/workspace/leads/${props.lead.id}/convert`);
const working = useForm({});
const start = () => working.post(`/workspace/leads/${props.lead.id}/working`);
</script>

<template>
  <Workspace>
    <div class="card">
      <h1>{{ lead.name }}</h1>
      <p class="muted">{{ lead.email }} · {{ lead.phone }} · {{ lead.state }}</p>
      <p v-if="lead.suspected_duplicate_of" class="muted">Flagged duplicate of {{ lead.suspected_duplicate_of }} — review before overwrite.</p>
      <button v-if="lead.state === 'claimed'" class="btn" @click="start">Start working</button>
    </div>
    <div class="grid">
      <section class="card">
        <h2>Timeline</h2>
        <div class="row" v-for="t in lead.transitions" :key="t.id">
          <div>{{ t.from_state || '∅' }} → {{ t.to_state }}</div>
          <div class="muted">{{ t.reason_code }} · {{ t.agent?.name || 'system' }}</div>
        </div>
      </section>
      <section class="card">
        <h2>Notes</h2>
        <div class="row" v-for="n in lead.notes" :key="n.id">
          <div>{{ n.body }}</div>
          <div class="muted">v{{ n.version }} · {{ n.agent?.name }} · {{ n.type }}</div>
        </div>
        <form @submit.prevent="addNote">
          <textarea v-model="note.body" rows="3"></textarea>
          <select v-model="note.type">
            <option value="note">Note</option>
            <option value="call_outcome">Call outcome</option>
            <option value="tag">Tag</option>
          </select>
          <button class="btn">Append</button>
        </form>
      </section>
    </div>
    <section class="card" v-if="lead.state === 'working'">
      <h2>Convert to order</h2>
      <form @submit.prevent="qualify">
        <label>SKU</label>
        <input v-model="convert.sku" />
        <label>Product</label>
        <input v-model="convert.name" />
        <label>Amount (minor)</label>
        <input v-model="convert.amount_minor" type="number" />
        <button class="btn">Qualify & hand off</button>
      </form>
    </section>
    <section class="card" v-if="lead.order">
      <h2>Order</h2>
      <p>{{ lead.order.product_name }} · {{ lead.order.payment_status }}</p>
    </section>
  </Workspace>
</template>
