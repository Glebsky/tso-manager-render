/**
 * Shared geometry helpers for the market SVG charts.
 *
 * Both MarketAnalytics.vue (admin) and PublicMarketAnalytics.vue (public portal)
 * used to carry their own duplicated copy of this math. Keeping it here means a
 * layout fix only has to be made once.
 */

export const VB_W = 600; // viewBox width
export const VB_H = 220; // viewBox height
export const PAD_L = 40; // left padding (space for the axis labels)
export const PAD_R = 590; // right-most drawable x
export const PLOT_W = PAD_R - PAD_L; // 550

/**
 * Convert a raw history array into plotted points.
 * Returns x plus a y coordinate per series: y (price), sy (sellers),
 * oy (offers) and vy (volume).
 */
export function buildChartPoints(history) {
    if (!history || history.length === 0) return [];

    const isSingle = history.length === 1;

    const prices = history.map(h => h.price);
    const maxPrice = Math.max(...prices) || 1;
    const minPrice = Math.min(...prices) || 0;
    const priceDiff = (maxPrice - minPrice) || 1;

    const maxSellers = Math.max(...history.map(h => h.sellers_count)) || 1;
    const maxOffers = Math.max(...history.map(h => h.offers_count)) || 1;
    const maxVolume = Math.max(...history.map(h => h.volume)) || 1;

    return history.map((d, idx) => {
        const stepX = isSingle ? 0 : PLOT_W / (history.length - 1);
        const x = isSingle ? PAD_L + PLOT_W / 2 : PAD_L + idx * stepX;

        const py = maxPrice === minPrice
            ? 120
            : VB_H - ((d.price - minPrice) / priceDiff) * 180 - 10;

        return {
            x,
            y: py,
            sy: VB_H - (d.sellers_count / maxSellers) * 180 - 10,
            oy: VB_H - (d.offers_count / maxOffers) * 180 - 10,
            vy: VB_H - (d.volume / maxVolume) * 180 - 10,
            price: d.price,
            volume: d.volume,
            sellers_count: d.sellers_count,
            offers_count: d.offers_count,
            avg_amount: d.avg_amount || 1,
            avg_target_amount: d.avg_target_amount || 1,
            collected_at: d.collected_at
        };
    });
}

/** Build an SVG polyline path for the given y-key of the points. */
export function chartLinePath(points, key = 'y') {
    if (!points || points.length === 0) return '';
    if (points.length === 1) return `M ${PAD_L} ${points[0][key]} L ${PAD_R} ${points[0][key]}`;
    return points.reduce(
        (path, p, idx) => (idx === 0 ? `M ${p.x} ${p[key]}` : `${path} L ${p.x} ${p[key]}`),
        ''
    );
}

/** Build the closed area path underneath the line for the given y-key. */
export function chartAreaPath(points, key = 'y') {
    if (!points || points.length === 0) return '';
    if (points.length === 1) {
        return `M ${PAD_L} ${points[0][key]} L ${PAD_R} ${points[0][key]} L ${PAD_R} ${VB_H} L ${PAD_L} ${VB_H} Z`;
    }
    return `${chartLinePath(points, key)} L ${points[points.length - 1].x} ${VB_H} L ${points[0].x} ${VB_H} Z`;
}

/** Largest value across sellers/offers, used for the demand chart axis label. */
export function maxDemandValue(history) {
    if (!history || history.length === 0) return 0;
    return Math.max(
        Math.max(...history.map(h => h.sellers_count)) || 0,
        Math.max(...history.map(h => h.offers_count)) || 0
    );
}
