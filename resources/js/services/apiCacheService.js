import axios from 'axios';

const CACHE_PREFIX = 'tso_cache:';
const CACHE_SCHEMA_VERSION = 1;

/**
 * Generate canonical storage key from URL and params.
 */
function buildKey(url, params = {}, locale = 'RU') {
    const sortedParams = Object.keys(params)
        .sort()
        .reduce((acc, key) => {
            acc[key] = params[key];
            return acc;
        }, {});
    const hashStr = JSON.stringify(sortedParams);
    return `${CACHE_PREFIX}${url}:${locale}:${hashStr}`;
}

/**
 * Safely purge oldest items if localStorage is near quota limits.
 */
function purgeOldestEntries() {
    try {
        const cacheEntries = [];
        for (let i = 0; i < localStorage.length; i++) {
            const key = localStorage.key(i);
            if (key && key.startsWith(CACHE_PREFIX)) {
                try {
                    const item = JSON.parse(localStorage.getItem(key));
                    if (item && item.savedAt) {
                        cacheEntries.push({ key, savedAt: item.savedAt });
                    }
                } catch {
                    cacheEntries.push({ key, savedAt: 0 });
                }
            }
        }
        cacheEntries.sort((a, b) => a.savedAt - b.savedAt);
        cacheEntries.slice(0, 5).forEach(e => localStorage.removeItem(e.key));
    } catch {
        // Ignore storage errors
    }
}

/**
 * Perform a GET request using Stale-While-Revalidate (SWR) cache policy over localStorage.
 *
 * @param {string} url
 * @param {Object} options
 * @param {Object} options.params
 * @param {number} options.ttlMs - Freshness TTL in ms (default 5 mins)
 * @param {boolean} options.bypass - Force network request
 * @param {Function} options.onRevalidate - Callback when revalidated data arrives
 */
export async function cachedGet(url, options = {}) {
    const { params = {}, ttlMs = 300000, bypass = false, onRevalidate = null } = options;
    const locale = localStorage.getItem('app_locale') || 'RU';
    const key = buildKey(url, params, locale);
    const now = Date.now();

    let cachedEntry = null;
    if (!bypass) {
        try {
            const raw = localStorage.getItem(key);
            if (raw) {
                const parsed = JSON.parse(raw);
                if (parsed.v === CACHE_SCHEMA_VERSION) {
                    cachedEntry = parsed;
                }
            }
        } catch {
            cachedEntry = null;
        }
    }

    const isFresh = cachedEntry && (now - cachedEntry.savedAt < ttlMs);

    if (isFresh) {
        if (onRevalidate) {
            fetchNetwork(url, params, key, cachedEntry.dataVersion)
                .then(freshData => {
                    if (freshData !== null) {
                        onRevalidate(freshData);
                    }
                })
                .catch(() => {});
        }
        return cachedEntry.payload;
    }

    if (cachedEntry && onRevalidate) {
        fetchNetwork(url, params, key, cachedEntry.dataVersion)
            .then(freshData => {
                if (freshData !== null) {
                    onRevalidate(freshData);
                }
            })
            .catch(() => {});

        return cachedEntry.payload;
    }

    const freshPayload = await fetchNetwork(url, params, key, cachedEntry?.dataVersion);
    return freshPayload ?? cachedEntry?.payload;
}

async function fetchNetwork(url, params, cacheKey, currentVersion = null) {
    const res = await axios.get(url, { params });
    const payload = res.data;
    const serverVersion = res.headers['x-data-version'] ? parseInt(res.headers['x-data-version'], 10) : null;

    try {
        const cacheObj = JSON.stringify({
            v: CACHE_SCHEMA_VERSION,
            dataVersion: serverVersion,
            savedAt: Date.now(),
            payload,
        });
        localStorage.setItem(cacheKey, cacheObj);
    } catch (e) {
        purgeOldestEntries();
        try {
            const cacheObj = JSON.stringify({
                v: CACHE_SCHEMA_VERSION,
                dataVersion: serverVersion,
                savedAt: Date.now(),
                payload,
            });
            localStorage.setItem(cacheKey, cacheObj);
        } catch {
            // Storage full or unavailable
        }
    }

    return payload;
}

export function clearApiCache() {
    try {
        const keysToRemove = [];
        for (let i = 0; i < localStorage.length; i++) {
            const key = localStorage.key(i);
            if (key && key.startsWith(CACHE_PREFIX)) {
                keysToRemove.push(key);
            }
        }
        keysToRemove.forEach(k => localStorage.removeItem(k));
    } catch {
        // Ignore
    }
}
