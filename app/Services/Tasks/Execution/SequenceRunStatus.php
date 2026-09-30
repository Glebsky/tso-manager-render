<?php

declare(strict_types=1);

namespace App\Services\Tasks\Execution;

enum SequenceRunStatus: string
{
    case Finished = 'finished';
    case Aborted = 'aborted';
    case HandOff = 'hand_off';
}
