<?php

namespace App\Services\Groups;

use RuntimeException;

/**
 * A saved card refers to rules that no longer belong to it, e.g. after a save in another tab.
 */
class StaleCardException extends RuntimeException {}
