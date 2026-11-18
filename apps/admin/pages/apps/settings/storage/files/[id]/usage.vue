<script setup lang="ts">
definePageMeta({
  redirect: route => ({
    path: `/apps/settings/storage/files/${encodeURIComponent(String(route.params.id))}`,
    query: route.query,
    hash: route.hash || '#usage',
    replace: true,
  }),
})
</script>

<template />
