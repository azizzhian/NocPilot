<?php

namespace App\Support;

class SimpleTemplateEngine
{
    public function render(string $template, array $data): string
    {
        $template = $this->renderInvertedSections($template, $data);
        $template = $this->renderSections($template, $data);

        return $this->renderVariables($template, $data);
    }

    private function renderSections(string $template, array $data): string
    {
        return (string) preg_replace_callback(
            '/\{\{#(\w+)\}\}(.*?)\{\{\/\1\}\}/s',
            function (array $matches) use ($data) {
                $key = $matches[1];
                $inner = $matches[2];
                $value = $data[$key] ?? null;

                if (is_array($value) && $this->isList($value)) {
                    $output = '';
                    foreach ($value as $item) {
                        $scope = is_array($item)
                            ? array_merge($data, $item)
                            : array_merge($data, [$key => $item]);
                        $output .= $this->render($inner, $scope);
                    }

                    return $output;
                }

                if ($this->isTruthy($value)) {
                    return $this->render($inner, $data);
                }

                return '';
            },
            $template,
        );
    }

    private function renderInvertedSections(string $template, array $data): string
    {
        return (string) preg_replace_callback(
            '/\{\{\^(\w+)\}\}(.*?)\{\{\/\1\}\}/s',
            function (array $matches) use ($data) {
                $key = $matches[1];
                $inner = $matches[2];
                $value = $data[$key] ?? null;

                if ($this->isEmptyValue($value)) {
                    return $this->render($inner, $data);
                }

                return '';
            },
            $template,
        );
    }

    private function renderVariables(string $template, array $data): string
    {
        return (string) preg_replace_callback(
            '/\{\{(\w+)\}\}/',
            fn (array $matches) => (string) ($data[$matches[1]] ?? ''),
            $template,
        );
    }

    private function isList(array $value): bool
    {
        if ($value === []) {
            return true;
        }

        return array_keys($value) === range(0, count($value) - 1);
    }

    private function isTruthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_array($value)) {
            return $value !== [];
        }

        return $value !== null && $value !== '';
    }

    private function isEmptyValue(mixed $value): bool
    {
        if ($value === null || $value === false) {
            return true;
        }

        if (is_array($value)) {
            return $value === [];
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        return false;
    }
}
