(function (Drupal, once) {
  'use strict';

  const instances = new WeakMap();
  const colors = { income: '#118AB2', expense: '#FF7F50' };

  Drupal.behaviors.villaBalanceCharts = {
    attach(context) {
      if (typeof window.Chart !== 'function') {
        return;
      }
      once('villa-balance-chart', '[data-balance-chart]', context).forEach((canvas) => {
        const data = JSON.parse(canvas.dataset.balanceChart);
        if (!data.labels.length) {
          return;
        }
        const pie = data.kind === 'pie';
        if (pie && !data.datasets[0].values.some((value) => Number(value) > 0)) {
          return;
        }
        const compact = new Intl.NumberFormat(document.documentElement.lang || 'en', {
          notation: 'compact', maximumFractionDigits: 1,
        });
        const datasets = data.datasets.map((series, index) => {
          const values = series.values.map(Number);
          return {
            label: series.label,
            data: values,
            backgroundColor: pie ? [colors.income, colors.expense]
              : (index === 0 ? colors.income : colors.expense),
            borderColor: pie ? '#fff' : (index === 0 ? colors.income : colors.expense),
            borderWidth: pie ? 2 : 1,
            borderRadius: pie ? 0 : 4,
            maxBarThickness: 52,
          };
        });
        canvas.closest('.balance-charts__visual').hidden = false;
        const chart = new window.Chart(canvas, {
          type: pie ? 'pie' : 'bar',
          plugins: [window.ChartDataLabels],
          data: { labels: data.labels, datasets },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            layout: { padding: pie ? 12 : { top: 60, right: 20, left: 20 } },
            animation: window.matchMedia('(prefers-reduced-motion: reduce)').matches
              ? false : { duration: 350 },
            interaction: { mode: pie ? 'nearest' : 'index', intersect: pie },
            plugins: {
              datalabels: {
                display: pie ? 'auto' : true,
                color: '#20232a',
                backgroundColor: 'rgba(255, 255, 255, 0.94)',
                borderRadius: 3,
                padding: 3,
                font: { size: 11, weight: 'bold' },
                anchor: pie ? 'center' : 'end',
                align: pie ? 'center' : 'end',
                rotation: pie ? 0 : -60,
                formatter(value, context) {
                  const amount = data.datasets[context.datasetIndex].formatted[context.dataIndex];
                  return pie ? [amount, data.currency] : amount;
                },
              },
              legend: {
                position: 'bottom',
                onClick: null,
                labels: pie ? {
                  generateLabels(chart) {
                    return window.Chart.overrides.pie.plugins.legend.labels.generateLabels(chart).map((item) => ({
                      ...item,
                      text: `${data.labels[item.index]}: ${data.datasets[0].formatted[item.index]} ${data.currency}`,
                    }));
                  },
                } : {},
              },
              tooltip: {
                callbacks: {
                  label(item) {
                    const series = data.datasets[item.datasetIndex];
                    const label = pie ? data.labels[item.dataIndex] : series.label;
                    return `${label}: ${series.formatted[item.dataIndex]} ${data.currency}`;
                  },
                },
              },
            },
            scales: pie ? {} : {
              x: {
                grid: { display: false },
                ticks: { autoSkip: true, maxTicksLimit: 8, maxRotation: 45 },
              },
              y: {
                beginAtZero: true,
                grace: '15%',
                title: { display: true, text: data.currency },
                ticks: { callback: (value) => compact.format(value) },
                grid: {
                  color: (item) => item.tick.value === 0 ? '#8e929b' : '#eceef1',
                },
              },
            },
          },
        });
        instances.set(canvas, chart);
      });
    },
    detach(context, settings, trigger) {
      if (trigger !== 'unload') {
        return;
      }
      once.remove('villa-balance-chart', '[data-balance-chart]', context).forEach((canvas) => {
        const chart = instances.get(canvas);
        if (chart) {
          chart.destroy();
          instances.delete(canvas);
        }
        canvas.closest('.balance-charts__visual').hidden = true;
      });
    },
  };
})(Drupal, once);
