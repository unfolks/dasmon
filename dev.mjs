import { existsSync, rmSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import concurrently from 'concurrently';
import { loadEnv } from 'vite';

const projectPath = fileURLToPath(new URL('.', import.meta.url));
const environment = { ...loadEnv('development', projectPath, ''), ...process.env };
const windowsRuntimePath = new URL('.tools/frankenphp/frankenphp.exe', import.meta.url);

if (process.platform === 'win32' && !existsSync(windowsRuntimePath)) {
    console.error('FrankenPHP Windows belum terpasang di .tools/frankenphp. Ikuti setup di README.md.');
    process.exit(1);
}

const serverBinary = process.platform === 'win32'
    ? '".tools\\frankenphp\\frankenphp.exe"'
    : 'frankenphp';

console.log(`Aplikasi: http://localhost:${environment.APP_PORT || '8000'}`);

const { result } = concurrently([
    {
        name: 'server',
        command: `${serverBinary} run --config Caddyfile --adapter caddyfile`,
        env: {
            ...environment,
            XDG_DATA_HOME: fileURLToPath(new URL('.tools/caddy/data', import.meta.url)),
            XDG_CONFIG_HOME: fileURLToPath(new URL('.tools/caddy/config', import.meta.url)),
        },
    },
    {
        name: 'queue',
        command: 'php artisan queue:listen --tries=1 --timeout=0',
        env: environment,
    },
    {
        name: 'vite',
        command: 'npm run dev',
        env: environment,
    },
], {
    cwd: projectPath,
    prefix: 'name',
    prefixColors: ['blue', 'magenta', 'yellow'],
    killOthersOn: ['failure', 'success'],
});

try {
    await result;
} catch {
    process.exitCode = 1;
} finally {
    rmSync(new URL('public/hot', import.meta.url), { force: true });
}
