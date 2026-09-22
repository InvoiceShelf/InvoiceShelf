{{--
    As document-custom-fields, for the two templates whose details block is a
    stack of headings rather than a table.
--}}
@foreach($documentFields as $field)
    @php($answer = $document->getFormattedCustomFieldValueBySlug($field->slug))
    @if(filled($answer))
        <h4>{{ $field->label }}: {{ $answer }}</h4>
    @endif
@endforeach
