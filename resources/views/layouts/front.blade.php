<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Monaclick' }}</title>
    <link rel="stylesheet" media="screen" href="/finder/assets/vendor/simplebar/dist/simplebar.min.css"/>
    <link rel="stylesheet" media="screen" href="/finder/assets/css/theme.min.css">
</head>
<body>
    <main class="page-wrapper">
        <header class="navbar navbar-expand-lg navbar-light bg-light fixed-top" data-scroll-header>
            <div class="container">
                <a class="navbar-brand me-3 me-xl-4" href="{{ route('home') }}">
                    <span class="fw-bold">Monaclick</span>
                </a>
                <nav class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav navbar-nav-scroll ms-auto" style="max-height:35rem;">
                        <li class="nav-item"><a class="nav-link" href="{{ route('listings.index', ['module' => 'contractors']) }}">Contractors</a></li>
                        <li class="nav-item"><a class="nav-link" href="{{ route('listings.index', ['module' => 'real-estate']) }}">Real Estate</a></li>
                        <li class="nav-item"><a class="nav-link" href="{{ route('listings.index', ['module' => 'cars']) }}">Cars</a></li>
                        <li class="nav-item"><a class="nav-link" href="{{ route('listings.index', ['module' => 'events']) }}">Events</a></li>
                    </ul>
                </nav>
            </div>
        </header>

        <section class="container pt-5 mt-5 pb-4">
            @yield('content')
        </section>
    </main>

    <script src="/finder/assets/vendor/simplebar/dist/simplebar.min.js"></script>
    <script src="/finder/assets/vendor/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/finder/assets/js/theme.min.js"></script>
</body>
</html>
