<?php

declare(strict_types=1);

namespace App\Support\Http;

use Illuminate\Http\JsonResponse;

/**
 * Single place that owns the JSON envelope of the admin/public API.
 *
 * Before this class every controller re-implemented
 * `response()->json(['success' => ..., 'message' => ...], $status)` by hand,
 * which produced dozens of near-identical blocks and made the contract easy
 * to break. Controllers now depend on this collaborator instead (DIP), and
 * the envelope shape has exactly one reason to change (SRP).
 */
final class ApiResponder
{
    /**
     * Return a raw payload without the success envelope.
     *
     * @param  array<array-key, mixed>|\JsonSerializable  $payload
     */
    public function data(mixed $payload, int $status = 200): JsonResponse
    {
        return response()->json($payload, $status);
    }

    /**
     * Return a successful envelope: `success: true` plus optional extra keys.
     *
     * @param  array<string, mixed>  $extra
     */
    public function success(string $message, array $extra = [], int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message] + $extra, $status);
    }

    /**
     * Return a failed envelope: `success: false` plus optional extra keys.
     *
     * @param  array<string, mixed>  $extra
     */
    public function failure(string $message, array $extra = [], int $status = 422): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message] + $extra, $status);
    }

    /**
     * Return a Laravel-style validation error envelope.
     *
     * @param  array<string, list<string>>  $errors
     */
    public function validationError(array $errors, ?string $message = null): JsonResponse
    {
        return response()->json([
            'message' => $message ?? __('validation.invalid_data'),
            'errors' => $errors,
        ], 422);
    }
}
