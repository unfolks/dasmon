<script>
    import MonitoringSidebar from '../Components/Monitoring/MonitoringSidebar.svelte';
    import MonitoringHeader from '../Components/Monitoring/MonitoringHeader.svelte';

    let {
        activeSection,
        currentNavigation,
        warehouse,
        offlineCameraCount = 0,
        warehouses,
        warehouseId = $bindable(),
        mobileMenuOpen = $bindable(false),
        updatedAt,
        navigate,
        changeWarehouse,
        refreshPreview,
        children,
    } = $props();
</script>

<svelte:window
    onkeydown={(event) => {
        if (event.key === 'Escape') {
            mobileMenuOpen = false;
        }
    }}
/>

<div class="monitoring-app">
    {#if mobileMenuOpen}
        <button
            class="sidebar-backdrop"
            aria-label="Close navigation"
            onclick={() => (mobileMenuOpen = false)}
        ></button>
    {/if}
    <MonitoringSidebar {activeSection} {offlineCameraCount} {mobileMenuOpen} {navigate} />
    <main class="workspace">
        <MonitoringHeader
            {activeSection}
            {currentNavigation}
            {warehouses}
            bind:warehouseId
            bind:mobileMenuOpen
            {updatedAt}
            {navigate}
            {changeWarehouse}
            {refreshPreview}
        />
        {@render children()}
        <footer class="workspace-footer">
            <span
                ><span class="status-dot blue"></span>CCTV, Access Point, Computers & NAS: Zabbix · Other
                sensors and charts: demo</span
            >
            <span>TCL IT Monitoring<span class="footer-divider">/</span>{warehouse.name}</span>
        </footer>
    </main>
</div>
