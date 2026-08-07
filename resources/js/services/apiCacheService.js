import axios from 'axios';

const CACHE_PREFIX = 'tso_cache:';
const BULK_PREFIX = 'tso_bulk:';
const DATA_VERSION_PREFIX = 'tso_data_version:';
const VERSION_CHECKED_AT_PREFIX = 'tso_data_version_checked_at:';
const CACHE_STRATEGY_KEY = 'tso_market_cache_strategy';
// v3: cache-first responses backed by lightweight server-version checks.
const CACHE_SCHEMA_VERSION = 3;
const VERSION_CHECK_INTERVAL_MS = 60000;

export function getMarketCacheStrategy() {
    try {
        if (typeof window !== 'undefined' && window.__MARKET_CACHE_STRATEGY__) {
            return window.__MARKET_CACHE_STRATEGY__ === 'individual' ? 'individual' : 'bulk';
        }
        const stored = localStorage.getItem(CACHE_STRATEGY_KEY);
        if (stored) {
            return stored === 'individual' ? 'individual' : 'bulk';
        }
        return 'individual';
    } catch {
        return 'individual';
    }
}

export function setMarketCacheStrategy(strategy) {
    try {
        const validStrategy = strategy === 'individual' ? 'individual' : 'bulk';
        if (typeof window !== 'undefined') {
            window.__MARKET_CACHE_STRATEGY__ = validStrategy;
        }
        localStorage.setItem(CACHE_STRATEGY_KEY, validStrategy);
        return validStrategy;
    } catch {
        return 'individual';
    }
}


// Deduplicates concurrent requests/revalidations per cache key.
const inFlightRequests = new Map();
const inFlightVersionChecks = new Map();

function getKnownServerVersion(serverId) {
    if (!serverId) return 0;
    try {
        return parseInt(localStorage.getItem(`${DATA_VERSION_PREFIX}${serverId}`) || '0', 10);
    } catch {
        return 0;
    }
}

function getLastVersionCheckAt(serverId) {
    if (!serverId) return 0;
    try {
        return parseInt(localStorage.getItem(`${VERSION_CHECKED_AT_PREFIX}${serverId}`) || '0', 10);
    } catch {
        return 0;
    }
}

function setLastVersionCheckAt(serverId, timestamp) {
    if (!serverId || !timestamp) return;
    try {
        localStorage.setItem(`${VERSION_CHECKED_AT_PREFIX}${serverId}`, String(timestamp));
    } catch {
        // Ignore storage errors
    }
}

/**
 * Remove every cached response belonging to a server. Called when the server
 * announces a newer data version: all older entries are stale by definition.
 */
function purgeServerEntries(serverId) {
    try {
        const marker = `"server_id":"${serverId}"`;
        const bulkMarker = `${BULK_PREFIX}${serverId}:`;
        const keysToRemove = [];
        for (let i = 0; i < localStorage.length; i++) {
            const key = localStorage.key(i);
            if (
                key &&
                (
                    (key.startsWith(CACHE_PREFIX) && key.includes(marker)) ||
                    key.startsWith(bulkMarker)
                )
            ) {
                keysToRemove.push(key);
            }
        }
        keysToRemove.forEach(k => localStorage.removeItem(k));
    } catch {
        // Ignore storage errors
    }
}

function updateKnownServerVersion(serverId, version) {
    if (!serverId || !version) return;
    try {
        const current = getKnownServerVersion(serverId);
        if (version > current) {
            localStorage.setItem(`${DATA_VERSION_PREFIX}${serverId}`, String(version));
            purgeServerEntries(serverId);
            return;
        }

        if (current === 0) {
            localStorage.setItem(`${DATA_VERSION_PREFIX}${serverId}`, String(version));
        }
    } catch {
        // Ignore storage errors
    }
}

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

function readEntry(key) {
    try {
        const raw = localStorage.getItem(key);
        if (!raw) return null;
        const parsed = JSON.parse(raw);
        return parsed && parsed.v === CACHE_SCHEMA_VERSION ? parsed : null;
    } catch {
        return null;
    }
}

function writeEntry(key, dataVersion, payload) {
    const serialized = JSON.stringify({
        v: CACHE_SCHEMA_VERSION,
        dataVersion,
        savedAt: Date.now(),
        payload,
    });
    try {
        localStorage.setItem(key, serialized);
    } catch {
        purgeOldestEntries();
        try {
            localStorage.setItem(key, serialized);
        } catch {
            // Storage full or unavailable
        }
    }
}

function clonePayload(payload) {
    if (payload === null || payload === undefined) return payload;
    return JSON.parse(JSON.stringify(payload));
}

function adjustPayloadForAge(url, payload, savedAt) {
    const adjusted = clonePayload(payload);
    if (!adjusted || !savedAt || !url.includes('/market/analytics')) {
        return adjusted;
    }

    const ageSeconds = Math.max(0, Math.floor((Date.now() - savedAt) / 1000));
    if (ageSeconds === 0 || !Array.isArray(adjusted.active_offers)) {
        return adjusted;
    }

    adjusted.active_offers = adjusted.active_offers.map(offer => {
        if (!offer || typeof offer !== 'object') {
            return offer;
        }

        const timeLeft = Number(offer.time_left);
        if (!Number.isFinite(timeLeft)) {
            return offer;
        }

        return {
            ...offer,
            time_left: Math.max(0, timeLeft - ageSeconds),
        };
    });

    return adjusted;
}

function fetchNetwork(url, params, cacheKey) {
    if (inFlightRequests.has(cacheKey)) {
        return inFlightRequests.get(cacheKey);
    }

    const request = axios
        .get(url, { params })
        .then(res => {
            const payload = res.data;
            const headerVersion = res.headers['x-data-version'];
            const serverVersion = headerVersion ? parseInt(headerVersion, 10) : null;
            if (params?.server_id && serverVersion) {
                updateKnownServerVersion(params.server_id, serverVersion);
            }
            writeEntry(cacheKey, serverVersion, payload);
            return payload;
        })
        .finally(() => inFlightRequests.delete(cacheKey));

    inFlightRequests.set(cacheKey, request);
    return request;
}

function getVersionUrlForEndpoint(url) {
    return url.startsWith('/api/public/market/')
        ? '/api/public/market/version'
        : '/api/market/version';
}

async function checkServerVersion(url, serverId, options = {}) {
    if (!serverId) return null;

    const { force = false } = options;
    const now = Date.now();
    const requestKey = `${getVersionUrlForEndpoint(url)}:${serverId}`;

    if (!force) {
        const lastCheckedAt = getLastVersionCheckAt(serverId);
        if (lastCheckedAt > 0 && now - lastCheckedAt < VERSION_CHECK_INTERVAL_MS) {
            const knownVersion = getKnownServerVersion(serverId);
            return knownVersion > 0 ? knownVersion : null;
        }
    }

    if (inFlightVersionChecks.has(requestKey)) {
        return inFlightVersionChecks.get(requestKey);
    }

    const request = axios
        .get(getVersionUrlForEndpoint(url), {
            params: { server_id: serverId },
            headers: { 'Cache-Control': 'no-cache' },
        })
        .then(res => {
            const rawVersion = res.data?.data_version ?? res.headers['x-data-version'] ?? null;
            const version = rawVersion !== null ? parseInt(rawVersion, 10) : null;
            if (Number.isFinite(version) && version > 0) {
                updateKnownServerVersion(serverId, version);
            }
            setLastVersionCheckAt(serverId, Date.now());
            return Number.isFinite(version) ? version : null;
        })
        .finally(() => inFlightVersionChecks.delete(requestKey));

    inFlightVersionChecks.set(requestKey, request);
    return request;
}

function refreshIfVersionChanged(url, params, cacheKey, cachedEntry, onRevalidate) {
    const serverId = params?.server_id;
    if (!serverId || !cachedEntry) {
        return;
    }

    const cachedSerialized = JSON.stringify(cachedEntry.payload);

    checkServerVersion(url, serverId)
        .then(version => {
            if (version === null || (cachedEntry.dataVersion || 0) >= version) {
                return null;
            }

            return fetchNetwork(url, params, cacheKey).then(freshData => {
                if (
                    onRevalidate &&
                    freshData !== undefined &&
                    freshData !== null &&
                    JSON.stringify(freshData) !== cachedSerialized
                ) {
                    onRevalidate(freshData);
                }

                return freshData;
            });
        })
        .catch(() => {});
}

/**
 * Perform a GET request using a cache-first policy over localStorage.
 *
 * Contract:
 * - Cached market responses are returned instantly when their stored server
 *   version is not known to be stale.
 * - A lightweight `/version` request checks freshness in the background.
 * - Heavy data endpoints reload only after a version bump or when bypassing.
 * - `time_left` values are adjusted locally from `savedAt` so active offers do
 *   not appear frozen after a page refresh.
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

    const serverId = params.server_id;
    const knownVersion = getKnownServerVersion(serverId);

    const cachedEntry = bypass ? null : readEntry(key);
    const isStaleVersion =
        cachedEntry && serverId && knownVersion > 0 && (cachedEntry.dataVersion || 0) < knownVersion;
    const isFresh = cachedEntry && !isStaleVersion && now - cachedEntry.savedAt < ttlMs;
    const canServeCached = cachedEntry && !isStaleVersion && (serverId ? true : (isFresh || onRevalidate));

    if (canServeCached) {
        refreshIfVersionChanged(url, params, key, cachedEntry, onRevalidate);
        return adjustPayloadForAge(url, cachedEntry.payload, cachedEntry.savedAt);
    }

    try {
        return await fetchNetwork(url, params, key);
    } catch (e) {
        if (cachedEntry && !isStaleVersion) {
            return adjustPayloadForAge(url, cachedEntry.payload, cachedEntry.savedAt);
        }
        throw e;
    }
}

export function clearApiCache() {
    try {
        const keysToRemove = [];
        for (let i = 0; i < localStorage.length; i++) {
            const key = localStorage.key(i);
            if (
                key &&
                (
                    key.startsWith(CACHE_PREFIX) ||
                    key.startsWith(DATA_VERSION_PREFIX) ||
                    key.startsWith(VERSION_CHECKED_AT_PREFIX) ||
                    key.startsWith(BULK_PREFIX)
                )
            ) {
                keysToRemove.push(key);
            }
        }
        keysToRemove.forEach(k => localStorage.removeItem(k));
    } catch {
        // Ignore
    }
}

function buildBulkKey(serverId, locale) {
    return `${BULK_PREFIX}${serverId}:${locale}`;
}

function readBulkEntry(serverId) {
    try {
        const locale = localStorage.getItem('app_locale') || 'RU';
        const key = buildBulkKey(serverId, locale);
        const raw = localStorage.getItem(key);
        if (!raw) return null;
        const parsed = JSON.parse(raw);
        if (!parsed || parsed.v !== CACHE_SCHEMA_VERSION) return null;
        
        // Check if TTL has expired
        const age = Date.now() - parsed.savedAt;
        if (parsed.ttlMs && age > parsed.ttlMs) return null;
        
        return parsed;
    } catch {
        return null;
    }
}

function writeBulkEntry(serverId, payload, ttlMs) {
    const locale = localStorage.getItem('app_locale') || 'RU';
    const key = buildBulkKey(serverId, locale);
    const serialized = JSON.stringify({
        v: CACHE_SCHEMA_VERSION,
        savedAt: Date.now(),
        ttlMs,
        payload,
    });
    try {
        localStorage.setItem(key, serialized);
    } catch {
        purgeOldestEntries();
        try {
            localStorage.setItem(key, serialized);
        } catch {
            // Storage full
        }
    }
}

export async function cachedGetBulk(url, serverId, options = {}) {
    const { bypass = false, onRevalidate = null } = options;
    
    if (!bypass) {
        const cached = readBulkEntry(serverId);
        if (cached) {
            // Adjust time_left on active_offers
            const adjusted = clonePayload(cached.payload);
            if (adjusted && Array.isArray(adjusted.active_offers)) {
                const ageSeconds = Math.max(0, Math.floor((Date.now() - cached.savedAt) / 1000));
                adjusted.active_offers = adjusted.active_offers.map(offer => {
                    if (offer && offer.expires_at) {
                        const expiresAt = new Date(offer.expires_at).getTime();
                        const timeLeft = Math.max(0, Math.floor((expiresAt - Date.now()) / 1000));
                        return { ...offer, time_left: timeLeft };
                    }
                    return offer;
                });
            }
            
            // Background revalidation via version check
            if (serverId) {
                checkServerVersion(url, serverId)
                    .then(version => {
                        const knownVersion = getKnownServerVersion(serverId);
                        // If server has newer data, re-fetch bulk
                        if (version !== null && version > (cached.payload?.data_version || 0)) {
                            return axios.get(url, { params: { server_id: serverId } }).then(res => {
                                const freshData = res.data;
                                const ttlMs = (freshData.cache_ttl_seconds || 300) * 1000;
                                writeBulkEntry(serverId, freshData, ttlMs);
                                if (onRevalidate && freshData) {
                                    onRevalidate(freshData);
                                }
                                const headerVersion = res.headers['x-data-version'];
                                if (headerVersion) {
                                    updateKnownServerVersion(serverId, parseInt(headerVersion, 10));
                                }
                            });
                        }
                    })
                    .catch(() => {});
            }
            
            return adjusted;
        }
    }
    
    // No cache or bypass - fetch from network
    try {
        const res = await axios.get(url, { params: { server_id: serverId } });
        const payload = res.data;
        const ttlMs = (payload.cache_ttl_seconds || 300) * 1000;
        writeBulkEntry(serverId, payload, ttlMs);
        
        const headerVersion = res.headers['x-data-version'];
        if (serverId && headerVersion) {
            updateKnownServerVersion(serverId, parseInt(headerVersion, 10));
        }
        
        return payload;
    } catch (e) {
        // Fallback to cached data even if expired
        const cached = readBulkEntry(serverId);
        if (cached) {
            return clonePayload(cached.payload);
        }
        throw e;
    }
}

/**
 * Read bulk data from localStorage without any network requests.
 * Returns null if no cached bulk data is available.
 */
export function readBulkCache(serverId) {
    const entry = readBulkEntry(serverId);
    if (!entry) return null;
    
    const adjusted = clonePayload(entry.payload);
    if (adjusted && Array.isArray(adjusted.active_offers)) {
        adjusted.active_offers = adjusted.active_offers.map(offer => {
            if (offer && offer.expires_at) {
                const expiresAt = new Date(offer.expires_at).getTime();
                const timeLeft = Math.max(0, Math.floor((expiresAt - Date.now()) / 1000));
                return { ...offer, time_left: timeLeft };
            }
            return offer;
        });
    }
    
    return adjusted;
}
