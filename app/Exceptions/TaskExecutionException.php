<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Throwable;

class TaskExecutionException extends Exception
{
    protected string $translationKey;

    protected array $params;

    public function __construct(string $translationKey, array $params = [], int $code = 0, ?Throwable $previous = null)
    {
        $this->translationKey = $translationKey;
        $this->params = $params;

        $fullKey = 'tasks.'.$translationKey;
        $localizedMessage = __($fullKey, $params);
        if (! is_string($localizedMessage) || $localizedMessage === $fullKey) {
            $localizedMessage = "Task execution error: {$translationKey}";
        }

        parent::__construct($localizedMessage, $code, $previous);
    }

    public function getTranslationKey(): string
    {
        return $this->translationKey;
    }

    public function getParams(): array
    {
        return $this->params;
    }

    public function toPayload(): array
    {
        return [
            'key' => 'tasks.'.$this->translationKey,
            'params' => $this->params,
            'message' => $this->getMessage(),
        ];
    }
}
