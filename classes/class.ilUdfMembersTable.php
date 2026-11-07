<?php

declare(strict_types=1);

use ILIAS\Data\Order;
use ILIAS\Data\Range;
use ILIAS\UI\Component\Table\DataRetrieval;
use ILIAS\UI\Component\Table\DataRowBuilder;

/**
 * Data of the members table (tab "Mitglieder", sub tab "Ansicht"): text
 * columns, sortable by every column header, with paging.
 */
class ilUdfMembersTable implements DataRetrieval
{
    /**
     * @param array<int, array<string, string>> $records user id => column => text
     */
    public function __construct(private array $records)
    {
    }

    public function getRows(
        DataRowBuilder $row_builder,
        array $visible_column_ids,
        Range $range,
        Order $order,
        mixed $additional_viewcontrol_data,
        mixed $filter_data,
        mixed $additional_parameters
    ): Generator {
        foreach (array_slice($this->sort($this->records, $order), $range->getStart(), $range->getLength(), true) as $user_id => $record) {
            yield $row_builder->buildDataRow((string) $user_id, $record);
        }
    }

    public function getTotalRowCount(
        mixed $additional_viewcontrol_data,
        mixed $filter_data,
        mixed $additional_parameters
    ): ?int {
        return count($this->records);
    }

    /**
     * @param array<int, array<string, string>> $records
     * @return array<int, array<string, string>>
     */
    public function sort(array $records, Order $order): array
    {
        $subjects = $order->get();
        $column = (string) array_key_first($subjects);
        $first = reset($records);
        if ($first === false || !array_key_exists($column, $first)) {
            $column = 'lastname';
        }
        $direction = ($subjects[$column] ?? Order::ASC) === Order::DESC ? -1 : 1;

        uksort($records, static function (int $a, int $b) use ($records, $column, $direction): int {
            $cmp = strnatcasecmp(self::key($records[$a][$column] ?? ''), self::key($records[$b][$column] ?? ''));
            if ($cmp !== 0) {
                return $direction * $cmp;
            }
            // same value: by name, then first name
            return strnatcasecmp(self::key($records[$a]['lastname'] ?? ''), self::key($records[$b]['lastname'] ?? ''))
                ?: strnatcasecmp(self::key($records[$a]['firstname'] ?? ''), self::key($records[$b]['firstname'] ?? ''));
        });
        return $records;
    }

    private static function key(string $text): string
    {
        $map = ['ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'Ä' => 'a', 'Ö' => 'o', 'Ü' => 'u', 'é' => 'e', 'è' => 'e', 'à' => 'a', 'ç' => 'c', 'ß' => 'ss'];
        return mb_strtolower(strtr($text, $map));
    }
}
