<?php

namespace PhpMx;

use Error;
use Exception;
use ReflectionMethod;

abstract class Terminal
{
    /** Executa uma linha de comando */
    final static function run(...$commandLine)
    {
        try {
            $commandLine = array_map(fn($v) => trim($v), $commandLine);
            $commandLine = array_filter($commandLine, fn($v) => boolval($v));
            if (empty($commandLine)) $commandLine = ['logo'];

            $command = array_shift($commandLine);
            $params = $commandLine;

            $commandFile = remove_accents($command);
            $commandFile = strtolower($commandFile);

            $commandFile = explode('.', $commandFile);
            $commandFile = array_map(fn($v) => strtolower($v), $commandFile);
            $commandFile = Path::format('terminal', ...$commandFile);
            $commandFile = File::setEx($commandFile, 'php');

            $commandFile = Path::seekFile($commandFile);

            if (!$commandFile)
                throw new Error("Command [$command] not fond");

            $action = Import::return($commandFile);

            if (!is_class($action, Terminal::class))
                throw new Error("Command [$command] not extends [" . static::class . "]");

            $reflection = new ReflectionMethod($action, '__invoke');

            $countParams = count($params);
            foreach ($reflection->getparameters() as $required) {
                if ($countParams) {
                    $countParams--;
                } elseif (!$required->isDefaultValueAvailable()) {
                    $name = $required->getName();
                    throw new Error("Parameter [$name] is required in [$command]");
                }
            }

            return $action(...$params);
        } catch (Exception | Error $e) {
            self::echo('ERROR');
            self::echo(' | [#]', $e->getMessage());
            self::echo(' | [#] ([#])', [$e->getFile(), $e->getLine()]);
            return false;
        }
    }

    /** Exibe uma linha de texto no terminal */
    static function echo(string $line = '', string|array $prepare = []): void
    {
        echo Prepare::prepare("$line\n", $prepare);
    }

    /** Exibe uma linha de separação no terminal */
    static function echoLine(): void
    {
        self::echo('------------------------------------------------------------');
    }
}
