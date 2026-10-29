<?php

namespace Tests\FFMpeg\Functional;

use FFMpeg\FFProbe;
use Symfony\Component\Process\Process;

class FFProbeTest extends FunctionalTestCase
{
    public function testProbeOnFile()
    {
        $ffprobe = FFProbe::create();
        $this->assertGreaterThan(0, count($ffprobe->streams(__DIR__ . '/../files/Audio.mp3')));
    }

    public function testValidateExistingFile()
    {
        $ffprobe = FFProbe::create();
        $this->assertTrue($ffprobe->isValid(__DIR__ . '/../files/sample.3gp'));
    }


    public function testValidateNonExistingFile()
    {
        $ffprobe = FFProbe::create();
        $this->assertFalse($ffprobe->isValid(__DIR__ . '/../files/WrongFile.mp4'));
    }

    public function testProbeOnNonExistantFile()
    {
        $this->expectException('FFMpeg\Exception\RuntimeException');
        $ffprobe = FFProbe::create();
        $ffprobe->streams('/path/to/no/file');
    }

    public function testProbeOnRemoteFile()
    {
        // Serve a local fixture over HTTP so this test does not depend on an external CDN.
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
        $this->assertNotFalse($socket, $errorMessage);
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        $server = new Process(array(PHP_BINARY, '-S', $address, '-t', __DIR__ . '/../files'));
        $server->start();
        try {
            $ready = false;
            $port = (int) substr(strrchr($address, ':'), 1);
            for ($attempt = 0; $attempt < 100; $attempt++) {
                $connection = @fsockopen('127.0.0.1', $port, $errorCode, $errorMessage, 0.1);
                if ($connection !== false) {
                    fclose($connection);
                    $ready = true;
                    break;
                }
                if (!$server->isRunning()) {
                    break;
                }
                usleep(20000);
            }
            $this->assertTrue($ready, $server->getErrorOutput());
            $ffprobe = FFProbe::create();
            $this->assertGreaterThan(0, count($ffprobe->streams('http://' . $address . '/Audio.mp3')));
        } finally {
            $server->stop();
        }
    }
}
