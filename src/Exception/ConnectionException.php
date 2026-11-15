<?php

declare(strict_types=1);

namespace CoinoLive\Exception;

/** Coino Live could not be reached (network, DNS, timeout). Retried automatically, then thrown. */
final class ConnectionException extends \RuntimeException
{
}
