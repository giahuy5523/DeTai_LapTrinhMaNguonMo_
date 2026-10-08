<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Admin Layout</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-dark bg-dark p-2">
        <a class="navbar-brand" href="{{ route('admin.dashboard') }}">Admin Panel</a>
    </nav>

    <div class="container mt-4">
        @yield('content')
    </div>

    <footer class="text-center mt-4">
        <hr>
        <p>&copy; {{ date('Y') }} SneakerShop Admin</p>
    </footer>
</body>
</html>
