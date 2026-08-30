<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Exceptions\UnknownTaskActionException;
use App\Services\Tasks\Contracts\TaskActionHandlerInterface;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\Container;

final readonly class TaskHandlerRegistry
{
    /**
     * @param  array<int, class-string<TaskActionHandlerInterface>>  $handlerClasses
     */
    public function __construct(
        private Container $container,
        private array $handlerClasses,
    ) {}

    /**
     * @throws BindingResolutionException
     * @throws UnknownTaskActionException
     */
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
