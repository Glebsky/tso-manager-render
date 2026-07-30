<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * A market use case could not be completed for a business reason.
 *
 * The services throw it instead of building an HTTP response themselves, which
 * keeps the domain layer free of presentation concerns. Laravel renders the
 * exception through {@see self::render()}, so the JSON envelope stays exactly
 * the one the SPA already consumes.
 */
final class MarketOperationException extends RuntimeException
{
    public function __construct(string $message, private readonly int $status = 422)
    {
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

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
        ], $this->status);
    }
}
