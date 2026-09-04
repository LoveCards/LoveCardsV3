<script setup lang="ts">
definePageMeta({
  redirect: route => ({
    path: `/apps/settings/storage/files/${encodeURIComponent(String(route.params.id))}/usage`,
    query: route.query,
    hash: route.hash,
    replace: true,
  }),
})
</script>

<template />
