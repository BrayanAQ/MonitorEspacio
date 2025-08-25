<?php
class Auth {
    public function __construct() {
        session_start();
    }

    public function login($user, $pass, $host, $dbName) {
        $_SESSION['host'] = $host;
        $_SESSION['db']   = $dbName;
        $_SESSION['user'] = $user;
        $_SESSION['pass'] = $pass;
        $_SESSION['logged_in'] = true;
        return true;
    }

    public function check() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }

    public function logout() {
        session_destroy();
    }
}
