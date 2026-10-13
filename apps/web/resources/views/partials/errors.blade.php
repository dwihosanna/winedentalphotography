@if ($errors->any())
    <div class="notice danger" role="alert">
        @foreach ($errors->all() as $message)
            <p>{{ $message }}</p>
        @endforeach
    </div>
@endif
