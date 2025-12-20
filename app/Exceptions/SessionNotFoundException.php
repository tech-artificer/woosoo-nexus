<?php

namespace App\Exceptions;

use Exception;

class SessionNotFoundException extends Exception
{
    public function __construct(string $message = 'No active POS session available. Transaction cannot proceed.', int $code = 0)
    {
        parent::__construct($message, $code);
    }

    public function render()
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'code' => 'SESSION_NOT_FOUND',
        ], 503);
    }
}
