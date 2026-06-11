<?php

namespace App\Policies;

use App\Models\Note;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Silber\Bouncer\BouncerFacade;

class NotePolicy
{
    use HandlesAuthorization;

    public function manageNotes(User $user, ?Note $note = null)
    {
        if (! BouncerFacade::can('manage-all-notes', $note ?? Note::class)) {
            return false;
        }

        // When acting on a specific note, enforce tenant isolation: the note
        // must belong to a company the user is a member of.
        return $note === null || $user->hasCompany($note->company_id);
    }

    public function viewNotes(User $user, ?Note $note = null)
    {
        if (! BouncerFacade::can('view-all-notes', $note ?? Note::class)) {
            return false;
        }

        return $note === null || $user->hasCompany($note->company_id);
    }
}
