<?php

/*
 * This file is part of PHP-FFmpeg.
 *
 * (c) Alchemy <info@alchemy.fr>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FFMpeg\Coordinate;

use FFMpeg\Exception\InvalidArgumentException;

class TimeCode
{
    //see http://www.dropframetimecode.org/
    private $hours;
    private $minutes;
    private $seconds;
    private $frames;

    public function __construct($hours, $minutes, $seconds, $frames)
    {
        $this->hours = $hours;
        $this->minutes = $minutes;
        $this->seconds = $seconds;
        $this->frames = $frames;
    }

    public function __toString()
    {
        return sprintf('%02d:%02d:%02d.%02d', $this->hours, $this->minutes, $this->seconds, $this->frames);
    }

    /**
     * Creates timecode from string.
     *
     * @param string $timecode
     *
     * @return TimeCode
     *
     * @throws InvalidArgumentException In case an invalid timecode is supplied
     */
    public static function fromString($timecode)
    {
        $days = 0;

        if (preg_match('/^[0-9]+:[0-9]+:[0-9]+:[0-9]+\.[0-9]+$/', $timecode)) {
            list($days, $hours, $minutes, $seconds, $frames) = sscanf($timecode, '%d:%d:%d:%d.%d');
        } elseif (preg_match('/^[0-9]+:[0-9]+:[0-9]+:[0-9]+:[0-9]+$/', $timecode)) {
            list($days, $hours, $minutes, $seconds, $frames) = sscanf($timecode, '%d:%d:%d:%d:%d');
        } elseif (preg_match('/^[0-9]+:[0-9]+:[0-9]+\.[0-9]+$/', $timecode)) {
            list($hours, $minutes, $seconds, $frames) = sscanf($timecode, '%d:%d:%d.%s');
        } elseif (preg_match('/^[0-9]+:[0-9]+:[0-9]+:[0-9]+$/', $timecode)) {
            list($hours, $minutes, $seconds, $frames) = sscanf($timecode, '%d:%d:%d:%s');
        } else {
            throw new InvalidArgumentException(sprintf('Unable to parse timecode %s', $timecode));
        }

        $hours += $days * 24;

        return new static($hours, $minutes, $seconds, $frames);
    }

    /**
     * Creates timecode from number of seconds.
     *
     * @param float $quantity
     *
     * @return TimeCode
     */
    public static function fromSeconds($quantity)
    {
        if (!is_numeric($quantity) || !is_finite((float) $quantity) || $quantity < 0 || $quantity >= PHP_INT_MAX / 100) {
            throw new InvalidArgumentException('Seconds must be a finite, non-negative number within range.');
        }

        $centiseconds = (int) round($quantity * 100);
        $hours = intdiv($centiseconds, 360000);
        $minutes = intdiv($centiseconds % 360000, 6000);
        $seconds = intdiv($centiseconds % 6000, 100);
        $frames = $centiseconds % 100;

        return new static($hours, $minutes, $seconds, $frames);
    }

    /**
     * Returns this timecode in seconds
     * @return float
     */
    public function toSeconds()
    {
        $seconds = 0;

        $seconds += $this->hours * 60 * 60;
        $seconds += $this->minutes * 60;
        $seconds += $this->seconds;

        return $seconds + $this->frames / 100;
    }

    /**
     * Helper function wether `$timecode` is after this one
     *
     * @param   TimeCode    $timecode   The Timecode to compare
     * @return bool
     */
    public function isAfter(TimeCode $timecode)
    {
        // convert everything to seconds and compare
        return ($this->toSeconds() > $timecode->toSeconds());
    }
}
