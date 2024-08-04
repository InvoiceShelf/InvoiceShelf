@component('mail::message')
@lang('mail_viewed_invoice', ['name' => $data['user']['name']])

@component('mail::button', ['url' => url('/admin/invoices/'.$data['invoice']['id'].'/view')])@lang('mail_view_invoice')
@endcomponent

@lang('mail_thanks'),<br>
{{ config('app.name') }}
@endcomponent
