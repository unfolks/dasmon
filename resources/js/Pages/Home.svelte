<script>
    import { onMount, untrack } from 'svelte';
    import { createMonitoringFeed } from '../monitoring-device-feed.svelte.js';
    import MonitoringSource from '../Components/Monitoring/MonitoringSource.svelte';
    import MonitoringLayout from '../Layouts/MonitoringLayout.svelte';
    import DashboardOverview from '../Components/Monitoring/DashboardOverview.svelte';
    import DeviceInventory from '../Components/Monitoring/DeviceInventory.svelte';
    import ReportsPanel from '../Components/Monitoring/ReportsPanel.svelte';
    import SettingsPanel from '../Components/Monitoring/SettingsPanel.svelte';
    import ToastMessage from '../Components/Monitoring/ToastMessage.svelte';
    import { navigation } from '../monitoring-navigation.js';
    import {
        warehouses,
        buildInventory,
        buildAlerts,
    } from '../monitoring-data.js';
    import { buildNasTemperatures } from '../monitoring-nas.js';
    import {
        loadMonitoringPreferences,
        persistMonitoringPreferences,
    } from '../monitoring-preferences.js';

    let {
        appName,
        cctvEndpoints = {},
        accessPointEndpoints = {},
        computerEndpoints = {},
        nasEndpoints = {},
    } = $props();
    const cctv = createMonitoringFeed();
    const accessPoints = createMonitoringFeed();
    const computers = createMonitoringFeed();
    const nas = createMonitoringFeed();
    let activeSection = $state('dashboard');
    let warehouseId = $state('krian');
    let initialDeviceId = $state(null);
    let viewRevision = $state(0);
    let chartRange = $state('1');
    let mobileMenuOpen = $state(false);
    let updatedAt = $state('Belum diperbarui');
    let toastMessage = $state('');
    let temperatureThreshold = $state(35);
    let defaultWarehouse = $state('krian');
    let toastTimer;

    let warehouse = $derived(
        warehouses.find((item) => item.id === warehouseId) ?? warehouses[0],
    );
    let demoInventory = $derived(
        buildInventory(warehouse, temperatureThreshold),
    );
    let inventory = $derived({
        ...demoInventory,
        cctv: cctv.devices.map((camera) =>
            cctv.meta.status === 'live'
                ? camera
                : {
                      ...camera,
                      status: 'Unknown',
                      isReachable: null,
                      latencyMs: null,
                      packetLossPercent: null,
                      detail: 'Koneksi monitoring bermasalah; status kamera belum terverifikasi ulang.',
                  },
        ),
        access: accessPoints.devices,
        computers: computers.devices,
        nas: nas.devices,
        temperature: [
            ...demoInventory.temperature.filter(
                (item) => item.location !== 'NAS',
            ),
            ...buildNasTemperatures(
                nas.devices,
                nas.meta.thresholds?.temperature ?? 60,
            ),
        ],
    });
    let offlineCameraCount = $derived(
        inventory.cctv.filter((camera) => camera.status === 'Offline').length,
    );
    let alerts = $derived(
        buildAlerts(warehouse).filter(
            (alert) =>
                !['cctv', 'access', 'computers', 'nas'].includes(alert.section),
        ),
    );
    let currentNavigation = $derived(
        navigation.find((item) => item.id === activeSection) ?? navigation[0],
    );

    $effect(() => {
        const cctvEndpoint = cctvEndpoints[warehouseId];
        const endpoint = accessPointEndpoints[warehouseId];
        const computerEndpoint = computerEndpoints[warehouseId];
        const nasEndpoint = nasEndpoints[warehouseId];
        const refresh = () => {
            cctv.refresh(cctvEndpoint);
            accessPoints.refresh(endpoint);
            computers.refresh(computerEndpoint);
            nas.refresh(nasEndpoint);
        };
        untrack(refresh);
        const timer = setInterval(refresh, 30_000);
        return () => {
            clearInterval(timer);
            cctv.dispose();
            accessPoints.dispose();
            computers.dispose();
            nas.dispose();
        };
    });

    onMount(() => {
        const preferences = loadMonitoringPreferences(warehouses);
        defaultWarehouse = preferences.defaultWarehouse;
        warehouseId = preferences.defaultWarehouse;
        temperatureThreshold = preferences.temperatureThreshold;

        const syncNavigation = () => {
            const section = window.location.hash.slice(1) || 'dashboard';
            if (navigation.some((item) => item.id === section)) {
                selectSection(section);
            }
        };

        syncNavigation();
        window.addEventListener('hashchange', syncNavigation);
        return () => {
            window.removeEventListener('hashchange', syncNavigation);
            clearTimeout(toastTimer);
        };
    });

    function selectSection(section, deviceId = null) {
        activeSection = section;
        initialDeviceId = deviceId;
        viewRevision += 1;
        mobileMenuOpen = false;
    }

    function navigate(section, deviceId = null) {
        selectSection(section, deviceId);
        window.history.pushState(null, '', `#${section}`);
    }

    function changeWarehouse() {
        initialDeviceId = null;
        viewRevision += 1;
    }

    function notify(message) {
        toastMessage = message;
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => (toastMessage = ''), 4000);
    }

    function refreshPreview() {
        cctv.refresh(cctvEndpoints[warehouseId]);
        accessPoints.refresh(accessPointEndpoints[warehouseId]);
        computers.refresh(computerEndpoints[warehouseId]);
        nas.refresh(nasEndpoints[warehouseId]);
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
        notify(
            'Memperbarui CCTV, Access Point, Computers dan NAS dari Zabbix. Kategori lainnya masih demo.',
        );
    }

    function savePreferences(event) {
        event.preventDefault();
        const saved = persistMonitoringPreferences({
            defaultWarehouse,
            temperatureThreshold,
        });
        warehouseId = defaultWarehouse;
        changeWarehouse();
        notify(
            saved
                ? 'Preferences saved in this browser.'
                : 'Browser storage is unavailable. Preferences apply for this session.',
        );
    }
</script>

<svelte:head>
    <title
        >{activeSection === 'dashboard'
            ? 'IT Hardware Monitoring'
            : currentNavigation.label} — {appName}</title
    >
    <meta
        name="description"
        content="IT hardware monitoring dashboard for CCTV, network, computers, access points, NAS, temperature and UPS."
    />
</svelte:head>

<MonitoringLayout
    {activeSection}
    {currentNavigation}
    {warehouse}
    {offlineCameraCount}
    {warehouses}
    bind:warehouseId
    bind:mobileMenuOpen
    {updatedAt}
    {navigate}
    {changeWarehouse}
    {refreshPreview}
>
    {#if ['dashboard', 'cctv'].includes(activeSection)}
        <MonitoringSource
            label="CCTV"
            meta={cctv.meta}
            loading={cctv.loading}
        />
    {/if}
    {#if ['dashboard', 'access'].includes(activeSection)}
        <MonitoringSource
            label="Access Point"
            meta={accessPoints.meta}
            loading={accessPoints.loading}
        />
    {/if}
    {#if ['dashboard', 'computers'].includes(activeSection)}
        <MonitoringSource
            label="Computers"
            meta={computers.meta}
            loading={computers.loading}
            showThresholds={activeSection === 'computers'}
        />
    {/if}
    {#if activeSection === 'dashboard'}
        <MonitoringSource label="NAS" meta={nas.meta} loading={nas.loading} />
        <DashboardOverview
            {inventory}
            {alerts}
            warehouseIndex={warehouses.indexOf(warehouse)}
            {temperatureThreshold}
            bind:chartRange
            {navigate}
        />
    {:else if activeSection === 'settings'}
        <SettingsPanel
            {warehouses}
            bind:defaultWarehouse
            bind:temperatureThreshold
            {savePreferences}
        />
    {:else}
        {#if activeSection === 'nas'}
            <MonitoringSource
                label="NAS"
                meta={nas.meta}
                loading={nas.loading}
                showThresholds
            />
        {/if}
        {#key viewRevision}
            {#if activeSection === 'reports'}
                <ReportsPanel {alerts} {warehouse} {navigate} {notify} />
            {:else}
                <DeviceInventory
                    devices={inventory[activeSection] ?? []}
                    switchGroups={activeSection === 'cctv'
                        ? (cctv.meta.switchGroups ?? [])
                        : []}
                    {activeSection}
                    {currentNavigation}
                    {alerts}
                    {initialDeviceId}
                />
            {/if}
        {/key}
    {/if}
</MonitoringLayout>

{#if toastMessage}
    <ToastMessage message={toastMessage} dismiss={() => (toastMessage = '')} />
{/if}
