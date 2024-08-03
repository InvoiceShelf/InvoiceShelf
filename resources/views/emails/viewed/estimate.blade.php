@component('mail::message')
{{ $data['user']['name'] }} viewed this Estimate.

@component('mail::button', ['url' => url('/admin/quotations/'.$data['estimate']['id'].'/view')])
View Estimate
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
