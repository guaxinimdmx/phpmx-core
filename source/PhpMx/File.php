<?php

namespace PhpMx;

abstract class File
{
    /** Cria um arquivo de texto */
    static function create(string $path, string $content, bool $recreate = false): ?bool
    {
        if ($recreate || !self::check($path)) {
            $path = Path::format($path);
            Dir::create($path);
            $fp = fopen($path, 'w');
            fwrite($fp, $content);
            fclose($fp);
            return true;
        }
        return null;
    }

    /** Remove um arquivo */
    static function remove(string $path): ?bool
    {
        if (self::check($path)) {
            $path = Path::format($path);
            unlink($path);
            return !is_file($path);
        }
        return null;
    }

    /** Cria uma copia de um arquivo */
    static function copy(string $path_from, string $path_for, bool $recreate = false): ?bool
    {
        if ($recreate || !self::check($path_for)) {
            if (self::check($path_from)) {
                Dir::create($path_for);
                return boolval(copy(Path::format($path_from), Path::format($path_for)));
            }
        }
        return null;
    }

    /** Altera o local de um arquivo */
    static function move(string $path_from, string $path_for, bool $replace = false): ?bool
    {
        if ($replace || !self::check($path_for)) {
            if (self::check($path_from)) {
                Dir::create($path_for);
                return boolval(rename(Path::format($path_from), Path::format($path_for)));
            }
        }
        return null;
    }

    /** Retorna apenas o nome do arquivo com a extensão */
    static function getOnly(string $path): string
    {
        $path = Path::format($path);
        $path = explode('/', $path);
        return array_pop($path);
    }

    /** Retorna apenas o nome do arquivo */
    static function getName(string $path): string
    {
        $fileName = self::getOnly($path);
        $ex = self::getEx($path);
        return substr($fileName, 0, (strlen($ex) + 1) * -1);
    }

    /** Retorna apenas a extensão do arquivo */
    static function getEx(string $path): string
    {
        $parts = explode('.', self::getOnly($path));
        return strtolower(end($parts));
    }

    /** Define/Altera a extensão de um arquivo */
    static function setEx(string $path, string $extension = 'php'): string
    {
        if (!str_ends_with($path, ".$extension")) {
            $path = explode('.', $path);

            if (count($path) > 1) array_pop($path);

            $path[] = $extension;

            $path = implode('.', $path);
        }
        return $path;
    }

    /** Verifica se um arquivo existe */
    static function check(string $path): bool
    {
        return is_file(Path::format($path));
    }

    /** Retorna o tamanho do arquivo */
    public static function getSize($path, $human = true): int|string
    {
        $path = Path::format($path);

        if (!self::check($path)) return '-';

        $size =  filesize($path);

        if ($human) {
            $units = [' b', ' kb', ' mb', ' gb', ' tb'];
            $i = 0;
            while ($size >= 1024 && $i < count($units) - 1) {
                $size /= 1024;
                $i++;
            }

            $size = round($size, 2) .  $units[$i];
        }

        return $size;
    }

    /** Retorna a data de modificação do arquivo */
    public static function getLastModified($path): ?int
    {
        $path = Path::format($path);
        return self::check($path) ? filemtime($path) : null;
    }
}
