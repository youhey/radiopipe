<?php

namespace App\Topics\Screening;

use App\Models\TopicScreeningKeywordRule;
use DateInterval;
use DateTimeInterface;
use InvalidArgumentException;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
use Stringable;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Topic screening keyword rule の xlsx import/export を扱う service。
 */
class TopicScreeningKeywordRuleSpreadsheet
{
    private const FILE_NAME = 'topic-screening-keyword-rules.xlsx';

    /** @var list<string> */
    private const HEADERS = [
        'rule_type',
        'keyword',
        'match_type',
        'target_fields',
        'penalty',
        'severity',
        'action',
        'is_active',
        'sort_order',
        'notes',
    ];

    /**
     * Excel download response を返す。
     */
    public function downloadResponse(): StreamedResponse
    {
        $path = $this->temporaryFilePath();

        $this->write($path);

        return response()->streamDownload(
            static function () use ($path): void {
                readfile($path);
                @unlink($path);
            },
            self::FILE_NAME,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    /**
     * Topic screening keyword rules を xlsx に書き出す。
     */
    public function write(string $path): void
    {
        $writer = new Writer();
        $writer->openToFile($path);

        try {
            $writer->addRow(Row::fromValues(self::HEADERS));

            $query = TopicScreeningKeywordRule::query();
            $query->getQuery()
                ->orderBy('rule_type')
                ->orderBy('sort_order')
                ->orderBy('keyword');

            $query->each(function (TopicScreeningKeywordRule $rule) use ($writer): void {
                $writer->addRow(Row::fromValues([
                    $rule->rule_type,
                    $rule->keyword,
                    $rule->match_type,
                    $this->formatTargetFields($rule->target_fields),
                    $rule->penalty,
                    $rule->severity,
                    $rule->action,
                    $rule->is_active ? 'true' : 'false',
                    $rule->sort_order,
                    $rule->notes,
                ]));
            });
        } finally {
            $writer->close();
        }
    }

    /**
     * xlsx から Topic screening keyword rules を作成・更新する。
     */
    public function import(string $path): TopicScreeningKeywordRuleImportResult
    {
        $reader = new Reader();
        $reader->open($path);

        $headers = null;
        $createdCount = 0;
        $updatedCount = 0;
        $skippedCount = 0;

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $rowNumber => $row) {
                    $rowIndex = $this->rowIndex($rowNumber);
                    $values = $this->rowValues($row);

                    if ($rowIndex === 1) {
                        $headers = $this->headers($values);

                        continue;
                    }

                    if ($headers === null) {
                        throw new InvalidArgumentException('Topic screening keyword rule spreadsheet header is missing.');
                    }

                    if ($this->isEmptyRow($values)) {
                        ++$skippedCount;

                        continue;
                    }

                    $payload = $this->payload($headers, $values, $rowIndex);
                    $rule = TopicScreeningKeywordRule::query()
                        ->where('rule_type', $payload['rule_type'])
                        ->where('keyword', $payload['keyword'])
                        ->where('match_type', $payload['match_type'])
                        ->first();

                    if ($rule instanceof TopicScreeningKeywordRule) {
                        $rule->fill($payload);
                        $rule->save();
                        ++$updatedCount;
                    } else {
                        TopicScreeningKeywordRule::query()->create($payload);
                        ++$createdCount;
                    }
                }

                break;
            }
        } finally {
            $reader->close();
        }

        return new TopicScreeningKeywordRuleImportResult($createdCount, $updatedCount, $skippedCount);
    }

    /**
     * row iterator の key を行番号へ変換する。
     */
    private function rowIndex(mixed $rowNumber): int
    {
        if (is_int($rowNumber)) {
            return $rowNumber;
        }

        if (is_string($rowNumber) && ctype_digit($rowNumber)) {
            return (int) $rowNumber;
        }

        return 0;
    }

    /**
     * target_fields を spreadsheet 用文字列へ変換する。
     *
     * @param mixed $targetFields
     */
    private function formatTargetFields(mixed $targetFields): string
    {
        if (! is_array($targetFields)) {
            return '';
        }

        $fields = [];

        foreach ($targetFields as $field) {
            if (is_string($field) && $field !== '') {
                $fields[] = $field;
            }
        }

        return implode(',', $fields);
    }

    /**
     * Row を scalar 値 list に変換する。
     *
     * @return list<mixed>
     */
    private function rowValues(Row $row): array
    {
        return array_values(array_map(
            static fn (Cell $cell): mixed => $cell->getValue(),
            $row->getCells(),
        ));
    }

    /**
     * header 行を検証して返す。
     *
     * @param list<mixed> $values
     *
     * @return list<string>
     */
    private function headers(array $values): array
    {
        $headers = [];

        foreach ($values as $value) {
            $headers[] = $this->stringValue($value);
        }

        foreach (self::HEADERS as $requiredHeader) {
            if (! in_array($requiredHeader, $headers, true)) {
                throw new InvalidArgumentException("Topic screening keyword rule spreadsheet header [{$requiredHeader}] is missing.");
            }
        }

        return $headers;
    }

    /**
     * 空行かどうかを判定する。
     *
     * @param list<mixed> $values
     */
    private function isEmptyRow(array $values): bool
    {
        foreach ($values as $value) {
            if ($this->stringValue($value) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * spreadsheet 行を保存 payload に変換する。
     *
     * @param list<string> $headers
     * @param list<mixed> $values
     *
     * @return array<string, mixed>
     */
    private function payload(array $headers, array $values, int $rowNumber): array
    {
        $row = array_combine($headers, array_slice(array_pad($values, count($headers), null), 0, count($headers)));

        return [
            'rule_type' => $this->requiredOption($row, 'rule_type', TopicScreeningKeywordRule::ruleTypeOptions(), $rowNumber),
            'keyword' => $this->requiredString($row, 'keyword', $rowNumber, 255),
            'match_type' => $this->requiredOption($row, 'match_type', TopicScreeningKeywordRule::matchTypeOptions(), $rowNumber),
            'target_fields' => $this->targetFields($row['target_fields'] ?? null, $rowNumber),
            'penalty' => $this->nullableInteger($row, 'penalty', $rowNumber),
            'severity' => $this->nullableString($row, 'severity', 50),
            'action' => $this->requiredOption($row, 'action', TopicScreeningKeywordRule::actionOptions(), $rowNumber),
            'is_active' => $this->booleanValue($row['is_active'] ?? null, $rowNumber),
            'sort_order' => $this->integerValue($row, 'sort_order', $rowNumber),
            'notes' => $this->nullableString($row, 'notes', 65535),
        ];
    }

    /**
     * 必須文字列を取り出す。
     *
     * @param array<string, mixed> $row
     */
    private function requiredString(array $row, string $key, int $rowNumber, int $maxLength): string
    {
        $value = $this->stringValue($row[$key] ?? null);

        if ($value === '') {
            throw new InvalidArgumentException("Topic screening keyword rule row [{$rowNumber}] field [{$key}] is required.");
        }

        if (mb_strlen($value) > $maxLength) {
            throw new InvalidArgumentException("Topic screening keyword rule row [{$rowNumber}] field [{$key}] is too long.");
        }

        return $value;
    }

    /**
     * allow-list 付き必須文字列を取り出す。
     *
     * @param array<string, mixed> $row
     * @param array<string, string> $options
     */
    private function requiredOption(array $row, string $key, array $options, int $rowNumber): string
    {
        $value = $this->requiredString($row, $key, $rowNumber, 255);

        if (! array_key_exists($value, $options)) {
            throw new InvalidArgumentException("Topic screening keyword rule row [{$rowNumber}] field [{$key}] has unsupported value [{$value}].");
        }

        return $value;
    }

    /**
     * target_fields セルを list に変換する。
     *
     * @return list<string>
     */
    private function targetFields(mixed $value, int $rowNumber): array
    {
        $text = $this->stringValue($value);
        $splitFields = preg_split('/[\r\n,;]+/', $text);
        $fields = $splitFields === false ? [] : $splitFields;
        $normalized = [];
        $options = TopicScreeningKeywordRule::targetFieldOptions();

        foreach ($fields as $field) {
            $field = trim($field);

            if ($field === '') {
                continue;
            }

            if (! array_key_exists($field, $options)) {
                throw new InvalidArgumentException("Topic screening keyword rule row [{$rowNumber}] field [target_fields] has unsupported value [{$field}].");
            }

            $normalized[] = $field;
        }

        $normalized = array_values(array_unique($normalized));

        if ($normalized === []) {
            throw new InvalidArgumentException("Topic screening keyword rule row [{$rowNumber}] field [target_fields] is required.");
        }

        return $normalized;
    }

    /**
     * nullable integer を取り出す。
     *
     * @param array<string, mixed> $row
     */
    private function nullableInteger(array $row, string $key, int $rowNumber): ?int
    {
        $value = $this->stringValue($row[$key] ?? null);

        if ($value === '') {
            return null;
        }

        if (! ctype_digit($value)) {
            throw new InvalidArgumentException("Topic screening keyword rule row [{$rowNumber}] field [{$key}] must be a non-negative integer.");
        }

        return (int) $value;
    }

    /**
     * integer を取り出す。
     *
     * @param array<string, mixed> $row
     */
    private function integerValue(array $row, string $key, int $rowNumber): int
    {
        return $this->nullableInteger($row, $key, $rowNumber) ?? 0;
    }

    /**
     * nullable string を取り出す。
     *
     * @param array<string, mixed> $row
     */
    private function nullableString(array $row, string $key, int $maxLength): ?string
    {
        $value = $this->stringValue($row[$key] ?? null);

        if ($value === '') {
            return null;
        }

        if (mb_strlen($value) > $maxLength) {
            throw new InvalidArgumentException("Topic screening keyword rule field [{$key}] is too long.");
        }

        return $value;
    }

    /**
     * boolean 値を取り出す。
     */
    private function booleanValue(mixed $value, int $rowNumber): bool
    {
        $normalized = strtolower($this->stringValue($value));

        return match ($normalized) {
            '', '1', 'true', 'yes', 'on' => true,
            '0', 'false', 'no', 'off' => false,
            default => throw new InvalidArgumentException("Topic screening keyword rule row [{$rowNumber}] field [is_active] must be boolean."),
        };
    }

    /**
     * spreadsheet cell value を trim 済み文字列へ変換する。
     */
    private function stringValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            return (string) (int) $value;
        }

        if (is_string($value)) {
            return trim($value);
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if ($value instanceof DateInterval) {
            return '';
        }

        if ($value instanceof Stringable) {
            return trim((string) $value);
        }

        return '';
    }

    /**
     * xlsx 出力用の一時ファイル path を返す。
     */
    private function temporaryFilePath(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'radiopipe-screening-rules-');

        if ($path === false) {
            throw new InvalidArgumentException('Could not create a temporary file for topic screening keyword rule export.');
        }

        return $path;
    }
}
