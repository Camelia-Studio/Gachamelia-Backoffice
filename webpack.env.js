import { existsSync, readFileSync } from 'node:fs';
import { join } from 'node:path';

export const normalizeBasePath = (value = '') => {
    const path = value.trim();

    if (path === '' || path === '/') {
        return '';
    }

    return `/${path.replace(/^\/+|\/+$/g, '')}`;
};

export const loadSymfonyDotenvFiles = ({ cwd = process.cwd(), target = process.env } = {}) => {
    const environmentKeys = new Set(Object.keys(target));

    loadEnvFile(join(cwd, '.env'), target, environmentKeys);

    const appEnv = target.APP_ENV || 'dev';

    if (appEnv !== 'test') {
        loadEnvFile(join(cwd, '.env.local'), target, environmentKeys);
    }

    loadEnvFile(join(cwd, `.env.${appEnv}`), target, environmentKeys);
    loadEnvFile(join(cwd, `.env.${appEnv}.local`), target, environmentKeys);
};

const loadEnvFile = (filePath, target, protectedKeys) => {
    if (!existsSync(filePath)) {
        return;
    }

    for (const [key, value] of parseEnvFile(readFileSync(filePath, 'utf8'))) {
        if (!protectedKeys.has(key)) {
            target[key] = value;
        }
    }
};

const parseEnvFile = (contents) => {
    const entries = [];

    for (const line of contents.split(/\r?\n/)) {
        const match = line.match(/^\s*(?:export\s+)?([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)?\s*$/);

        if (!match) {
            continue;
        }

        entries.push([match[1], parseEnvValue(match[2] ?? '')]);
    }

    return entries;
};

const parseEnvValue = (value) => {
    const trimmed = value.trim();

    if (
        (trimmed.startsWith('"') && trimmed.endsWith('"'))
        || (trimmed.startsWith("'") && trimmed.endsWith("'"))
    ) {
        return trimmed.slice(1, -1);
    }

    return trimmed.replace(/\s+#.*$/, '');
};
