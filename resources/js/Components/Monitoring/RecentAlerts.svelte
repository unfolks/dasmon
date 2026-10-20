<script>
    import StatusBadge from './StatusBadge.svelte';
    import Icon from '../../MonitoringIcon.svelte';
    let { alerts, offlineCameraCount, unknownCameraCount, cameraCount, navigate } = $props();
</script>

<article class="panel alerts-panel">
    <div class="panel-heading">
        <h2>
            <span class="section-icon alert-icon"><Icon name="bell" size={18} /></span>Recent Alerts
            <span class="count-pill">{alerts.length}</span>
        </h2>
        <button class="outline-button compact" onclick={() => navigate('reports')}
            >View All<Icon name="chevron" size={14} /></button
        >
    </div>
    <div class="table-scroll">
        <table class="alerts-table">
            <thead
                ><tr
                    ><th>Time</th><th>Device</th><th>Category</th><th>Message</th><th>Status</th
                    ></tr
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
                                onclick={() => navigate(alert.section, alert.device)}
                                >{alert.device}</button
                            ></td
                        ><td>{alert.category}</td><td>{alert.message}</td><td
                            ><StatusBadge status={alert.status} /></td
                        ></tr
                    >{/each}</tbody
            >
        </table>
    </div>
    <div class="panel-footnote">
        <span
            class="status-dot"
            class:orange={offlineCameraCount > 0 || unknownCameraCount > 0}
            class:green={cameraCount > 0 && offlineCameraCount === 0 && unknownCameraCount === 0}
        ></span>{offlineCameraCount
            ? `${offlineCameraCount} cameras do not respond to ping`
            : unknownCameraCount
              ? `${unknownCameraCount} camera statuses are unknown`
              : cameraCount
                ? 'All cameras respond to ping'
                : 'Camera monitoring data is not available'}<span>View devices for details</span>
    </div>
</article>
