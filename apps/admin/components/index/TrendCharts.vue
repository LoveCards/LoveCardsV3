<template>
	<Line id="my-chart-id" :options="chartOptions" :data="chartData" style="margin: 0px 16px 16px;" />
</template>

<script setup lang="ts">
import { Line } from 'vue-chartjs'
import { Chart as ChartJS, Title, Tooltip, Legend, LineElement, CategoryScale, LinearScale, PointElement, Filler } from 'chart.js'
import { useDashboardStore } from '~/stores/dashboardStore';
const dashboardStore = useDashboardStore();

//图表
ChartJS.register(Title, Tooltip, Legend, LineElement, CategoryScale, LinearScale, PointElement, Filler)
let chartData = ref({
	labels: [],
	datasets: [] as any,
});
let chartOptions = {
	responsive: true, // 响应式
	plugins: {
		filler: {
			propagate: false,
		},
	},
}

//Props
const props = defineProps({
	chartData: Object,
});

//处理数据
const getDatasets = (jsonData: any) => {
	let ColorArray = ['33,150,243', '253,216,53', '229,57,35']
	let datasets = []

	for (let i = 0; i < jsonData.length; i++) {
		datasets.push({
			label: jsonData[i].label,
			data: jsonData[i].data.y,
			borderColor: 'rgba(' + ColorArray[i] + ',1)',
			backgroundColor: 'rgba(' + ColorArray[i] + ',0.5)',
			fill: true,
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
	if (dashboardStore.dashboard !== null) {
		chartData.value = {
			labels: dashboardStore.dashboard.chart[0].data.x,
			datasets: getDatasets(dashboardStore.dashboard.chart)
		}
	}
}

//加载数据
onMounted(() => {
	setChartData();
})
</script>