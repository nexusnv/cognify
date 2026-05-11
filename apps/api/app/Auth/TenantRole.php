<?php

namespace App\Auth;

enum TenantRole: string
{
    case Requester = 'requester';
    case Buyer = 'buyer';
    case Approver = 'approver';
    case Admin = 'admin';
}
