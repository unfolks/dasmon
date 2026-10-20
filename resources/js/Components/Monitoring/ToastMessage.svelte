<script>
    import Icon from '../../MonitoringIcon.svelte';
    let { message, dismiss } = $props();
</script>

<div class="toast" role="status">
    <Icon name="check" size={19} /><span>{message}</span><button
        aria-label="Dismiss notification"
        onclick={dismiss}><Icon name="close" size={16} /></button
    >
</div>
