<?php

namespace App\Auth\Exceptions;

use RuntimeException;

/**
 * The provider refused the sign-in code or answered with something unusable. Never rendered as JSON:
 * the callback is a browser redirect, so `SocialAuthController` turns it into a `provider_error` redirect.
 */
class SocialProviderFailed extends RuntimeException {}
