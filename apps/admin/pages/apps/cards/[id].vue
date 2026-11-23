<script setup lang="ts">
definePageMeta({
  redirect: route => ({
    path: '/apps/content/cards/' + String(route.params.id),
    query: Object.fromEntries(Object.entries(route.query).filter(([key]) => key !== 'inspect')),
    hash: route.hash,
    replace: true,
  }),
})
</script>

<template />
