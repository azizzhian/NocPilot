<?php

namespace App\Services\Report;

use App\Models\ReportTemplate;
use App\Support\ReportTemplateDefaults;

class ReportTemplateService
{
    /** @return array<string, array{body: string, is_custom: bool, hints: array<string, string>}> */
    public function all(): array
    {
        $saved = ReportTemplate::query()->get()->keyBy('type');

        $types = [
            ReportTemplate::TYPE_DAILY,
            ReportTemplate::TYPE_NOC,
            ReportTemplate::TYPE_MONITORING,
        ];

        $result = [];
        foreach ($types as $type) {
            $custom = $saved->get($type);
            $result[$type] = [
                'body' => $custom?->body ?? ReportTemplateDefaults::body($type),
                'is_custom' => $custom !== null,
                'hints' => ReportTemplateDefaults::hints($type),
            ];
        }

        return $result;
    }

    public function bodyFor(string $type): string
    {
        $custom = ReportTemplate::query()->where('type', $type)->value('body');

        return $custom ?? ReportTemplateDefaults::body($type);
    }

    public function save(string $type, string $body): ReportTemplate
    {
        return ReportTemplate::updateOrCreate(['type' => $type], ['body' => $body]);
    }

    public function reset(string $type): void
    {
        ReportTemplate::query()->where('type', $type)->delete();
    }

    /** @return array<string, string> */
    public function parseSectionedTemplate(string $body): array
    {
        $sections = [];
        $current = 'default';
        $buffer = [];

        foreach (preg_split('/\R/', $body) as $line) {
            if (preg_match('/^@@(\w+)@@$/', trim($line), $matches)) {
                if ($buffer !== [] || $current !== 'default') {
                    $sections[$current] = rtrim(implode("\n", $buffer))."\n";
                }
                $current = $matches[1];
                $buffer = [];

                continue;
            }

            $buffer[] = $line;
        }

        if ($buffer !== [] || isset($sections[$current])) {
            $sections[$current] = rtrim(implode("\n", $buffer))."\n";
        }

        return $sections;
    }
}
