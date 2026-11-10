<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="google-site-verification" content="oU6uX_Uk6xKGdQigeK5OvPSf52OD5BNlNGffBz9Dxsc" />

    <title>@yield('title', 'ZeroRiskInitiative')</title>

    
    @include('partials.theme-init')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    @yield('content')
</body>
</html>
