<?php

declare(strict_types=1);

namespace App\Services\Auth;

use RuntimeException;

/** A login through the Grupo Klube central login failed. The message is safe to show to the person. */
final class GrupoKlubeSsoException extends RuntimeException
{
}
