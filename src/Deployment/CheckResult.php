<?php

declare(strict_types=1);

namespace Zana\Core\Deployment;

final readonly class CheckResult
{
    public function __construct(
        public CheckStatus $status,
        public string $message = '',
    ) {
    }

    public static function ok(): self
    {
        return new self(CheckStatus::Ok);
    }

    public static function warning(string $message): self
    {
        return new self(CheckStatus::Warning, $message);
    }

    public static function fatal(string $message): self
    {
        return new self(CheckStatus::Fatal, $message);
    }
}
