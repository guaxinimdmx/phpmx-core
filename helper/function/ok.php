<?php

if (!function_exists('ok')) {

    /** Retorna um status OK com uma mensagem adicionarl */
    function ok(string $message = 'OK'): never
    {
        throw new Exception($message, STS_OK);
    }
}
