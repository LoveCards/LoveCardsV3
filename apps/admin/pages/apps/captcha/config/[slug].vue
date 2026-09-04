<script setup lang="ts">
definePageMeta({
  redirect: route => ({
    path: `/apps/settings/verification/${encodeURIComponent(String(route.params.slug))}`,
    query: route.query,
    hash: route.hash,
    replace: true,
  }),
})
</script>

<template />
