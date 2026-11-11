<?php

namespace App\Auth\Exceptions;

use RuntimeException;

/**
 * The provider refused the sign-in code or answered with something unusable (no code, token or user id, or an
 * OAuth error other than a declined consent). A real failure, so it is reported; the browser is sent to the
 * `provider_error` failure page like any other unexpected error from the callback.
 */
class SocialProviderFailed extends RuntimeException {}
