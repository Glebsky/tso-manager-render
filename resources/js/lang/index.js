const catalogs = {};

const FALLBACK_LOCALE = 'en';

/**
 * Single source of truth for the languages available in the UI.
 * Every language switcher must render this list; add a new locale here
 * (plus its generated catalog) to expose it everywhere at once.
 */
export const AVAILABLE_LOCALES = [
    { code: 'uk', name: 'Українська', flag: '🇺🇦' },
    { code: 'ru', name: 'Русский', flag: '🇷🇺' },
    { code: 'en', name: 'English', flag: '🇺🇸' },
];

/** Legacy/incorrect locale codes mapped to their canonical form. */
const LOCALE_ALIASES = { ua: 'uk' };

const localeLoaders = {
    en: () => import('./generated/en.json'),
    ru: () => import('./generated/ru.json'),
    uk: () => import('./generated/uk.json'),
};

function detectLocale() {
    const raw = (typeof localStorage !== 'undefined' && localStorage.getItem('app_locale'))
        || (typeof window !== 'undefined' && window.__APP_LOCALE__)
        || (typeof document !== 'undefined' && document.documentElement.lang)
        || FALLBACK_LOCALE;
    const normalized = String(raw).toLowerCase().split(/[-_]/)[0];
    const resolved = LOCALE_ALIASES[normalized] || normalized;

    return localeLoaders[resolved] ? resolved : FALLBACK_LOCALE;
}

export const locale = detectLocale();

export async function initLang(targetLocale = locale) {
    const resolved = LOCALE_ALIASES[targetLocale] || targetLocale;
    const loader = localeLoaders[resolved] || localeLoaders[FALLBACK_LOCALE];

    const promises = [
        loader().then(mod => { catalogs[resolved] = mod.default || mod; })
    ];

    if (resolved !== FALLBACK_LOCALE && !catalogs[FALLBACK_LOCALE]) {
        promises.push(
            localeLoaders[FALLBACK_LOCALE]().then(mod => { catalogs[FALLBACK_LOCALE] = mod.default || mod; })
        );
    }

    await Promise.all(promises);
}

export function setLocale(newLocale) {
    const resolved = LOCALE_ALIASES[newLocale] || newLocale;
    if (AVAILABLE_LOCALES.some(l => l.code === resolved)) {
        if (typeof localStorage !== 'undefined') {
            localStorage.setItem('app_locale', resolved);
        }
        if (typeof document !== 'undefined') {
            document.cookie = `app_locale=${resolved}; path=/; max-age=31536000; SameSite=Lax`;
        }
        if (typeof window !== 'undefined') {
            window.location.reload();
        }
    }
}

const localeChain = locale === FALLBACK_LOCALE ? [locale] : [locale, FALLBACK_LOCALE];

const INTL_LOCALES = { en: 'en-GB', ru: 'ru-RU', uk: 'uk-UA' };

/** BCP-47 locale for Intl / toLocaleString date & number formatting. */
export const intlLocale = INTL_LOCALES[locale] || locale;

/**
 * Order in which game sections are scanned when a legacy caller looks up an
 * id without knowing its section (RES -> BUI -> LAB -> SPE -> ADN).
 */
const GAME_SECTION_LOOKUP_ORDER = ['RES', 'BUI', 'LAB', 'SPE', 'ADN'];

const MAX_PLACEHOLDER_DEPTH = 3;

function getDotPath(obj, key) {
    if (!obj || typeof obj !== 'object') return null;
    if (key in obj && typeof obj[key] === 'string') return obj[key];
    const parts = key.split('.');
    let current = obj;
    for (const part of parts) {
        if (current && typeof current === 'object' && part in current) {
            current = current[part];
        } else {
            return null;
        }
    }
    return typeof current === 'string' ? current : null;
}

/**
 * UI & Tasks translation. Keys live in lang/<locale>/ui.php and lang/<locale>/tasks.php
 * on the backend and are exported into generated catalogs.
 * Named params: t('tasks.error.task_inactive', { id: 5 }) replaces {id}.
 */
export function t(key, params = null) {
    let text = null;

    if (key.startsWith('tasks.')) {
        const subKey = key.substring(6);
        for (const chainLocale of localeChain) {
            const candidate = getDotPath(catalogs[chainLocale]?.tasks, subKey);
            if (typeof candidate === 'string') {
                text = candidate;
                break;
            }
        }
    }

    if (text === null) {
        for (const chainLocale of localeChain) {
            const candidate = catalogs[chainLocale]?.ui?.[key] || getDotPath(catalogs[chainLocale]?.ui, key);

            if (typeof candidate === 'string') {
                text = candidate;
                break;
            }
        }
    }

    if (text === null) {
        return key;
    }

    if (params) {
        text = text.replace(/\{(\w+)\}|:(\w+)/g, (full, name1, name2) => {
            const name = name1 || name2;
            return name in params ? String(params[name]) : full;
        });
    }

    return text;
}

/** Raw game catalog lookup by (section, id); null when missing everywhere. */
export function gameLookup(section, id) {
    if (!id) return null;
    const strId = String(id).trim();
    const lcFirst = strId.charAt(0).toLowerCase() + strId.slice(1);
    const ucFirst = strId.charAt(0).toUpperCase() + strId.slice(1);

    const cleanId = strId.replace(/[\s_]+/g, '');
    const cleanLcFirst = cleanId.charAt(0).toLowerCase() + cleanId.slice(1);
    const cleanUcFirst = cleanId.charAt(0).toUpperCase() + cleanId.slice(1);
    const cleanUcFirstLcRest = cleanId.charAt(0).toUpperCase() + cleanId.slice(1).toLowerCase();

    const candidates = Array.from(new Set([
        strId,
        lcFirst,
        ucFirst,
        cleanId,
        cleanLcFirst,
        cleanUcFirst,
        cleanUcFirstLcRest,
    ]));

    for (const chainLocale of localeChain) {
        const sec = catalogs[chainLocale]?.game?.[section];
        if (sec) {
            for (const cand of candidates) {
                if (typeof sec[cand] === 'string') return sec[cand];
            }

            const idLowerClean = strId.replace(/[\s_]+/g, '').toLowerCase();
            for (const k in sec) {
                if (k.replace(/[\s_]+/g, '').toLowerCase() === idLowerClean && typeof sec[k] === 'string') {
                    return sec[k];
                }
            }
        }
    }

    return null;
}

/** Raw lookup without a known section (or with a custom prioritized section list); null when missing. */
export function gameAnyLookup(id, customSections = null) {
    const sections = Array.isArray(customSections) && customSections.length > 0
        ? customSections
        : GAME_SECTION_LOOKUP_ORDER;

    for (const section of sections) {
        const text = gameLookup(section, id);

        if (text !== null) {
            return text;
        }
    }

    return null;
}

/**
 * Strict game placeholder interpolation: only {N} and {N,SECTION} are
 * interpolated; any other curly-brace construct (e.g. {Christmas}) is kept
 * literally. Typed placeholders resolve their value through the referenced
 * section with a depth limit.
 */
function interpolateGame(template, params, depth = 0) {
    return template.replace(/\{(\d+)(?:,([A-Za-z0-9_]+))?\}/g, (full, index, section) => {
        if (!params || !(index in params)) {
            return full;
        }

        const value = String(params[index]);

        if (!section || depth >= MAX_PLACEHOLDER_DEPTH) {
            return value;
        }

        const nested = gameLookup(section, value);

        return nested === null ? value : interpolateGame(nested, null, depth + 1);
    });
}

/** Game translation by (section, id) with fallback to `fallback` or the raw id. */
export function game(section, id, params = null, fallback = null) {
    const template = gameLookup(section, id);

    if (template === null) {
        return fallback ?? id;
    }

    return interpolateGame(template, params);
}

/** Game translation when the section is unknown (legacy compatibility). */
export function gameAny(id, params = null, fallback = null) {
    const template = gameAnyLookup(id);

    if (template === null) {
        return fallback ?? id;
    }

    return interpolateGame(template, params);
}

export default { locale, intlLocale, AVAILABLE_LOCALES, setLocale, initLang, t, game, gameAny, gameLookup, gameAnyLookup };
