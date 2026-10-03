<script>
    let { name, size = 20, stroke = 1.8, ...attributes } = $props();

    const paths = {
        dashboard: ['M3 10 12 3l9 7', 'M5 9v12h5v-7h4v7h5V9'],
        camera: ['m3 4 14 3-2 10-12-3z', 'm16 9 5-2v8l-6-3', 'M7 15v5h6', 'M5 20h10'],
        globe: [
            'M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0',
            'M3 12h18',
            'M5 6h14M5 18h14',
            'M12 3c5 5 5 13 0 18-5-5-5-13 0-18',
        ],
        computer: ['M4 3h16v13H4z', 'M2 20h20', 'M9 16v4m6-4v4'],
        wifi: [
            'M2 8a16 16 0 0 1 20 0',
            'M5 12a11 11 0 0 1 14 0',
            'M8 16a6 6 0 0 1 8 0',
            'M12 20h.01',
        ],
        server: ['M4 3h16v7H4zM4 14h16v7H4z', 'M7 6.5h.01M7 17.5h.01M14 6.5h3M14 17.5h3'],
        temperature: ['M9 14.5V5a3 3 0 0 1 6 0v9.5a5 5 0 1 1-6 0', 'M12 8v10', 'M18 6h2M18 10h2'],
        battery: ['M8 4V2h8v2', 'M5 4h14v18H5z', 'M9 13h6M12 10v6'],
        report: ['M5 3h9l5 5v13H5z', 'M14 3v6h5', 'M8 13h8M8 17h5'],
        settings: [
            'm9 3-1 3-3 1-2 3 2 2-1 3 2 3 3-1 2 2 3-1 1-3 3-1 2-3-2-2 1-3-2-3-3 1-2-2z',
            'M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0',
        ],
        chevron: ['m9 5 7 7-7 7'],
        down: ['m6 9 6 6 6-6'],
        pin: [
            'M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0',
            'M15 10a3 3 0 1 1-6 0 3 3 0 0 1 6 0',
        ],
        bell: ['M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9', 'M10 21h4'],
        chart: ['m3 17 5-6 4 3 7-9', 'M15 5h4v4'],
        search: ['M17 10a7 7 0 1 1-14 0 7 7 0 0 1 14 0', 'm15 15 6 6'],
        eye: ['M2 12s4-7 10-7 10 7 10 7-4 7-10 7-10-7-10-7', 'M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0'],
        close: ['m6 6 12 12M18 6 6 18'],
        refresh: ['M20 7a9 9 0 1 0 1 8', 'M20 3v5h-5'],
        download: ['M12 3v12m-5-5 5 5 5-5', 'M4 16v5h16v-5'],
        play: ['m9 5 11 7-11 7z'],
        stop: ['M6 6h12v12H6z'],
        check: ['m5 12 4 4L19 6'],
        menu: ['M3 6h18M3 12h18M3 18h18'],
        signalOff: ['m2 2 20 20', 'M5 5h14v14H5z', 'm19 9 4-2v10l-4-2'],
        clock: ['M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0', 'M12 7v5l3 2'],
        shield: ['m12 2 8 4v6c0 5-8 10-8 10S4 17 4 12V6z', 'm8 12 3 3 5-6'],
    };
</script>

<svg
    width={size}
    height={size}
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width={stroke}
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
    {...attributes}
>
    {#each paths[name] ?? paths.computer as path}
        <path d={path} />
    {/each}
</svg>
