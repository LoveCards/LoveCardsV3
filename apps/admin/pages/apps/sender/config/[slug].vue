<script setup lang="ts">
definePageMeta({
  redirect: route => ({
    path: `/apps/settings/messaging/${encodeURIComponent(String(route.params.slug))}`,
    query: route.query,
    hash: route.hash,
    replace: true,
  }),
})
</script>

<template />
