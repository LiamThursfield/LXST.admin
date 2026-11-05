<?php

namespace App\Services\Messaging;

use RuntimeException;

/**
 * A text or WhatsApp message the provider wouldn't send, with its reason
 */
class MessagingException extends RuntimeException {}
