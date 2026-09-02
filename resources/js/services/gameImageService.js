const IMAGE_FOLDERS = Object.freeze({
    buff: ['buffs', 'other'],
    adventure: ['adventures', 'other'],
    building: ['buildings', 'other'],
    resource: ['resources', 'other'],
    unit: ['units', 'other'],
    other: ['other'],
});

const IMAGE_ROOT = '/images';

const BUFF_ALIASES = Object.freeze({
    aunt_irmas_basket: 'aunt_irma_basket',
    aunt_irmas_feast_basket: 'aunt_irma_feast_basket',
    secretsanta: 'buff_secretsanta',
    buff_secretsanta: 'buff_secretsanta',
    filldeposit_fishfood: 'filldeposit_fish',
    filldeposit_hunter: 'filldeposit_meat',
    filldeposit_grain: 'filldeposit_wheat',
    filldeposit_wheat_01: 'filldeposit_wheat',
    filldeposit_iron: 'filldeposit_ironore',
    filldeposit_gold: 'filldeposit_goldore',
    filldeposit_bronze: 'filldeposit_bronzeore',
    filldeposit_copper: 'filldeposit_bronzeore',
    filldeposit_copperore: 'filldeposit_bronzeore',
    filldeposit_titanium: 'filldeposit_titaniumore',
    filldeposit_pumpkin: 'filldeposit_halloweenresource',
    filldeposit_flowers: 'filldeposit_valentinesflower',
    productivitybufflvl_commandcenterboost: 'tactic_grapeshot',
    changeskin_anniversary2026_mayorshouse_long: 'anniversary2026mayors_house',
    changeskin_anniversary2026_mayorshouse: 'anniversary2026mayors_house',
});

const BUILDING_ALIASES = Object.freeze({
    realwoodsawmill: 'sawmill_real_planks',
    exoticwoodsawmill: 'sawmill_exotic_planks',
    mahoganysawmill: 'mahogany_sawmill',
    exoticwoodtreeschool: 'exoticwood_treeschool',
    stonecutter: 'stonemason',
    marblecutter: 'marblemason',
    granitecutter: 'granitemason',
});

export function parseItemIdentifier(type, name) {
    let resolvedType = (typeof type === 'string' && type) ? type.toLowerCase() : '';
    let raw = (typeof name === 'string') ? name.trim() : '';

    if (!raw && resolvedType.includes(':')) {
        raw = resolvedType;
        resolvedType = '';
    }

    if (raw.includes(':')) {
        const parts = raw.split(':');
        const prefix = parts[0].toLowerCase();
        if (IMAGE_FOLDERS[prefix]) {
            resolvedType = prefix;
            const segments = parts.slice(1).filter(Boolean);
            if (segments.length >= 2 && segments[0].toLowerCase() === segments[1].toLowerCase()) {
                raw = segments[0];
            } else {
                raw = segments.join('_');
            }
        }
    }

    if (!resolvedType || !IMAGE_FOLDERS[resolvedType]) {
        resolvedType = 'resource';
    }

    return { type: resolvedType, name: raw };
}

function normalizeName(name) {
    return String(name || '')
        .trim()
        .replace(/^\/+|\/+$/g, '')
        .replace(/\.webp$/i, '')
        .replace(/\s+/g, '_')
        .toLowerCase();
}

function resolveAlias(type, imageName) {
    if (type === 'buff') {
        const deduped = imageName.replace(/^([a-z0-9_]+)_\1$/i, '$1');
        const grimoireMatch = deduped.match(/^witchcovengrimoirepage(\d+)(?:_witchcovengrimoirepage\d+)?$/i);
        if (grimoireMatch) {
            return `grimoirepage${grimoireMatch[1]}`;
        }
        return BUFF_ALIASES[deduped] || BUFF_ALIASES[imageName] || deduped;
    }
    if (type === 'building') {
        const cleanName = imageName.replace(/_lvl_\d+/i, '').replace(/decoration_/g, '');
        return BUILDING_ALIASES[cleanName] || cleanName;
    }
    return imageName;
}

export function getGameImageUrl(type, name) {
    const parsed = parseItemIdentifier(type, name);
    const folders = IMAGE_FOLDERS[parsed.type] || IMAGE_FOLDERS.resource;
    const imageName = normalizeName(parsed.name);

    if (!folders || !imageName) {
        return null;
    }

    const aliased = resolveAlias(parsed.type, imageName);

    return `${IMAGE_ROOT}/${folders[0]}/${aliased}.webp`;
}

/**
 * Advances an <img> to the next configured folder or fallback candidate after a failed request.
 * Returns true when a fallback URL was applied, otherwise false.
 */
export function handleGameImageError(event, type, name, fallbackUrl = null) {
    const image = event?.target;
    const parsed = parseItemIdentifier(type, name);
    const folders = IMAGE_FOLDERS[parsed.type] || IMAGE_FOLDERS.resource;
    const imageName = normalizeName(parsed.name);

    if (!image || !folders || !imageName || image.dataset.imageFailed === 'true') {
        return false;
    }

    const aliased = resolveAlias(parsed.type, imageName);
    const deduped = imageName.replace(/^([a-z0-9_]+)_\1$/i, '$1');
    const candidateNames = [aliased];
    if (deduped !== aliased && !candidateNames.includes(deduped)) {
        candidateNames.push(deduped);
    }
    if (imageName !== aliased && !candidateNames.includes(imageName)) {
        candidateNames.push(imageName);
    }
    if (parsed.type === 'buff' && deduped.includes('_')) {
        const baseOnly = deduped.split('_')[0];
        if (!candidateNames.includes(baseOnly)) {
            candidateNames.push(baseOnly);
        }
    }

    const candidateUrls = [];
    for (const folder of folders) {
        for (const candidate of candidateNames) {
            const url = `${IMAGE_ROOT}/${folder}/${candidate}.webp`;
            if (!candidateUrls.includes(url)) {
                candidateUrls.push(url);
            }
        }
    }

    const nextIndex = Number(image.dataset.imageAttemptIndex || 0) + 1;
    if (nextIndex < candidateUrls.length) {
        image.dataset.imageAttemptIndex = String(nextIndex);
        image.src = candidateUrls[nextIndex];
        return true;
    }

    if (fallbackUrl && image.dataset.imageFallbackApplied !== 'true') {
        image.dataset.imageFallbackApplied = 'true';
        image.src = fallbackUrl;
        return true;
    }

    image.dataset.imageFailed = 'true';
    image.style.display = 'none';
    return false;
}
