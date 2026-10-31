<?php

declare(strict_types=1);

namespace Zana\Core\Inspection;

/**
 * One result of the check command (ADR-023), readable by people and agents.
 *
 *     new Finding('routing.handler', Severity::Error, 'GET /en/about: Modules\Site\…\PageController is not registered.',
 *         hint: 'Register it in SiteModule::register(): $services->transient(PageController::class);');
 */
final readonly class Finding
{
    /**
     * @param string $id "<source>.<rule>", stable across versions, e.g. "container.B5" or "architecture.layers"
     * @param string|null $hint What to do about it
     */
    public function __construct(
        public string $id,
        public Severity $severity,
        public string $message,
        public ?string $file = null,
        public ?int $line = null,
        public ?string $hint = null,
    ) {
    }

    /**
     * @return array{id: string, severity: string, message: string, file: string|null, line: int|null, hint: string|null}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'severity' => $this->severity->value,
            'message' => $this->message,
            'file' => $this->file,
            'line' => $this->line,
            'hint' => $this->hint,
        ];
    }
}
