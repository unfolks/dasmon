const storageKey = 'monitoring-preferences';

export function parseMonitoringPreferences(value, warehouses) {
    const defaults = { defaultWarehouse: warehouses[0].id, temperatureThreshold: 35 };

    try {
        const preferences = JSON.parse(value ?? 'null');

        if (
            !preferences ||
            !warehouses.some((warehouse) => warehouse.id === preferences.defaultWarehouse)
        ) {
            return defaults;
        }

        return {
            defaultWarehouse: preferences.defaultWarehouse,
            temperatureThreshold:
                Number.isFinite(preferences.temperatureThreshold) &&
                preferences.temperatureThreshold >= 25 &&
                preferences.temperatureThreshold <= 60
                    ? preferences.temperatureThreshold
                    : defaults.temperatureThreshold,
        };
    } catch {
        return defaults;
    }
}

export function loadMonitoringPreferences(warehouses) {
    try {
        return parseMonitoringPreferences(localStorage.getItem(storageKey), warehouses);
    } catch {
        return parseMonitoringPreferences(null, warehouses);
    }
}

export function persistMonitoringPreferences(preferences) {
    try {
        localStorage.setItem(storageKey, JSON.stringify(preferences));
        return true;
    } catch {
        return false;
    }
}
