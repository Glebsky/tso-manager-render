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
import { isBuffableBuildingName } from './buffTargets';

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

/**
 * Market item -> localized display name. The SINGLE entry point for
 * translating market item names everywhere (admin + public market views).
 * Robust by design: tries the locale catalog by item id first, then by the
 * raw backend-provided name, and finally falls back to the backend name (or
 * a humanized id), so a missing translation can never blank out the UI.
 *
 *   marketItemName(good.item_name, good.item_id)
 */
export function marketItemName(name, id) {
    const rawId = (id === null || id === undefined) ? '' : String(id).trim();
    const rawName = (name === null || name === undefined) ? '' : String(name).trim();
    if (!rawId && !rawName) return '';
    const translated = (rawId ? gameAnyLookup(rawId) : null)
        ?? (rawName ? gameAnyLookup(rawName) : null);
    if (translated) return translated;
    return rawName || humanizeGameId(rawId);
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

const BUILDING_MAP = {
    // Basic
    'mayorhouse': 'Basic', 'storehouse': 'Basic', 'woodcutter': 'Basic', 'forester': 'Basic',
    'sawmill': 'Basic', 'stonecutter': 'Basic', 'stonemason': 'Basic', 'fishfarm': 'Basic',
    'fisher': 'Basic', 'farm': 'Basic', 'well': 'Basic', 'provisionhouse': 'Basic',
    'tavern': 'Basic', 'barracks': 'Basic',

    // Improved
    'cokingplant': 'Improved', 'copperore': 'Improved', 'coppermine': 'Improved',
    'bronzesmelter': 'Improved', 'bronzeweaponsmith': 'Improved', 'toolmaker': 'Improved',
    'improvedstorehouse': 'Improved', 'improvedfarm': 'Improved', 'improvedwell': 'Improved',
    'silo': 'Improved', 'improvedsilo': 'Improved', 'mill': 'Improved', 'bakery': 'Improved',
    'brewery': 'Improved',

    // Advanced
    'ironore': 'Advanced', 'ironmine': 'Advanced', 'ironsmelter': 'Advanced',
    'steelsmelter': 'Advanced', 'ironweaponsmith': 'Advanced', 'steelweaponsmith': 'Advanced',
    'stable': 'Advanced', 'bowmaker': 'Advanced', 'longbowmaker': 'Advanced',
    'hunter': 'Advanced', 'deerstalkerhut': 'Advanced', 'butcher': 'Advanced',
    'marblecutter': 'Advanced', 'marblemason': 'Advanced',

    // Elite
    'coalmine': 'Elite', 'goldore': 'Elite', 'goldmine': 'Elite', 'goldsmelter': 'Elite',
    'coinage': 'Elite', 'titaniummine': 'Elite', 'titaniumsmelter': 'Elite',
    'titaniumweaponsmith': 'Elite', 'crossbowmaker': 'Elite', 'gunpowderforge': 'Elite',
    'cannonforge': 'Elite', 'eliteresidence': 'Elite', 'spaciousstorehouse': 'Elite',
    'floatingstorehouse': 'Elite', 'granite_pit': 'Elite', 'grout_factory': 'Elite'
};

const NON_BUFFABLE_PATTERNS = [
    // Warehouses / Storage
    'mayorhouse', 'storehouse', 'improvedstorehouse', 'spaciousstorehouse',
    'floatingstorehouse', 'waterstorehouse', 'guild_bank_building', 'guild_building',

    // Housing / Residences
    'residence', 'nobleresidence', 'floatingresidence', 'magnificentresidence',
    'eliteresidence', 'townhouse', 'manor', 'palace',

    // Special non-production / Towers / Castles / Decorations
    'decoration', 'monument', 'statue', 'flowerbed', 'bench', 'tree', 'lantern',
    'signpost', 'gate', 'fence', 'tent', 'camp', 'trophy', 'garden',
    'lake', 'well_0', 'excelsior', 'garrison', 'tavern', 'mountain', 'deposit',
    'rubble', 'ruin', 'rock', 'peak', 'buffad_building_site', 'bandit',
    'destroyablemountain', 'loot', 'pioneercastle', 'lookouttower', 'watercastle',
    'witchtower', 'darkcastle', 'bonechurch', 'frozenmanor', 'wreckage', 'ship'
];

/**
 * Check if a building object can be buffed or stopped.
 * Now backed by the exact whitelist generated from the game's bld.xml
 * (ResourceDefinitions workyards + buffable attribute), instead of the old
 * name-pattern blacklist.
 */
export function isBuffableBuilding(b) {
    if (!b) return false;
    const raw = b && typeof b === 'object'
        ? (b.buildingName_string || b.buildingName || '')
        : String(b || '');
    if (!raw) return false;
    return isBuffableBuildingName(raw) || isBuffableBuildingName(buildingBaseId(raw));
}

/** Get building category: Basic, Improved, Advanced, Elite, Decorations. */
export function getBuildingCategory(b) {
    if (!b) return 'Basic';
    const name = (b.buildingName_string || b.buildingName || '').toLowerCase();

    for (const key in BUILDING_MAP) {
        if (name.includes(key)) return BUILDING_MAP[key];
    }

    if (name.includes('residence') || name.includes('manor') || name.includes('castle') || name.includes('palace') || name.includes('deco') || name.includes('monument') || name.includes('statue') || name.includes('flowerbed') || name.includes('bench') || name.includes('tree') || name.includes('lantern') || name.includes('signpost') || name.includes('gate') || name.includes('tower') || name.includes('tent') || name.includes('camp') || name.includes('trophy') || name.includes('garden') || name.includes('lake') || name.includes('well_0') || name.includes('excelsior') || name.includes('garrison') || name.includes('mountain') || name.includes('deposit') || name.includes('rubble') || name.includes('ruin') || name.includes('rock') || name.includes('peak')) {
        return 'Decorations';
    }
    if (name.includes('titanium') || name.includes('platinum') || name.includes('gold') || name.includes('gunpowder') || name.includes('cannon') || name.includes('crossbow') || name.includes('salpeter') || name.includes('granite') || name.includes('elite')) {
        return 'Elite';
    }
    if (name.includes('iron') || name.includes('steel') || name.includes('stable') || name.includes('bow') || name.includes('hunter') || name.includes('deerstalker') || name.includes('butcher') || name.includes('marble')) {
        return 'Advanced';
    }
    if (name.includes('coke') || name.includes('copper') || name.includes('bronze') || name.includes('tool') || name.includes('improved') || name.includes('silo') || name.includes('mill') || name.includes('bakery') || name.includes('brewery')) {
        return 'Improved';
    }
    return 'Basic';
}

export default { humanizeGameId, resourceName, marketItemName, buildingBaseId, buildingName, isBuffableBuilding, getBuildingCategory };

