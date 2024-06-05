@component('mail::message')
@lang('mail_viewed_estimate', ['name' => $data['user']['name']])

@component('mail::button', ['url' => url('/admin/estimates/'.$data['estimate']['id'].'/view')])@lang('mail_view_estimate')
@endcomponent

@lang('mail_thanks'),<br>
{{ config('app.name') }}
@endcomponent
