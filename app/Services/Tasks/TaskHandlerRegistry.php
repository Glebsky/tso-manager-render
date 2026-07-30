<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Exceptions\UnknownTaskActionException;
use App\Services\Tasks\Contracts\TaskActionHandlerInterface;
use Illuminate\Contracts\Container\Container;

final class TaskHandlerRegistry
{
    /**
     * @param  array<int, class-string<TaskActionHandlerInterface>>  $handlerClasses
     */
    public function __construct(
        private readonly Container $container,
        private readonly array $handlerClasses,
    ) {}

    public function getHandler(string $actionType): TaskActionHandlerInterface
    {
        foreach ($this->handlerClasses as $handlerClass) {
            /** @var TaskActionHandlerInterface $handler */
            $handler = $this->container->make($handlerClass);

            if ($handler->supports($actionType)) {
                return $handler;
            }
        }

        throw new UnknownTaskActionException($actionType);
    }
}
