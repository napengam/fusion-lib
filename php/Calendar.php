<?php

class Calendar
{
    private string $lang;
    private array $entries;

    public function __construct(string $lang = 'de', array $entries = [])
    {
        $this->lang = in_array($lang, ['de', 'en', 'sql'], true) ? $lang : 'de';
        $this->entries = $entries;
    }

    private function formatDate(int $d, int $m, int $y): string
    {
        return match ($this->lang) {
            'en'  => sprintf('%02d/%02d/%04d', $m, $d, $y),
            'sql' => sprintf('%04d-%02d-%02d', $y, $m, $d),
            default => sprintf('%02d.%02d.%04d', $d, $m, $y),
        };
    }

    public function makeCalendar(int $m, int $y, string $target): string
    {
        $id = 'fc-' . bin2hex(random_bytes(4));

        $daysInMonth = [0,31,28,31,30,31,30,31,31,30,31,30,31];
        $months = [
            '', 'January','February','March','April','May','June',
            'July','August','September','October','November','December'
        ];

        $today = getdate();
        $m = ($m < 1 || $m > 12) ? $today['mon'] : $m;
        $y = ($y <= 0) ? $today['year'] : $y;

        $dateInfo = explode(' ', date('D d n Y W L w', mktime(0,0,0,$m,1,$y)));
        $daysInMonth[2] += (int)$dateInfo[5]; // leap year

        [$prevM, $prevY] = $m === 1 ? [12, $y - 1] : [$m - 1, $y];
        [$nextM, $nextY] = $m === 12 ? [1, $y + 1] : [$m + 1, $y];

        $weekday = (int)$dateInfo[6];
        $weekday = $weekday === 0 ? 7 : $weekday;

        $out = [];
        $out[] = "<table id='{$id}' class='fusion-calendar'>";

        // Header
        $out[] = "
            <thead class='fusion-calendar-head'>
                <tr class='fusion-calendar-nav'>
                    <th class='fusion-calendar-prev'
                        data-myv='{$prevM}|{$prevY}|{$target}'>«</th>
                    <th class='fusion-calendar-title' colspan='5'>
                        {$months[(int)$dateInfo[2]]} {$y}
                    </th>
                    <th class='fusion-calendar-next'
                        data-myv='{$nextM}|{$nextY}|{$target}'>»</th>
                </tr>
                <tr class='fusion-calendar-weekdays'>
                    <th>Mo</th><th>Di</th><th>Mi</th>
                    <th>Do</th><th>Fr</th><th>Sa</th><th>So</th>
                </tr>
            </thead>
            <tbody class='fusion-calendar-body'>
            <tr>
        ";

        // Previous month spill
        $prevStart = $daysInMonth[$prevM] - $weekday + 2;
        for ($i = 1; $i < $weekday; $i++, $prevStart++) {
            $date = $this->formatDate($prevStart, $prevM, $prevY);
            $out[] = $this->renderDay($date, $prevStart, true);
        }

        // Current month
        $cell = $weekday;
        for ($day = 1; $day <= $daysInMonth[$m]; $day++, $cell++) {
            $date = $this->formatDate($day, $m, $y);
            $out[] = $this->renderDay($date, $day, false);
            if ($cell % 7 === 0) {
                $out[] = '</tr><tr>';
            }
        }

        // Next month spill
        for ($day = 1; $cell % 7 !== 1; $day++, $cell++) {
            $date = $this->formatDate($day, $nextM, $nextY);
            $out[] = $this->renderDay($date, $day, true);
        }

        $out[] = '</tr></tbody></table>';

        return implode('', $out);
    }

    private function renderDay(string $date, int $label, bool $other): string
    {
        $classes = ['fusion-calendar-day'];
        if ($other) {
            $classes[] = 'fusion-calendar-other';
        }

        $tooltip = '';
        if (isset($this->entries[$date])) {
            $classes[] = 'fusion-calendar-has-entry';
            $tooltip = htmlspecialchars(
                is_array($this->entries[$date])
                    ? implode("\n", $this->entries[$date])
                    : (string)$this->entries[$date]
            );
        }

        return sprintf(
            "<td class='%s' data-thedate='%s' data-tooltip='%s'>%d</td>",
            implode(' ', $classes),
            $date,
            $tooltip,
            $label
        );
    }
}
