<?php

declare(strict_types=1);

namespace App\Services\Updates;

use RuntimeException;

/** An update failed — the message is meant for the UI (translated). */
final class UpdateException extends RuntimeException {}
