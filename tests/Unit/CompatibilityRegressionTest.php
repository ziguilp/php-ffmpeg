<?php

namespace Tests\FFMpeg\Unit;

use Alchemy\BinaryDriver\Exception\InvalidArgumentException as DriverInvalidArgumentException;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\Exception\InvalidArgumentException;
use FFMpeg\FFProbe\DataMapping\Format;
use FFMpeg\FFProbe\DataMapping\Stream;
use FFMpeg\FFProbe\DataMapping\StreamCollection;
use FFMpeg\Filters\Video\CustomFilter;
use FFMpeg\Filters\Video\ExtractMultipleFramesFilter;
use FFMpeg\Format\Audio\Mp3;
use FFMpeg\Format\Video\X264;
use FFMpeg\Media\Concat;
use FFMpeg\Media\Video;

class CompatibilityRegressionTest extends TestCase
{
    public function testTimeCodeRoundingAndComparison()
    {
        $this->assertSame('00:01:00.00', (string) TimeCode::fromSeconds(59.999));
        $this->assertSame('01:00:00.00', (string) TimeCode::fromSeconds(3599.999));
        $this->assertEqualsWithDelta(1.9, TimeCode::fromSeconds(1.9)->toSeconds(), 0.00001);
        $this->assertTrue(TimeCode::fromSeconds(1.9)->isAfter(TimeCode::fromSeconds(1.1)));
        $this->assertFalse(TimeCode::fromSeconds(1.1)->isAfter(TimeCode::fromSeconds(1.9)));
    }

    /** @dataProvider invalidSeconds */
    public function testRejectsInvalidSeconds($seconds)
    {
        $this->expectException(InvalidArgumentException::class);
        TimeCode::fromSeconds($seconds);
    }

    public function invalidSeconds()
    {
        return array(array(-1), array(INF), array(NAN), array('invalid'));
    }

    /** @dataProvider sampleRatios */
    public function testNonSquarePixels($sar, $dar, $width, $height)
    {
        $stream = new Stream(array('codec_type' => 'video', 'width' => 720, 'height' => 576,
            'sample_aspect_ratio' => $sar, 'display_aspect_ratio' => $dar));
        $dimensions = $stream->getDimensions();
        $this->assertEquals($width, $dimensions->getWidth());
        $this->assertEquals($height, $dimensions->getHeight());
    }

    public function sampleRatios()
    {
        return array(array('2:1', '5:2', 1440, 576), array('1:2', '5:8', 360, 576));
    }

    public function testSampleRatioWithoutDisplayRatio()
    {
        $stream = new Stream(array('codec_type' => 'video', 'width' => 720, 'height' => 576,
            'sample_aspect_ratio' => '2:1'));
        $this->assertEquals(1440, $stream->getDimensions()->getWidth());
        $this->assertEquals(576, $stream->getDimensions()->getHeight());
    }

    /** @dataProvider invalidFrameRates */
    public function testRejectsInvalidFrameRates($rate)
    {
        $video = $this->getVideoMock();
        $video->method('getStreams')->willReturn(new StreamCollection());
        $this->expectException(InvalidArgumentException::class);
        (new ExtractMultipleFramesFilter($rate))->apply($video, new X264('aac'));
    }

    public function invalidFrameRates()
    {
        return array(array('invalid'), array('1/0'), array('0/1'), array('1+2'), array('x1/2'), array('1/2suffix'), array(null), array(array()));
    }

    private function driver()
    {
        $driver = $this->getFFMpegDriverMock();
        $configuration = $this->getConfigurationMock();
        $configuration->method('has')->willReturn(true);
        $configuration->method('get')->willReturn(2);
        $driver->method('getConfiguration')->willReturn($configuration);
        return $driver;
    }

    public function testAllVideoFiltersAreMergedInOrder()
    {
        $driver = $this->driver();
        $driver->expects($this->once())->method('command')->with($this->callback(function ($command) {
            $this->assertCount(1, array_keys($command, '-vf', true));
            $this->assertSame('[in]scale=320:240[p0];[p0]fps=1[p1];[p1]hflip[out]', $command[array_search('-vf', $command, true) + 1]);
            return true;
        }), false, $this->anything());
        $video = new Video('input.mp4', $driver, $this->getFFProbeMock());
        foreach (array('scale=320:240', 'fps=1', 'hflip') as $filter) {
            $video->addFilter(new CustomFilter($filter));
        }
        $video->save((new X264('aac'))->setPasses(1), 'output.mp4');
    }

    public function testClipWithoutDurationUsesRemainingDurationForProgress()
    {
        $driver = $this->driver();
        $driver->expects($this->once())->method('command');
        $probe = $this->getFFProbeMock();
        $probe->method('format')->willReturn(new Format(array('duration' => 10.5)));
        $format = $this->getMockBuilder(X264::class)->setConstructorArgs(array('aac'))
            ->onlyMethods(array('createProgressListener'))->getMock();
        $format->setPasses(1);
        $video = new Video('input.mp4', $driver, $probe);
        $video->filters()->clip(TimeCode::fromSeconds(1.25));
        $format->expects($this->once())->method('createProgressListener')
            ->with($video, $probe, 1, 1, 9.25)->willReturn(array());
        $video->save($format, 'output.mp4');
    }

    /** @dataProvider concatLayouts */
    public function testConcatStreamMappingsAndFormatParameters($video, $audio, $audioOutput, $graph)
    {
        $driver = $this->driver();
        $probe = $this->getFFProbeMock();
        $streams = array();
        if ($video) {
            $streams[] = new Stream(array('codec_type' => 'video'));
        }
        if ($audio) {
            $streams[] = new Stream(array('codec_type' => 'audio'));
        }
        $probe->method('streams')->willReturn(new StreamCollection($streams));
        $format = $this->getMockBuilder($audioOutput ? Mp3::class : X264::class)
            ->setConstructorArgs($audioOutput ? array() : array('aac'))
            ->onlyMethods(array('getExtraParams'))->getMock();
        $format->method('getExtraParams')->willReturn(array('-metadata', 'title=concat'));
        $driver->expects($this->once())->method('command')->with($this->callback(function ($command) use ($graph, $audioOutput, $video, $audio) {
            $this->assertSame($graph, $command[array_search('-filter_complex', $command, true) + 1]);
            $this->assertContains('-threads', $command);
            $this->assertContains('title=concat', $command);
            if ($video && !$audioOutput) {
                $this->assertContains('libx264', $command);
                $this->assertContains('-b:v', $command);
            } else {
                $this->assertNotContains('-vcodec', $command);
            }
            if ($audio) {
                $this->assertContains($audioOutput ? 'libmp3lame' : 'aac', $command);
            } else {
                $this->assertNotContains('-acodec', $command);
            }
            $this->assertSame('output', end($command));
            return true;
        }));
        // Non-numeric source keys must not leak into ffmpeg input indices.
        (new Concat(array('first' => 'a', 'second' => 'b'), $driver, $probe))->saveFromDifferentCodecs($format, 'output');
    }

    public function concatLayouts()
    {
        return array(
            array(true, true, false, '[0:v:0] [0:a:0] [1:v:0] [1:a:0] concat=n=2:v=1:a=1 [v] [a]'),
            array(true, false, false, '[0:v:0] [1:v:0] concat=n=2:v=1:a=0 [v]'),
            array(false, true, true, '[0:a:0] [1:a:0] concat=n=2:v=0:a=1 [a]'),
            array(true, true, true, '[0:a:0] [1:a:0] concat=n=2:v=0:a=1 [a]'),
        );
    }

    public function testConcatRejectsInconsistentStreamsBeforeExecuting()
    {
        $driver = $this->driver();
        $driver->expects($this->never())->method('command');
        $probe = $this->getFFProbeMock();
        $probe->method('streams')->willReturnOnConsecutiveCalls(
            new StreamCollection(array(new Stream(array('codec_type' => 'video')))),
            new StreamCollection(array(new Stream(array('codec_type' => 'audio'))))
        );
        $this->expectException(DriverInvalidArgumentException::class);
        (new Concat(array('a', 'b'), $driver, $probe))->saveFromDifferentCodecs(new X264('aac'), 'output');
    }

    public function testProgressRateUsesElapsedTimeAndKeepsSubsecondDuration()
    {
        $probe = $this->getFFProbeMock();
        $probe->method('format')->willReturn(new Format(array('duration' => 10, 'size' => 10240)));
        $listener = new class($probe, 'input', 1, 1, 0.5) extends \FFMpeg\Format\ProgressListener\AudioProgressListener {
            public $timestamp = 0;

            protected function getTimestamp(): float
            {
                return $this->timestamp;
            }
        };
        $events = array();
        $listener->on('progress', function ($percent, $remaining, $rate) use (&$events) {
            $events[] = array($percent, $remaining, $rate);
        });
        $listener->handle('stderr', 'size= 100kB time=00:00:00.10 bitrate=128kbits/s');
        $listener->timestamp = 2;
        $listener->handle('stderr', 'size= 300kB time=00:00:00.25 bitrate=128kbits/s');
        $this->assertCount(1, $events);
        $this->assertEquals(50, $events[0][0]);
        $this->assertEquals(100, $events[0][2]);
    }

    public function testProgressIgnoresUnknownOrZeroSourceDuration()
    {
        $probe = $this->getFFProbeMock();
        $probe->method('format')->willReturn(new Format(array('duration' => 0, 'size' => 1024)));
        $listener = new \FFMpeg\Format\ProgressListener\AudioProgressListener($probe, 'input', 1, 1);
        $events = array();
        $listener->on('progress', function () use (&$events) { $events[] = true; });
        $listener->handle('stderr', 'size= 100kB time=00:00:00.10 bitrate=128kbits/s');
        $this->assertSame(array(), $events);
    }

    public function testX264DefaultAudioCodecRemainsLibfaac()
    {
        $this->assertSame('libfaac', (new X264())->getAudioCodec());
    }
}
