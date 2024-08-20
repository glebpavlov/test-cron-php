<?php

class CronTimer {

    // Основная функция для нахождения следующего времени по заданным критериям
    public static function nextTime($params = [], $currentTime = null) {
        // Если текущее время не указано, используем системное время
        if ($currentTime === null) {
            $currentTime = date("d.m.Y H:i:s");
        }

        // Преобразуем строку текущего времени в объект DateTime
        $current = DateTime::createFromFormat("d.m.Y H:i:s", $currentTime);
        if (!$current) {
            throw new InvalidArgumentException("Invalid date format: $currentTime");
        }

        // Приводим параметры к стандартному виду
        $criteria = self::normalizeParams($params);

        // Сразу увеличиваем текущее время на одну секунду, чтобы начать поиск с будущего времени
        $current->modify('+1 second');

        // Проверяем и корректируем временные единицы начиная с секунд и выше
        for ($level = 0; $level < 6; $level++) {
            // Получаем текущее значение единицы времени и её максимальное значение
            list($unit, $limit) = self::getTimeUnitAndLimit($level, $current);

            // Проверяем, соответствует ли текущее значение критерию
            if (!self::matchCriteria($unit, $criteria[self::getUnitName($level)])) {
                // Находим следующее подходящее значение
                $nextValue = self::findNextValidValue($unit, $criteria[self::getUnitName($level)], $limit);

                // Если нет подходящего значения, возвращаем false
                if ($nextValue === false) {
                    return false;
                }

                // Устанавливаем новое значение и сбрасываем младшие единицы времени
                self::setTimeUnit($current, $level, $nextValue);
                $level = -1; // Сбрасываем уровень, чтобы начать с секунд снова
            }
        }

        // Возвращаем результат в нужном формате
        return $current->format("d.m.Y H:i:s");
    }

    // Приведение параметров к полному массиву, если передан краткий вариант
    private static function normalizeParams($params) {
        // Стандартные значения для всех единиц времени
        $defaultCriteria = ["sec" => "*", "min" => "*", "hour" => "*", "day" => "*", "mon" => "*", "year" => "*"];

        // Если параметры заданы числовым массивом, приводим его к ассоциативному
        if (isset($params[0])) {
            $keys = array_keys($defaultCriteria);
            foreach ($params as $key => $value) {
                $defaultCriteria[$keys[$key]] = $value;
            }
        } else {
            // Объединяем переданные параметры с дефолтными значениями
            $defaultCriteria = array_merge($defaultCriteria, $params);
        }

        return $defaultCriteria;
    }

    // Функция проверки, соответствует ли текущее значение критерию
    private static function matchCriteria($value, $criterion) {
        if ($criterion === "*") {
            return true;
        }

        // Если критерий - перечисление значений, проверяем наличие текущего значения
        if (strpos($criterion, ',') !== false) {
            $values = explode(',', $criterion);
            return in_array($value, $values);
        }

        // Если критерий - диапазон значений, проверяем принадлежность диапазону
        if (strpos($criterion, '-') !== false) {
            list($start, $end) = explode('-', $criterion);
            return $value >= $start && $value <= $end;
        }

        // Если критерий - деление с остатком, проверяем выполнение условия
        if (strpos($criterion, '/') !== false) {
            list($mod, $divisor) = explode('/', $criterion);
            if ($mod === '') {
                $mod = 0;
            }
            return ($value % $divisor) == $mod;
        }

        // Простое сравнение с числовым значением
        return $value == $criterion;
    }

    // Поиск следующего подходящего значения для текущей единицы времени
    private static function findNextValidValue($currentValue, $criterion, $limit) {
        if ($criterion === "*") {
            return $currentValue;
        }

        // Обработка перечислений значений
        if (strpos($criterion, ',') !== false) {
            $values = array_map('intval', explode(',', $criterion));
            foreach ($values as $value) {
                if ($value > $currentValue) {
                    return $value;
                }
            }
            return $values[0]; // Если не нашли большее значение, возвращаем первое
        }

        // Обработка диапазона значений
        if (strpos($criterion, '-') !== false) {
            list($start, $end) = explode('-', $criterion);
            if ($currentValue < $start) {
                return $start;
            }
            if ($currentValue >= $end) {
                return false; // Если вышли за пределы диапазона, возвращаем false
            }
            return $currentValue + 1;
        }

        // Обработка деления с остатком
        if (strpos($criterion, '/') !== false) {
            list($mod, $divisor) = explode('/', $criterion);
            if ($mod === '') {
                $mod = 0;
            }
            for ($i = $currentValue + 1; $i <= $limit; $i++) {
                if ($i % $divisor == $mod) {
                    return $i;
                }
            }
            return false; // Если не нашли подходящее значение, возвращаем false
        }

        // Проверяем простое числовое значение
        return $currentValue < $criterion ? $criterion : false;
    }

    // Получение текущего значения единицы времени и его лимита
    private static function getTimeUnitAndLimit($level, $current) {
        switch ($level) {
            case 0: return [(int)$current->format('s'), 59];  // Секунды
            case 1: return [(int)$current->format('i'), 59];  // Минуты
            case 2: return [(int)$current->format('H'), 23];  // Часы
            case 3: return [(int)$current->format('d'), (int)$current->format('t')];  // Дни (учитывается количество дней в месяце)
            case 4: return [(int)$current->format('m'), 12];  // Месяцы
            case 5: return [(int)$current->format('Y'), 9999];  // Годы
        }
        return [0, 0]; // Невалидный случай, просто возвращаем 0, 0
    }

    // Установка нового значения для текущей единицы времени и сброс младших единиц
    private static function setTimeUnit(&$current, $level, $value) {
        switch ($level) {
            case 0: $current->setTime($current->format('H'), $current->format('i'), $value); break; // Устанавливаем секунды
            case 1: $current->setTime($current->format('H'), $value, 0); break; // Устанавливаем минуты и сбрасываем секунды
            case 2: $current->setTime($value, 0, 0); break; // Устанавливаем часы и сбрасываем минуты и секунды
            case 3: $current->setDate($current->format('Y'), $current->format('m'), $value); break; // Устанавливаем день месяца
            case 4: $current->setDate($current->format('Y'), $value, 1); break; // Устанавливаем месяц и сбрасываем день
            case 5: $current->setDate($value, 1, 1); break; // Устанавливаем год и сбрасываем месяц и день
        }
    }

    // Получение названия единицы времени по её уровню
    private static function getUnitName($level) {
        return ['sec', 'min', 'hour', 'day', 'mon', 'year'][$level];
    }
}