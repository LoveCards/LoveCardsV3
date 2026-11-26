<template>
  <div>
    <div v-if="rows.length" class="workbench-chart">
      <ClientOnly>
        <Line :data="chartData" :options="chartOptions" role="img" :aria-label="`${title}，${range}；每日数值见下方数据表`" />
        <template #fallback><v-skeleton-loader type="image" /></template>
      </ClientOnly>
    </div>
    <p v-else class="text-body-2 text-medium-emphasis py-8 text-center">暂无可用趋势数据</p>
    <v-expansion-panels v-if="rows.length" variant="accordion" flat class="mt-3">
      <v-expansion-panel>
        <v-expansion-panel-title min-height="44">查看每日数据</v-expansion-panel-title>
        <v-expansion-panel-text>
          <v-table density="compact">
            <caption class="text-left text-body-2 pb-2">{{ title }} · 单位：{{ unit }}</caption>
            <thead><tr><th scope="col">日期</th><th scope="col" class="text-right">数量</th></tr></thead>
            <tbody><tr v-for="row in rows" :key="row.date"><th scope="row">{{ row.date }}</th><td class="text-right">{{ row.value === null ? '未提供' : row.value.toLocaleString('zh-CN') }}</td></tr></tbody>
          </v-table>
        </v-expansion-panel-text>
      </v-expansion-panel>
    </v-expansion-panels>
  </div>
</template>

<script setup lang="ts">
import type { ChartDataset } from '@lovecards/sdk'
import { useTheme } from 'vuetify'
import { Line } from 'vue-chartjs'
import { Chart as ChartJS, Tooltip, CategoryScale, LinearScale, PointElement, LineElement, type ChartOptions } from 'chart.js'

const props = defineProps<{ dataset: ChartDataset | null; unit: string; title: string; range: string }>()
ChartJS.register(Tooltip, CategoryScale, LinearScale, PointElement, LineElement)
const theme = useTheme()
// 图表本身不播放动画，刷新、指标切换和 Reduced Motion 均保持稳定。
const rows = computed(() => (props.dataset?.data.x ?? []).map((date, index) => {
  const value = props.dataset?.data.y[index]
  return { date, value: typeof value === 'number' && Number.isFinite(value) ? value : null }
}))
const chartData = computed(() => ({
  labels: rows.value.map(row => /^\d{4}-/.test(row.date) ? row.date.slice(5) : row.date),
  datasets: [{
    label: props.title,
    data: rows.value.map(row => row.value),
    borderColor: theme.current.value.colors.primary,
    backgroundColor: theme.current.value.colors.primary,
    borderWidth: 2,
    pointRadius: 3,
    pointHoverRadius: 5,
    pointHitRadius: 12,
    tension: 0,
    spanGaps: false,
    fill: false,
  }],
}))
const scale = computed(() => {
  const maximum = Math.max(0, ...rows.value.map(row => row.value ?? 0))
  const raw = Math.max(1, maximum / 4)
  const power = 10 ** Math.floor(Math.log10(raw))
  const step = Math.max(1, ([1, 2, 5, 10].find(value => value * power >= raw) ?? 10) * power)
  return { step, max: Math.max(step, Math.ceil(maximum / step) * step) }
})
const chartOptions = computed<ChartOptions<'line'>>(() => ({
  responsive: true,
  maintainAspectRatio: false,
  animation: false,
  interaction: { mode: 'index', intersect: false },
  plugins: { legend: { display: false }, tooltip: { callbacks: { label: context => `${context.parsed.y ?? '未提供'} ${props.unit}` } } },
  scales: {
    x: { grid: { display: false }, border: { display: false }, ticks: { color: theme.current.value.colors['on-surface'], maxRotation: 0, autoSkip: true, maxTicksLimit: 6 } },
    y: { min: 0, max: scale.value.max, border: { display: false }, grid: { color: theme.current.value.colors.outline }, ticks: { color: theme.current.value.colors['on-surface'], stepSize: scale.value.step, precision: 0 } },
  },
}))
</script>

<style scoped>
.workbench-chart { position: relative; height: 220px; min-width: 0; }
@media (max-width: 680px) { .workbench-chart { height: 190px; } }
</style>
