<?php

declare(strict_types=1);

namespace Cans\Domain;

use RuntimeException;

/** A per-conversation cap was hit (messages, participants). The message is for logs, not agents. */
final class LimitReached extends RuntimeException
{
}
