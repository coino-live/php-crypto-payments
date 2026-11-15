<?php

declare(strict_types=1);

namespace CoinoLive\Exception;

/** The request was refused; $errors names the fields (422). */
final class ValidationException extends ApiException
{
}
