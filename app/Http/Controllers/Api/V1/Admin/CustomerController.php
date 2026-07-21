<?php

namespace App\Http\Controllers\Api\V1\Admin;

/**
 * Admin management of customer accounts.
 *
 * The whole class is the two answers {@see BaseUserManagementController} asks
 * for. Customers file no paperwork and register no vehicle, so their review is
 * the account itself — which is why this is the shortest of the three and has
 * no extra action to declare.
 */
class CustomerController extends BaseUserManagementController
{
    protected function role(): string
    {
        return 'customer';
    }

    protected function label(): string
    {
        return 'Customer';
    }
}
