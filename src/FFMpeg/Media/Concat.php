<?php
/*
 * @Author        : turbo 664120459@qq.com
 * @Date          : 2026-10-28 23:37:28
 * @LastEditors   : turbo 664120459@qq.com
 * @LastEditTime  : 2026-10-29 11:37:04
 * @FilePath      : /php-ffmpeg/src/FFMpeg/Media/Concat.php
 * @Description   : 
 * 
 * Copyright (c) 2026 by ${git_name}, All Rights Reserved. 
 */

/*
 * This file is part of PHP-FFmpeg.
 *
 * (c) Strime <contact@strime.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FFMpeg\Media;

use Alchemy\BinaryDriver\Exception\ExecutionFailureException;
use Alchemy\BinaryDriver\Exception\InvalidArgumentException;
use FFMpeg\Filters\Concat\ConcatFilterInterface;
use FFMpeg\Filters\Concat\ConcatFilters;
use FFMpeg\Driver\FFMpegDriver;
use FFMpeg\FFProbe;
use FFMpeg\Exception\RuntimeException;
use FFMpeg\Format\FormatInterface;
use FFMpeg\Format\AudioInterface;
use FFMpeg\Format\VideoInterface;
use Neutron\TemporaryFilesystem\Manager as FsManager;

class Concat extends AbstractMediaType
{
    /** @var array */
    private $sources;

    public function __construct($sources, FFMpegDriver $driver, FFProbe $ffprobe)
    {
        parent::__construct($sources, $driver, $ffprobe);
        $this->sources = $sources;
    }

    /**
     * Returns the path to the sources.
     *
     * @return string
     */
    public function getSources()
    {
        return $this->sources;
    }

    /**
     * {@inheritdoc}
     *
     * @return ConcatFilters
     */
    public function filters()
    {
        return new ConcatFilters($this);
    }

    /**
     * {@inheritdoc}
     *
     * @return Concat
     */
    public function addFilter(ConcatFilterInterface $filter)
    {
        $this->filters->add($filter);

        return $this;
    }

    /**
     * Saves the concatenated video in the given array, considering that the sources videos are all encoded with the same codec.
     *
     * @param string  $outputPathfile
     * @param bool    $streamCopy
     *
     * @return Concat
     *
     * @throws RuntimeException
     */
    public function saveFromSameCodecs($outputPathfile, $streamCopy = TRUE)
    {
        /**
         * @see https://ffmpeg.org/ffmpeg-formats.html#concat
         * @see https://trac.ffmpeg.org/wiki/Concatenate
         */

        // Create the file which will contain the list of videos
        $fs = FsManager::create();
        /** @var string $sourcesFile */
        $sourcesFile = $fs->createTemporaryFile('ffmpeg-concat');

        // Set the content of this file
        $fileStream = @fopen($sourcesFile, 'w');

        if ($fileStream === false) {
            throw new ExecutionFailureException('ffmpeg', 'open concat list', 'Cannot open the temporary file.');
        }

        $count_videos = 0;
        if (is_array($this->sources) && (count($this->sources) > 0)) {
            foreach ($this->sources as $videoPath) {
                $line = "";

                if ($count_videos != 0)
                    $line .= "\n";

                $line .= "file " . addcslashes($videoPath, '\'"\\\0 ');

                fwrite($fileStream, $line);

                $count_videos++;
            }
        } else {
            throw new InvalidArgumentException('The list of videos is not a valid array.');
        }
        fclose($fileStream);


        $commands = array(
            '-f',
            'concat',
            '-safe',
            '0',
            '-i',
            $sourcesFile
        );

        // Check if stream copy is activated
        if ($streamCopy === TRUE) {
            $commands[] = '-c';
            $commands[] = 'copy';
        }

        // If not, we can apply filters
        else {
            foreach ($this->filters as $filter) {
                $commands = array_merge($commands, $filter->apply($this));
            }
        }

        // Set the output file in the command
        $commands = array_merge($commands, array($outputPathfile));

        // Execute the command
        try {
            $this->driver->command($commands);
        } catch (ExecutionFailureException $e) {
            $this->cleanupTemporaryFile($outputPathfile);
            // TODO@v1: paste this line into an `finally` block.
            $this->cleanupTemporaryFile($sourcesFile);
            throw new RuntimeException('Unable to save concatenated video', $e->getCode(), $e);
        }

        $this->cleanupTemporaryFile($sourcesFile);
        return $this;
    }

    /**
     * Saves the concatenated video in the given filename, considering that the sources videos are all encoded with the same codec.
     *
     * @param string  $outputPathfile
     *
     * @return Concat
     *
     * @throws RuntimeException
     */
    public function saveFromDifferentCodecs(FormatInterface $format, $outputPathfile)
    {
        /**
         * @see https://ffmpeg.org/ffmpeg-formats.html#concat
         * @see https://trac.ffmpeg.org/wiki/Concatenate
         */

        // Check the validity of the parameter
        if (!is_array($this->sources) || (count($this->sources) == 0)) {
            throw new InvalidArgumentException('The list of videos is not a valid array.');
        }

        $commands = array();
        $hasVideo = $hasAudio = null;
        $inputs = array();
        foreach ($this->sources as $source) {
            $streams = $this->ffprobe->streams($source);
            $video = count($streams->videos()) > 0;
            $audio = count($streams->audios()) > 0;
            // Audio formats intentionally discard video streams.
            if ($format instanceof AudioInterface && !$format instanceof VideoInterface) {
                $video = false;
            }
            if (!$video && !$audio) {
                throw new InvalidArgumentException('Each source must contain a stream supported by the output format.');
            }
            if ($hasVideo !== null && ($hasVideo !== $video || $hasAudio !== $audio)) {
                throw new InvalidArgumentException('All sources must have the same audio/video stream layout.');
            }
            $hasVideo = $video;
            $hasAudio = $audio;
            $commands[] = '-i';
            $commands[] = $source;
            $input = count($inputs);
            $inputs[] = ($video ? '[' . $input . ':v:0] ' : '') . ($audio ? '[' . $input . ':a:0] ' : '');
        }

        $labels = ($hasVideo ? ' [v]' : '') . ($hasAudio ? ' [a]' : '');
        $commands[] = '-filter_complex';
        $commands[] = implode('', $inputs) . 'concat=n=' . count($inputs)
            . ':v=' . (int) $hasVideo . ':a=' . (int) $hasAudio . $labels;
        foreach (array_filter(array($hasVideo ? '[v]' : null, $hasAudio ? '[a]' : null)) as $label) {
            $commands[] = '-map';
            $commands[] = $label;
        }

        // Format parameters are command arguments, not Audio filters applied to a Concat object.
        $commands = array_merge($commands, $format->getExtraParams());
        if ($this->driver->getConfiguration()->has('ffmpeg.threads')) {
            $commands[] = '-threads';
            $commands[] = $this->driver->getConfiguration()->get('ffmpeg.threads');
        }
        if ($hasVideo && $format instanceof VideoInterface && $format->getVideoCodec() !== null) {
            $commands[] = '-vcodec';
            $commands[] = $format->getVideoCodec();
        }
        if ($hasAudio && $format instanceof AudioInterface && $format->getAudioCodec() !== null) {
            $commands[] = '-acodec';
            $commands[] = $format->getAudioCodec();
        }

        // Add the filters
        foreach ($this->filters as $filter) {
            $commands = array_merge($commands, $filter->apply($this));
        }

        if ($hasAudio && $format instanceof AudioInterface) {
            if (null !== $format->getAudioKiloBitrate()) {
                $commands[] = '-b:a';
                $commands[] = $format->getAudioKiloBitrate() . 'k';
            }
            if (null !== $format->getAudioChannels()) {
                $commands[] = '-ac';
                $commands[] = $format->getAudioChannels();
            }
        }

        // If the user passed some additional parameters
        if ($hasVideo && $format instanceof VideoInterface) {
            if ($format->getKiloBitrate() !== null) {
                $commands[] = '-b:v';
                $commands[] = $format->getKiloBitrate() . 'k';
            }
            if (null !== $format->getAdditionalParameters()) {
                foreach ($format->getAdditionalParameters() as $additionalParameter) {
                    $commands[] = $additionalParameter;
                }
            }
        }

        // Set the output file in the command
        $commands[] = $outputPathfile;

        $failure = null;

        try {
            $this->driver->command($commands);
        } catch (ExecutionFailureException $e) {
            throw new RuntimeException('Encoding failed', $e->getCode(), $e);
        }

        return $this;
    }
}
