<script setup lang="ts">
definePageMeta({
  redirect: route => ({
    path: `/apps/community/reports/${encodeURIComponent(String(route.params.id))}`,
    query: route.query,
    hash: route.hash,
    replace: true,
  }),
})
</script>

<template />
