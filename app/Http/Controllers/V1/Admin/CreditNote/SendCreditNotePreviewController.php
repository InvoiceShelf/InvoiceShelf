<?php

namespace App\Http\Controllers\V1\Admin\CreditNote;

use App\Http\Controllers\Controller;
use App\Models\CreditNote;
use Illuminate\Http\Request;
use Illuminate\Mail\Markdown;

class SendCreditNotePreviewController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return \Illuminate\Http\Response
     */
    public function __invoke(Request $request, CreditNote $creditNote)
    {
        $this->authorize('send credit note', $creditNote);

        $markdown = new Markdown(view(), config('mail.markdown'));

        $data = $creditNote->sendCreditNoteData($request->all());
        $data['url'] = $creditNote->creditNotePdfUrl;

        return $markdown->render('emails.send.credit_note', ['data' => $data]);
    }
}
