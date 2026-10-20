<script>
    import Icon from '../../MonitoringIcon.svelte';
    let { selectedDevice } = $props();
</script>

<div class="camera-preview" class:no-signal={selectedDevice.status === 'Offline'}>
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
            name={selectedDevice.status === 'Offline' ? 'signalOff' : 'camera'}
            size={44}
            stroke={1.4}
        /><strong>{selectedDevice.status === 'Offline' ? 'No Signal' : 'Camera preview'}</strong
        ><span
            >{selectedDevice.status === 'Offline'
                ? 'Camera is currently disconnected'
                : 'Connect a camera stream to enable live view'}</span
        >
    </div>
    <div class="preview-bottom">
        <span>{selectedDevice.location}</span><span>4 MP · H.265</span>
    </div>
</div>
<div class="camera-health">
    <span><Icon name="clock" size={15} />Last seen</span><strong>{selectedDevice.lastSeen}</strong>
</div>
<div class="detail-notice" class:danger={selectedDevice.status === 'Offline'}>
    <Icon name={selectedDevice.status === 'Offline' ? 'signalOff' : 'shield'} size={18} /><span
        >{selectedDevice.status === 'Offline'
            ? 'Connection lost. Check camera power and network connection.'
            : 'This is a demo device. No live camera stream is connected.'}</span
    >
</div>
