<?php

/*
 * This file is part of PHP-FFmpeg.
 *
 * (c) Alchemy <info@alchemy.fr>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FFMpeg\Media;

use Alchemy\BinaryDriver\Exception\ExecutionFailureException;
use FFMpeg\Filters\Audio\AudioFilters;
use FFMpeg\Format\FormatInterface;
use FFMpeg\Filters\Audio\SimpleFilter;
use FFMpeg\Exception\RuntimeException;
use FFMpeg\Exception\InvalidArgumentException;
use FFMpeg\Filters\Audio\AudioFilterInterface;
use FFMpeg\Filters\FilterInterface;
use FFMpeg\Format\ProgressableInterface;

use FFMpeg\Coordinate\TimeCode;
use FFMpeg\Coordinate\Dimension;


class Audio extends AbstractStreamableMedia
{

     /** @var TimeCode Start time */
     protected $start = null;

     /** @var TimeCode Duration */
     protected $duration = null;

     /** @var Boolean */
     protected $onlyAudio = false;


      /** @var Boolean */
      protected $onlyVideo = false;

    /**
     * {@inheritdoc}
     *
     * @return AudioFilters
     */
    public function filters()
    {
        return new AudioFilters($this);
    }

    /**
     * {@inheritdoc}
     *
     * @return Audio
     */
    public function addFilter(FilterInterface $filter)
    {
        if (!$filter instanceof AudioFilterInterface) {
            throw new InvalidArgumentException('Audio only accepts AudioFilterInterface filters');
        }

        $this->filters->add($filter);

        return $this;
    }

    /**
     * Exports the audio in the desired format, applies registered filters.
     *
     * @param FormatInterface   $format
     * @param string            $outputPathfile
     * @return Audio
     * @throws RuntimeException
     */
    public function save(FormatInterface $format, $outputPathfile)
    {
        $listeners = null;

        if ($format instanceof ProgressableInterface) {
            $listeners = $format->createProgressListener($this, $this->ffprobe, 1, 1, 0);
        }

        $commands = $this->buildCommand($format, $outputPathfile);

        try {
            $this->driver->command($commands, false, $listeners);
        } catch (ExecutionFailureException $e) {
            $this->cleanupTemporaryFile($outputPathfile);
            throw new RuntimeException('Encoding failed', $e->getCode(), $e);
        }

        return $this;
    }

    /**
     * Returns the final command as a string, useful for debugging purposes.
     *
     * @param FormatInterface   $format
     * @param string            $outputPathfile
     * @return string
     * @since 0.11.0
     */
    public function getFinalCommand(FormatInterface $format, $outputPathfile) {
        return implode(' ', $this->buildCommand($format, $outputPathfile));
    }

    /**
     * Builds the command which will be executed with the provided format
     *
     * @param FormatInterface   $format
     * @param string            $outputPathfile
     * @return string[] An array which are the components of the command
     * @since 0.11.0
     */
    protected function buildCommand(FormatInterface $format, $outputPathfile) {
        $commands = $this->basePartOfCommand();

        $filters = clone $this->filters;
        $filters->add(new SimpleFilter($format->getExtraParams(), 10));

        if ($this->driver->getConfiguration()->has('ffmpeg.threads')) {
            $filters->add(new SimpleFilter(array('-threads', $this->driver->getConfiguration()->get('ffmpeg.threads'))));
        }
        if (null !== $format->getAudioCodec()) {
            $filters->add(new SimpleFilter(array('-acodec', $format->getAudioCodec())));
        }

        foreach ($filters as $filter) {
            $commands = array_merge($commands, $filter->apply($this, $format));
        }

        if (null !== $format->getAudioKiloBitrate()) {
            $commands[] = '-b:a';
            $commands[] = $format->getAudioKiloBitrate() . 'k';
        }
        if (null !== $format->getAudioChannels()) {
            $commands[] = '-ac';
            $commands[] = $format->getAudioChannels();
        }

        $commands = array_merge($commands, $this->generatOutStreamParam());

        $commands[] = $outputPathfile;

        return $commands;
    }

    /**
     * Gets the waveform of the video.
     *
     * @param  integer $width
     * @param  integer $height
     * @param array $colors Array of colors for ffmpeg to use. Color format is #000000 (RGB hex string with #)
     * @return Waveform
     */
    public function waveform($width = 640, $height = 120, $colors = array(Waveform::DEFAULT_COLOR))
    {
        return new Waveform($this, $this->driver, $this->ffprobe, $width, $height, $colors);
    }

    /**
     * Concatenates a list of audio files into one unique audio file.
     *
     * @param  array $sources
     * @return Concat
     */
    public function concat($sources)
    {
        return new Concat($sources, $this->driver, $this->ffprobe);
    }


    /**
     * Return base part of command.
     *
     * @return array
     */
    protected function basePartOfCommand()
    {
        $arr = array('-y', '-i', $this->pathfile);

        if (is_null($this->start) === false) {
            $arr[] = '-ss';
            $arr[] = (string) $this->start;
        }

        if (is_null($this->duration) === false) {
            $arr[] = '-t';
            $arr[] = (string) $this->duration;
        }
       
        return $arr;
    }

    /**
     * audio、video 
     */
    protected function generatOutStreamParam(){
        $arr = [];
        if($this->onlyAudio == $this->onlyVideo){
           return $arr;
        }
        if($this->onlyAudio){
            // 只输出音频
            $arr[] = "-vn";
        }elseif($this->onlyVideo){
            // 只输出视频
            $arr[] = "-an";
        }
        return $arr;
    }

    public function setOnlyAudio(Bool $bal){
        $this->onlyAudio = $bal;
        return $this;
    }

    public function setOnlyVideo(Bool $bal){
        $this->onlyVideo = $bal;
        return $this;
    }

    /**
     * Clips the video at the given time(s).
     *
     * @param TimeCode $start Start time
     * @param TimeCode $duration Duration
     * @return \FFMpeg\Media\Clip
     */
    public function clip(TimeCode $start, TimeCode $duration = null)
    {
        $this->start = $start;
        $this->duration = $duration;

        return $this;
    }
}
