<script setup>
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();
</script>

<template>
  <div class="shell">
    <header class="top">
      <div class="brand">CRM Flow</div>
      <nav class="nav">
        <Link href="/workspace">My queue</Link>
        <Link href="/workspace/supervisor">Supervisor</Link>
        <span class="muted" v-if="page.props.auth?.user">{{ page.props.auth.user.email }}</span>
        <form class="inline" method="post" action="/logout">
          <input type="hidden" name="_token" :value="page.props.csrf_token || ''" />
        </form>
        <Link href="/logout" method="post" as="button" class="btn secondary">Log out</Link>
      </nav>
    </header>
    <slot />
  </div>
</template>
