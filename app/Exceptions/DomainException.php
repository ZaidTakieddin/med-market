<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by domain services when a business rule is violated on an otherwise
 * well-formed request (e.g. insufficient stock, an invalid order-state transition,
 * a credit limit exceeded). The message is user-safe and surfaced verbatim.
 *
 * The central exception Handler renders this as HTTP 422 — controllers must NOT
 * catch it; let it propagate so error handling stays in one place.
 */
class DomainException extends RuntimeException {}
