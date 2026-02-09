<?php

namespace App\Exceptions;

class EmailNotSendException extends \ErrorException
{
    public ?int $log_id = null;

    public ?string $email_driver_msg = null;

    public function __construct(?string $email_driver_msg, int $log_id, int $severity = E_RECOVERABLE_ERROR)
    {
        parent::__construct($email_driver_msg, $severity, $severity, __FILE__, __LINE__);

        $this->message = $email_driver_msg;
        $this->log_id = $log_id;
        $this->severity = $severity;
    }
}

