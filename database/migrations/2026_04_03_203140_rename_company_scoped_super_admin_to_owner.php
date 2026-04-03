<?php

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Silber\Bouncer\BouncerFacade;
use Silber\Bouncer\Database\Role;

return new class extends Migration
{
    /**
     * Rename all company-scoped "super admin" roles to "owner".
     * Ensure every company owner has the owner role assigned.
     */
    public function up(): void
    {
        // Rename existing company-scoped super admin roles to owner
        Role::whereNotNull('scope')
            ->where('name', 'super admin')
            ->update([
                'name' => 'owner',
                'title' => 'Owner',
            ]);

        // Ensure every company owner has the owner role
        foreach (Company::whereNotNull('owner_id')->get() as $company) {
            BouncerFacade::scope()->to($company->id);
            $user = User::find($company->owner_id);
            if ($user && ! $user->isA('owner')) {
                $user->assign('owner');
            }
        }
    }

    /**
     * Reverse: rename owner roles back to super admin.
     */
    public function down(): void
    {
        Role::whereNotNull('scope')
            ->where('name', 'owner')
            ->update([
                'name' => 'super admin',
                'title' => 'Super Admin',
            ]);
    }
};
