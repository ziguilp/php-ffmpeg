<?php

namespace Tests\FFMpeg\Functional;

use FFMpeg\Format\Audio\Mp3;
use FFMpeg\Format\Video\X264;
use FFMpeg\Media\Concat;

class ConcatTranscodeTest extends FunctionalTestCase
{
    /** @dataProvider streamLayouts */
    public function testTranscodeConcatenation($video, $audio)
    {
        $directory = sys_get_temp_dir() . '/ffmpeg-concat-test-' . uniqid();
        mkdir($directory);
        try {
            $ffmpeg = $this->getFFMpeg();
            $driver = $ffmpeg->getFFMpegDriver();
            $probe = $ffmpeg->getFFProbe();
            $sources = array();
            for ($i = 0; $i < 2; $i++) {
                $source = $directory . '/input-' . $i . ($video ? '.mp4' : '.wav');
                $command = array('-y');
                if ($video) {
                    $command = array_merge($command, array('-f', 'lavfi', '-i', 'color=c=black:s=64x64:r=25'));
                }
                if ($audio) {
                    $command = array_merge($command, array('-f', 'lavfi', '-i', 'sine=frequency=440:sample_rate=44100'));
                }
                if ($video) {
                    $command = array_merge($command, array('-c:v', 'libx264'));
                    if ($audio) {
                        $command = array_merge($command, array('-c:a', 'aac'));
                    }
                }
                $command = array_merge($command, array('-t', '0.3', $source));
                $driver->command($command);
                $sources[] = $source;
            }
            $output = $directory . ($video ? '/output.mp4' : '/output.mp3');
            $format = $video ? (new X264('aac'))->setPasses(1) : new Mp3();
            (new Concat($sources, $driver, $probe))->saveFromDifferentCodecs($format, $output);
            $streams = $probe->streams($output);
            $this->assertCount($video ? 1 : 0, $streams->videos());
            $this->assertCount($audio ? 1 : 0, $streams->audios());
            $this->assertGreaterThan(0.5, (float) $probe->format($output)->get('duration'));
            if ($video) {
                $this->assertSame('h264', $streams->videos()->first()->get('codec_name'));
            }
            if ($audio) {
                $this->assertSame($video ? 'aac' : 'mp3', $streams->audios()->first()->get('codec_name'));
            }
        } finally {
            foreach (glob($directory . '/*') as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
    }

    public function streamLayouts()
    {
        return array('audio' => array(false, true), 'silent video' => array(true, false), 'video with audio' => array(true, true));
    }
}
