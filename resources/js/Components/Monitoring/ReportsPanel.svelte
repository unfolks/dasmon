<script>
    import StatusBadge from './StatusBadge.svelte';
    import EmptyState from './EmptyState.svelte';
    import Icon from '../../MonitoringIcon.svelte';
    import { downloadAlertReport } from '../../monitoring-reports.js';
    import { filterAlerts } from '../../monitoring-filters.js';
    let { alerts, warehouse, navigate, notify } = $props();
    let search = $state('');
    let statusFilter = $state('all');
    let filteredAlerts = $derived(filterAlerts(alerts, search, statusFilter));
    function exportReport() {
        downloadAlertReport(filteredAlerts, warehouse);
        notify('Demo report exported as CSV.');
    }
</script>

<section class="report-summary">
    {#each [{ label: 'Total alerts', value: alerts.length, icon: 'bell', color: 'alert-icon' }, { label: 'Critical', value: alerts.filter((item) => item.status === 'Critical').length, icon: 'signalOff', color: 'critical-icon' }, { label: 'Warnings', value: alerts.filter((item) => item.status === 'Warning').length, icon: 'temperature', color: 'warning-icon' }, { label: 'Resolved', value: alerts.filter((item) => item.status === 'Resolved').length, icon: 'check', color: 'green-icon' }] as stat}<article
            class="panel report-stat"
        >
            <span class="metric-icon {stat.color}"><Icon name={stat.icon} size={24} /></span>
            <div><span>{stat.label}</span><strong>{stat.value}</strong></div>
        </article>{/each}
</section>

<section class="panel inventory-panel">
    <div class="panel-heading report-toolbar">
        <h2>Alert history<span class="count-pill">{filteredAlerts.length}</span></h2>
        <div class="table-controls">
            <label class="search-field"
                ><input
                    aria-label="Search alerts"
                    placeholder="Search alerts..."
                    bind:value={search}
                /><Icon name="search" size={17} /></label
            ><label class="compact-select"
                ><select aria-label="Alert severity" bind:value={statusFilter}
                    ><option value="all">All Severities</option><option>Critical</option><option
                        >Warning</option
                    ><option>Resolved</option></select
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
                    ><th>Time</th><th>Device</th><th>Category</th><th>Message</th><th>Status</th><th
                        >Action</th
                    ></tr
                ></thead
            ><tbody
                >{#each filteredAlerts as alert}<tr
                        ><td>{alert.time}</td><td
                            ><button
                                class="device-link"
                                onclick={() => navigate(alert.section, alert.device)}
                                >{alert.device}</button
                            ></td
                        ><td>{alert.category}</td><td>{alert.message}</td><td
                            ><StatusBadge status={alert.status} /></td
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
                            ><EmptyState
                                title="No matching alerts"
                                description="Try another search or severity."
                                clearFilters={() => {
                                    search = '';
                                    statusFilter = 'all';
                                }}
                            /></td
                        ></tr
                    >{/each}</tbody
            >
        </table>
    </div>
    <div class="table-footer">
        <span>{filteredAlerts.length} demo alerts · 30 Sep 2026</span><span>{warehouse.name}</span>
    </div>
</section>
