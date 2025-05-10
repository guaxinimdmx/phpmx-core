<?php

namespace PhpMx;

abstract class Import
{
    /** Importa um arquivo PHP */
    static function only(string $filePath, bool $once = true): bool
    {
        $filePath = Path::format($filePath);
        $filePath = File::setEx($filePath, 'php');

        if (File::check($filePath))
            return $once ? require_once $filePath : require $filePath;

        return false;
    }

    /** Retorna o conteúdo de um aquivo */
    static function content(string $filePath, string|array $prepare = []): string
    {
        $filePath = Path::format($filePath);
        $content = File::check($filePath) ? file_get_contents($filePath) : '';
        $return = Prepare::prepare($content, $prepare);
        return $return;
    }

    /** Retorna o resultado (return) em um arquivo php  */
    static function return(string $filePath, array $params = []): mixed
    {
        $filePath = Path::format($filePath);
        $filePath = File::setEx($filePath, 'php');

        if (File::check($filePath)) {
            $return = function ($__FILEPATH__, &$__PARAMS__) {
                foreach (array_keys($__PARAMS__) as $__KEY__)
                    if (!is_numeric($__KEY__))
                        $$__KEY__ = &$__PARAMS__[$__KEY__];
                ob_start();
                $__RETURN__ = require $__FILEPATH__;
                ob_end_clean();
                return $__RETURN__;
            };

            $return = $return($filePath, $params);
        }

        return $return ?? null;
    }

    /** Retorna o valor de uma variavel dentro de em um arquivo php  */
    static function var(string $filePath, string $varName, array $params = []): mixed
    {
        $filePath = Path::format($filePath);
        $filePath = File::setEx($filePath, 'php');

        if (File::check($filePath)) {
            $return = function ($__FILEPATH__, $__VARNAME__, &$__PARAMS__) {
                foreach (array_keys($__PARAMS__) as $__KEY__)
                    if (!is_numeric($__KEY__))
                        $$__KEY__ = &$__PARAMS__[$__KEY__];
                ob_start();
                require $__FILEPATH__;
                $__RETURN__ = $$__VARNAME__ ?? null;
                ob_end_clean();
                return $__RETURN__;
            };

            $return = $return($filePath, $varName, $params);
        }

        return $return ?? null;
    }

    /** Retorna a saída de texto gerada por um arquivo */
    static function output(string $filePath, array $params = []): string
    {
        $filePath = Path::format($filePath);

        if (File::check($filePath)) {
            $return = function ($__FILEPATH__, &$__PARAMS__) {
                foreach (array_keys($__PARAMS__) as $__KEY__)
                    if (!is_numeric($__KEY__))
                        $$__KEY__ = &$__PARAMS__[$__KEY__];
                ob_start();
                require $__FILEPATH__;
                $__RETURN__ = ob_get_clean();
                return $__RETURN__;
            };
            $return = $return($filePath, $params);
        }

        return $return ?? '';
    }
}
