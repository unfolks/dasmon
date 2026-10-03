<script>
    import { onMount } from 'svelte';
    import Icon from '../MonitoringIcon.svelte';
    import LatencyChart from '../LatencyChart.svelte';
    import { navigation, warehouses, buildInventory, buildAlerts } from '../monitoring-data.js';

    let { appName } = $props();
    let activeSection = $state('dashboard');
    let warehouseId = $state('krian');
    let search = $state('');
    let statusFilter = $state('all');
    let page = $state(1);
    let selectedDeviceId = $state('CCTV-02');
    let detailTab = $state('Live View');
    let chartRange = $state('1');
    let mobileMenuOpen = $state(false);
    let updatedAt = $state('30 Sep 2026 14:25:32');
    let toastMessage = $state('');
    let temperatureThreshold = $state(35);
    let defaultWarehouse = $state('krian');
    let toastTimer;
    const pageSize = 10;

    let warehouse = $derived(warehouses.find((item) => item.id === warehouseId) ?? warehouses[0]);
    let inventory = $derived(buildInventory(warehouse, temperatureThreshold));
    let alerts = $derived(buildAlerts(warehouse));
    let currentNavigation = $derived(
        navigation.find((item) => item.id === activeSection) ?? navigation[0],
    );
    let cameraOnline = $derived(inventory.cctv.filter((item) => item.status === 'Online').length);
    let computerOnline = $derived(
        inventory.computers.filter((item) => item.status === 'Online').length,
    );
    let accessNormal = $derived(inventory.access.filter((item) => item.status === 'Online').length);
    let currentRows = $derived(inventory[activeSection] ?? []);
    let filteredRows = $derived(
        currentRows.filter((item) => {
            const matchesSearch = `${item.id} ${item.location} ${item.ip} ${item.detail ?? ''}`
                .toLowerCase()
                .includes(search.toLowerCase());
            const matchesStatus =
                statusFilter === 'all' ||
                item.status === statusFilter ||
                (statusFilter === 'Recording' && item.recording) ||
                (statusFilter === 'Not recording' && item.status === 'Online' && !item.recording);
            return matchesSearch && matchesStatus;
        }),
    );
    let totalPages = $derived(Math.max(1, Math.ceil(filteredRows.length / pageSize)));
    let visibleRows = $derived(filteredRows.slice((page - 1) * pageSize, page * pageSize));
    let selectedDevice = $derived(currentRows.find((item) => item.id === selectedDeviceId));
    let reportRows = $derived(
        alerts.filter(
            (item) =>
                `${item.device} ${item.category} ${item.message}`
                    .toLowerCase()
                    .includes(search.toLowerCase()) &&
                (statusFilter === 'all' || item.status === statusFilter),
        ),
    );

    onMount(() => {
        const syncNavigation = () => {
            const section = window.location.hash.slice(1) || 'dashboard';
            if (navigation.some((item) => item.id === section)) {
                activeSection = section;
                search = '';
                statusFilter = 'all';
                page = 1;
                selectedDeviceId = section === 'cctv' ? 'CCTV-02' : null;
            }
        };
        try {
            const preferences = JSON.parse(
                localStorage.getItem('monitoring-preferences') ?? 'null',
            );
            if (
                preferences &&
                warehouses.some((item) => item.id === preferences.defaultWarehouse)
            ) {
                defaultWarehouse = preferences.defaultWarehouse;
                warehouseId = preferences.defaultWarehouse;
                if (
                    Number.isFinite(preferences.temperatureThreshold) &&
                    preferences.temperatureThreshold >= 25 &&
                    preferences.temperatureThreshold <= 60
                ) {
                    temperatureThreshold = preferences.temperatureThreshold;
                }
            }
        } catch {
            // Keep default preferences when browser storage is unavailable.
        }
        syncNavigation();
        window.addEventListener('hashchange', syncNavigation);
        return () => {
            window.removeEventListener('hashchange', syncNavigation);
            clearTimeout(toastTimer);
        };
    });

    function navigate(section, deviceId = null) {
        activeSection = section;
        search = '';
        statusFilter = 'all';
        page = 1;
        mobileMenuOpen = false;
        selectedDeviceId = deviceId ?? (section === 'cctv' ? 'CCTV-02' : null);
        detailTab = 'Live View';
        window.history.pushState(null, '', `#${section}`);
    }

    function changeWarehouse() {
        page = 1;
        search = '';
        statusFilter = 'all';
        selectedDeviceId = activeSection === 'cctv' ? 'CCTV-02' : null;
    }

    function filterBy(status) {
        statusFilter = status;
        page = 1;
    }

    function notify(message) {
        toastMessage = message;
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => (toastMessage = ''), 4000);
    }

    function refreshPreview() {
        updatedAt = new Intl.DateTimeFormat('en-GB', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: false,
        })
            .format(new Date())
            .replace(',', '');
        notify('Demo preview refreshed. Device data is simulated.');
    }

    function exportReport() {
        const rows = [
            ['Time', 'Device', 'Category', 'Message', 'Status', 'Warehouse'],
            ...reportRows.map((item) => [
                item.time,
                item.device,
                item.category,
                item.message,
                item.status,
                warehouse.name,
            ]),
        ];
        const csv = rows
            .map((row) => row.map((value) => `"${String(value).replaceAll('"', '""')}"`).join(','))
            .join('\r\n');
        const url = URL.createObjectURL(
            new Blob(['\uFEFF', csv], { type: 'text/csv;charset=utf-8;' }),
        );
        const link = document.createElement('a');
        link.href = url;
        link.download = `monitoring-${warehouse.id}-demo.csv`;
        link.click();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
        notify('Demo report exported as CSV.');
    }

    function savePreferences(event) {
        event.preventDefault();
        try {
            localStorage.setItem(
                'monitoring-preferences',
                JSON.stringify({ defaultWarehouse, temperatureThreshold }),
            );
            warehouseId = defaultWarehouse;
            changeWarehouse();
            notify('Preferences saved in this browser.');
        } catch {
            notify('Browser storage is unavailable. Preferences apply for this session.');
        }
    }
</script>

<svelte:head>
    <title
        >{activeSection === 'dashboard' ? 'IT Hardware Monitoring' : currentNavigation.label} — {appName}</title
    >
    <meta
        name="description"
        content="IT hardware monitoring dashboard for CCTV, network, computers, access points, NAS, temperature and UPS."
    />
</svelte:head>

<svelte:window
    onkeydown={(event) => {
        if (event.key === 'Escape') {
            mobileMenuOpen = false;
        }
    }}
/>

<div class="monitoring-app">
    {#if mobileMenuOpen}<button
            class="sidebar-backdrop"
            aria-label="Close navigation"
            onclick={() => (mobileMenuOpen = false)}
        ></button>{/if}
    <aside class="sidebar" class:mobile-open={mobileMenuOpen}>
        <a
            class="brand"
            href="#dashboard"
            onclick={(event) => {
                event.preventDefault();
                navigate('dashboard');
            }}
        >
            <div class="brand-line">
                <strong>TCL<span class="brand-dot">.</span></strong><span class="brand-chevron"
                    ><Icon name="down" size={14} /></span
                >
            </div>
            <span>IT Monitoring</span>
        </a>
        <div class="nav-caption">WORKSPACE</div>
        <nav aria-label="Main navigation">
            {#each navigation as item}
                <a
                    href={`#${item.id}`}
                    class:active={activeSection === item.id}
                    aria-current={activeSection === item.id ? 'page' : undefined}
                    onclick={(event) => {
                        event.preventDefault();
                        navigate(item.id);
                    }}
                    ><Icon name={item.icon} size={18} /><span>{item.label}</span
                    >{#if item.id === 'cctv' && warehouse.offline > 0}<span class="nav-count"
                            >{warehouse.offline}</span
                        >{/if}</a
                >
            {/each}
        </nav>
        <div class="sidebar-bottom">
            <div class="system-state">
                <span class="status-dot green"></span>Local workspace<span class="version"
                    >v1.0</span
                >
            </div>
            <div class="sidebar-profile">
                <div class="avatar">IT</div>
                <div><strong>IT Administrator</strong><span>Monitoring workspace</span></div>
                <Icon name="shield" size={17} />
            </div>
        </div>
    </aside>

    <main class="workspace">
        <header class="topbar">
            <div class="heading-group">
                <button
                    class="mobile-menu icon-button"
                    aria-label="Open navigation"
                    onclick={() => (mobileMenuOpen = true)}><Icon name="menu" /></button
                >
                <div>
                    {#if activeSection === 'dashboard'}
                        <div class="eyebrow">WORKSPACE OVERVIEW</div>
                        <h1>IT Hardware Monitoring</h1>
                        <p>
                            Real-time monitoring for CCTV, Network, Computers, Access Point, NAS,
                            Temperature and UPS
                        </p>
                    {:else}
                        <div class="breadcrumb">
                            <button onclick={() => navigate('dashboard')}
                                >IT Hardware Monitoring</button
                            ><Icon name="chevron" size={13} /><span>{currentNavigation.label}</span>
                        </div>
                        <div class="page-heading">
                            <span class="heading-icon"
                                ><Icon name={currentNavigation.icon} size={25} /></span
                            >
                            <div>
                                <h1>
                                    {['reports', 'settings'].includes(activeSection)
                                        ? currentNavigation.label
                                        : activeSection === 'cctv'
                                          ? 'CCTV Monitoring'
                                          : `${currentNavigation.label} Monitoring`}
                                </h1>
                                <p>
                                    {activeSection === 'cctv'
                                        ? 'Monitor status and health of all CCTV cameras'
                                        : activeSection === 'reports'
                                          ? 'Review hardware alerts and export monitoring reports'
                                          : activeSection === 'settings'
                                            ? 'Customize your local monitoring preferences'
                                            : `Monitor status and health of ${currentNavigation.label.toLowerCase()} devices`}
                                </p>
                            </div>
                        </div>
                    {/if}
                </div>
            </div>
            <div class="topbar-actions">
                <div class="last-update">
                    <span class="status-dot green"></span>
                    <div>
                        <span>Last Update <b class="demo-label">DEMO</b></span><strong
                            >{updatedAt}</strong
                        >
                    </div>
                    <button
                        class="refresh-button"
                        aria-label="Refresh demo preview"
                        title="Refresh demo preview"
                        onclick={refreshPreview}><Icon name="refresh" size={15} /></button
                    >
                </div>
                <label class="warehouse-selector"
                    ><Icon name="pin" size={18} /><select
                        aria-label="Warehouse"
                        bind:value={warehouseId}
                        onchange={changeWarehouse}
                        >{#each warehouses as item}<option value={item.id}>{item.name}</option
                            >{/each}</select
                    ><Icon name="down" size={15} /></label
                >
            </div>
        </header>

        {#if activeSection === 'dashboard'}
            <section class="overview-grid" aria-label="Hardware status overview">
                <button class="panel metric-card" onclick={() => navigate('cctv')}
                    ><span class="metric-icon"><Icon name="camera" size={29} /></span>
                    <div class="metric-content">
                        <div class="metric-heading">
                            <h2>CCTV</h2>
                            <Icon name="chevron" size={17} />
                        </div>
                        <div class="metric-value">
                            <strong>{cameraOnline}</strong><span>/ {warehouse.cameras}</span>
                        </div>
                        <div class="metric-description">
                            <span>Online</span><small
                                ><i class="status-square red"></i>{warehouse.offline} Offline</small
                            >
                        </div>
                        <div class="progress-track">
                            <span
                                class="green-bar"
                                style:width={`${(cameraOnline / warehouse.cameras) * 100}%`}
                            ></span><span
                                class="red-bar"
                                style:width={`${(warehouse.offline / warehouse.cameras) * 100}%`}
                            ></span>
                        </div>
                    </div></button
                >
                <button class="panel metric-card" onclick={() => navigate('network')}
                    ><span class="metric-icon"><Icon name="globe" size={29} /></span>
                    <div class="metric-content">
                        <div class="metric-heading">
                            <h2>ISP</h2>
                            <Icon name="chevron" size={17} />
                        </div>
                        <div class="metric-value"><strong>2</strong><span>/ 2</span></div>
                        <div class="metric-description"><span>Normal</span></div>
                        <div class="status-legend">
                            <span><i class="status-square green"></i>Normal <b>2</b></span><span
                                ><i class="status-square orange"></i>Low <b>0</b></span
                            ><span><i class="status-square red"></i>Loss <b>0</b></span>
                        </div>
                    </div></button
                >
                <button class="panel metric-card" onclick={() => navigate('computers')}
                    ><span class="metric-icon blue-icon"><Icon name="computer" size={29} /></span>
                    <div class="metric-content">
                        <div class="metric-heading">
                            <h2>Computers</h2>
                            <Icon name="chevron" size={17} />
                        </div>
                        <div class="metric-value">
                            <strong>{computerOnline}</strong><span>/ {warehouse.computers}</span>
                        </div>
                        <div class="metric-description">
                            <span>Online</span><small
                                ><i class="status-square red"></i>{warehouse.computers -
                                    computerOnline} Offline</small
                            >
                        </div>
                        <div class="progress-track">
                            <span
                                class="green-bar"
                                style:width={`${(computerOnline / warehouse.computers) * 100}%`}
                            ></span><span
                                class="red-bar"
                                style:width={`${((warehouse.computers - computerOnline) / warehouse.computers) * 100}%`}
                            ></span>
                        </div>
                    </div></button
                >
                <button class="panel metric-card" onclick={() => navigate('access')}
                    ><span class="metric-icon blue-icon"><Icon name="wifi" size={29} /></span>
                    <div class="metric-content">
                        <div class="metric-heading">
                            <h2>Access Point</h2>
                            <Icon name="chevron" size={17} />
                        </div>
                        <div class="metric-value">
                            <strong>{accessNormal}</strong><span>/ {warehouse.accessPoints}</span>
                        </div>
                        <div class="metric-description"><span>Normal</span></div>
                        <div class="status-legend">
                            <span
                                ><i class="status-square green"></i>Normal
                                <b>{accessNormal}</b></span
                            ><span><i class="status-square orange"></i>Low <b>2</b></span><span
                                ><i class="status-square red"></i>Loss <b>0</b></span
                            >
                        </div>
                    </div></button
                >
            </section>
            <section class="infrastructure-grid" aria-label="Infrastructure health">
                <button
                    class="panel infrastructure-card nas-card"
                    onclick={() => navigate('nas', 'NAS-01')}
                    ><span class="metric-icon"><Icon name="server" size={29} /></span>
                    <div class="infrastructure-content">
                        <h2>NAS</h2>
                        <div class="online-label"><span class="status-dot green"></span>Online</div>
                        <div class="nas-metrics">
                            <div class="storage-metric">
                                <span>Storage Usage <b>68%</b></span>
                                <div class="progress-track">
                                    <span class="blue-bar" style:width="68%"></span>
                                </div>
                            </div>
                            <div>
                                <span>Health</span><strong class="badge online">Normal</strong>
                            </div>
                            <div><span>Backup</span><strong class="badge online">OK</strong></div>
                        </div>
                    </div></button
                >
                <button class="panel infrastructure-card" onclick={() => navigate('temperature')}
                    ><span class="metric-icon"><Icon name="temperature" size={29} /></span>
                    <div class="infrastructure-content">
                        <h2>Temperature</h2>
                        <div class="temperature-metrics">
                            {#each [{ name: 'IT Room', value: 24 }, { name: 'NAS', value: 32 }, { name: 'Cache', value: 38 }] as item}<div
                                >
                                    <span>{item.name}</span><strong
                                        ><i
                                            class="status-dot"
                                            class:orange={item.value >= temperatureThreshold}
                                            class:green={item.value < temperatureThreshold}
                                        ></i>{item.value}°C</strong
                                    >
                                </div>{/each}
                        </div>
                    </div></button
                >
                <button class="panel infrastructure-card" onclick={() => navigate('ups', 'UPS-01')}
                    ><span class="metric-icon"><Icon name="battery" size={29} /></span>
                    <div class="infrastructure-content">
                        <div class="metric-heading">
                            <h2>UPS</h2>
                            <Icon name="chevron" size={17} />
                        </div>
                        <div class="online-label"><span class="status-dot green"></span>Online</div>
                        <div class="ups-metrics">
                            <div>
                                <span>Load</span><strong>42%</strong>
                                <div class="progress-track">
                                    <span class="blue-bar" style:width="42%"></span>
                                </div>
                            </div>
                            <div>
                                <span>Battery</span><strong>98%</strong>
                                <div class="progress-track">
                                    <span class="green-bar" style:width="98%"></span>
                                </div>
                            </div>
                            <div>
                                <span>Runtime</span><strong class="runtime-value">42 min</strong>
                            </div>
                        </div>
                    </div></button
                >
            </section>
            <section class="dashboard-bottom">
                <article class="panel alerts-panel">
                    <div class="panel-heading">
                        <h2>
                            <span class="section-icon alert-icon"
                                ><Icon name="bell" size={18} /></span
                            >Recent Alerts <span class="count-pill">{alerts.length}</span>
                        </h2>
                        <button class="outline-button compact" onclick={() => navigate('reports')}
                            >View All<Icon name="chevron" size={14} /></button
                        >
                    </div>
                    <div class="table-scroll">
                        <table class="alerts-table">
                            <thead
                                ><tr
                                    ><th>Time</th><th>Device</th><th>Category</th><th>Message</th
                                    ><th>Status</th></tr
                                ></thead
                            ><tbody
                                >{#each alerts.slice(0, 5) as alert}<tr
                                        ><td
                                            ><span class="alert-time"
                                                ><i
                                                    class="status-dot"
                                                    class:red={alert.status === 'Critical'}
                                                    class:orange={alert.status === 'Warning'}
                                                ></i>{alert.time}</span
                                            ></td
                                        ><td
                                            ><button
                                                class="device-link"
                                                onclick={() =>
                                                    navigate(alert.section, alert.device)}
                                                >{alert.device}</button
                                            ></td
                                        ><td>{alert.category}</td><td>{alert.message}</td><td
                                            ><span
                                                class="badge"
                                                class:critical={alert.status === 'Critical'}
                                                class:warning={alert.status === 'Warning'}
                                                >{alert.status}</span
                                            ></td
                                        ></tr
                                    >{/each}</tbody
                            >
                        </table>
                    </div>
                    <div class="panel-footnote">
                        <span
                            class="status-dot"
                            class:orange={warehouse.offline > 0}
                            class:green={warehouse.offline === 0}
                        ></span>{warehouse.offline
                            ? `${warehouse.offline} cameras need attention`
                            : 'All cameras are online'}<span>View devices for details</span>
                    </div>
                </article>
                <article class="panel latency-panel">
                    <div class="panel-heading">
                        <h2>
                            <span class="section-icon blue-icon"
                                ><Icon name="chart" size={20} /></span
                            >Network Latency
                        </h2>
                        <label class="compact-select"
                            ><select aria-label="Network latency time range" bind:value={chartRange}
                                ><option value="1">Last 1 Hour</option><option value="6"
                                    >Last 6 Hours</option
                                ><option value="24">Last 24 Hours</option></select
                            ><Icon name="down" size={13} /></label
                        >
                    </div>
                    <LatencyChart
                        range={chartRange}
                        warehouseIndex={warehouses.indexOf(warehouse)}
                    />
                </article>
            </section>
            <div class="overview-note">
                <Icon name="shield" size={15} /><span
                    >One workspace. Every device. A clearer view of your infrastructure.</span
                >
            </div>
        {:else if activeSection === 'settings'}
            <section class="panel settings-panel">
                <div class="panel-heading">
                    <h2><Icon name="settings" />Monitoring preferences</h2>
                    <span class="badge neutral">This browser</span>
                </div>
                <form onsubmit={savePreferences}>
                    <label
                        >Default warehouse<span>Open this warehouse the next time you visit.</span
                        ><select bind:value={defaultWarehouse}
                            >{#each warehouses as item}<option value={item.id}>{item.name}</option
                                >{/each}</select
                        ></label
                    ><label
                        >Temperature warning threshold<span
                            >Dashboard temperature indicators turn orange at this value.</span
                        >
                        <div class="threshold-input">
                            <input
                                type="number"
                                min="25"
                                max="60"
                                required
                                bind:value={temperatureThreshold}
                            /><span>°C</span>
                        </div></label
                    >
                    <div class="settings-notice">
                        <Icon name="shield" size={20} />
                        <div>
                            <strong>Demo workspace</strong>
                            <p>
                                These preferences are stored in your browser. Device connections,
                                credentials and real monitoring are not configured yet.
                            </p>
                        </div>
                    </div>
                    <button class="primary-button" type="submit"
                        ><Icon name="check" size={17} />Save preferences</button
                    >
                </form>
            </section>
        {:else if activeSection === 'reports'}
            <section class="report-summary">
                {#each [{ label: 'Total alerts', value: alerts.length, icon: 'bell', color: 'alert-icon' }, { label: 'Critical', value: alerts.filter((item) => item.status === 'Critical').length, icon: 'signalOff', color: 'critical-icon' }, { label: 'Warnings', value: alerts.filter((item) => item.status === 'Warning').length, icon: 'temperature', color: 'warning-icon' }, { label: 'Resolved', value: alerts.filter((item) => item.status === 'Resolved').length, icon: 'check', color: 'green-icon' }] as stat}<article
                        class="panel report-stat"
                    >
                        <span class="metric-icon {stat.color}"
                            ><Icon name={stat.icon} size={24} /></span
                        >
                        <div><span>{stat.label}</span><strong>{stat.value}</strong></div>
                    </article>{/each}
            </section>
            <section class="panel inventory-panel">
                <div class="panel-heading report-toolbar">
                    <h2>Alert history<span class="count-pill">{reportRows.length}</span></h2>
                    <div class="table-controls">
                        <label class="search-field"
                            ><input
                                aria-label="Search alerts"
                                placeholder="Search alerts..."
                                bind:value={search}
                            /><Icon name="search" size={17} /></label
                        ><label class="compact-select"
                            ><select aria-label="Alert severity" bind:value={statusFilter}
                                ><option value="all">All Severities</option><option>Critical</option
                                ><option>Warning</option><option>Resolved</option></select
                            ><Icon name="down" size={14} /></label
                        ><button class="primary-button compact" onclick={exportReport}
                            ><Icon name="download" size={16} />Export CSV</button
                        >
                    </div>
                </div>
                <div class="table-scroll">
                    <table>
                        <thead
                            ><tr
                                ><th>Time</th><th>Device</th><th>Category</th><th>Message</th><th
                                    >Status</th
                                ><th>Action</th></tr
                            ></thead
                        ><tbody
                            >{#each reportRows as alert}<tr
                                    ><td>{alert.time}</td><td
                                        ><button
                                            class="device-link"
                                            onclick={() => navigate(alert.section, alert.device)}
                                            >{alert.device}</button
                                        ></td
                                    ><td>{alert.category}</td><td>{alert.message}</td><td
                                        ><span
                                            class="badge"
                                            class:critical={alert.status === 'Critical'}
                                            class:warning={alert.status === 'Warning'}
                                            class:online={alert.status === 'Resolved'}
                                            >{alert.status}</span
                                        ></td
                                    ><td
                                        ><button
                                            class="icon-button"
                                            aria-label={`View ${alert.device}`}
                                            onclick={() => navigate(alert.section, alert.device)}
                                            ><Icon name="eye" size={17} /></button
                                        ></td
                                    ></tr
                                >{:else}<tr
                                    ><td colspan="6" class="empty-state"
                                        ><Icon name="search" size={28} /><strong
                                            >No matching alerts</strong
                                        ><span>Try another search or severity.</span><button
                                            class="outline-button compact"
                                            onclick={() => {
                                                search = '';
                                                statusFilter = 'all';
                                            }}>Clear filters</button
                                        ></td
                                    ></tr
                                >{/each}</tbody
                        >
                    </table>
                </div>
                <div class="table-footer">
                    <span>{reportRows.length} demo alerts · 30 Sep 2026</span><span
                        >{warehouse.name}</span
                    >
                </div>
            </section>
        {:else}
            <section class="inventory-toolbar" aria-label="Device summary and filters">
                <div class="device-stats">
                    {#if activeSection === 'cctv'}
                        {#each [{ label: 'Total Camera', value: warehouse.cameras, icon: 'camera', color: 'navy', filter: 'all' }, { label: 'Online', value: cameraOnline, icon: 'check', color: 'green', filter: 'Online' }, { label: 'Offline', value: warehouse.offline, icon: 'signalOff', color: 'red', filter: 'Offline' }, { label: 'Recording', value: cameraOnline, icon: 'play', color: 'purple', filter: 'Recording' }, { label: 'Not Recording', value: 0, icon: 'stop', color: 'orange', filter: 'Not recording' }] as stat}<button
                                class="panel device-stat"
                                class:chosen={statusFilter === stat.filter}
                                onclick={() => filterBy(stat.filter)}
                                ><span class="stat-icon {stat.color}"
                                    ><Icon name={stat.icon} size={18} /></span
                                ><span
                                    ><small>{stat.label}</small><strong>{stat.value}</strong></span
                                ></button
                            >{/each}
                    {:else}
                        {#each [{ label: 'Total Devices', value: currentRows.length, icon: currentNavigation.icon, color: 'navy', filter: 'all' }, { label: 'Online', value: currentRows.filter((item) => item.status === 'Online').length, icon: 'check', color: 'green', filter: 'Online' }, { label: 'Needs Attention', value: currentRows.filter((item) => item.status !== 'Online').length, icon: 'bell', color: 'orange', filter: activeSection === 'computers' ? 'Offline' : 'Warning' }] as stat}<button
                                class="panel device-stat"
                                class:chosen={statusFilter === stat.filter}
                                onclick={() => filterBy(stat.filter)}
                                ><span class="stat-icon {stat.color}"
                                    ><Icon name={stat.icon} size={18} /></span
                                ><span
                                    ><small>{stat.label}</small><strong>{stat.value}</strong></span
                                ></button
                            >{/each}
                    {/if}
                </div>
                <div class="table-controls">
                    <label class="search-field"
                        ><input
                            aria-label="Search devices"
                            placeholder={activeSection === 'cctv'
                                ? 'Search camera...'
                                : 'Search devices...'}
                            bind:value={search}
                            oninput={() => (page = 1)}
                        /><Icon name="search" size={17} /></label
                    ><label class="compact-select"
                        ><select
                            aria-label="Device status"
                            bind:value={statusFilter}
                            onchange={() => (page = 1)}
                            ><option value="all">All Status</option><option>Online</option><option
                                >Offline</option
                            >{#if activeSection === 'cctv'}<option>Recording</option><option
                                    >Not recording</option
                                >{:else}<option>Warning</option>{/if}</select
                        ><Icon name="down" size={14} /></label
                    >
                </div>
            </section>
            <div class="inventory-layout" class:with-detail={selectedDevice}>
                <section class="panel inventory-panel">
                    <div class="table-scroll">
                        <table class="device-table">
                            <thead
                                ><tr
                                    ><th>No</th><th
                                        >{activeSection === 'cctv'
                                            ? 'Camera Name'
                                            : 'Device Name'}</th
                                    ><th>Location</th><th>IP Address</th><th>Status</th
                                    >{#if activeSection === 'cctv'}<th>Recording</th><th
                                            >Last Seen</th
                                        >{:else}<th>Details</th>{/if}<th>Action</th></tr
                                ></thead
                            ><tbody
                                >{#each visibleRows as device, index}<tr
                                        class:selected-row={selectedDeviceId === device.id}
                                        class:offline-row={device.status === 'Offline'}
                                        ><td>{(page - 1) * pageSize + index + 1}</td><td
                                            ><button
                                                class="device-link"
                                                onclick={() => {
                                                    selectedDeviceId = device.id;
                                                    detailTab = 'Live View';
                                                }}>{device.id}</button
                                            ></td
                                        ><td>{device.location}</td><td class="ip-address"
                                            >{device.ip}</td
                                        ><td
                                            ><span
                                                class="badge"
                                                class:online={device.status === 'Online'}
                                                class:critical={device.status === 'Offline'}
                                                class:warning={device.status === 'Warning'}
                                                >{device.status}</span
                                            ></td
                                        >{#if activeSection === 'cctv'}<td
                                                >{#if device.recording}<span class="badge online"
                                                        >Recording</span
                                                    >{:else}<span class="muted">—</span>{/if}</td
                                            ><td class="tabular">{device.lastSeen}</td>{:else}<td
                                                >{device.detail}</td
                                            >{/if}<td
                                            ><button
                                                class="icon-button view-device"
                                                aria-label={`View ${device.id}`}
                                                onclick={() => {
                                                    selectedDeviceId = device.id;
                                                    detailTab = 'Live View';
                                                }}><Icon name="eye" size={16} /></button
                                            ></td
                                        ></tr
                                    >{:else}<tr
                                        ><td
                                            colspan={activeSection === 'cctv' ? 8 : 7}
                                            class="empty-state"
                                            ><Icon name="search" size={28} /><strong
                                                >No matching devices</strong
                                            ><span>Try another search or status.</span><button
                                                class="outline-button compact"
                                                onclick={() => {
                                                    search = '';
                                                    filterBy('all');
                                                }}>Clear filters</button
                                            ></td
                                        ></tr
                                    >{/each}</tbody
                            >
                        </table>
                    </div>
                    <div class="table-footer">
                        <span
                            >Showing {filteredRows.length
                                ? (page - 1) * pageSize + 1
                                : 0}–{Math.min(page * pageSize, filteredRows.length)} of {filteredRows.length}
                            {activeSection === 'cctv' ? 'cameras' : 'devices'}</span
                        >
                        <div class="pagination">
                            <button
                                aria-label="Previous page"
                                disabled={page <= 1}
                                onclick={() => (page -= 1)}
                                ><span class="previous-chevron"
                                    ><Icon name="chevron" size={14} /></span
                                ></button
                            >{#each Array.from({ length: totalPages }, (_, index) => index + 1) as pageNumber}<button
                                    class:current={page === pageNumber}
                                    aria-label={`Page ${pageNumber}`}
                                    aria-current={page === pageNumber ? 'page' : undefined}
                                    onclick={() => (page = pageNumber)}>{pageNumber}</button
                                >{/each}<button
                                aria-label="Next page"
                                disabled={page >= totalPages}
                                onclick={() => (page += 1)}
                                ><Icon name="chevron" size={14} /></button
                            >
                        </div>
                    </div>
                </section>
                {#if selectedDevice}
                    <aside class="panel device-detail" aria-label={`${selectedDevice.id} details`}>
                        <div class="detail-heading">
                            <span class="metric-icon"
                                ><Icon name={currentNavigation.icon} size={24} /></span
                            >
                            <div>
                                <h2>
                                    {selectedDevice.id}<span
                                        class="badge"
                                        class:online={selectedDevice.status === 'Online'}
                                        class:critical={selectedDevice.status === 'Offline'}
                                        class:warning={selectedDevice.status === 'Warning'}
                                        ><i
                                            class="status-dot"
                                            class:green={selectedDevice.status === 'Online'}
                                            class:red={selectedDevice.status === 'Offline'}
                                            class:orange={selectedDevice.status === 'Warning'}
                                        ></i>{selectedDevice.status}</span
                                    >
                                </h2>
                                <p>{selectedDevice.location}</p>
                            </div>
                            <button
                                class="icon-button close-detail"
                                aria-label="Close device details"
                                onclick={() => (selectedDeviceId = null)}
                                ><Icon name="close" size={17} /></button
                            >
                        </div>
                        {#if activeSection === 'cctv'}
                            <nav class="detail-tabs" aria-label="Camera detail sections">
                                {#each ['Live View', 'Details', 'Network', 'Recording', 'Events'] as tab}<button
                                        class:active={detailTab === tab}
                                        aria-pressed={detailTab === tab}
                                        onclick={() => (detailTab = tab)}>{tab}</button
                                    >{/each}
                            </nav>
                            {#if detailTab === 'Live View'}
                                <div
                                    class="camera-preview"
                                    class:no-signal={selectedDevice.status === 'Offline'}
                                >
                                    <div class="camera-preview-grid"></div>
                                    <div class="preview-label">
                                        <span
                                            class="status-dot"
                                            class:red={selectedDevice.status === 'Offline'}
                                            class:green={selectedDevice.status === 'Online'}
                                        ></span>{selectedDevice.id}<span>DEMO PREVIEW</span>
                                    </div>
                                    <div class="preview-center">
                                        <Icon
                                            name={selectedDevice.status === 'Offline'
                                                ? 'signalOff'
                                                : 'camera'}
                                            size={44}
                                            stroke={1.4}
                                        /><strong
                                            >{selectedDevice.status === 'Offline'
                                                ? 'No Signal'
                                                : 'Camera preview'}</strong
                                        ><span
                                            >{selectedDevice.status === 'Offline'
                                                ? 'Camera is currently disconnected'
                                                : 'Connect a camera stream to enable live view'}</span
                                        >
                                    </div>
                                    <div class="preview-bottom">
                                        <span>{selectedDevice.location}</span><span
                                            >4 MP · H.265</span
                                        >
                                    </div>
                                </div>
                                <div class="camera-health">
                                    <span><Icon name="clock" size={15} />Last seen</span><strong
                                        >{selectedDevice.lastSeen}</strong
                                    >
                                </div>
                                <div
                                    class="detail-notice"
                                    class:danger={selectedDevice.status === 'Offline'}
                                >
                                    <Icon
                                        name={selectedDevice.status === 'Offline'
                                            ? 'signalOff'
                                            : 'shield'}
                                        size={18}
                                    /><span
                                        >{selectedDevice.status === 'Offline'
                                            ? 'Connection lost. Check camera power and network connection.'
                                            : 'This is a demo device. No live camera stream is connected.'}</span
                                    >
                                </div>
                            {:else if detailTab === 'Details'}
                                <dl class="detail-list">
                                    {#each [['Camera name', selectedDevice.id], ['Location', selectedDevice.location], ['Model', selectedDevice.model], ['Resolution', '2560 × 1440'], ['Video codec', 'H.265'], ['Data source', 'Demo inventory']] as item}<div
                                        >
                                            <dt>{item[0]}</dt>
                                            <dd>{item[1]}</dd>
                                        </div>{/each}
                                </dl>
                            {:else if detailTab === 'Network'}
                                <dl class="detail-list">
                                    {#each [['IP address', selectedDevice.ip], ['Subnet mask', '255.255.255.0'], ['Connection', 'Ethernet · PoE'], ['Network status', selectedDevice.status]] as item}<div
                                        >
                                            <dt>{item[0]}</dt>
                                            <dd>{item[1]}</dd>
                                        </div>{/each}
                                </dl>
                                <div class="detail-notice">
                                    <Icon name="globe" size={18} /><span
                                        >Network values are simulated. No connection checks have
                                        been performed.</span
                                    >
                                </div>
                            {:else if detailTab === 'Recording'}
                                <div class="recording-state">
                                    <span
                                        class="stat-icon"
                                        class:green={selectedDevice.recording}
                                        class:red={!selectedDevice.recording}
                                        ><Icon
                                            name={selectedDevice.recording ? 'play' : 'stop'}
                                            size={24}
                                        /></span
                                    ><strong
                                        >{selectedDevice.recording
                                            ? 'Recording active'
                                            : 'Recording unavailable'}</strong
                                    >
                                    <p>
                                        {selectedDevice.recording
                                            ? 'Demo recording status · continuous mode'
                                            : 'Recording paused while camera is offline'}
                                    </p>
                                </div>
                                <dl class="detail-list">
                                    <div>
                                        <dt>Storage</dt>
                                        <dd>NAS-01</dd>
                                    </div>
                                    <div>
                                        <dt>Retention</dt>
                                        <dd>30 days</dd>
                                    </div>
                                    <div>
                                        <dt>Recordings</dt>
                                        <dd>Demo · no video files</dd>
                                    </div>
                                </dl>
                            {:else}
                                <div class="camera-events">
                                    {#each alerts.filter((item) => item.device === selectedDevice.id) as alert}<div
                                        >
                                            <span
                                                class="status-dot"
                                                class:red={alert.status === 'Critical'}
                                                class:orange={alert.status === 'Warning'}
                                                class:green={alert.status === 'Resolved'}
                                            ></span>
                                            <div>
                                                <strong>{alert.message}</strong><span
                                                    >30 Sep 2026 · {alert.time}</span
                                                >
                                            </div>
                                        </div>{:else}<div class="detail-empty">
                                            <Icon name="check" size={28} /><strong
                                                >No recent events</strong
                                            ><span>No alerts for this demo camera.</span>
                                        </div>{/each}
                                </div>
                            {/if}
                        {:else}
                            <dl class="detail-list">
                                {#each [['Device', selectedDevice.id], ['Location', selectedDevice.location], ['IP address', selectedDevice.ip], ['Status', selectedDevice.status], ['Health', selectedDevice.detail]] as item}<div
                                    >
                                        <dt>{item[0]}</dt>
                                        <dd>{item[1]}</dd>
                                    </div>{/each}
                            </dl>
                            <div class="detail-notice">
                                <Icon name="shield" size={18} /><span
                                    >Demo inventory. Connect a monitoring source to show real device
                                    data.</span
                                >
                            </div>
                        {/if}
                    </aside>
                {/if}
            </div>
        {/if}
        <footer class="workspace-footer">
            <span
                ><span class="status-dot blue"></span>Demo workspace · Device data is simulated</span
            ><span>TCL IT Monitoring<span class="footer-divider">/</span>{warehouse.name}</span>
        </footer>
    </main>
    {#if toastMessage}<div class="toast" role="status">
            <Icon name="check" size={19} /><span>{toastMessage}</span><button
                aria-label="Dismiss notification"
                onclick={() => (toastMessage = '')}><Icon name="close" size={16} /></button
            >
        </div>{/if}
</div>
