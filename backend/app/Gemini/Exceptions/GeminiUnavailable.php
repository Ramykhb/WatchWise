<?php

namespace App\Gemini\Exceptions;

use RuntimeException;

/**
 * Gemini could not be reached, answered with an error, or returned something that is not JSON.
 * Callers turn it into their own user-facing error. The message holds only the HTTP status or a
 * short reason, never Gemini's response body.
 */
class GeminiUnavailable extends RuntimeException {}
