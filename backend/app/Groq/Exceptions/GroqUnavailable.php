<?php

namespace App\Groq\Exceptions;

use RuntimeException;

/** Groq could not be reached or returned an unusable response. */
class GroqUnavailable extends RuntimeException {}
