/**
 * Reusable display-name helpers for game entities (resources, buildings,
 * specialists, buffs). Single source of truth: the generated locale catalog
 * (game sections BUI / LAB / RES / SPE) with a "humanize the raw id"
 * fallback so unknown ids still render readably.
 *
 * Usage:
 *   import { resourceName, buildingName, humanizeGameId } from '../lang/gameNames';
 *   resourceName('CollectibleAdamantium')      // "Adamantium Ore" / "\u0410\u0434\u0430\u043c\u0430\u043d\u0442\u043e\u0432\u0430\u044f \u0440\u0443\u0434\u0430"
 *   buildingName(b)                            // accepts a raw id or a building object
 */
import { gameAnyLookup } from './index';

/** Legacy prettifier: CamelCase/underscores -> "Title Case" words. */
export function humanizeGameId(id) {
    if (!id) return '';
    return String(id)
        .replace(/(?<!^)(?=[A-Z])/g, ' ')
        .replace(/_/g, ' ')
        .trim()
        .replace(/\w\S*/g, (w) => w.replace(/^\w/, (c) => c.toUpperCase()));
}

/** Resource / buff / any catalog id -> localized name (catalog first). */
export function resourceName(id) {
    if (!id) return '';
    return gameAnyLookup(String(id)) ?? humanizeGameId(id);
}

/** Strip level / decoration suffixes from a raw building id. */
export function buildingBaseId(raw) {
    return String(raw || '').replace(/_lvl_\d+/i, '').replace(/decoration_/gi, '').trim();
}

/**
 * Building id or object ({ buildingName_string, buildingName }) -> localized name.
 */
export function buildingName(rawOrObject) {
    const raw = rawOrObject && typeof rawOrObject === 'object'
        ? (rawOrObject.buildingName_string || rawOrObject.buildingName || '')
        : (rawOrObject || '');
    if (!raw) return '';
    return gameAnyLookup(raw) ?? gameAnyLookup(buildingBaseId(raw)) ?? humanizeGameId(raw);
}

export default { humanizeGameId, resourceName, buildingBaseId, buildingName };
