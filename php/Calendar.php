<?php
declare(strict_types=1);

class Calendar
{
    private string $lang;
    private array $entries = [];

    public function __construct(string $lang = 'de', array $entries = [])
    {
        $this->lang = in_array($lang, ['de', 'en', 'sql'], true) ? $lang : 'de';
        $this->setEntries($entries);
    }

    /**
     * Add or append event entries.
     */
    public function addEntry(string $date, string|array $description): void
    {
        $date = $this->normalizeDate($date);
        if ($date === null) {
            return;
        }

        $desc = (array)$description;
        $this->entries[$date] = array_merge($this->entries[$date] ?? [], $desc);
    }

    /**
     * Replace all entries.
     */
    public function setEntries(array $entries): void
    {
        $this->entries = [];
        foreach ($entries as $date => $desc) {
            $this->addEntry($date, $desc);
        }
    }

    /**
     * Get all entries.
     */
    public function getEntries(): array
    {
        return $this->entries;
    }

    /**
     * Main calendar rendering method.
     * Optionally accepts an $extraEntries array for per-render highlighting.
     */
    public function make_calendar(int $m, int $y, string $target, array $extraEntries = []): string
    {
        // Merge optional month-specific entries temporarily
        $mergedEntries = $this->entries;
        foreach ($extraEntries as $date => $desc) {
            $norm = $this->normalizeDate($date);
            if ($norm) {
                $mergedEntries[$norm] = array_merge($mergedEntries[$norm] ?? [], (array)$desc);
            }
        }

        $months = [
            '', 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni',
            'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'
        ];

        $daysOfWeek = ($this->lang === 'en')
            ? ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su']
            : ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];

        $today = getdate();
        $m = ($m < 1 || $m > 12) ? $today['mon'] : $m;
        $y = ($y <= 0) ? $today['year'] : $y;

        $daysInMonth = [31, $this->isLeapYear($y) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];

        [$prevM, $prevY] = ($m === 1) ? [12, $y - 1] : [$m - 1, $y];
        [$nextM, $nextY] = ($m === 12) ? [1, $y + 1] : [$m + 1, $y];

        $prevLink = "<a href='#' data-myv='$prevM|$prevY|$target' title='Previous month'>&lt;&lt;</a>";
        $nextLink = "<a href='#' data-myv='$nextM|$nextY|$target' title='Next month'>&gt;&gt;</a>";

        $html = [];
        $html[] = "<table class='table' id='hgs_calendar' style='background:white;font-size:0.8em;border:1px solid black'>";
        $html[] = "<tr style='background:silver'>
            <th>$prevLink</th>
            <th colspan='4'>{$months[$m]} $y</th>
            <th>$nextLink</th>
            <th style='cursor:pointer;color:white;background:darkred;' title='Close'><b id='close'>X</b></th>
        </tr>";
        $html[] = '<tr>' . implode('', array_map(fn($d) => "<th>$d</th>", $daysOfWeek)) . '</tr><tr>';

        $firstWeekday = (int)date('N', mktime(0, 0, 0, $m, 1, $y));
        $prevDaysStart = $daysInMonth[$prevM - 1] - $firstWeekday + 2;

        for ($i = 1; $i < $firstWeekday; $i++) {
            $date = $this->formatDate($prevDaysStart, $prevM, $prevY);
            $html[] = $this->makeDayCell($prevDaysStart++, $date, 'hgspcc', $mergedEntries);
        }

        $cellCount = $firstWeekday;
        for ($day = 1; $day <= $daysInMonth[$m - 1]; $day++, $cellCount++) {
            $date = $this->formatDate($day, $m, $y);
            $html[] = $this->makeDayCell($day, $date, 'hgsacc', $mergedEntries);
            if ($cellCount % 7 === 0) {
                $html[] = "</tr><tr>";
            }
        }

        for ($day = 1; $cellCount % 7 !== 1; $day++, $cellCount++) {
            $date = $this->formatDate($day, $nextM, $nextY);
            $html[] = $this->makeDayCell($day, $date, 'hgspcc', $mergedEntries);
        }

        $html[] = "</tr></table>";

        return implode('', $html);
    }

    private function isLeapYear(int $year): bool
    {
        return ($year % 4 === 0 && $year % 100 !== 0) || ($year % 400 === 0);
    }

    private function makeDayCell(int $day, string $date, string $class, array $entries): string
    {
        $normalized = $this->normalizeDate($date);
        $tooltip = $entries[$normalized] ?? null;

        if ($tooltip) {
            $tooltipText = is_array($tooltip)
                ? implode('<br>', array_map('htmlspecialchars', $tooltip))
                : htmlspecialchars((string)$tooltip);

            $highlight = 'has-background-success-light';
            return "<td data-thedate='$date' class='$class $highlight' title='$tooltipText'>$day</td>";
        }

        return "<td data-thedate='$date' class='$class'>$day</td>";
    }

    private function formatDate(int $d, int $m, int $y): string
    {
        return match ($this->lang) {
            'en' => sprintf('%02d/%02d/%04d', $m, $d, $y),
            'sql' => sprintf('%04d-%02d-%02d', $y, $m, $d),
            default => sprintf('%02d.%02d.%04d', $d, $m, $y),
        };
    }

    private function normalizeDate(string $date): ?string
    {
        $date = trim($date);
        if ($date === '') {
            return null;
        }

        $formats = ['Y-m-d', 'd.m.Y', 'm/d/Y'];
        foreach ($formats as $fmt) {
            $dt = \DateTime::createFromFormat($fmt, $date);
            if ($dt && $dt->format($fmt) === $date) {
                return $dt->format('Y-m-d');
            }
        }

        $ts = strtotime($date);
        return $ts ? date('Y-m-d', $ts) : null;
    }
}
