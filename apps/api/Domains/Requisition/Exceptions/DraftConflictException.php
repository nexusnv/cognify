<?php

namespace Domains\Requisition\Exceptions;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class DraftConflictException extends ConflictHttpException
{
}
