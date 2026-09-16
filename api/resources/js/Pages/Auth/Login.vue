<script setup>
import { useForm } from '@inertiajs/vue3';

const form = useForm({ email: 'agent@crmflow.test', password: 'password' });
const submit = () => form.post('/login');
</script>

<template>
  <div class="shell">
    <div class="card" style="max-width: 420px; margin: 80px auto;">
      <h1>CRM Flow</h1>
      <p class="muted">Agent workspace login</p>
      <form @submit.prevent="submit">
        <label>Email</label>
        <input v-model="form.email" type="email" />
        <label>Password</label>
        <input v-model="form.password" type="password" />
        <button class="btn" :disabled="form.processing">Sign in</button>
      </form>
    </div>
  </div>
</template>
