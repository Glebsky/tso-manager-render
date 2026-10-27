<?php

declare(strict_types=1);

namespace App\Services\Game\Session;

use App\Exceptions\GameServerErrorException;
use App\Exceptions\SessionBusyException;
use App\Models\Account;
use App\Services\Game\Zone\ZoneCacheInvalidator;
use App\Services\GameErrorResolver;
use App\Services\TsoAmfService;
use App\Services\TsoAuthService;
use App\Services\ZoneParserService;
use Illuminate\Support\Sleep;
use RuntimeException;

/** Shared recovery primitives; callers own the one-forced-login budget. */
final readonly class GameSessionRecovery
{
    public function __construct(
        private TsoAuthService $auth,
        private TsoAmfService $amf,
        private ZoneParserService $parser,
        private ZoneCacheInvalidator $zones,
        private GameClientIdentity $identities,
    ) {}

    public function currentIdentity(Account $account): int
    {
        return $this->identities->current($account);
    }

    /**
     * Take the game session over like a freshly started client after 1012,
     * keeping the working web token: new client identity AND a new
     * load-server session (DSId) for every process. Reusing the superseded
     * DSId is what kept 1012 alive. Only the compare-and-swap winner opens the
     * new session; a parallel loser just joins it. Callers confirm with
     * enterHomeZone() (or the next CMD_ZONE_LOGIN) before retrying.
     *
     * @return bool true when this call opened the new session
     *
     * @throws SessionBusyException
     */
    public function renewGameIdentity(Account $account, int $rejectedIdentity): bool
    {
        $won = $this->identities->renew($account, $rejectedIdentity);
        if ($won) {
            $this->amf->invalidateSession($account->id);
        } else {
            $this->amf->resetClient($account->id);
        }
        $this->zones->invalidateHomeZone($account->id);
        $this->zones->invalidateFriendZones($account->id);

        return $won;
    }

    /** Only after a rejected session, never on the ordinary authenticated path. */
    public function reset(Account $account): void
    {
        $this->auth->resetSession($account);
        $this->amf->invalidateSession($account->id);
        $this->amf->resetClient($account->id);
        // Web authentication and game ownership are separate.
        // Only explicit1012 recovery renews the game identity.
        $this->zones->invalidateHomeZone($account->id);
        $this->zones->invalidateFriendZones($account->id);
    }

    public function reauthenticate(Account $account): void
    {
        $this->reset($account);
        $this->auth->ensureAuthenticated($account, force: true);
        $account->refresh();
    }

    /**
     * Read-only CMD_ZONE_LOGIN(1002). Never retries the caller's game mutation.
     * A successful login alone does not prove the game zone is usable.
     */
    public function enterHomeZone(Account $account, int $maxAttempts = 3): void
    {
        for ($attempt = 1; $attempt <= max(1, $maxAttempts); $attempt++) {
            $zone = $this->parser->parse($this->amf->getZone($account));
            $rawCode = $zone['errorCode'] ?? null;
            if (! is_int($rawCode) && ! (is_string($rawCode) && ctype_digit($rawCode))) {
                throw new RuntimeException('Cannot confirm home zone login: invalid response');
            }
            $code = (int) $rawCode;
            if ($code === 1012 && $attempt < $maxAttempts) {
                Sleep::for(2)->seconds();

                continue;
            }
            if ($code !== 0) {
                throw new GameServerErrorException($code, GameErrorResolver::getMessage($code));
            }
            if (! is_array($zone['buildings'] ?? null) || $zone['buildings'] === []) {
                throw new RuntimeException('Cannot confirm home zone login: empty zone');
            }

            return;
        }
    }
}
