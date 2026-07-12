<?php

namespace App\Http\Controllers\Api\V1\Auth;

class CustomerAuthController extends SelfServiceAuthController
{
    protected function role(): string
    {
        return 'customer';
    }
}
