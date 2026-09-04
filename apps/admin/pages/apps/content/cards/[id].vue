<script setup lang="ts">
definePageMeta({
  redirect: route => ({
    path: '/apps/content/cards',
    query: { ...route.query, inspect: String(route.params.id) },
    hash: route.hash,
    replace: true,
  }),
})
</script>

<template />
