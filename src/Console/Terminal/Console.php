<?php

namespace Vatts\Console\Terminal;

class Console {
    // Array associativo armazenando as linhas ativas e seu offset a partir do cursor atual (linhas acima)
    private static array $activeLines = [];
    private static bool $isWriting = false;

    // --- THEME / HELPERS VISUAIS ---

    private const ANSI_REGEX = '/[\x1b\x9b][[()#;?]*(?:[0-9]{1,4}(?:;[0-9]{0,4})*)?[0-9A-ORZcf-nqry=><]/';

    private static function stripAnsi(string $text): string {
        return preg_replace(self::ANSI_REGEX, '', $text) ?? $text;
    }

    private static function fg(int $r, int $g, int $b): string {
        return "\x1b[38;2;{$r};{$g};{$b}m";
    }

    private static function bg(int $r, int $g, int $b): string {
        return "\x1b[48;2;{$r};{$g};{$b}m";
    }

    private static function padCenter(string $text, int $width): string {
        $cleanLen = mb_strlen(self::stripAnsi($text));
        if ($cleanLen >= $width) return $text;

        $total = $width - $cleanLen;
        $left = (int) floor($total / 2);
        $right = $total - $left;

        return str_repeat(" ", $left) . $text . str_repeat(" ", $right);
    }

    private static function getTime(): string {
        return date('H:i:s');
    }

    private static function normalizeLevelName(Levels|string|null $level): string {
        if ($level === null) return "";
        $value = $level instanceof Levels ? $level->value : (string)$level;
        return trim($value);
    }

    private static function isKnownLevel(Levels|string|null $level): bool {
        $normalized = self::normalizeLevelName($level);
        if (!$normalized) return false;

        $upper = strtoupper($normalized);
        return in_array($upper, [
            Levels::ERROR->value,
            Levels::WARN->value,
            Levels::INFO->value,
            Levels::DEBUG->value,
            Levels::SUCCESS->value,
            "WAIT"
        ], true);
    }

    private static function bgForFgColor(?Colors $fgColor = null): string {
        return match ($fgColor) {
            Colors::FgRed => self::bg(86, 24, 24),
            Colors::FgGreen => self::bg(18, 64, 34),
            Colors::FgYellow => self::bg(72, 58, 20),
            Colors::FgBlue => self::bg(18, 38, 70),
            Colors::FgMagenta => self::bg(46, 28, 66),
            Colors::FgCyan => self::bg(18, 56, 64),
            Colors::FgGray => self::bg(48, 48, 48),
            Colors::FgWhite, Colors::FgAlmostWhite => self::bg(64, 64, 64),
            Colors::FgBlack => self::bg(20, 20, 20),
            default => self::bg(64, 64, 64),
        };
    }

    private static function levelStyle(Levels|string $level): array {
        $C = [
            'red' => ['fg' => self::fg(255, 95, 95), 'bg' => self::bg(86, 24, 24)],
            'yellow' => ['fg' => self::fg(255, 210, 90), 'bg' => self::bg(72, 58, 20)],
            'cyan' => ['fg' => self::fg(120, 230, 255), 'bg' => self::bg(18, 56, 64)],
            'green' => ['fg' => self::fg(120, 255, 165), 'bg' => self::bg(18, 64, 34)],
            'gray' => ['fg' => self::fg(170, 170, 170), 'bg' => self::bg(48, 48, 48)],
            'white' => ['fg' => self::fg(230, 230, 230), 'bg' => self::bg(64, 64, 64)],
            'purple' => ['fg' => self::fg(200, 160, 255), 'bg' => self::bg(46, 28, 66)],
        ];

        $val = $level instanceof Levels ? $level->value : strtoupper((string)$level);

        return match ($val) {
            Levels::ERROR->value => ['icon' => "✖", 'badgeFg' => $C['red']['fg'], 'badgeBg' => $C['red']['bg'], 'msgFg' => Colors::FgAlmostWhite->value],
            Levels::WARN->value => ['icon' => "▲", 'badgeFg' => $C['yellow']['fg'], 'badgeBg' => $C['yellow']['bg'], 'msgFg' => Colors::FgAlmostWhite->value],
            Levels::INFO->value => ['icon' => "ⓘ", 'badgeFg' => $C['cyan']['fg'], 'badgeBg' => $C['cyan']['bg'], 'msgFg' => Colors::FgAlmostWhite->value],
            Levels::SUCCESS->value => ['icon' => "✓", 'badgeFg' => $C['green']['fg'], 'badgeBg' => $C['green']['bg'], 'msgFg' => Colors::FgAlmostWhite->value],
            Levels::DEBUG->value => ['icon' => "›", 'badgeFg' => $C['gray']['fg'], 'badgeBg' => $C['gray']['bg'], 'msgFg' => Colors::FgGray->value],
            "WAIT" => ['icon' => "", 'badgeFg' => $C['purple']['fg'], 'badgeBg' => $C['purple']['bg'], 'msgFg' => Colors::FgAlmostWhite->value],
            default => ['icon' => "", 'badgeFg' => $C['white']['fg'], 'badgeBg' => $C['white']['bg'], 'msgFg' => Colors::FgAlmostWhite->value],
        };
    }

    private static function renderBadge(Levels|string $level, ?Colors $badgeFgOverride = null): string {
        $reset = Colors::Reset->value;
        $bold = Colors::Bright->value;

        $normalized = self::normalizeLevelName($level);
        if ($normalized === "") return "";

        $name = strtoupper($normalized);
        $trimmed = mb_strlen($name) > 7 ? mb_substr($name, 0, 7) : $name;

        $padded = self::padCenter($trimmed, 7);
        $base = self::levelStyle($level);

        $fg = $badgeFgOverride ? $badgeFgOverride->value : $base['badgeFg'];
        $bg = $badgeFgOverride ? self::bgForFgColor($badgeFgOverride) : $base['badgeBg'];

        return "{$bg}{$fg}{$bold} {$padded} {$reset}";
    }

    private static function indentMultiline(string $msg, string $indent): string {
        $lines = explode("\n", $msg);
        foreach ($lines as $i => &$line) {
            if ($i > 0) $line = $indent . $line;
        }
        return implode("\n", $lines);
    }

    // --- HELPER PARA CALCULAR ALTURA ADICIONADA ---

    private static function getColumns(): int {
        if (strncasecmp(PHP_OS, 'WIN', 3) === 0) return 80;
        $cols = @exec('tput cols');
        return (int)($cols ?: 80);
    }

    private static function countRowsAdded(string $text): int {
        $columns = self::getColumns();
        $clean = self::stripAnsi($text);

        $rowsAdded = 0;
        $lines = explode("\n", $clean);

        foreach ($lines as $i => $line) {
            $width = mb_strlen($line);
            if ($width > 0) {
                $rowsAdded += (int) floor(($width - 1) / $columns);
            }
            if ($i < count($lines) - 1) {
                $rowsAdded++;
            }
        }
        return $rowsAdded;
    }

    // --- HELPER WRITER ---

    private static function writeStatic(string $content): void {
        $cleanContent = rtrim($content, "\n") . "\n";
        $rowsAdded = self::countRowsAdded($cleanContent);

        // Tracker manual simulando o hook de stdout
        if ($rowsAdded > 0 && !self::$isWriting) {
            foreach (self::$activeLines as &$line) {
                $line['offset'] += $rowsAdded;
            }
        }

        echo $cleanContent;
    }

    public static function formatLog(Levels|string $level, string $message, ?Colors $color = null): string {
        if ($message === "end_clear") return "";

        $reset = Colors::Reset->value;
        $dim = Colors::Dim->value;

        $time = self::getTime();
        $timePart = "{$dim}" . Colors::FgGray->value . "{$time}{$reset}";

        $normalizedLevel = self::normalizeLevelName($level);
        $isEmptyLevel = $normalizedLevel === "";

        $known = self::isKnownLevel($level);
        $st = self::levelStyle($level);

        $shouldOverrideBadge = !$known && $color !== null && !$isEmptyLevel;

        $badge = $isEmptyLevel ? "" : self::renderBadge($level, $shouldOverrideBadge ? $color : null);

        $gapAfterTime = "  ";
        $gapAfterBadge = $badge ? "  " : " ";

        $iconFg = $shouldOverrideBadge ? $color->value : $st['badgeFg'];
        $iconSymbol = $isEmptyLevel ? "" : $st['icon'];
        $iconPart = "{$iconFg}{$iconSymbol}{$reset}";

        $prefix = $badge
            ? " {$timePart}{$gapAfterTime}{$badge}{$gapAfterBadge}{$iconPart} "
            : " {$timePart}{$gapAfterTime}{$iconPart} ";

        $msgColor = $st['msgFg'];

        $indent = str_repeat(" ", mb_strlen(self::stripAnsi($prefix)));
        $prettyMsg = self::indentMultiline($message, $indent);

        return "{$prefix}{$msgColor}{$prettyMsg}{$reset}";
    }

    // --- INTERATIVIDADE (SELECTION) ---

    public static function selection(string $question, array $options): string {
        $entries = array_keys($options);
        $values = array_values($options);
        $currentIndex = 0;
        $firstRender = true;

        echo "\x1b[?25l"; // Esconder cursor

        // Ativa raw mode para capturar setas do teclado sem Enter no Unix
        $sttyStr = '';
        if (strncasecmp(PHP_OS, 'WIN', 3) !== 0) {
            $sttyStr = exec('stty -g');
            exec('stty -icanon -echo');
        }

        $render = function() use (&$firstRender, $entries, $values, &$currentIndex, $question) {
            if (!$firstRender) {
                echo "\x1b[" . (count($entries) + 2) . "A"; // Move up
            }

            echo "\x1b[0G\x1b[J"; // Cursor para 0 e clear screen down

            $title = " " . Colors::FgCyan->value . Colors::Bright->value . "◆" . Colors::Reset->value . " " . Colors::Bright->value . $question . Colors::Reset->value;
            $output = "{$title}\n " . Colors::Dim->value . Colors::FgGray->value . "Use ↑/↓ e Enter" . Colors::Reset->value . "\n";

            foreach ($entries as $i => $key) {
                $label = $values[$i];
                $isSelected = $i === $currentIndex;

                $bullet = $isSelected
                    ? self::bg(18, 56, 64) . self::fg(120, 230, 255) . " " . Colors::Bright->value . "❯" . Colors::Reset->value . self::bg(18, 56, 64) . self::fg(120, 230, 255) . " "
                    : "   ";

                $text = $isSelected
                    ? self::bg(18, 56, 64) . self::fg(220, 245, 255) . Colors::Bright->value . $label . Colors::Reset->value
                    : Colors::FgGray->value . $label . Colors::Reset->value;

                $suffix = $isSelected ? self::bg(18, 56, 64) . Colors::Reset->value : "";
                $output .= " {$bullet}{$text}{$suffix}\n";
            }

            self::$isWriting = true;
            echo $output;
            self::$isWriting = false;
            $firstRender = false;
        };

        $render();

        $stdin = fopen('php://stdin', 'r');
        stream_set_blocking($stdin, true);

        while (true) {
            $char = fread($stdin, 1);

            // Tratamento de control sequences (\e)
            if ($char === "\x1b") {
                $char .= fread($stdin, 2);
                if ($char === "\x1b[A") { // up
                    $currentIndex = ($currentIndex - 1 + count($entries)) % count($entries);
                    $render();
                } elseif ($char === "\x1b[B") { // down
                    $currentIndex = ($currentIndex + 1) % count($entries);
                    $render();
                }
            } elseif ($char === "\n" || $char === "\r") {
                break;
            } elseif ($char === "\x03") { // Ctrl+C
                echo "\x1b[?25h";
                if ($sttyStr) exec("stty {$sttyStr}");
                exit;
            }
        }

        // Cleanup
        echo "\x1b[?25h"; // Mostrar cursor
        if ($sttyStr) exec("stty {$sttyStr}");

        echo "\x1b[" . (count($entries) + 2) . "A";
        echo "\x1b[0G\x1b[J";

        $selectedKey = $entries[$currentIndex];
        $selectedLabel = $values[$currentIndex];
        self::writeStatic(self::formatLog(Levels::SUCCESS, "{$question} " . Colors::FgGray->value . "›" . Colors::Reset->value . " {$selectedLabel}"));

        return $selectedKey;
    }

    // --- MÉTODOS PÚBLICOS ---

    public static function error(mixed ...$args): void { self::log(Levels::ERROR, null, ...$args); }
    public static function warn(mixed ...$args): void { self::log(Levels::WARN, null, ...$args); }
    public static function info(mixed ...$args): void { self::log(Levels::INFO, null, ...$args); }
    public static function success(mixed ...$args): void { self::log(Levels::SUCCESS, null, ...$args); }
    public static function default_log(mixed ...$args): void { self::log(Levels::INFO, null, ...$args); }
    public static function debug(mixed ...$args): void { self::log(Levels::DEBUG, null, ...$args); }

    public static function logCustomLevel(string $levelName, bool $without = true, ?Colors $color = null, mixed ...$args): void {
        $level = Levels::tryFrom(strtoupper($levelName)) ?? $levelName;
        self::log($level, $color, ...$args);
    }

    public static function logWithout(Levels $level, ?Colors $color = null, mixed ...$args): void {
        self::log($level, $color, ...$args);
    }

    public static function log(Levels|string $level, ?Colors $color = null, mixed ...$args): void {
        $output = "";
        foreach ($args as $arg) {
            $msg = $arg instanceof \Throwable
                ? (string) $arg
                : (is_string($arg) ? $arg : json_encode($arg, JSON_PRETTY_PRINT));

            if ($msg) $output .= self::formatLog($level, $msg, $color) . "\n";
        }
        self::writeStatic($output);
    }

    public static function ask(string $question, ?string $defaultValue = null): string {
        $defaultPart = $defaultValue !== null ? " " . Colors::Dim->value . Colors::FgGray->value . "({$defaultValue})" . Colors::Reset->value : "";
        $prompt = " " . Colors::FgCyan->value . Colors::Bright->value . "◆" . Colors::Reset->value . " " . Colors::Bright->value . $question . Colors::Reset->value . "{$defaultPart}\n" .
            " " . Colors::FgCyan->value . "❯" . Colors::Reset->value . " ";

        echo $prompt;

        $ans = trim(fgets(STDIN));
        return ($ans === "" && $defaultValue !== null) ? $defaultValue : $ans;
    }

    public static function confirm(string $message, bool $defaultYes = false): bool {
        $suffix = $defaultYes ? "Y/n" : "y/N";
        $ans = strtolower(self::ask("{$message} " . Colors::Dim->value . Colors::FgGray->value . "[{$suffix}]" . Colors::Reset->value));
        if ($ans === "") return $defaultYes;
        return in_array($ans, ["y", "yes", "s", "sim"]);
    }

    public static function table(array $data): void {
        $rows = [];
        // Verifica se é uma matriz associativa simples ou uma array de arrays com Field/Value
        if (isset($data[0]) && is_array($data[0]) && isset($data[0]['Field'])) {
            foreach ($data as $row) {
                $rows[] = ['Field' => (string)$row['Field'], 'Value' => (string)$row['Value']];
            }
        } else {
            foreach ($data as $field => $value) {
                $rows[] = ['Field' => (string)$field, 'Value' => (string)$value];
            }
        }

        $fieldLen = max(mb_strlen("Field"), ...array_map(fn($r) => mb_strlen($r['Field']), $rows));
        $valueLen = max(mb_strlen("Value"), ...array_map(fn($r) => mb_strlen($r['Value']), $rows));

        $h_line = str_repeat("─", $fieldLen + 2);
        $v_line = str_repeat("─", $valueLen + 2);

        $top = "┌{$h_line}┬{$v_line}┐";
        $mid = "├{$h_line}┼{$v_line}┤";
        $bottom = "└{$h_line}┴{$v_line}┘";

        $headFg = self::fg(120, 230, 255);
        $headBg = self::bg(18, 56, 64);
        $dimGray = Colors::Dim->value . Colors::FgGray->value;
        $reset = Colors::Reset->value;
        $white = Colors::FgAlmostWhite->value;
        $bright = Colors::Bright->value;

        $output = "{$dimGray}{$top}{$reset}\n";
        $output .= "{$dimGray}│{$reset} {$headBg}{$headFg}{$bright}" . str_pad("Field", $fieldLen) . "{$reset} {$dimGray}│{$reset} {$headBg}{$headFg}{$bright}" . str_pad("Value", $valueLen) . "{$reset} {$dimGray}│{$reset}\n";
        $output .= "{$dimGray}{$mid}{$reset}\n";

        foreach ($rows as $row) {
            $output .= "{$dimGray}│{$reset} {$white}" . str_pad($row['Field'], $fieldLen) . "{$reset} {$dimGray}│{$reset} {$white}" . str_pad($row['Value'], $valueLen) . "{$reset} {$dimGray}│{$reset}\n";
        }

        $output .= "{$dimGray}{$bottom}{$reset}\n";
        self::writeStatic($output);
    }

    public static function dynamicLine(string $initialContent): DynamicLine {
        return new DynamicLine($initialContent);
    }

    public static function registerDynamicLine(string $id, string $content): void {
        $formatted = self::formatLog("WAIT", $content);
        $rows = self::countRowsAdded($formatted . "\n");

        self::writeStatic($formatted);
        self::$activeLines[$id] = ['offset' => $rows];
    }

    public static function updateDynamicLine(string $id, string $newContent): void {
        self::editLine($id, $newContent, "WAIT");
    }

    public static function endDynamicLine(string $id, string $finalContent): void {
        if (isset(self::$activeLines[$id])) {
            self::editLine($id, $finalContent, Levels::SUCCESS);
            unset(self::$activeLines[$id]);
        }
    }

    private static function editLine(string $id, string $content, string|Levels $level): void {
        if (!isset(self::$activeLines[$id])) return;
        $line = self::$activeLines[$id];

        $formatted = self::formatLog($level, $content);
        self::$isWriting = true;

        try {
            // Move cursor para cima
            if ($line['offset'] > 0) {
                echo "\x1b[" . $line['offset'] . "A";
            }

            // Clear line e reescreve
            echo "\x1b[2K\x1b[0G";
            echo $formatted . "\n";

            $newRows = self::countRowsAdded($formatted . "\n");
            $rowsToMoveDown = $line['offset'] - $newRows;

            // Move cursor de volta para onde deveria estar
            if ($rowsToMoveDown > 0) {
                echo "\x1b[" . $rowsToMoveDown . "B";
            } elseif ($rowsToMoveDown < 0) {
                echo "\x1b[" . abs($rowsToMoveDown) . "A";
            }
        } catch (\Exception $e) {
            // ignore
        }

        self::$isWriting = false;
    }
}