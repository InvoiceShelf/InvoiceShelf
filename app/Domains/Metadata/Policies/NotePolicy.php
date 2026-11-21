<?php

namespace App\Domains\Metadata\Policies;

use App\Domains\Accounts\Models\User;
use App\Domains\Metadata\Models\Note;
use Illuminate\Auth\Access\HandlesAuthorization;
use Silber\Bouncer\BouncerFacade;

/**
 * Notes divide their permissions in two: reading the library answers to one
 * ability, changing it to another. Handed an actual note, either check also
 * becomes a tenancy check — holding the ability is not enough when the row
 * belongs to a company the user is not a member of.
 */
class NotePolicy
{
    use HandlesAuthorization;

    public function manageNotes(User $user, ?Note $note = null)
    {
        return $this->passes($user, $note, 'manage-all-notes');
    }

    public function viewNotes(User $user, ?Note $note = null)
    {
        return $this->passes($user, $note, 'view-all-notes');
    }

    /**
     * Bouncer is asked about the note itself where there is one and about the
     * class otherwise. Membership is only meaningful in the first case: a
     * class-level question carries no company to test the user against.
     *
     * Note that the ability is resolved for whoever is logged in rather than
     * for `$user`, which is what the facade does; only the membership half of
     * the decision actually reads the argument.
     */
    private function passes(User $user, ?Note $note, string $ability): bool
    {
        if (! BouncerFacade::can($ability, $note ?? Note::class)) {
            return false;
        }

        if ($note === null) {
            return true;
        }

        return $user->isActingIn($note->company_id);
    }
}
