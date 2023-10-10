<?php

namespace OrangeHRM\Calendar\Exception;

use Exception;

class CalendarServiceException extends Exception
{
    /**
     * @return static
     */
    public static function cannotCreateClient(): self
    {
        return new self(
            "There was a problem creating a Google Client. Please check your credentials and try again."
        );
    }
}
