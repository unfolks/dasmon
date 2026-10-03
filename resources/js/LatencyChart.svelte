<script>
    let { range = '1', warehouseIndex = 0 } = $props();
    let hoveredPoint = $state(null);
    const series = [
        { name: 'ISP 1', color: '#2563eb', base: 30, amplitude: 9 },
        { name: 'ISP 2', color: '#27a16b', base: 24, amplitude: 7 },
        { name: 'Avg Access Point', color: '#ed932e', base: 49, amplitude: 13 },
    ];

    let points = $derived(
        series.map((item, seriesIndex) =>
            Array.from({ length: 61 }, (_, index) => {
                const wave =
                    Math.sin(index * 1.7 + seriesIndex * 3 + warehouseIndex) * item.amplitude;
                const secondaryWave =
                    Math.cos(index * 0.73 + Number(range)) * item.amplitude * 0.45;
                const spike = seriesIndex === 2 && index === 41 ? 75 : 0;
                const value = Math.round(item.base + wave + secondaryWave + spike);

                return { x: 58 + (index / 60) * 612, y: 176 - (value / 200) * 152, value };
            }),
        ),
    );

    let tickLabels = $derived(
        range === '24'
            ? ['14:30', '18:30', '22:30', '02:30', '06:30', '10:30', '14:30']
            : range === '6'
              ? ['08:30', '09:30', '10:30', '11:30', '12:30', '13:30', '14:30']
              : ['13:30', '13:40', '13:50', '14:00', '14:10', '14:20', '14:30'],
    );

    function inspectPoint(event) {
        const bounds = event.currentTarget.getBoundingClientRect();
        const chartPosition = ((event.clientX - bounds.left) / bounds.width) * 700;
        hoveredPoint = Math.max(0, Math.min(60, Math.round(((chartPosition - 58) / 612) * 60)));
    }
</script>

<div class="latency-plot">
    <svg
        viewBox="0 0 700 210"
        role="img"
        aria-label={`Demo network latency over the last ${range} hours. ISP 1 around 30 milliseconds, ISP 2 around 24, access points around 49.`}
        onpointermove={inspectPoint}
        onpointerleave={() => (hoveredPoint = null)}
    >
        {#each [200, 150, 100, 50, 0] as value}
            <line
                x1="58"
                x2="670"
                y1={176 - (value / 200) * 152}
                y2={176 - (value / 200) * 152}
                stroke="#e7ebf1"
            />
            <text
                x="47"
                y={180 - (value / 200) * 152}
                text-anchor="end"
                fill="#8390a3"
                font-size="12">{value} ms</text
            >
        {/each}
        {#each tickLabels as label, index}
            <line x1={58 + index * 102} x2={58 + index * 102} y1="24" y2="176" stroke="#edf0f5" />
            <text x={58 + index * 102} y="200" text-anchor="middle" fill="#8390a3" font-size="12"
                >{label}</text
            >
        {/each}
        {#each series as item, index}
            <polyline
                points={points[index].map((point) => `${point.x},${point.y}`).join(' ')}
                stroke={item.color}
                stroke-width="2"
                fill="none"
                stroke-linejoin="round"
            />
        {/each}
        {#if hoveredPoint !== null}
            <line
                x1={points[0][hoveredPoint].x}
                x2={points[0][hoveredPoint].x}
                y1="24"
                y2="176"
                stroke="#a6b2c3"
                stroke-dasharray="4 4"
            />
            {#each series as item, index}
                <circle
                    cx={points[index][hoveredPoint].x}
                    cy={points[index][hoveredPoint].y}
                    r="4"
                    fill={item.color}
                    stroke="white"
                    stroke-width="2"
                />
            {/each}
        {/if}
    </svg>
    <div class="chart-legend" aria-live="polite">
        {#each series as item, index}
            <span
                ><i style:background={item.color}></i>{item.name}{#if hoveredPoint !== null}<strong
                        >{points[index][hoveredPoint].value} ms</strong
                    >{/if}</span
            >
        {/each}
    </div>
</div>
