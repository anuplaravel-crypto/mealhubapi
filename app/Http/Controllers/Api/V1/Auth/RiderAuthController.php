<?php

namespace App\Http\Controllers\Api\V1\Auth;

class RiderAuthController extends SelfServiceAuthController
{
    protected function role(): string
    {
        return 'rider';
    }
}
