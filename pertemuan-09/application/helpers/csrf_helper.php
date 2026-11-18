    <?php
// P9 BARU - application/helpers/csrf_helper.php
// Token CSRF sederhana berbasis session untuk formulir POST pembayaran dan verifikasi.

function csrf_token(string $scope): string
{
    $key = '_csrf_' . $scope;

    if (empty($_SESSION[$key]) || !is_string($_SESSION[$key])) {
        $_SESSION[$key] = bin2hex(random_bytes(32));
    }

    return $_SESSION[$key];
}

function csrf_valid(string $scope, mixed $token): bool
{
    $key = '_csrf_' . $scope;
    $tokenSesi = $_SESSION[$key] ?? '';

    return is_string($tokenSesi)
        && $tokenSesi !== ''
        && is_string($token)
        && hash_equals($tokenSesi, $token);
}
