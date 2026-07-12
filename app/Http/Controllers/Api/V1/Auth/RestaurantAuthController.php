<?php

namespace App\Http\Controllers\Api\V1\Auth;

class RestaurantAuthController extends SelfServiceAuthController
{
    protected function role(): string
    {
        return 'restaurant';
    }
}
