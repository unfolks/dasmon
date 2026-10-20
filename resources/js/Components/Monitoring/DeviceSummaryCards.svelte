<script>
    import Icon from '../../MonitoringIcon.svelte';
    let { inventory, navigate } = $props();
    let cameraOnline = $derived(inventory.cctv.filter((item) => item.status === 'Online').length);
    let cameraOffline = $derived(inventory.cctv.filter((item) => item.status === 'Offline').length);
    let cameraUnknown = $derived(inventory.cctv.filter((item) => item.status === 'Unknown').length);
    let computerOnline = $derived(
        inventory.computers.filter((item) => item.isReachable === true).length,
    );
    let computerOffline = $derived(
        inventory.computers.filter((item) => item.status === 'Offline').length,
    );
    let computerWarning = $derived(
        inventory.computers.filter((item) => item.status === 'Warning').length,
    );
    let computerUnknown = $derived(
        inventory.computers.filter((item) => item.status === 'Unknown').length,
    );
    let accessNormal = $derived(inventory.access.filter((item) => item.status === 'Online').length);
    let accessReachable = $derived(
        inventory.access.filter((item) => item.isReachable === true).length,
    );
    let accessLow = $derived(inventory.access.filter((item) => item.health === 'Low').length);
    let accessLoss = $derived(inventory.access.filter((item) => item.health === 'Loss').length);
    let accessUnknown = $derived(
        inventory.access.filter((item) => item.health === 'Unknown').length,
    );
</script>

<section class="overview-grid" aria-label="Hardware status overview">
    <button class="panel metric-card" onclick={() => navigate('cctv')}
        ><span class="metric-icon"><Icon name="camera" size={29} /></span>
        <div class="metric-content">
            <div class="metric-heading">
                <h2>CCTV</h2>
                <Icon name="chevron" size={17} />
            </div>
            <div class="metric-value">
                <strong>{inventory.cctv.length ? cameraOnline : '—'}</strong><span>/ {inventory.cctv.length}</span>
            </div>
            <div class="metric-description">
                <span>Online · ICMP</span><small
                    ><i class="status-square red"></i>{cameraOffline} Offline</small
                >
            </div>
            <div class="progress-track">
                <span
                    class="green-bar"
                    style:width={`${inventory.cctv.length ? (cameraOnline / inventory.cctv.length) * 100 : 0}%`}
                ></span><span
                    class="red-bar"
                    style:width={`${inventory.cctv.length ? (cameraOffline / inventory.cctv.length) * 100 : 0}%`}
                ></span>
            </div>
            <div class="status-legend"><span>Unknown <b>{cameraUnknown}</b></span></div>
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
                <strong>{inventory.computers.length ? computerOnline : '—'}</strong><span
                    >/ {inventory.computers.length}</span
                >
            </div>
            <div class="metric-description">
                <span>Reachable · Zabbix</span><small
                    ><i class="status-square red"></i>{computerOffline} Offline</small
                >
            </div>
            <div class="progress-track">
                <span
                    class="green-bar"
                    style:width={`${inventory.computers.length ? (computerOnline / inventory.computers.length) * 100 : 0}%`}
                ></span><span
                    class="red-bar"
                    style:width={`${inventory.computers.length ? (computerOffline / inventory.computers.length) * 100 : 0}%`}
                ></span>
            </div>
            <div class="status-legend">
                <span><i class="status-square orange"></i>Warning <b>{computerWarning}</b></span
                ><span>Unknown <b>{computerUnknown}</b></span>
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
                <strong>{inventory.access.length ? accessReachable : '—'}</strong><span
                    >/ {inventory.access.length}</span
                >
            </div>
            <div class="metric-description"><span>Reachable · Zabbix</span></div>
            <div class="status-legend">
                <span
                    ><i class="status-square green"></i>Normal
                    <b>{accessNormal}</b></span
                ><span><i class="status-square orange"></i>Low <b>{accessLow}</b></span><span
                    ><i class="status-square red"></i>Loss <b>{accessLoss}</b></span
                >
                {#if accessUnknown}<span>Unknown <b>{accessUnknown}</b></span>{/if}
            </div>
        </div></button
    >
</section>
