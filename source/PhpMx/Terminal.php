<?php

namespace PhpMx;

use Exception;
use ReflectionMethod;

abstract class Terminal
{
    /** Executa uma linha de comando */
    final static function run(...$commandLine)
    {
        $showLog = false;


        $commandLine = array_map(fn($v) => trim($v), $commandLine);
        $commandLine = array_filter($commandLine, fn($v) => boolval($v));

        if (!empty($commandLine) && str_starts_with($commandLine[0], '+')) {
            $showLog = true;
            $commandLine[0] = substr($commandLine[0], 1);
            if (empty($commandLine[0])) unset($commandLine[0]);
        }

        if (empty($commandLine)) $commandLine = ['logo'];

        $result = log_add('MX', 'terminal [#]', [implode(' ', $commandLine)], function () use ($commandLine) {
            try {

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
                    throw new Exception("Command [$command] not fond");

                $action = Import::return($commandFile);

                if (!is_class($action, Terminal::class))
                    throw new Exception("Command [$command] not extends [" . static::class . "]");

                $reflection = new ReflectionMethod($action, '__invoke');

                $countParams = count($params);
                foreach ($reflection->getparameters() as $required) {
                    if ($countParams) {
                        $countParams--;
                    } elseif (!$required->isDefaultValueAvailable()) {
                        $name = $required->getName();
                        throw new Exception("Parameter [$name] is required in [$command]");
                    }
                }

                return $action(...$params);
            } catch (Exception $e) {
                self::echo('Exception');
                self::echo(' | [#]', $e->getMessage());
                self::echo(' | [#] ([#])', [$e->getFile(), $e->getLine()]);
                log_exception($e);
                return false;
            }
        });

        if (env('DEV') && $showLog) {
            self::echo();
            self::echo(Log::getString());
        }

        return $result;
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
