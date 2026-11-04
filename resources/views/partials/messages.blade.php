@if (session('success'))
    <div class="alert alert-success" role="status">{{ session('success') }}</div>
@endif
@if (session('warning'))
    <div class="alert alert-warning" role="status">{{ session('warning') }}</div>
@endif
@if ($errors->any())
    <div class="alert alert-error" role="alert" tabindex="-1" data-error-summary>
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
