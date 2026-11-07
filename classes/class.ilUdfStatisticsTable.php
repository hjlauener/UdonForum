<?php

declare(strict_types=1);

use ILIAS\Data\Order;
use ILIAS\Data\Range;
use ILIAS\UI\Component\Table\DataRetrieval;
use ILIAS\UI\Component\Table\DataRowBuilder;

/**
 * Data of the statistics table "Standard": one row per participant with
 * name, first name, e-mail, number of posts, number of replies and number of
 * words. Sorting and paging are done here (the ILIAS data table asks for the
 * rows in the order and range the user chose).
 */
class ilUdfStatisticsTable implements DataRetrieval
{
    public const COL_LASTNAME = 'lastname';
    public const COL_FIRSTNAME = 'firstname';
    public const COL_EMAIL = 'email';
    public const COL_POSTS = 'posts';
    public const COL_REPLIES = 'replies';
    public const COL_WORDS = 'words';

    private const NUMERIC_COLUMNS = [self::COL_POSTS, self::COL_REPLIES, self::COL_WORDS];

    /**
     * @param array<int, array<string, string|int>> $records user id => record (keys: COL_*)
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
        $sorted = $this->sort($this->records, $order);
        $page = array_slice($sorted, $range->getStart(), $range->getLength(), true);
        foreach ($page as $user_id => $record) {
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
     * @param array<int, array<string, string|int>> $records
     * @return array<int, array<string, string|int>>
     */
    public function sort(array $records, Order $order): array
    {
        $subjects = $order->get();
        $column = (string) array_key_first($subjects);
        if (!in_array($column, [self::COL_LASTNAME, self::COL_FIRSTNAME, self::COL_EMAIL, ...self::NUMERIC_COLUMNS], true)) {
            $column = self::COL_LASTNAME;
        }
        $direction = ($subjects[$column] ?? Order::ASC) === Order::DESC ? -1 : 1;

        uksort($records, function (int $a, int $b) use ($records, $column, $direction): int {
            $cmp = $this->compare($records[$a][$column], $records[$b][$column], $column);
            if ($cmp === 0) {
                // same value: always by name, then first name
                $cmp = $this->compare($records[$a][self::COL_LASTNAME], $records[$b][self::COL_LASTNAME], self::COL_LASTNAME)
                    ?: $this->compare($records[$a][self::COL_FIRSTNAME], $records[$b][self::COL_FIRSTNAME], self::COL_FIRSTNAME);
                return $cmp;
            }
            return $direction * $cmp;
        });
        return $records;
    }

    private function compare(string|int $a, string|int $b, string $column): int
    {
        if (in_array($column, self::NUMERIC_COLUMNS, true)) {
            return (int) $a <=> (int) $b;
        }
        return strnatcasecmp(self::sortKey((string) $a), self::sortKey((string) $b));
    }

    /**
     * Umlauts are sorted like their base letter (Müller next to Muller).
     */
    private static function sortKey(string $text): string
    {
        $map = ['ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'Ä' => 'a', 'Ö' => 'o', 'Ü' => 'u', 'é' => 'e', 'è' => 'e', 'à' => 'a', 'ç' => 'c', 'ß' => 'ss'];
        return mb_strtolower(strtr($text, $map));
    }

    /**
     * Number of words of a text (Markdown characters do not count).
     */
    public static function countWords(string $plain_text): int
    {
        // words with apostrophe or hyphen (p-Wert) and numbers like 0.05 or 3,5 count as one word
        return (int) preg_match_all('/[\p{L}\p{N}]+(?:[\'’\-][\p{L}\p{N}]+|(?<=\p{N})[.,]\p{N}+)*/u', $plain_text);
    }
}
