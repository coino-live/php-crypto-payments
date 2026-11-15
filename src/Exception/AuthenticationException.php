<?php

declare(strict_types=1);

namespace CoinoLive\Exception;

/** Missing, wrong or revoked API key, or the gateway is suspended (401). */
final class AuthenticationException extends ApiException
{
}
