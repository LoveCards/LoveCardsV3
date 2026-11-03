<template>
  <v-toolbar color="surface" height="auto">
    <v-container class="pa-3" fluid>
      <div class="admin-filter-toolbar">
        <slot />
      </div>
    </v-container>
  </v-toolbar>
</template>

<style scoped>
.admin-filter-toolbar {
  width: 100%;
}
</style>
