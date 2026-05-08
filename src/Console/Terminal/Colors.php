<?php

namespace Vatts\Console\Terminal;

enum Colors: string
{
    case Reset = "\x1b[0m";
    case Bright = "\x1b[1m";
    case Dim = "\x1b[2m";
    case Underscore = "\x1b[4m";
    case Blink = "\x1b[5m";
    case Reverse = "\x1b[7m";
    case Hidden = "\x1b[8m";

    case FgBlack = "\x1b[30m";
    case FgRed = "\x1b[31m";
    case FgGreen = "\x1b[32m";
    case FgYellow = "\x1b[33m";
    case FgBlue = "\x1b[34m";
    case FgMagenta = "\x1b[35m";
    case FgCyan = "\x1b[36m";
    case FgWhite = "\x1b[37m";
    case FgGray = "\x1b[90m";
    case FgAlmostWhite = "\x1b[38;2;220;220;220m";

    case BgBlack = "\x1b[40m";
    case BgRed = "\x1b[41m";
    case BgGreen = "\x1b[42m";
    case BgYellow = "\x1b[43m";
    case BgBlue = "\x1b[44m";
    case BgMagenta = "\x1b[45m";
    case BgCyan = "\x1b[46m";
    case BgWhite = "\x1b[47m";
    case BgGray = "\x1b[100m";
}