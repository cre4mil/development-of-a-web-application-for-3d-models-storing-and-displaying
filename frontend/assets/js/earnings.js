/** Creator earnings chart (Chart.js is loaded on demand). */
(() => {
  'use strict';

  const source = document.getElementById('chartData');
  if (!source) return;

  const CHART = ['https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js', 'sha384-e6nUZLBkQ86NJ6TVVKAeSaK8jWa3NhkYWZFomE39AvDbQWeie9PlQqM3pmYW5d1g'];
  const monthly = JSON.parse(source.textContent);
  let drawn = false;

  const draw = async () => {
    if (drawn) return;
    drawn = true;
    await App.loadScript(...CHART);
    const labels = monthly.map(({ month }) => {
      const [year, number] = month.split('-');
      return new Date(year, number - 1).toLocaleDateString('th-TH', { month: 'short', year: '2-digit' });
    });
    new Chart(document.getElementById('earningsChart'), {
      data: {
        labels,
        datasets: [
          { type: 'bar', label: 'รายได้ (฿)', data: monthly.map((m) => m.earning), backgroundColor: 'rgba(8,145,178,.65)', borderRadius: 8, yAxisID: 'y' },
          { type: 'line', label: 'จำนวนรายการ', data: monthly.map((m) => m.sales), borderColor: '#7c3aed', backgroundColor: 'rgba(124,58,237,.1)', tension: 0.35, fill: true, yAxisID: 'y1' },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        scales: {
          y: { beginAtZero: true, ticks: { callback: (value) => `฿${Number(value).toLocaleString()}` } },
          y1: { position: 'right', beginAtZero: true, grid: { drawOnChartArea: false }, ticks: { precision: 0 } },
        },
      },
    });
  };
  draw();
})();
