<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Support\Facades\Log;

class InvalidKeyException extends Exception
{
    public function report(): void
    {
        Log::error($this->getMessage());
    }
}
