<?php

namespace App\Logging;

use Monolog\Formatter\JsonFormatter;
use Monolog\LogRecord;
use Throwable;

class StructuredFormatter extends JsonFormatter
{
    public function format(LogRecord $record): string
    {
        $data = [
            'level' => strtolower($record->level->getName()),
            'message' => $record->message,
            'context' => $this->normalizeContext($record->context),
        ];

        return $this->toJson($data) . "\n";
    }

    private function normalizeContext(array $context): array
    {
        foreach ($context as $key => $value) {
            if ($value instanceof Throwable) {
                $context[$key] = $this->formatException($value);
            }
        }

        return $context;
    }

    private function formatException(Throwable $exception): array
    {
        return [
            'class' => $exception::class,
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $this->applicationTrace($exception),
        ];
    }

    private function applicationTrace(Throwable $exception): array
    {
        return collect($exception->getTrace())
            ->filter(function (array $frame) {
                return $this->isApplicationFile($frame['file'] ?? null);
            })
            ->map(function (array $frame) {
                return [
                    'file' => $frame['file'] ?? null,
                    'line' => $frame['line'] ?? null,
                    'function' => $frame['function'] ?? null,
                    'class' => $frame['class'] ?? null,
                ];
            })
            ->values()
            ->all();
    }

    private function isApplicationFile(?string $file): bool
    {
        if (!$file) {
            return false;
        }

        $basePath = base_path();

        $allowed = [
            $basePath . '/app/',
            $basePath . '/routes/',
            $basePath . '/database/',
            $basePath . '/config/',
        ];

        foreach ($allowed as $path) {
            if (str_starts_with($file, $path)) {
                return true;
            }
        }

        return false;
    }
}
