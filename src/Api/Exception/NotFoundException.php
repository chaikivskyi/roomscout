<?php

namespace App\Api\Exception;

abstract class NotFoundException extends \RuntimeException implements DomainExceptionInterface
{
}
