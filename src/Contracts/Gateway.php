<?php

namespace Th3JK\Treasurer\Contracts;

interface Gateway
{
    /**
     * Canonical name of this gateway driver (e.g. 'gopay', 'comgate').
     * Stored on Payment / Refund rows so the right driver can be resolved
     * for follow-up operations (status refresh, refund) later.
     */
    public function getName(): string;
}
