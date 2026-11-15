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

// Mirrors the semantic tokens in app.css (ECharts cannot read CSS variables).
const palettes = {
    light: {
        text: '#5d6572',
        line: '#e6e9ef',
        income: '#0f8a5f',
        expense: '#c2352f',
        balance: '#1f3a5f',
        tooltipBg: '#ffffff',
        tooltipText: '#15181e',
    },
    dark: {
        text: '#98a1ae',
        line: '#2a303b',
        income: '#4fd1a1',
        expense: '#ff8d86',
        balance: '#8fb4e8',
        tooltipBg: '#1c222c',
        tooltipText: '#e8ebf0',
    },
};

const option = computed(() => {
    const c = palettes[resolvedAppearance.value === 'dark' ? 'dark' : 'light'];

    return {
        backgroundColor: 'transparent',
        textStyle: { fontFamily: 'Instrument Sans, system-ui, sans-serif' },
        tooltip: {
            trigger: 'axis',
            backgroundColor: c.tooltipBg,
            borderColor: c.line,
            textStyle: { color: c.tooltipText },
            extraCssText:
                'border-radius:10px;box-shadow:0 8px 24px rgba(15,23,42,.15);',
            valueFormatter: (value: number) => formatMoney(value),
        },
        legend: {
            data: ['Money in', 'Money out', 'Running balance'],
            bottom: 0,
            icon: 'roundRect',
            itemWidth: 10,
            itemHeight: 10,
            textStyle: { color: c.text },
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
            axisLabel: { hideOverlap: true, color: c.text },
            axisLine: { lineStyle: { color: c.line } },
            axisTick: { show: false },
        },
        yAxis: [
            {
                type: 'value',
                axisLabel: {
                    color: c.text,
                    formatter: (value: number) =>
                        `${new Intl.NumberFormat('en-US', {
                            notation: 'compact',
                            maximumFractionDigits: 0,
                        }).format(value / 100)} DA`,
                },
                splitLine: { lineStyle: { color: c.line, type: 'dashed' } },
            },
        ],
        series: [
            {
                name: 'Money in',
                type: 'bar',
                data: props.points.map((point) => point.inflow),
                itemStyle: { color: c.income, borderRadius: [4, 4, 0, 0] },
                barMaxWidth: 18,
            },
            {
                name: 'Money out',
                type: 'bar',
                data: props.points.map((point) => point.outflow),
                itemStyle: { color: c.expense, borderRadius: [4, 4, 0, 0] },
                barMaxWidth: 18,
            },
            {
                name: 'Running balance',
                type: 'line',
                data: props.points.map((point) => point.balance),
                smooth: true,
                symbol: 'circle',
                symbolSize: 7,
                lineStyle: { width: 2.5, color: c.balance },
                itemStyle: {
                    color: c.balance,
                    borderColor: c.tooltipBg,
                    borderWidth: 2,
                },
                areaStyle: {
                    color: {
                        type: 'linear',
                        x: 0,
                        y: 0,
                        x2: 0,
                        y2: 1,
                        colorStops: [
                            { offset: 0, color: `${c.balance}33` },
                            { offset: 1, color: `${c.balance}00` },
                        ],
                    },
                },
            },
        ],
    };
});
</script>

<template>
    <!-- The wrapper owns the height: vue-echarts sets its own height:100%, which would
         otherwise grow with the canvas and never settle. -->
    <div class="h-80 w-full min-w-0">
        <VChart class="h-full w-full" :option="option" autoresize />
    </div>
</template>
