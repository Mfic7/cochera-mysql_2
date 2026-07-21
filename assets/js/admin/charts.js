// Paleta validada (ver skill dataviz): categórico slots 1-3 para identidad de método de pago,
// azul secuencial (slot 1) para la magnitud de ingresos. Nunca se reordenan por rango.
const CHART_COLORS = {
    blue: '#2a78d6',
    orange: '#eb6834',
    aqua: '#1baf7a',
    ink: '#52514e',
    grid: '#e1e0d9',
};

let ingresosChart = null;
let metodosChart = null;

function renderIngresosChart(canvas, labels, data) {
    if (ingresosChart) ingresosChart.destroy();
    ingresosChart = new Chart(canvas, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                data,
                backgroundColor: CHART_COLORS.blue,
                borderRadius: 4,
                maxBarThickness: 26,
            }],
        },
        options: {
            plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => `S/ ${c.parsed.y.toFixed(2)}` } } },
            scales: {
                x: { grid: { display: false }, ticks: { color: CHART_COLORS.ink, font: { size: 11 } } },
                y: { grid: { color: CHART_COLORS.grid }, ticks: { color: CHART_COLORS.ink, font: { size: 11 }, callback: (v) => 'S/ ' + v } },
            },
        },
    });
}

function renderMetodosChart(canvas, legendEl, rows) {
    const colorByTipo = { yape: CHART_COLORS.blue, plin: CHART_COLORS.orange, transferencia: CHART_COLORS.aqua };
    const labelByTipo = { yape: 'Yape', plin: 'Plin', transferencia: 'Transferencia' };
    const orden = ['yape', 'plin', 'transferencia'];
    const ordenadas = orden.map((tipo) => rows.find((r) => r.metodo === tipo) || { metodo: tipo, total: 0 });
    const total = ordenadas.reduce((s, r) => s + Number(r.total), 0) || 1;

    if (metodosChart) metodosChart.destroy();
    metodosChart = new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels: ordenadas.map((r) => labelByTipo[r.metodo]),
            datasets: [{
                data: ordenadas.map((r) => Number(r.total)),
                backgroundColor: ordenadas.map((r) => colorByTipo[r.metodo]),
                borderWidth: 2,
                borderColor: '#fcfcfb',
            }],
        },
        options: { plugins: { legend: { display: false } }, cutout: '65%' },
    });

    legendEl.innerHTML = ordenadas.map((r) => {
        const pct = Math.round((Number(r.total) / total) * 100);
        return `<div class="list-row"><span><i class="dot" style="background:${colorByTipo[r.metodo]};width:9px;height:9px;border-radius:50%;display:inline-block;margin-right:6px"></i>${labelByTipo[r.metodo]}</span><strong>S/ ${Number(r.total).toFixed(2)} (${pct}%)</strong></div>`;
    }).join('');
}
