(function (Drupal, once) {
  'use strict';

  const instances = new WeakMap();
  const colors = { income: '#118AB2', expense: '#FF7F50' };

  Drupal.behaviors.villaExpenseChart = {
    attach(context) {
      if (typeof window.Chart !== 'function') {
        return;
      }
      once('villa-expense-pie', '[data-expense-pie]', context).forEach((canvas) => {
        const data = JSON.parse(canvas.dataset.expensePie);
        const values = data.amounts.map(Number);
        if (!values.some((value) => Number.isFinite(value) && value > 0)) {
          return;
        }
        const palette = values.map(() => colors[data.type]);
        const card = canvas.closest('.expense-chart');
        const visual = canvas.closest('.expense-chart__visual');
        const total = values.reduce((sum, value) => sum + value, 0);
        card.querySelectorAll('tbody tr').forEach((row, index) => {
          row.style.setProperty('--expense-slice-color', palette[index]);
        });
        visual.hidden = false;
        const chart = new window.Chart(canvas, {
          type: 'pie',
          plugins: [window.ChartDataLabels],
          data: {
            labels: data.labels,
            datasets: [{
              data: values,
              backgroundColor: palette,
              borderColor: '#fff',
              borderWidth: 2,
              hoverOffset: 8,
            }],
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: window.matchMedia('(prefers-reduced-motion: reduce)').matches
              ? false : { duration: 350 },
            layout: { padding: 10 },
            plugins: {
              datalabels: {
                display: 'auto',
                color: '#20232a',
                backgroundColor: 'rgba(255, 255, 255, 0.94)',
                borderRadius: 3,
                padding: 3,
                font: { size: 11, weight: 'bold' },
                formatter(value, context) {
                  return [data.formatted[context.dataIndex], data.currency];
                },
              },
              legend: { display: false },
              tooltip: {
                callbacks: {
                  label(item) {
                    const index = item.dataIndex;
                    const percent = new Intl.NumberFormat(document.documentElement.lang || 'en', {
                      style: 'percent', maximumFractionDigits: 1,
                    }).format(values[index] / total);
                    return `${data.labels[index]}: ${data.formatted[index]} ${data.currency} (${percent})`;
                  },
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
      once.remove('villa-expense-pie', '[data-expense-pie]', context).forEach((canvas) => {
        const chart = instances.get(canvas);
        if (chart) {
          chart.destroy();
          instances.delete(canvas);
        }
        canvas.closest('.expense-chart__visual').hidden = true;
      });
    },
  };
})(Drupal, once);
