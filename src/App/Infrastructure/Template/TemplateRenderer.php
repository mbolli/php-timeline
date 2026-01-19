<?php

declare(strict_types=1);

namespace App\Infrastructure\Template;

final class TemplateRenderer {
    public function __construct(
        private readonly string $templatesPath,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data = []): string {
        $templateFile = $this->templatesPath . '/' . $template . '.php';

        if (!file_exists($templateFile)) {
            throw new \RuntimeException("Template not found: {$template}");
        }

        extract($data);

        ob_start();

        include $templateFile;

        return ob_get_clean();
    }
}
