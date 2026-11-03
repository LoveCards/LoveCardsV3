<template>
	<v-sheet class="ma-4" color="transparent">
		<Line id="my-chart-id" :options="chartOptions" :data="chartData" />
	</v-sheet>
</template>

<script setup lang="ts">
import { Line } from 'vue-chartjs'
import { Chart as ChartJS, Title, Tooltip, Legend, LineElement, CategoryScale, LinearScale, PointElement, Filler } from 'chart.js'
import { useDashboardStore } from '~/stores/dashboardStore';
import type { ChartDataset } from '@lovecards/sdk';
const dashboardStore = useDashboardStore();
const theme = useTheme();

//图表
ChartJS.register(Title, Tooltip, Legend, LineElement, CategoryScale, LinearScale, PointElement, Filler)
type LineDataset = {
	label: string
	data: number[]
	borderColor: string
	backgroundColor: string
	fill: boolean
	tension: number
	pointRadius: number
	pointHoverRadius: number
}

const chartData = ref<{ labels: string[]; datasets: LineDataset[] }>({
	labels: [],
	datasets: [],
});
let chartOptions = {
	responsive: true, // 响应式
	plugins: {
		filler: {
			propagate: false,
		},
	},
}

//处理数据
const getDatasets = (jsonData: ChartDataset[]): LineDataset[] => {
	const colors = [
		theme.current.value.colors.info,
		theme.current.value.colors.warning,
		theme.current.value.colors.error,
	]
	const datasets: LineDataset[] = []

	for (let i = 0; i < jsonData.length; i++) {
		const dataset = jsonData[i]
		if (!dataset) continue
		datasets.push({
			label: dataset.label,
			data: dataset.data.y,
			borderColor: colors[i] ?? theme.current.value.colors.primary,
			backgroundColor: 'transparent',
			fill: false,
			tension: 0.4,
			pointRadius: 5,
			pointHoverRadius: 10,
		})
	}
	return datasets
}

//获取数据
const setChartData = () => {
	//await dashboardStore.init();
	const chart = dashboardStore.dashboard?.chart
	if (chart && chart.length > 0) {
		const first = chart[0]
		if (!first) return
		chartData.value = {
			labels: first.data.x,
			datasets: getDatasets(chart)
		}
	}
}

//加载数据
onMounted(() => {
	setChartData();
})
</script>
