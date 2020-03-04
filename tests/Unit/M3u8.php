<?php

namespace Tests\FFMpeg\Unit;

use FFMpeg\FFMpeg;
use FFMpeg\Format\Video\M3u8 as FormatM3u8;
use FFMpeg\Format\Video\X264;
use FFMpeg\Coordinate\TimeCode;
use Symfony\Component\Process\ExecutableFinder;
use Alchemy\BinaryDriver\ConfigurationInterface;
use Alchemy\BinaryDriver\Configuration;

class M3u8 extends TestCase
{
    /**
     * mp42m3u8
     */
    public function testCreateM3u8()
    {
        $file = __DIR__.'/../../bin/';

        $ffmpeg = FFMpeg::create([
            'ffmpeg.binaries'  => $file.'ffmpeg.exe',
            'ffprobe.binaries' => $file.'ffprobe.exe',
            'timeout'          => 3600, // The timeout for the underlying process
            'ffmpeg.threads'   => 12,   // The number of threads that FFMpeg should use
        ]);
        $video = $ffmpeg->open("D:\movie\超验骇客_origin.mp4");
        $m3u8Path = "D:\movie\m3u83";

        $stream = $video->getStreams();
        $format = $video->getFormat();

        var_dump($stream, $format->get('duration'));die;

        
        $frame = $video->frame(TimeCode::fromSeconds(30));
        $frame->save($m3u8Path.'/image.jpg');
      

        $format = new FormatM3u8($m3u8Path);
        $format->setAudioCodec("aac");
        $format->setAudioChannels(2);
        $format->setVideoCodec("libx264");
        $format->setTsTime(10);
        $format->setTsName($m3u8Path."/%04d.ts");
        $format->on('progress', function ($video, $format, $percentage) {
            var_dump( "$percentage % transcoded\n" );
        });

        // var_dump($video->stream());
      

        $clip = $video->clip(TimeCode::fromSeconds(30), TimeCode::fromSeconds(60));
        // $mp4 = new X264();
        // $mp4->setAudioChannels(2);
        // $mp4->setAudioCodec('aac');

        // $clip->save($mp4,$m3u8Path.'/test.mp4');

        $clip->save($format,$m3u8Path.'/test.m3u8');

    

        $command = $clip->getFinalCommand($format, $m3u8Path.'/test.m3u8');
        // var_dump($command);
        return true;
        // $format
        //     ->setKiloBitrate(1000)
        //     ->setAudioChannels(2)
        //     ->setAudioKiloBitrate(256);
        $video->save($format, $m3u8Path.'/test.m3u8');
        return true;
    }
}