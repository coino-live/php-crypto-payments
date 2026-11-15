<?php

declare(strict_types=1);

namespace CoinoLive\Exception;

/** A webhook whose signature is missing, wrong or too old: refuse it. */
final class SignatureVerificationException extends \RuntimeException
{
}
