@if (config('services.google.client_id'))
    <p class="or">atau</p>
    <a class="btn btn-secondary btn-block" href="{{ route('google.redirect') }}">Lanjutkan dengan Google</a>
@endif
