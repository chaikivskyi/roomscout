<?php

namespace App\Api\Exception;

abstract class AccessDeniedException extends \RuntimeException implements DomainExceptionInterface
{
}
