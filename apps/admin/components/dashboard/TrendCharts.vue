<template>
  <div>
    <div class="d-flex flex-wrap ga-2 mb-3" role="group" aria-label="趋势图例">
      <v-btn v-for="series in seriesList" :key="series.key" size="small" variant="text" :class="{ 'text-medium-emphasis': hiddenKeys.includes(series.key) }" :aria-pressed="!hiddenKeys.includes(series.key)" @click="toggleSeries(series.key)"><template #prepend><v-icon :icon="hiddenKeys.includes(series.key) ? 'mdi-eye-off-outline' : series.icon" :color="hiddenKeys.includes(series.key) ? undefined : series.color" /></template>{{ series.label }}（{{ series.unit }}）</v-btn>
    </div>
    <div v-if="dates.length && visibleSeries.length" class="workbench-chart">
      <ClientOnly>
        <Line :data="chartData" :options="chartOptions" role="img" :aria-label="`${title}，${range}；每日数值见下方数据表`" />
        <template #fallback><v-skeleton-loader type="image" /></template>
      </ClientOnly>
    </div>
    <p v-else class="text-body-2 text-medium-emphasis py-8 text-center">{{ dates.length ? '请从图例选择要显示的趋势' : '暂无可用趋势数据' }}</p>
    <v-expansion-panels v-if="dates.length" variant="accordion" flat class="mt-3">
      <v-expansion-panel>
        <v-expansion-panel-title min-height="44">查看每日数据</v-expansion-panel-title>
        <v-expansion-panel-text>
          <v-table density="compact">
            <caption class="text-left text-body-2 pb-2">{{ title }}</caption>
            <thead><tr><th scope="col">日期</th><th v-for="series in seriesList" :key="series.key" scope="col" class="text-right">{{ series.label }}（{{ series.unit }}）</th></tr></thead>
            <tbody><tr v-for="date in dates" :key="date"><th scope="row">{{ date }}</th><td v-for="series in seriesList" :key="series.key" class="text-right">{{ valueAt(series, date)?.toLocaleString('zh-CN') ?? '未提供' }}</td></tr></tbody>
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

type TrendSeries = ChartDataset & { key: string; unit: string }
const props = defineProps<{ series: TrendSeries[]; title: string; range: string }>()
ChartJS.register(Tooltip, CategoryScale, LinearScale, PointElement, LineElement)
const theme = useTheme()
const hiddenKeys = ref<string[]>([])
const presentation: Record<string, { color: string; dash: number[]; icon: string }> = {
  cards: { color: 'primary', dash: [], icon: 'mdi-minus' },
  comments: { color: 'info', dash: [6, 4], icon: 'mdi-minus-thick' },
  good: { color: 'success', dash: [2, 3], icon: 'mdi-dots-horizontal' },
}
const seriesList = computed(() => props.series.map(series => ({ ...series, ...(presentation[series.key] ?? presentation.cards!) })))
const visibleSeries = computed(() => seriesList.value.filter(series => !hiddenKeys.value.includes(series.key)))
// 按日期对齐三条真实序列；缺失点保持 null，不补零或跨缺失点连接。
const dates = computed(() => [...new Set(props.series.flatMap(series => series.data.x))])
function valueAt(series: TrendSeries, date: string) {
  const index = series.data.x.indexOf(date)
  const value = index < 0 ? undefined : series.data.y[index]
  return typeof value === 'number' && Number.isFinite(value) ? value : null
}
function toggleSeries(key: string) {
  hiddenKeys.value = hiddenKeys.value.includes(key) ? hiddenKeys.value.filter(item => item !== key) : [...hiddenKeys.value, key]
}
const chartData = computed(() => ({
  labels: dates.value.map(date => /^\d{4}-/.test(date) ? date.slice(5) : date),
  datasets: visibleSeries.value.map(series => ({
    label: `${series.label}（${series.unit}）`,
    data: dates.value.map(date => valueAt(series, date)),
    borderColor: theme.current.value.colors[series.color],
    backgroundColor: theme.current.value.colors[series.color],
    borderDash: series.dash,
    borderWidth: 2,
    pointRadius: 3,
    pointHoverRadius: 5,
    pointHitRadius: 12,
    tension: 0,
    spanGaps: false,
    fill: false,
  })),
}))
const scale = computed(() => {
  const maximum = Math.max(0, ...visibleSeries.value.flatMap(series => dates.value.map(date => valueAt(series, date) ?? 0)))
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
  plugins: { legend: { display: false }, tooltip: { callbacks: { label: context => `${context.dataset.label}：${context.parsed.y ?? '未提供'}` } } },
  scales: {
    x: { grid: { display: false }, border: { display: false }, ticks: { color: theme.current.value.colors['on-surface'], maxRotation: 0, autoSkip: true, maxTicksLimit: 6 } },
    y: { min: 0, max: scale.value.max, border: { display: false }, grid: { color: theme.current.value.colors.outline }, ticks: { color: theme.current.value.colors['on-surface'], stepSize: scale.value.step, precision: 0 } },
  },
}))
</script>

<style scoped>
.workbench-chart { position: relative; height: 320px; min-width: 0; }
@media (max-width: 680px) { .workbench-chart { height: 260px; } }
</style>
