<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Boleo' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/boleo.css') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="{{ $bodyClass ?? '' }}">
    @yield('content')

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('[data-confirm-submit]').forEach((button) => {
                button.addEventListener('click', (event) => {
                    event.preventDefault();

                    const form = document.getElementById(button.getAttribute('data-confirm-submit'));

                    if (!form) {
                        return;
                    }

                    Swal.fire({
                        title: button.dataset.confirmTitle || '¿Estás seguro?',
                        text: button.dataset.confirmText || 'No podrás revertir esta acción.',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#1f5c4f',
                        cancelButtonColor: '#d33',
                        confirmButtonText: button.dataset.confirmButtonText || 'Sí, continuar',
                        cancelButtonText: 'Cancelar',
                    }).then((result) => {
                        if (result.isConfirmed) {
                            form.submit();
                        }
                    });
                });
            });
        });
    </script>
</body>
</html>