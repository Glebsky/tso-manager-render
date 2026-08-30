<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Exceptions\Contracts\HasApiPresentation;
use Exception;
use Throwable;

class TaskExecutionException extends Exception implements HasApiPresentation
{
    protected string $translationKey;

    /**
     * @var array<string, mixed>
     */
    protected array $params;

    protected int $httpStatus;

    /**
     * @param  array<string, mixed>  $params
     */
    public function __construct(string $translationKey, array $params = [], int $httpStatus = 422, int $code = 0, ?Throwable $previous = null)
    {
        $this->translationKey = $translationKey;
        $this->params = $params;
        $this->httpStatus = $httpStatus;

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

    /**
     * @return array<string, mixed>
     */
    public function getParams(): array
    {
        return $this->params;
    }

    public function userMessage(): string
    {
        return $this->getMessage();
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->params;
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [
            'key' => 'tasks.'.$this->translationKey,
            'params' => $this->params,
            'message' => $this->getMessage(),
        ];
    }
}
