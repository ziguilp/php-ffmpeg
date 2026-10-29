<?php

namespace Tests\FFMpeg\Unit\Media;

use FFMpeg\Format\AudioInterface;
use FFMpeg\Format\VideoInterface;
use FFMpeg\Media\Audio;
use FFMpeg\Media\Video;
use Tests\FFMpeg\Unit\TestCase;

class M3u8Test extends TestCase
{
    /**
     * @dataProvider provideM3u8Outputs
     */
    public function testSaveBuildsHlsCommandsWithoutLeakingOptions($video, $passes, $playlist, $segments)
    {
        $driver = $this->getFFMpegDriverMock();
        $driver->method('getConfiguration')->willReturn($this->getConfigurationMock());
        $commands = array();
        $driver->expects($this->exactly($passes * 2))
            ->method('command')
            ->willReturnCallback(function ($command) use (&$commands) {
                $commands[] = $command;
                return '';
            });

        $format = $this->createMock($video ? VideoInterface::class : AudioInterface::class);
        $format->method('getExtraParams')->willReturn(array());
        $format->method('getAudioCodec')->willReturn('aac');
        if ($video) {
            $format->method('getPasses')->willReturn($passes);
            $format->method('getVideoCodec')->willReturn('libx264');
            $format->method('getKiloBitrate')->willReturn(1000);
        }

        $media = $video
            ? new Video('input.mp4', $driver, $this->getFFProbeMock())
            : new Audio('input.wav', $driver, $this->getFFProbeMock());
        $this->assertSame($media, $media->save($format, $playlist));
        // The directory name contains .m3u8, but the output is not an HLS playlist.
        $ordinaryOutput = '/output/archive.m3u8/ordinary.' . ($video ? 'mp4' : 'm4a');
        $this->assertSame($media, $media->save($format, $ordinaryOutput));

        foreach (array_slice($commands, 0, $passes) as $index => $command) {
            $this->assertOption($command, '-hls_time', 5);
            $this->assertOption($command, '-hls_list_size', 0);
            $this->assertOption($command, '-hls_segment_filename', $segments);
            $this->assertSame($playlist, end($command));
            $this->assertContains('aac', $command);
            if ($video) {
                $this->assertContains('libx264', $command);
            }
            if ($passes > 1) {
                $this->assertOption($command, '-pass', $index + 1);
            } else {
                $this->assertNotContains('-pass', $command);
            }
        }

        foreach (array_slice($commands, $passes) as $command) {
            $this->assertNotContains('-hls_time', $command);
            $this->assertNotContains('-hls_list_size', $command);
            $this->assertNotContains('-hls_segment_filename', $command);
            $this->assertSame($ordinaryOutput, end($command));
        }
    }

    private function assertOption(array $command, $option, $value)
    {
        $positions = array_keys($command, $option, true);
        $this->assertCount(1, $positions, $option . ' must appear exactly once');
        $this->assertSame($value, $command[$positions[0] + 1]);
    }

    public function provideM3u8Outputs()
    {
        $cases = array();
        foreach (array('audio' => array(false, 1), 'video' => array(true, 1), 'two-pass video' => array(true, 2)) as $type => $settings) {
            $cases[$type . ': relative path'] = array_merge($settings, array('index.m3u8', 'index_%04d.ts'));
            $cases[$type . ': absolute path'] = array_merge($settings, array('/output/movie.v1.m3u8', '/output/movie.v1_%04d.ts'));
            $cases[$type . ': spaces and unicode'] = array_merge($settings, array('/output/测试 video/index.m3u8', '/output/测试 video/index_%04d.ts'));
        }
        return $cases;
    }
}
