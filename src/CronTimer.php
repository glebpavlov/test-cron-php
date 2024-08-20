<?php

class CronTimer
{
    /**
     * Находит ближайшее будущее время, соответствующее критериям.
     *
     * @param array $params Ассоциативный массив критериев времени.
     * @param string|null $currentTime Текущая дата и время в формате "дд.мм.гггг чч:мм:сс" (необязательный параметр).
     * @return string|false Время в формате "дд.мм.гггг чч:мм:сс" или false, если подходящее время не найдено.
     */
    public static function nextTime($params = [], $currentTime = null)
    {
        // Нормализуем параметры
        $params = self::normalizeParams($params);

        // Устанавливаем текущее время
        $current = $currentTime ? DateTime::createFromFormat('d.m.Y H:i:s', $currentTime) : new DateTime();
        if (!$current) {
            return false;
        }

        for ($level = 0; $level < 6; $level++) {
            $unitName = self::getUnitName($level);
            $value = (int)$current->format(self::getFormatByLevel($level));
            $criterion = $params[$unitName];

            // Находим следующее подходящее значение
            $nextValue = self::findNextValidValue($value, $criterion, self::getLimitByLevel($level));
            if ($nextValue === false) {
                // Если следующее значение не найдено, переходим к следующему уровню
                if ($level === 5) {
                    // Если достигли последнего уровня (год), то нет подходящего времени
                    return false;
                }

                // Устанавливаем текущий уровень в 0 и переходим к следующему уровню
                self::setTimeUnit($current, $level + 1, 0);
                self::setTimeUnit($current, $level, 0);
                continue;
            }

            // Если следующее значение найдено и больше текущего значения, устанавливаем его
            if ($nextValue > $value) {
                self::setTimeUnit($current, $level, $nextValue);
                // Сбрасываем меньшие единицы времени
                for ($resetLevel = $level - 1; $resetLevel >= 0; $resetLevel--) {
                    self::setTimeUnit($current, $resetLevel, 0);
                }
                break;
            } else {
                // Если значение не больше, то сбрасываем текущее значение и переходим к следующему уровню
                self::setTimeUnit($current, $level, $nextValue);
                self::setTimeUnit($current, $level + 1, 0);
                for ($resetLevel = $level - 1; $resetLevel >= 0; $resetLevel--) {
                    self::setTimeUnit($current, $resetLevel, 0);
                }
            }
        }

        return $current->format('d.m.Y H:i:s');
    }

    /**
     * Нормализует параметры, заполняя отсутствующие значения '*'.
     *
     * @param array $params Исходные параметры.
     * @return array Нормализованные параметры.
     */
    private static function normalizeParams($params)
    {
        $defaultParams = [
            "sec" => "*",
            "min" => "*",
            "hour" => "*",
            "day" => "*",
            "mon" => "*",
            "year" => "*"
        ];
        if (is_array($params) && !empty($params)) {
            foreach ($defaultParams as $key => $value) {
                if (!array_key_exists($key, $params)) {
                    $params[$key] = $value;
                }
            }
        } else {
            $params = array_fill_keys(array_keys($defaultParams), "*");
        }
        return $params;
    }

    /**
     * Проверяет, соответствует ли значение критерию.
     *
     * @param int $value Значение.
     * @param string $criterion Критерий.
     * @return bool
     */
    private static function matchCriteria($value, $criterion)
    {
        if ($criterion === "*") {
            return true;
        }

        if (preg_match('/^(\d+)-(\d+)$/', $criterion, $matches)) {
            return $value >= $matches[1] && $value <= $matches[2];
        }

        if (preg_match('/^(\d+(?:,\d+)*)$/', $criterion, $matches)) {
            return in_array($value, explode(',', $matches[1]));
        }

        if (preg_match('/^\/(\d+)$/', $criterion, $matches)) {
            return $value % $matches[1] === 0;
        }

        if (preg_match('/^(\d+)\/(\d+)$/', $criterion, $matches)) {
            return $matches[1] === "0" || $value % $matches[2] === 0;
        }

        return false;
    }

    /**
     * Находит следующее подходящее значение.
     *
     * @param int $currentValue Текущее значение.
     * @param string $criterion Критерий.
     * @param int $limit Лимит.
     * @return int|false Следующее подходящее значение или false, если не найдено.
     */
    private static function findNextValidValue($currentValue, $criterion, $limit)
    {
        for ($i = $currentValue + 1; $i <= $limit; $i++) {
            if (self::matchCriteria($i, $criterion)) {
                return $i;
            }
        }
        return false;
    }

    /**
     * Устанавливает значение для единицы времени.
     *
     * @param DateTime $current Текущее время.
     * @param int $level Уровень единицы времени.
     * @param int $value Новое значение.
     */
    private static function setTimeUnit(&$current, $level, $value)
    {
        switch ($level) {
            case 0: // секунды
                $current->setTime($current->format('H'), $current->format('i'), $value);
                break;
            case 1: // минуты
                $current->setTime($current->format('H'), $value, $current->format('s'));
                break;
            case 2: // часы
                $current->setTime($value, $current->format('i'), $current->format('s'));
                break;
            case 3: // дни
                $current->setDate($current->format('Y'), $current->format('n'), $value);
                break;
            case 4: // месяцы
                $current->setDate($current->format('Y'), $value, $current->format('j'));
                break;
            case 5: // годы
                $current->setDate($value, $current->format('n'), $current->format('j'));
                break;
        }
    }

    /**
     * Получает имя единицы времени по уровню.
     *
     * @param int $level Уровень.
     * @return string Имя единицы времени.
     */
    private static function getUnitName($level)
    {
        $units = ['sec', 'min', 'hour', 'day', 'mon', 'year'];
        return $units[$level];
    }

    /**
     * Получает лимит единицы времени по уровню.
     *
     * @param int $level Уровень.
     * @return int Лимит.
     */
    private static function getLimitByLevel($level)
    {
        $limits = [59, 59, 23, 31, 12, 9999];
        return $limits[$level];
    }

    /**
     * Получает формат для единицы времени по уровню.
     *
     * @param int $level Уровень.
     * @return string Формат.
     */
    private static function getFormatByLevel($level)
    {
        $formats = ['s', 'i', 'H', 'j', 'n', 'Y'];
        return $formats[$level];
    }
}
