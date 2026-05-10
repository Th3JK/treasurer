<?php

namespace Th3JK\Treasurer\Enums;

enum RefundState: string
{
    case REQUESTED = 'requested';
    case FAILED = 'failed';
    case SUCCESS = 'success';
}
