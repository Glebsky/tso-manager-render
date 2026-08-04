<?php

declare(strict_types=1);

namespace App\Services\Market\Arbitrage;

use App\Models\MarketOffer;
use App\Services\Market\Contracts\ArbitrageFinder;
use App\Services\Market\Contracts\ResourceNameResolver;
use App\Services\Market\MarketOfferQueryService;

/**
 * Detects profitable 2-step and 3-step barter loops (A -> B -> A and
 * A -> B -> C -> A) among the active offers of a single server.
 *
 * The algorithm is a verbatim extraction of the former
 * `MarketAnalyticsController::buildArbitrageData()` (roughly 290 lines of
 * nested loops living inside an HTTP controller). Only its collaborators
 * changed: resource naming and the active-offer window are now injected.
 */
final class LoopArbitrageFinder implements ArbitrageFinder
{
    /**
     * Maximum number of loops returned, best profit first.
     */
    private const MAX_LOOPS = 20;

    public function __construct(
        private readonly ResourceNameResolver $names,
        private readonly MarketOfferQueryService $offers,
        private readonly bool $threeStepLoopsEnabled = true,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function find(string $serverId): array
    {
        $since = $this->offers->activeSince();
        $offers = MarketOffer::query()
            ->where('server_id', $serverId)
            ->where(static function ($query) use ($since): void {
                $query->where('created_at', '>=', $since)
                    ->orWhere('collected_at', '>=', $since);
            })
            ->get();
        $byPair = [];

        foreach ($offers as $offer) {
            $from = $offer->target_item_id;
            $to = $offer->item_id;
            $byPair[$from][$to][] = [
                'offer_id' => $offer->offer_id,
                'sender_name' => $offer->sender_name,
                'item_id' => $offer->item_id,
                'item_name' => $this->names->resolve($offer->item_id, $offer->item_name),
                'amount' => $offer->amount,
                'target_item_id' => $offer->target_item_id,
                'target_item_name' => $this->names->resolve($offer->target_item_id, $offer->target_item_name),
                'target_amount' => $offer->target_amount,
                'lots_remaining' => $offer->lots_remaining,
            ];
        }

        $loops = [];
        $resources = array_keys($byPair);

        foreach ($resources as $A) {
            if (! isset($byPair[$A])) {
                continue;
            }

            foreach ($byPair[$A] as $B => $t1List) {
                if ($B === $A) {
                    continue;
                }

                // 1. 2-step loops: A -> B -> A
                if (isset($byPair[$B][$A])) {
                    $t2List = $byPair[$B][$A];
                    foreach ($t1List as $t1) {
                        foreach ($t2List as $t2) {
                            $bestProfit = -99999999;
                            $best_x = 0;
                            $best_y = 0;

                            $maxX = $t1['lots_remaining'];
                            $maxY = $t2['lots_remaining'];

                            for ($x = 1; $x <= $maxX; $x++) {
                                $gotB = $x * $t1['amount'];
                                $neededForT2Lot = $t2['target_amount'];
                                if ($neededForT2Lot <= 0) {
                                    continue;
                                }
                                $y = (int) floor($gotB / $neededForT2Lot);
                                if ($y > $maxY) {
                                    $y = $maxY;
                                }
                                if ($y <= 0) {
                                    continue;
                                }

                                $costA = $x * $t1['target_amount'];
                                $returnedA = $y * $t2['amount'];
                                $profitA = $returnedA - $costA;

                                if ($profitA > $bestProfit) {
                                    $bestProfit = $profitA;
                                    $best_x = $x;
                                    $best_y = $y;
                                }
                            }

                            if ($bestProfit > 0) {
                                $gotB = $best_x * $t1['amount'];
                                $usedB = $best_y * $t2['target_amount'];
                                $leftoverB = $gotB - $usedB;

                                $loops[] = [
                                    'type' => '2-step',
                                    'start_resource' => $A,
                                    'start_resource_name' => $t1['target_item_name'],
                                    'steps' => [
                                        [
                                            'step' => 1,
                                            'sender' => $t1['sender_name'],
                                            'offer_id' => $t1['offer_id'],
                                            'give_item' => $A,
                                            'give_name' => $t1['target_item_name'],
                                            'give_amount' => $best_x * $t1['target_amount'],
                                            'give_per_lot' => $t1['target_amount'],
                                            'receive_item' => $B,
                                            'receive_name' => $t1['item_name'],
                                            'receive_amount' => $best_x * $t1['amount'],
                                            'receive_per_lot' => $t1['amount'],
                                            'lots' => $best_x,
                                        ],
                                        [
                                            'step' => 2,
                                            'sender' => $t2['sender_name'],
                                            'offer_id' => $t2['offer_id'],
                                            'give_item' => $B,
                                            'give_name' => $t2['target_item_name'],
                                            'give_amount' => $best_y * $t2['target_amount'],
                                            'give_per_lot' => $t2['target_amount'],
                                            'receive_item' => $A,
                                            'receive_name' => $t2['item_name'],
                                            'receive_amount' => $best_y * $t2['amount'],
                                            'receive_per_lot' => $t2['amount'],
                                            'lots' => $best_y,
                                        ],
                                    ],
                                    'profit' => [
                                        'item_id' => $A,
                                        'item_name' => $t1['target_item_name'],
                                        'amount' => $bestProfit,
                                    ],
                                    'leftovers' => $leftoverB > 0 ? [
                                        [
                                            'item_id' => $B,
                                            'item_name' => $t1['item_name'],
                                            'amount' => $leftoverB,
                                        ],
                                    ] : [],
                                ];
                            }
                        }
                    }
                }

                // 2. 3-step loops: A -> B -> C -> A
                if ($this->threeStepLoopsEnabled && isset($byPair[$B])) {
                    foreach ($byPair[$B] as $C => $t2List) {
                        if ($C === $A || $C === $B) {
                            continue;
                        }

                        if (isset($byPair[$C][$A])) {
                            $t3List = $byPair[$C][$A];
                            foreach ($t1List as $t1) {
                                foreach ($t2List as $t2) {
                                    foreach ($t3List as $t3) {
                                        $bestProfit = -99999999;
                                        $best_x = 0;
                                        $best_y = 0;
                                        $best_z = 0;

                                        $maxX = $t1['lots_remaining'];
                                        $maxY = $t2['lots_remaining'];
                                        $maxZ = $t3['lots_remaining'];

                                        for ($x = 1; $x <= $maxX; $x++) {
                                            $gotB = $x * $t1['amount'];
                                            $neededForT2Lot = $t2['target_amount'];
                                            if ($neededForT2Lot <= 0) {
                                                continue;
                                            }
                                            $y = (int) floor($gotB / $neededForT2Lot);
                                            if ($y > $maxY) {
                                                $y = $maxY;
                                            }
                                            if ($y <= 0) {
                                                continue;
                                            }

                                            $gotC = $y * $t2['amount'];
                                            $neededForT3Lot = $t3['target_amount'];
                                            if ($neededForT3Lot <= 0) {
                                                continue;
                                            }
                                            $z = (int) floor($gotC / $neededForT3Lot);
                                            if ($z > $maxZ) {
                                                $z = $maxZ;
                                            }
                                            if ($z <= 0) {
                                                continue;
                                            }

                                            $costA = $x * $t1['target_amount'];
                                            $returnedA = $z * $t3['amount'];
                                            $profitA = $returnedA - $costA;

                                            if ($profitA > $bestProfit) {
                                                $bestProfit = $profitA;
                                                $best_x = $x;
                                                $best_y = $y;
                                                $best_z = $z;
                                            }
                                        }

                                        if ($bestProfit > 0) {
                                            $gotB = $best_x * $t1['amount'];
                                            $usedB = $best_y * $t2['target_amount'];
                                            $leftoverB = $gotB - $usedB;

                                            $gotC = $best_y * $t2['amount'];
                                            $usedC = $best_z * $t3['target_amount'];
                                            $leftoverC = $gotC - $usedC;

                                            $leftovers = [];
                                            if ($leftoverB > 0) {
                                                $leftovers[] = [
                                                    'item_id' => $B,
                                                    'item_name' => $t1['item_name'],
                                                    'amount' => $leftoverB,
                                                ];
                                            }
                                            if ($leftoverC > 0) {
                                                $leftovers[] = [
                                                    'item_id' => $C,
                                                    'item_name' => $t2['item_name'],
                                                    'amount' => $leftoverC,
                                                ];
                                            }

                                            $loops[] = [
                                                'type' => '3-step',
                                                'start_resource' => $A,
                                                'start_resource_name' => $t1['target_item_name'],
                                                'steps' => [
                                                    [
                                                        'step' => 1,
                                                        'sender' => $t1['sender_name'],
                                                        'offer_id' => $t1['offer_id'],
                                                        'give_item' => $A,
                                                        'give_name' => $t1['target_item_name'],
                                                        'give_amount' => $best_x * $t1['target_amount'],
                                                        'give_per_lot' => $t1['target_amount'],
                                                        'receive_item' => $B,
                                                        'receive_name' => $t1['item_name'],
                                                        'receive_amount' => $best_x * $t1['amount'],
                                                        'receive_per_lot' => $t1['amount'],
                                                        'lots' => $best_x,
                                                    ],
                                                    [
                                                        'step' => 2,
                                                        'sender' => $t2['sender_name'],
                                                        'offer_id' => $t2['offer_id'],
                                                        'give_item' => $B,
                                                        'give_name' => $t2['target_item_name'],
                                                        'give_amount' => $best_y * $t2['target_amount'],
                                                        'give_per_lot' => $t2['target_amount'],
                                                        'receive_item' => $C,
                                                        'receive_name' => $t2['item_name'],
                                                        'receive_amount' => $best_y * $t2['amount'],
                                                        'receive_per_lot' => $t2['amount'],
                                                        'lots' => $best_y,
                                                    ],
                                                    [
                                                        'step' => 3,
                                                        'sender' => $t3['sender_name'],
                                                        'offer_id' => $t3['offer_id'],
                                                        'give_item' => $C,
                                                        'give_name' => $t3['target_item_name'],
                                                        'give_amount' => $best_z * $t3['target_amount'],
                                                        'give_per_lot' => $t3['target_amount'],
                                                        'receive_item' => $A,
                                                        'receive_name' => $t3['item_name'],
                                                        'receive_amount' => $best_z * $t3['amount'],
                                                        'receive_per_lot' => $t3['amount'],
                                                        'lots' => $best_z,
                                                    ],
                                                ],
                                                'profit' => [
                                                    'item_id' => $A,
                                                    'item_name' => $t1['target_item_name'],
                                                    'amount' => $bestProfit,
                                                ],
                                                'leftovers' => $leftovers,
                                            ];
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        usort($loops, fn ($a, $b) => $b['profit']['amount'] <=> $a['profit']['amount']);

        return array_slice($loops, 0, self::MAX_LOOPS);
    }
}
