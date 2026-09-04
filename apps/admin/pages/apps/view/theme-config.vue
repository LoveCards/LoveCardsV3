<script setup lang="ts">
definePageMeta({
  redirect: (route) => {
    const queryId = Array.isArray(route.query.id) ? route.query.id[0] : route.query.id
    const slug = typeof queryId === 'string' && queryId.trim() ? queryId : 'current'
    return {
      path: `/apps/settings/appearance/themes/${encodeURIComponent(slug)}`,
      query: route.query,
      hash: route.hash,
      replace: true,
    }
  },
})
</script>

<template />
