<template>
  <v-row class="pt-2"><v-col cols="12"><h1 class="text-primary font-weight-bold">Theme configuration</h1></v-col></v-row>
  <v-card>
    <v-card-text>
      <div v-if="loading">Loading…</div>
      <v-alert v-else-if="loadError" type="info" variant="tonal">{{ loadError }}</v-alert>
      <v-text-field v-else v-for="(schema, key) in configSchema" :key="key" v-model="configValues[key]" :label="schema.label || schema.title || key" variant="underlined" />
    </v-card-text>
    <v-card-actions><v-spacer /><v-btn v-if="!loadError" color="accent" @click="saveConfig" :loading="saveLoading">Save</v-btn><span v-if="saveResult">{{ saveResult }}</span></v-card-actions>
  </v-card>
</template>

<script setup lang="ts">
import { useApi } from '~/lib/api';
import type { FieldDefinition, JsonObject } from '@lovecards/sdk';
const client = useApi();
const route = useRoute();
const loading = ref(true);
const saveLoading = ref(false);
const saveResult = ref('');
const loadError = ref('');
const configSchema = ref<Record<string, FieldDefinition>>({});
const configValues = ref<JsonObject>({});
const configTheme = ref<{ id: string; release: string }>({ id: '', release: '' });

onMounted(async () => {
  try {
    const result = await client.theme.config();
    configSchema.value = result.config_schema || {};
    configValues.value = result.config_values || {};
    configTheme.value = { id: result.id, release: result.release };
  } catch (error) {
    // A clean installation may not have an active theme yet. Keep the
    // management page usable and show an actionable empty state instead of
    // leaking an unhandled promise rejection into the browser.
    loadError.value = error instanceof Error ? error.message : 'Theme configuration is unavailable';
  } finally { loading.value = false; }
});

const saveConfig = async () => {
  saveLoading.value = true; saveResult.value = '';
  try {
    await client.theme.updateConfig({ id: configTheme.value.id, release: configTheme.value.release, values: configValues.value });
    saveResult.value = 'Saved';
  } catch { saveResult.value = 'Save failed'; }
  finally { saveLoading.value = false; }
};
</script>
