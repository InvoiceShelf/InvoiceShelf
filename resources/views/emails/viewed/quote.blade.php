@component('mail::message')
@lang('mail_viewed_quote', ['name' => $data['user']['name']])

@component('mail::button', ['url' => url('/admin/quotes/'.$data['quote']['id'].'/view')])@lang('mail_view_quote')
@endcomponent

@lang('mail_thanks'),<br>
{{ config('app.name') }}
@endcomponent
