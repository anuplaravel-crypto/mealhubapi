<?php

namespace App\Http\Controllers\Api\V1\Admin;

/**
 * Admin management of restaurant accounts.
 *
 * The identity documents an admin activates a restaurant on are already on the
 * response: `AdminUserResource` nests `RestaurantDocumentResource`, which emits
 * the admin download address for each filled slot. Streaming those files stays
 * where it was built, in {@see RestaurantDocumentController} — this controller
 * gains no action for it, because a second route to the same file would be a
 * second place to keep `UserPolicy::viewDocuments()` in mind.
 */
class RestaurantController extends BaseUserManagementController
{
    protected function role(): string
    {
        return 'restaurant';
    }

    protected function label(): string
    {
        return 'Restaurant';
    }
}
