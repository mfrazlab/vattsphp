<?php

namespace Vatts\Console\Terminal;

enum Levels: string
{
    case ERROR = "ERROR";
    case WARN = "WARN";
    case INFO = "INFO";
    case DEBUG = "DEBUG";
    case SUCCESS = "SUCCESS";
}