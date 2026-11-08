<script setup lang="ts">
definePageMeta({
  redirect: route => ({ path: '/apps/settings/site/policies', query: { ...route.query, tab: 'access' }, hash: route.hash, replace: true }),
})
</script>

<template />
