<?php

function startAuthSession(): void
{
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    $cookie = session_get_cookie_params();
    $https = isset($_SERVER['HTTPS']) && in_array(strtolower($_SERVER['HTTPS']), ['on', '1'], true);
    $cookie['secure'] = $cookie['secure'] || $https;
    $cookie['httponly'] = true;
    $cookie['samesite'] = 'Lax';
    session_set_cookie_params($cookie);
    session_start();
}
