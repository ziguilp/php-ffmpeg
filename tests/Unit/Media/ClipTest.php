<?php
namespace Tests\FFMpeg\Unit\Media;

use FFMpeg\Media\Video;

class ClipTest extends AbstractMediaTestCase
{

    /**
     * @dataProvider provideBuildOptions
     */
    public function testBuildCommand($startValue, $durationValue, $commands)
    {
        $configuration = $this->getConfigurationMock();

        $driver = $this->getFFMpegDriverMock();
        $driver->expects($this->any())
            ->method('getConfiguration')
            ->will($this->returnValue($configuration));

        $ffprobe = $this->getFFProbeMock();

        $start = $this->getTimeCodeMock();
        $start->expects($this->once())
            ->method('__toString')
            ->will($this->returnValue($startValue));

        $duration = null;
        if (null !== $durationValue) {
            $duration = $this->getTimeCodeMock();
            $duration->expects($this->once())
                ->method('__toString')
                ->will($this->returnValue($durationValue));
        }

        $outputPathfile = '/target/file';

        $format = $this->createMock('FFMpeg\Format\VideoInterface');
        $format->expects($this->any())
            ->method('getPasses')
            ->will($this->returnValue(1));
        $format->expects($this->any())
            ->method('getExtraParams')
            ->will($this->returnValue(array()));

        $clip = (new Video(__FILE__, $driver, $ffprobe))->clip($start, $duration);
        $fc = $clip->getFinalCommand($format, $outputPathfile);

        $this->assertCount(1, $fc);
        $this->assertStringStartsWith(implode(' ', $commands), $fc[0]);
    }

    public function provideBuildOptions()
    {
        return array(
            array('SS01', null, array(
                    '-y', '-i', __FILE__, '-ss', 'SS01')
            ),
            array('SS02', 'D02', array(
                    '-y', '-i', __FILE__, '-ss', 'SS02',
                    '-t', 'D02')
            )
        );
    }
}
