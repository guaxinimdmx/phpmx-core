<?php

if (!function_exists('STS_OK')) {

  /** Envia o status SUCESSO como resposta da requisição */
  function STS_OK(string $message = STS_OK): never
  {
    throw new Exception($message, STS_OK);
  }
}

if (!function_exists('STS_NOT_CONTENT')) {

  /** Envia um status SEM CONTEÚDO como resposta da requisição */
  function STS_NOT_CONTENT(string $message = STS_NOT_CONTENT): never
  {
    throw new Exception($message, STS_NOT_CONTENT);
  }
}

if (!function_exists('STS_BAD_REQUEST')) {

  /** Envia o status SINTAXE INTORRETA como resposta da requisição */
  function STS_BAD_REQUEST(string $message = STS_BAD_REQUEST): never
  {
    throw new Exception($message, STS_BAD_REQUEST);
  }
}

if (!function_exists('STS_UNAUTHORIZED')) {

  /** Envia o status REQUER PERMISSÃO como resposta da requisição */
  function STS_UNAUTHORIZED(string $message = STS_UNAUTHORIZED): never
  {
    throw new Exception($message, STS_UNAUTHORIZED);
  }
}

if (!function_exists('STS_FORBIDDEN')) {

  /** Envia o status PROIBIDO como resposta da requisição */
  function STS_FORBIDDEN(string $message = STS_FORBIDDEN): never
  {
    throw new Exception($message, STS_FORBIDDEN);
  }
}

if (!function_exists('STS_NOT_FOUND')) {

  /** Envia o status NÃO ENCONTRADO como resposta da requisição */
  function STS_NOT_FOUND(string $message = STS_NOT_FOUND): never
  {
    throw new Exception($message, STS_NOT_FOUND);
  }
}

if (!function_exists('STS_METHOD_NOT_ALLOWED')) {

  /** Envia o status MÉTODO NÃO PERMITIDO como resposta da requisição */
  function STS_METHOD_NOT_ALLOWED(string $message = STS_METHOD_NOT_ALLOWED): never
  {
    throw new Exception($message, STS_METHOD_NOT_ALLOWED);
  }
}

if (!function_exists('STS_INTERNAL_SERVER_ERROR')) {

  /** Envia o status ERRO INTERNO DO SERVIDOR como resposta da requisição */
  function STS_INTERNAL_SERVER_ERROR(string $message = STS_INTERNAL_SERVER_ERROR): never
  {
    throw new Exception($message, STS_INTERNAL_SERVER_ERROR);
  }
}

if (!function_exists('STS_NOT_IMPLEMENTED')) {

  /** Envia o status NÃO IMPLEMENTADO como resposta da requisição */
  function STS_NOT_IMPLEMENTED(string $message = STS_NOT_IMPLEMENTED): never
  {
    throw new Exception($message, STS_NOT_IMPLEMENTED);
  }
}

if (!function_exists('STS_SERVICE_UNAVAILABLE')) {

  /** Envia o status INDISPONÍVEL como resposta da requisição */
  function STS_SERVICE_UNAVAILABLE(string $message = STS_SERVICE_UNAVAILABLE): never
  {
    throw new Exception($message, STS_SERVICE_UNAVAILABLE);
  }
}
