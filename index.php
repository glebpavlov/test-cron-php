<?php

// Подключение файла с классом CronTimer
require_once 'src/CronTimer.php';

// Примеры вызова функции
echo "Примеры вызова функции:\n";
echo "nextTime([], '01.01.2024 10:00:00'):\n";
echo var_export(CronTimer::nextTime([], '01.01.2024 10:00:00'), true) . "\n";

echo "nextTime(['sec' => 15], '01.01.2024 10:00:00'):\n";
echo var_export(CronTimer::nextTime(['sec' => 15], '01.01.2024 10:00:00'), true) . "\n";

echo "nextTime(['min' => '5/10'], '01.01.2024 10:00:00'):\n";
echo var_export(CronTimer::nextTime(['min' => '5/10'], '01.01.2024 10:00:00'), true) . "\n";

echo "nextTime(['year' => 2023], '01.01.2024 10:00:00'):\n";
echo var_export(CronTimer::nextTime(['year' => 2023], '01.01.2024 10:00:00'), true) . "\n";

echo "nextTime(['sec' => 40], '18.08.2024 10:10:40'):\n";
echo var_export(CronTimer::nextTime(['sec' => 40], '18.08.2024 10:10:40'), true) . "\n";
