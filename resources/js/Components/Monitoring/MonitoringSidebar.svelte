<script>
    import Icon from '../../MonitoringIcon.svelte';
    import { navigation } from '../../monitoring-navigation.js';
    let { activeSection, offlineCameraCount = 0, mobileMenuOpen, navigate } = $props();
</script>

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
                >{#if item.id === 'cctv' && offlineCameraCount > 0}<span class="nav-count"
                        >{offlineCameraCount}</span
                    >{/if}</a
            >
        {/each}
    </nav>
    <div class="sidebar-bottom">
        <div class="system-state">
            <span class="status-dot green"></span>Local workspace<span class="version">v1.0</span>
        </div>
        <div class="sidebar-profile">
            <div class="avatar">IT</div>
            <div><strong>IT Administrator</strong><span>Monitoring workspace</span></div>
            <Icon name="shield" size={17} />
        </div>
    </div>
</aside>
