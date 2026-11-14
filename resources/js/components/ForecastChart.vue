<script setup lang="ts">
import { BarChart, LineChart } from 'echarts/charts';
import {
    GridComponent,
    LegendComponent,
    TooltipComponent,
} from 'echarts/components';
import { use } from 'echarts/core';
import { CanvasRenderer } from 'echarts/renderers';
import { computed } from 'vue';
import VChart from 'vue-echarts';
import { useAppearance } from '@/composables/useAppearance';
import { formatMoney } from '@/lib/format';

use([
    CanvasRenderer,
    BarChart,
    LineChart,
    GridComponent,
    LegendComponent,
    TooltipComponent,
]);

export type ForecastPoint = {
    label: string;
    inflow: number;
    outflow: number;
    balance: number;
};

const props = defineProps<{
    points: ForecastPoint[];
}>();

const { resolvedAppearance } = useAppearance();

const option = computed(() => ({
    tooltip: {
        trigger: 'axis',
        valueFormatter: (value: number) => formatMoney(value),
    },
    legend: {
        data: ['Money in', 'Money out', 'Running balance'],
        bottom: 0,
    },
    grid: {
        top: 24,
        left: 8,
        right: 8,
        bottom: 40,
        containLabel: true,
    },
    xAxis: {
        type: 'category',
        data: props.points.map((point) => point.label),
        axisLabel: { hideOverlap: true },
    },
    yAxis: [
        {
            type: 'value',
            axisLabel: {
                formatter: (value: number) =>
                    `${new Intl.NumberFormat('en-US', {
                        notation: 'compact',
                        maximumFractionDigits: 0,
                    }).format(value / 100)} DA`,
            },
        },
    ],
    series: [
        {
            name: 'Money in',
            type: 'bar',
            data: props.points.map((point) => point.inflow),
            itemStyle: { color: '#16a34a' },
            barMaxWidth: 18,
        },
        {
            name: 'Money out',
            type: 'bar',
            data: props.points.map((point) => point.outflow),
            itemStyle: { color: '#dc2626' },
            barMaxWidth: 18,
        },
        {
            name: 'Running balance',
            type: 'line',
            data: props.points.map((point) => point.balance),
            smooth: true,
            symbol: 'circle',
            symbolSize: 6,
            lineStyle: { width: 2 },
            itemStyle: { color: '#2563eb' },
        },
    ],
}));
</script>

<template>
    <VChart
        class="h-80 w-full"
        :option="option"
        :theme="resolvedAppearance === 'dark' ? 'dark' : undefined"
        autoresize
    />
</template>
