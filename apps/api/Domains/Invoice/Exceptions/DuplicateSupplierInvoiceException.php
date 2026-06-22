<?php

namespace Domains\Invoice\Exceptions;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class DuplicateSupplierInvoiceException extends ConflictHttpException
{
    /**
     * @param  array{id: string, number: string, invoiceNumber: string}  $matchingInvoice
     */
    public function __construct(
        public readonly array $matchingInvoice,
        string $message = 'A supplier invoice with this number already exists for the purchase order.',
    ) {
        parent::__construct($message);
    }
}
