const IMAGE_FOLDERS = Object.freeze({
    building: ['buildings', 'other'],
    resource: ['resources', 'other'],
    buff: ['buffs', 'other'],
    unit: ['units', 'other'],
    adventure: ['adventures', 'other'],
});

const IMAGE_ROOT = '/images';

function normalizeName(name) {
    return String(name || '')
        .trim()
        .replace(/^\/+|\/+$/g, '')
        .replace(/\.webp$/i, '')
        .toLowerCase();
}

export function getGameImageUrl(type, name) {
    const folders = IMAGE_FOLDERS[type];
    const imageName = normalizeName(name);

    if (!folders || !imageName) {
        return null;
    }

    return `${IMAGE_ROOT}/${folders[0]}/${imageName}.webp`;
}

/**
 * Advances an <img> to the next configured folder after a failed request.
 * Returns true when a fallback URL was applied, otherwise false.
 */
export function handleGameImageError(event, type, name, fallbackUrl = null) {
    const image = event?.target;
    const folders = IMAGE_FOLDERS[type];
    const imageName = normalizeName(name);

    if (!image || !folders || !imageName || image.dataset.imageFailed === 'true') {
        return false;
    }

    const nextIndex = Number(image.dataset.imageFolderIndex || 0) + 1;
    if (nextIndex < folders.length) {
        image.dataset.imageFolderIndex = String(nextIndex);
        image.src = `${IMAGE_ROOT}/${folders[nextIndex]}/${imageName}.webp`;
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
