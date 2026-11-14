<script setup lang="ts">
definePageMeta({
  redirect: route => ({
    path: route.query.section === 'eventing' ? '/apps/operations/eventing' : '/apps/operations/communication',
    query: Object.fromEntries(Object.entries(route.query).filter(([key]) => key !== 'section')),
    hash: route.hash,
    replace: true,
  }),
})
</script>

<template />
