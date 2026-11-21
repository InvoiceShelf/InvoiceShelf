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
        // must belong to the company the user is acting in.
        return $note === null || $user->isActingIn($note->company_id);
    }

    public function viewNotes(User $user, ?Note $note = null)
    {
        if (! BouncerFacade::can('view-all-notes', $note ?? Note::class)) {
            return false;
        }

        return $note === null || $user->isActingIn($note->company_id);
    }
}
