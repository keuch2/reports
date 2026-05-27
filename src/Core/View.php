<?php

declare(strict_types=1);

namespace MisterCo\Reports\Core;

use RuntimeException;

/**
 * Renderiza plantillas PHP. Escapado obligatorio en plantillas vía $this->e().
 */
final class View
{
    public function __construct(
        private readonly string $templatesPath,
        private readonly Session $session,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data = [], ?string $layout = 'layouts/main'): string
    {
        $content = $this->renderPartial($template, $data);

        if ($layout === null) {
            return $content;
        }

        return $this->renderPartial($layout, array_merge($data, ['content' => $content]));
    }

    /**
     * @param array<string, mixed> $data
     */
    public function renderPartial(string $template, array $data = []): string
    {
        $file = $this->templatesPath . '/' . $template . '.php';

        if (!is_file($file)) {
            throw new RuntimeException("Plantilla no encontrada: {$template}");
        }

        $view = $this;
        $session = $this->session;

        extract($data, EXTR_SKIP);

        ob_start();
        require $file;

        return (string) ob_get_clean();
    }

    public function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }

    public function csrfField(): string
    {
        $token = $this->session->csrfToken();

        return '<input type="hidden" name="_csrf" value="' . $this->e($token) . '">';
    }
}
