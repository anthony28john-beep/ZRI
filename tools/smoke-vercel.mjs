import assert from 'node:assert/strict';
import { generateKeyPairSync, randomBytes } from 'node:crypto';
import { spawnSync } from 'node:child_process';

const image = process.argv[2] ?? 'zerorisk-vercel:local';
const name = `zerorisk-smoke-${randomBytes(5).toString('hex')}`;
const containers = [];
let networkCreated = false;

function docker(args, env = {}, optional = false) {
    const result = spawnSync('docker', args, { encoding: 'utf8', env: { ...process.env, ...env }, timeout: 180000, maxBuffer: 4 * 1024 * 1024 });
    if (!optional && (result.error || result.status !== 0)) {
        throw new Error(result.error?.message ?? result.stderr.trim() ?? 'Docker command failed.');
    }
    return result.stdout?.trim() ?? '';
}

async function ready(base) {
    for (let attempt = 0; attempt < 40; attempt++) {
        try {
            const response = await fetch(`${base}/up`, { signal: AbortSignal.timeout(2000) });
            if (response.status === 200) return;
        } catch {}
        await new Promise(resolve => setTimeout(resolve, 500));
    }
    throw new Error('Container health endpoint did not become ready.');
}

try {
    docker(['image', 'inspect', image]);
    docker(['run', '--rm', '--entrypoint', 'php', image, '-r', `
        foreach (['/app/.env', '/app/storage/app/firebase/firebase-credentials.json', '/app/bootstrap/cache/config.php', '/app/database/database.sqlite', '/app/public/hot'] as $path) {
            if (file_exists($path)) { fwrite(STDERR, 'Unexpected packaged local configuration.'); exit(1); }
        }
        foreach (['redis', 'openssl', 'mbstring', 'curl'] as $extension) {
            if (!extension_loaded($extension)) { fwrite(STDERR, 'Missing runtime extension.'); exit(1); }
        }
    `]);
    console.log('PASS: runtime extensions and packaged-file checks');

    docker(['network', 'create', name]);
    networkCreated = true;
    const redis = `${name}-redis`;
    containers.push(redis);
    docker(['run', '-d', '--name', redis, '--network', name, '--network-alias', 'cache', 'redis:7-alpine']);

    // These synthetic credentials cannot access a real Firebase project.
    const { privateKey } = generateKeyPairSync('rsa', { modulusLength: 2048, privateKeyEncoding: { type: 'pkcs8', format: 'pem' }, publicKeyEncoding: { type: 'spki', format: 'pem' } });
    const environment = {
        APP_NAME: 'ZeroRiskSmoke', APP_KEY: `base64:${randomBytes(32).toString('base64')}`,
        APP_URL: 'http://127.0.0.1', FIREBASE_PROJECT_ID: 'zerorisk-smoke-test',
        FIREBASE_DATABASE_URL: 'https://zerorisk-smoke-test.firebaseio.com',
        FIREBASE_CREDENTIALS_JSON: JSON.stringify({ type: 'service_account', project_id: 'zerorisk-smoke-test', private_key_id: 'smoke-test', private_key: privateKey, client_email: 'smoke@zerorisk-smoke-test.iam.gserviceaccount.com', client_id: '123456789', token_uri: 'https://oauth2.googleapis.com/token' }),
        REDIS_URL: 'redis://cache:6379/0', REDIS_PREFIX: `${name}-`, CACHE_PREFIX: `${name}-cache-`,
    };
    const bases = [];
    for (const suffix of ['one', 'two']) {
        const container = `${name}-${suffix}`;
        containers.push(container);
        // Exercise Vercel's default port and an explicit runtime PORT override.
        const internalPort = suffix === 'one' ? 80 : 8080;
        const args = ['run', '-d', '--name', container, '--network', name, '-p', `127.0.0.1::${internalPort}`];
        if (suffix === 'two') args.push('-e', 'PORT=8080');
        for (const variable of Object.keys(environment)) args.push('-e', variable);
        docker([...args, image], environment);
        const port = docker(['port', container, `${internalPort}/tcp`]).match(/:(\d+)$/)?.[1];
        assert.ok(port, 'Expected a localhost-only mapped port.');
        const base = `http://127.0.0.1:${port}`;
        await ready(base);
        bases.push(base);
    }
    console.log('PASS: both container instances return healthy responses');

    const headers = { 'X-Forwarded-Proto': 'https', 'X-Forwarded-For': '203.0.113.10' };
    const login = await fetch(`${bases[0]}/login`, { headers });
    assert.equal(login.status, 200);
    const html = await login.text();
    assert.match(html, /action="https:\/\//, 'HTTPS proxy headers must produce HTTPS form URLs.');
    const csrf = html.match(/name="_token" value="([^"]+)"/)?.[1];
    assert.ok(csrf, 'Expected a CSRF token.');
    const setCookies = login.headers.getSetCookie();
    assert.ok(setCookies.some(cookie => /;\s*secure/i.test(cookie)), 'Expected secure session cookies.');
    const cookies = setCookies.map(cookie => cookie.split(';')[0]).join('; ');
    const asset = html.match(/href="([^"]+\/build\/assets\/[^"?]+\.css[^\"]*)"/)?.[1];
    assert.ok(asset, 'Expected a built CSS asset.');
    const css = await fetch(`${bases[0]}${new URL(asset).pathname}`);
    assert.equal(css.status, 200);
    assert.match(css.headers.get('content-type'), /text\/css/);
    for (const path of ['/.env', '/storage/app/firebase/firebase-credentials.json']) {
        assert.equal((await fetch(`${bases[0]}${path}`)).status, 404);
    }
    console.log('PASS: login, built assets, secure cookies, HTTPS URLs, and private paths');

    // Invalid form data stops before Firebase sign-in; the synthetic account has no live Firebase access.
    for (let attempt = 0; attempt < 6; attempt++) {
        const response = await fetch(`${bases[attempt % 2]}/login`, {
            method: 'POST', headers: { ...headers, Cookie: cookies, Accept: 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ _token: csrf, email: 'not-an-email', password: '' }),
        });
        assert.equal(response.status, attempt < 5 ? 422 : 429, 'Expected shared cookie sessions and Redis throttling across instances.');
    }
    console.log('PASS: CSRF session survives instance changes; login limits are shared through Redis');
} catch (error) {
    console.error(`FAIL: ${error.message}`);
    for (const container of containers.filter(container => !container.endsWith('-redis'))) {
        console.error(docker(['inspect', '--format', '{{.State.Status}} (exit {{.State.ExitCode}})', container], {}, true));
        console.error(docker(['logs', '--tail', '12', container], {}, true));
    }
    process.exitCode = 1;
} finally {
    for (const container of containers.reverse()) docker(['rm', '-f', container], {}, true);
    if (networkCreated) docker(['network', 'rm', name], {}, true);
}
