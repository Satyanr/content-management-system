<!DOCTYPE html>
<html lang="id">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Akun menunggu akses</title></head>
<body>
    <main>
        <h1>Akun menunggu akses</h1>
        <p>Hubungi administrator untuk memastikan perusahaan aktif dan akses dashboard telah diberikan.</p>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit">Keluar</button>
        </form>
    </main>
</body>
</html>
