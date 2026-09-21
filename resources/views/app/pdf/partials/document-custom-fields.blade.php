{{--
    Definitions the author marked as printed, rendered as extra rows of the
    details block so a custom date sits with the invoice date rather than
    needing a hand-edited template.

    Expects $documentFields (the definitions) and $document (the record
    answering them). A row whose answer is empty is skipped, so an optional
    field left blank costs nothing on the page.
--}}
@foreach($documentFields as $field)
    @php($answer = $document->getCustomFieldValueBySlug($field->slug))
    @if(filled($answer))
        <tr>
            <td class="attribute-label">{{ $field->label }}</td>
            <td class="attribute-value"> &nbsp;{{ $answer }}</td>
        </tr>
    @endif
@endforeach
