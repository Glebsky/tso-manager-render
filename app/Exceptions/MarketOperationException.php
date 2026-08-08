<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Exceptions\Contracts\HasApiPresentation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * A market use case could not be completed for a business reason.
 *
 * The services throw it instead of building an HTTP response themselves, which
 * keeps the domain layer free of presentation concerns. Laravel renders the
 * exception centrally via HasApiPresentation contract.
 */
final class MarketOperationException extends RuntimeException implements HasApiPresentation
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $message,
        private readonly int $status = 422,
        private readonly array $context = []
    ) {
        parent::__construct($message);
    }

    public static function unprocessable(string $message): self
    {
        return new self($message, 422);
    }

    public static function serverError(string $message): self
    {
        return new self($message, 500);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function userMessage(): string
    {
        return $this->getMessage();
    }

    public function httpStatus(): int
    {
        return $this->status;
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
        ], $this->status);
    }
}
