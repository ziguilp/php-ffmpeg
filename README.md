# turbo/php-ffmpeg

[简体中文](README.md) | [English](README.en.md)

[更新日志](CHANGELOG.md)

本项目基于 PHP-FFMpeg，提供 bug 修复与功能增强。如需使用上游项目，请访问 [php-ffmpeg/php-ffmpeg](https://github.com/PHP-FFMpeg/PHP-FFMpeg)。

主要增强功能：

- 支持音频裁剪。
- 支持保存为 m3u8。
- 支持只保留音频流或视频流。

转码速度仍有优化空间。

[![Build Status](https://secure.travis-ci.org/PHP-FFMpeg/PHP-FFMpeg.png?branch=master)](http://travis-ci.org/PHP-FFMpeg/PHP-FFMpeg)

[![SensioLabsInsight](https://insight.sensiolabs.com/projects/607f3111-e2d7-44e8-8bcc-54dd64521983/big.png)](https://insight.sensiolabs.com/projects/607f3111-e2d7-44e8-8bcc-54dd64521983)

使用 FFmpeg / AVConv 转换音视频文件的面向对象 PHP 库。

更多音视频格式可参考 [PHP FFMpeg extras](https://github.com/alchemy-fr/PHP-FFMpeg-Extras)。

## 使用前须知

### 运行方式

使用本库前需要安装 FFmpeg 和 FFProbe。将这两个可执行文件加入系统 PATH 后，本库可自动检测；也可以在创建实例时显式配置它们的路径。

Windows 用户需要自行安装 FFmpeg 和 FFProbe，并配置 PATH 或可执行文件路径。

### 已知问题

- 在 [libav](http://libav.org/) 0.8 中，同时使用旋转与缩放可能生成损坏的输出。该问题已在 libav 9 中修复。

## 安装

推荐通过 [Composer](https://getcomposer.org) 安装：

```bash
$ composer require turbo/php-ffmpeg
```

## PHP 兼容性与测试

目标运行版本为 PHP 7.4–8.4，依赖由 Composer 按当前 PHP 版本解析。
GitHub Actions 覆盖这六个版本，并分别验证最低和最新可安装依赖。

```bash
composer update
composer test
```

X264 默认音频编码器保持 `libfaac`；使用默认配置时，FFmpeg 必须提供该编码器。
测试显式使用 `aac`，以适配常见 FFmpeg 安装。

`alchemy/binary-driver` 5.2 仍会在 PHP 8.1+ 输出部分弃用提示；测试保留这些提示，
但不将弃用提示转换成异常。将弃用提示转换成异常的应用需要另外处理此上游依赖。
旧 Silex 1 服务提供器保留为可选集成，不属于 PHP 7.4–8.4 的测试支持范围；
未安装 Silex 时，其四项测试会跳过。功能测试需要 FFmpeg、FFProbe；HTTP 探测测试通过本机临时 HTTP 服务提供测试文件，不依赖外部媒体网站。

`TimeCode::toSeconds()` 现在保留百分之一秒精度，返回浮点数；依赖整数返回值的调用方应显式取整。
`saveFromDifferentCodecs()` 支持纯音频、无音轨视频和带音轨视频；输入必须具有一致的流布局。
纯音频输出会忽略输入的视频流。拼接视频仍需调用方统一尺寸、帧率等参数。

## 基本用法

```php

require 'vendor/autoload.php';

$ffmpeg = FFMpeg\FFMpeg::create();
$video = $ffmpeg->open('video.mpg');
$video
    ->filters()
    ->resize(new FFMpeg\Coordinate\Dimension(320, 240))
    ->synchronize();
$video
    ->frame(FFMpeg\Coordinate\TimeCode::fromSeconds(10))
    ->save('frame.jpg');
$video
    ->save(new FFMpeg\Format\Video\X264(), 'export-x264.mp4')
    ->save(new FFMpeg\Format\Video\WMV(), 'export-wmv.wmv')
    ->save(new FFMpeg\Format\Video\WebM(), 'export-webm.webm');
```

## API 文档

以下文档介绍主要 API。更详细的行为和参数说明可参阅源码中的注释。

### FFMpeg

`FFMpeg\FFMpeg` 是操作媒体的主要入口，使用静态方法 `FFMpeg\FFMpeg::create` 创建实例：

```php
$ffmpeg = FFMpeg\FFMpeg::create();
```

本库会自动检测 ffmpeg 和 ffprobe。也可以通过配置数组显式指定可执行文件路径，并传入 `Psr\Log\LoggerInterface` 实例记录执行日志。

```php
$ffmpeg = FFMpeg\FFMpeg::create(array(
    'ffmpeg.binaries'  => '/opt/local/ffmpeg/bin/ffmpeg',
    'ffprobe.binaries' => '/opt/local/ffmpeg/bin/ffprobe',
    'timeout'          => 3600, // 底层进程的超时时间（秒）
    'ffmpeg.threads'   => 12,   // FFmpeg 使用的线程数
), $logger);
```

### 打开与操作媒体

`FFMpeg\FFMpeg` 根据 URI 创建媒体对象。URI 可以指向本地文件、HTTP 资源，或 FFmpeg 支持的其他资源。

可通过以下命令查看当前 FFmpeg 支持的协议：

```
ffmpeg -protocols
```

使用 `FFMpeg\FFMpeg::open` 打开资源：

```php
$ffmpeg->open('video.mpeg');
```

打开资源后会得到 `FFMpeg\Media\Audio` 或 `FFMpeg\Media\Video` 对象。还可以从视频中提取 `FFMpeg\Media\Frame` 对象。

### 视频

`FFMpeg\Media\Video` 支持转码、更换编码器、提取音频或视频流，以及提取视频帧。

##### 转码

调用 `FFMpeg\Media\Video::save` 并传入实现 `FFMpeg\Format\FormatInterface` 的格式对象，即可转码视频。

音频与视频码率均在格式对象上设置。

```php
$format = new FFMpeg\Format\Video\X264();
$format->on('progress', function ($video, $format, $percentage) {
    echo "$percentage % transcoded";
});

$format
    ->setKiloBitrate(1000)
    ->setAudioChannels(2)
    ->setAudioKiloBitrate(256);

$video->save($format, 'video.avi');
```

支持实时监听转码进度，详见下方的“输出格式”说明。

##### 提取图片

使用 `FFMpeg\Media\Video::frame` 提取指定时间点的视频帧。

以下示例返回第 42 秒对应的 `FFMpeg\Media\Frame` 实例。时间点由 `FFMpeg\Coordinate\TimeCode` 表示，详见下方的“坐标与时间”说明。

```php
$frame = $video->frame(FFMpeg\Coordinate\TimeCode::fromSeconds(42));
$frame->save('image.jpg');
```

可以通过以下滤镜从视频中提取多张图片：

```php
$video
    ->filters()
    ->extractMultipleFrames(FFMpeg\Filters\Video\ExtractMultipleFramesFilter::FRAMERATE_EVERY_10SEC, '/path/to/destination/folder/')
    ->synchronize();

$video
    ->save(new FFMpeg\Format\Video\X264(), '/path/to/new/file');
```

默认保存为 `jpg` 图片。

使用 `setFrameFileType` 可以修改图片格式：

```php
$frameFileType = 'jpg'; // 可选 'jpg'、'jpeg' 或 'png'
$filter = new ExtractMultipleFramesFilter($frameRate, $destinationFolder);
$filter->setFrameFileType($frameFileType);

$video->addFilter($filter);
```

##### m3u8

输出路径以 `.m3u8` 结尾时，会自动启用 HLS 参数，目标分片时长为 5 秒，播放列表保留所有分片。请先创建输出目录。

```php
$format = new FFMpeg\Format\Video\X264();
$video->save($format, '/path/to/output/index.m3u8');
```

##### 裁剪

使用 `clip()` 指定裁剪起点和可选时长。该方法修改并返回当前媒体对象。

```php
$clip = $video->clip(FFMpeg\Coordinate\TimeCode::fromSeconds(30), FFMpeg\Coordinate\TimeCode::fromSeconds(15));
$clip->save(new FFMpeg\Format\Video\X264(), 'video.avi');
```

裁剪方法接受两个参数：

- `$start`：`FFMpeg\Coordinate\TimeCode` 实例，指定裁剪起点。
- `$duration`：可选的 `FFMpeg\Coordinate\TimeCode` 实例，指定裁剪时长。

裁剪后仍可应用视频滤镜，例如缩放：

```php
$clip = $video->clip(FFMpeg\Coordinate\TimeCode::fromSeconds(30), FFMpeg\Coordinate\TimeCode::fromSeconds(15));
$clip->filters()->resize(new FFMpeg\Coordinate\Dimension(320, 240), FFMpeg\Filters\Video\ResizeFilter::RESIZEMODE_INSET, true);
$clip->save(new FFMpeg\Format\Video\X264(), 'video.avi');
```

##### 生成波形图

使用 `FFMpeg\Media\Audio::waveform` 生成音频波形图。

该方法返回 `FFMpeg\Media\Waveform` 实例。前两个参数可指定宽度与高度，第三个参数为十六进制颜色字符串数组。

输出文件必须使用 PNG 扩展名。

```php
$waveform = $audio->waveform(640, 120, array('#00FF00'));
$waveform->save('waveform.png');
```

如需从视频生成波形图，先提取音频：

```php
// 打开视频文件
$video = $ffmpeg->open( 'video.mp4' );

// 设置音频输出格式
$audio_format = new FFMpeg\Format\Audio\Mp3();

// 将音频提取为 MP3 文件
$video->save($audio_format, 'audio.mp3');

// 打开音频文件
$audio = $ffmpeg->open( 'audio.mp3' );

// 生成波形图
$waveform = $audio->waveform();
$waveform->save( 'waveform.png' );
```

##### 滤镜

使用 `FFMpeg\Media\Video::addFilter` 添加滤镜。视频对象同时接受音频和视频滤镜。

可以自行实现滤镜，也可以通过 `FFMpeg\Media\Video::filters` 使用内置滤镜。

滤镜支持链式调用：

```php
$video
    ->filters()
    ->resize($dimension, $mode, $useStandards)
    ->framerate($framerate, $gop)
    ->synchronize();
```

###### 旋转

将视频旋转至指定角度：

```php
$video->filters()->rotate($angle);
```

`$angle` 必须使用以下常量之一：

- `FFMpeg\Filters\Video\RotateFilter::ROTATE_90`：顺时针旋转 90°。
- `FFMpeg\Filters\Video\RotateFilter::ROTATE_180`：旋转 180°。
- `FFMpeg\Filters\Video\RotateFilter::ROTATE_270`：逆时针旋转 90°。

###### 缩放

将视频缩放到指定尺寸：

```php
$video->filters()->resize($dimension, $mode, $useStandards);
```

缩放滤镜接受三个参数：

- `$dimension`：`FFMpeg\Coordinate\Dimension` 实例。
- `$mode`：`FFMpeg\Filters\Video\ResizeFilter::RESIZEMODE_*` 常量之一。
- `$useStandards`：布尔值，是否强制使用最接近的标准宽高比。

若需要非标准宽高比，可以使用填充滤镜，将画面放入指定尺寸的黑边区域：

```php
$video->filters()->pad($dimension);
```

填充滤镜接受一个参数：

- `$dimension`：`FFMpeg\Coordinate\Dimension` 实例。

应用滤镜后需要保存输出：

```php
$video->save(new FFMpeg\Format\Video\X264(), $new_file);
```

###### 水印

使用指定图片为视频添加水印：

```php
$video
    ->filters()
    ->watermark($watermarkPath, array(
        'position' => 'relative',
        'bottom' => 50,
        'right' => 50,
    ));
```

水印滤镜接受两个参数：

- `$watermarkPath`：水印图片路径。
- `$coordinates`：定义水印位置的数组。可以使用上例中的相对定位，也可以使用以下绝对定位：

```php
$video
    ->filters()
    ->watermark($watermarkPath, array(
        'position' => 'absolute',
        'x' => 1180,
        'y' => 620,
    ));
```

###### 帧率

修改视频帧率：

```php
$video->filters()->framerate($framerate, $gop);
```

帧率滤镜接受两个参数：

- `$framerate`：`FFMpeg\Coordinate\FrameRate` 实例。
- `$gop`：整数形式的 [GOP（图像组）](https://wikipedia.org/wiki/Group_of_pictures) 值。

###### 音视频同步

同步音频与视频：

某些容器的时间延迟可能导致输出音画不同步，可使用此滤镜处理。

```php
$video->filters()->synchronize();
```

###### 裁剪

从指定时间点裁剪视频：

```php
$video->filters()->clip(FFMpeg\Coordinate\TimeCode::fromSeconds(30), FFMpeg\Coordinate\TimeCode::fromSeconds(15));
```

裁剪滤镜接受两个参数：

- `$start`：`FFMpeg\Coordinate\TimeCode` 实例，指定裁剪起点。
- `$duration`：可选的 `FFMpeg\Coordinate\TimeCode` 实例，指定裁剪时长。

###### 画面裁切

根据起点和目标宽高裁切画面：

```php
$video->filters()->crop(new FFMpeg\Coordinate\Point("t*100", 0, true), new FFMpeg\Coordinate\Dimension(200, 600));
```

该滤镜接受两个参数：

- `$point`：`FFMpeg\Coordinate\Point` 实例，指定裁切起点。
- `$dimension`：`FFMpeg\Coordinate\Dimension` 实例，指定输出尺寸。

### 音频

`FFMpeg\Media\Audio` 支持音频转码和更换编码器。

##### 转码

调用 `FFMpeg\Media\Audio::save` 并传入实现 `FFMpeg\Format\FormatInterface` 的格式对象，即可转码音频。

音频码率在音频格式对象上设置，单位为 kb/s。

```php
$ffmpeg = FFMpeg\FFMpeg::create();
$audio = $ffmpeg->open('track.mp3');

$format = new FFMpeg\Format\Audio\Flac();
$format->on('progress', function ($audio, $format, $percentage) {
    echo "$percentage % transcoded";
});

$format
    ->setAudioChannels(2)
    ->setAudioKiloBitrate(256);

$audio->save($format, 'track.flac');
```

支持实时监听转码进度，详见下方的“输出格式”说明。

##### 滤镜

使用 `FFMpeg\Media\Audio::addFilter` 添加滤镜。音频对象仅接受音频滤镜。

可以自行实现滤镜，也可以通过 `FFMpeg\Media\Audio::filters` 使用内置滤镜。

##### 裁剪

从指定时间点裁剪音频：

```php
$audio->filters()->clip(FFMpeg\Coordinate\TimeCode::fromSeconds(30), FFMpeg\Coordinate\TimeCode::fromSeconds(15));
```

###### 元数据

传入键值对数组，为音频添加元数据。不传参数时，会移除输入文件的元数据和视频流。支持的字段包括 `title`、`artist`、`album`、`composer`、`track`、`year`、`description` 和 `artwork`。

```php
$audio->filters()->addMetadata(["title" => "Some Title", "track" => 1]);

// 移除音频文件中的元数据和视频流
$audio->filters()->addMetadata();
```

为音频文件添加封面：

```php
$audio->filters()->addMetadata(["artwork" => "/path/to/image/file.jpg"]);
```
历史兼容性说明：FFmpeg 3.2.2 的此封面用法仅支持 MP3 输出。

###### 重采样

对音频重新采样：

```php
$audio->filters()->resample($rate);
```

重采样滤镜接受一个参数：

- `$rate`：有效的音频采样率，整数。

#### 视频帧

视频帧是视频在某个时间点的图像，提取方式见上方说明。

使用 `FFMpeg\Media\Frame::save` 保存视频帧：

```php
$frame->save('target.jpg');
```

该方法的第二个参数为可选布尔值。设为 `true` 时使用精确定位，但执行时间更长。

#### GIF

GIF 是从视频片段中提取的动画图像。

使用 `FFMpeg\Media\Gif::save` 保存 GIF 文件：

```php
$video = $ffmpeg->open( '/path/to/video' );
$video
    ->gif(FFMpeg\Coordinate\TimeCode::fromSeconds(2), new FFMpeg\Coordinate\Dimension(640, 480), 3)
    ->save($new_file);
```

`Video::gif()` 的第三个参数是可选的动画时长；不指定时会生成静态 GIF。

#### 拼接

拼接功能可将多个输入文件合并成一个音频或视频文件。

根据输入文件的编码情况选择拼接方法：

- 输入编码一致时，使用 `FFMpeg\Media\Concat::saveFromSameCodecs`，配合流复制可提高速度。
- 输入编码不同时，使用 `FFMpeg\Media\Concat::saveFromDifferentCodecs`，并指定输出格式。

前一种方法可以沿用输入编码；后一种方法可通过格式对象选择输出编码。

`saveFromDifferentCodecs()` 支持纯音频、无音轨视频和带音轨视频；所有输入必须具有一致的流布局。纯音频输出会忽略输入的视频流。拼接视频仍需调用方统一尺寸、帧率等参数。

两种方法都需要传入文件路径数组。

拼接编码一致的视频：

```php
// 创建视频对象时，必须传入有效的视频文件路径。
// 可以使用待拼接视频中的任意一个文件。
$video = $ffmpeg->open( '/path/to/video' );
$video
    ->concat(array('/path/to/video1', '/path/to/video2'))
    ->saveFromSameCodecs('/path/to/new_file', TRUE);
```

`saveFromSameCodecs()` 的布尔参数设为 `true` 时使用流复制，可显著减少处理时间。

拼接编码不同的视频：

```php
// 创建视频对象时，必须传入有效的视频文件路径。
// 可以使用待拼接视频中的任意一个文件。
$video = $ffmpeg->open( '/path/to/video' );

$format = new FFMpeg\Format\Video\X264();
$format->setAudioCodec("libmp3lame");

$video
    ->concat(array('/path/to/video1', '/path/to/video2'))
    ->saveFromDifferentCodecs($format, '/path/to/new_file');
```

更多细节可参阅 FFmpeg 的[拼接说明](https://trac.ffmpeg.org/wiki/Concatenate)、[concat 解复用器](https://ffmpeg.org/ffmpeg-formats.html#concat-1)和[流复制文档](https://ffmpeg.org/ffmpeg.html#Stream-copy)。

#### 输出格式

格式对象实现 `FFMpeg\Format\FormatInterface`。视频输出使用 `FFMpeg\Format\VideoInterface`，音频输出使用 `FFMpeg\Format\AudioInterface`。

格式还可以实现 `FFMpeg\Format\ProgressableInterface`，提供实时转码进度。

内置格式已通过事件提供进度信息：

```php
$format = new FFMpeg\Format\Video\X264();
$format->on('progress', function ($video, $format, $percentage) {
    echo "$percentage % transcoded";
});

$video->save($format, 'video.avi');
```

事件回调可以使用任意有效的 callable。

##### 添加额外参数

可以通过视频格式对象为编码命令添加额外参数。

`setAdditionalParameters()` 接受数组：

```php
$format = new FFMpeg\Format\Video\X264();
$format->setAdditionalParameters(array('foo', 'bar'));
$video->save($format, 'video.avi');
```

##### 自定义输出格式

可以继承抽象类 `FFMpeg\Format\Video\DefaultVideo` 或 `FFMpeg\Format\Audio\DefaultAudio`，并实现所需的方法：

```php
class CustomWMVFormat extends FFMpeg\Format\Video\DefaultVideo
{
    public function __construct($audioCodec = 'wmav2', $videoCodec = 'wmv2')
    {
        $this
            ->setAudioCodec($audioCodec)
            ->setVideoCodec($videoCodec);
    }

    public function supportBFrames()
    {
        return false;
    }

    public function getAvailableAudioCodecs()
    {
        return array('wmav2');
    }

    public function getAvailableVideoCodecs()
    {
        return array('wmv2');
    }
}
```

#### 坐标与时间

FFMpeg 使用以下类型表示空间与时间：

- `FFMpeg\Coordinate\AspectRatio`：宽高比。
- `FFMpeg\Coordinate\Dimension`：尺寸。
- `FFMpeg\Coordinate\FrameRate`：帧率。
- `FFMpeg\Coordinate\Point`：坐标点，自 v0.10.0 起支持动态表达式。
- `FFMpeg\Coordinate\TimeCode`：时间码。

### FFProbe

`FFMpeg\FFMpeg` 内部使用 `FFMpeg\FFProbe` 探测媒体。也可以单独使用 FFProbe 提取媒体元数据。

```php
$ffprobe = FFMpeg\FFProbe::create();
$ffprobe
    ->streams('/path/to/video/mp4') // 提取媒体流信息
    ->videos()                      // 筛选视频流
    ->first()                       // 返回第一个视频流
    ->get('codec_name');            // 返回 codec_name 属性
```

```php
$ffprobe = FFMpeg\FFProbe::create();
$ffprobe
    ->format('/path/to/video/mp4') // 提取文件信息
    ->get('duration');             // 返回 duration 属性
```

### 验证媒体文件

自 0.10.0 起，可通过本库的 FFProbe 封装验证媒体文件：

```php
$ffprobe = FFMpeg\FFProbe::create();
$ffprobe->isValid('/path/to/file/to/check'); // 返回布尔值
```

## 集成 Silex 微框架

旧 Silex 1 集成为可选功能，不在 PHP 7.4–8.4 的测试支持范围内。安装相应依赖后，可注册服务提供器：

```php
$app = new Silex\Application();
$app->register(new FFMpeg\FFMpegServiceProvider());

$video = $app['ffmpeg']->open('video.mpeg');
```

可用配置如下：

```php
$app->register(new FFMpeg\FFMpegServiceProvider(), array(
    'ffmpeg.configuration' => array(
        'ffmpeg.threads'   => 4,
        'ffmpeg.timeout'   => 300,
        'ffmpeg.binaries'  => '/opt/local/ffmpeg/bin/ffmpeg',
        'ffprobe.timeout'  => 30,
        'ffprobe.binaries' => '/opt/local/ffmpeg/bin/ffprobe',
    ),
    'ffmpeg.logger' => $logger,
));
```

## 许可证

本项目使用 [MIT 许可证](http://opensource.org/licenses/MIT)。

测试音乐：“Favorite Secrets”，作者 Waylon Thornton。
来源：Free Music Archive。
许可：[CC BY NC SA](http://creativecommons.org/licenses/by-nc-sa/3.0/us/)。

测试音乐：“Siesta”，作者 Jahzzar。
来源：Free Music Archive。
许可：[CC BY SA](https://creativecommons.org/licenses/by-sa/3.0/)。
