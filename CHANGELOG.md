# 更新日志

[简体中文](CHANGELOG.md) | [English](CHANGELOG.en.md)

本文件记录项目的重要变更。
本项目遵循[语义化版本规范](http://semver.org/)。

## [未发布][Unreleased]

### 修复

- TimeCode 比较保留小数秒精度，并修复舍入跨秒、跨分钟时的进位。
- 拼接时正确应用输出格式参数，并为纯音频、无音轨视频和带音轨视频生成对应的流映射。
- 拒绝流布局不一致的拼接输入，以及格式错误或分母为零的抽帧帧率。
- 合并全部视频滤镜时不再修改正在遍历的数组，避免遗漏滤镜。
- 进度监听正确处理未指定时长的裁剪，保留不足一秒的时长，并修正处理速率计算。
- 修正非方形像素的宽高比计算，包括分子或分母为 1 的 SAR。
- 为集合接口方法补充兼容的返回类型，并显式声明可空参数。
- 展开按优先级分组的滤镜时，避免 PHP 8 将数组键解释为命名参数。

### 新增

- README 和 CHANGELOG 默认使用中文，并同步提供英文版本。

- 新增 PHP 7.4–8.4 CI 矩阵，分别验证最低和最新可安装依赖，并补充回归测试。

- 新增拉取请求和问题模板。
- 支持 FFmpeg 3 的 `aac` 编码器。

### 变更

- 运行环境要求调整为 PHP >=7.4 且 <8.5，依赖要求调整为 BinaryDriver ^5.2、Doctrine Cache ^1.13 和 Temporary Filesystem ^3.0.1。
- 测试迁移至 PHPUnit 9.6；隔离可选的旧 Silex 集成，并从开发依赖中移除 Sami。
- `TimeCode::toSeconds()` 返回保留百分之一秒精度的浮点数。X264 默认音频编码器仍为 `libfaac`。

- 更新日志采用 [Keep a Changelog](http://keepachangelog.com/) 格式。

[0.7.0] - 2016-12-15
--------------------

- 支持 FFmpeg 3 的 aac 编码器（@Nek-）。
- 新增波形滤镜，用于生成音频波形图（@Romain）。

[0.6.1] - 2016-03-08
--------------------

- 支持 PHP 7，并增加对应测试。
- 清理未使用的代码（@haphan）。
- 整理 Composer 配置和测试，采用 PSR-4 测试自动加载。
- 支持 evenement v2.0。

[0.6.0] - 2016-01-30
--------------------

- `AbstractData::get` 不再抛出异常（@sujayjaju）。
- 新增画面裁切滤镜（@cangelis）。
- 修复水印功能（@sujayjaju）。

[0.5.1] - 2016-08-26
--------------------

- 修复视频宽高比计算（@nlegoff）。

[0.5.0] - 2014-08-12
--------------------

- 支持 WAV 和 AAC 音频格式（@MrHash）。
- 新增水印滤镜（@sylvainv）。
- 支持配置音频声道数（@SimonSimCity）。

[0.4.4] - 2016-12-17
--------------------

- 修复宽度和高度的提取。

[0.4.3] - 2013-02-12
--------------------

- 修复同时使用旋转与缩放滤镜的问题（#78）。

[0.4.2] - 2013-11-29
--------------------

- 新增旋转滤镜。
- 使用同步滤镜时移除 `time_start` 元数据。
- 移除对文件系统资源的限制。

[0.4.1] - 2013-11-26
--------------------

- 新增裁剪滤镜（@guimeira）。

[0.4.0] - 2013-10-21
--------------------

- 支持将视频转码为音频。
- 不兼容变更：新增 `FormatInterface::getPasses` 和 `FormatInterface::getExtraParams`。

[0.3.5] - 2013-10-21
--------------------

- 新增 Vorbis 音频格式（@jacobbudin）。
- 修复 #66：允许单遍编码。

[0.3.4] - 2013-09-05
--------------------

- 修复宽高比计算错误。

[0.3.3] - 2013-09-05
--------------------

- 新增 `Stream::getDimensions` 方法，便于提取视频尺寸。
- 新增 DisplayRatioFixer 视频帧滤镜。

[0.3.2] - 2013-08-08
--------------------

- 修复 Flash 和 HTML5 播放器中的音视频同步问题。

[0.3.1] - 2013-08-06
--------------------

- 允许 FFProbe 探测远程 URI。
- 修复 #47：`MediaTypeInterface::save` 根据编码器添加滤镜。
- 保存视频帧到目标文件时不再提示确认。

[0.3.0] - 2013-07-04
--------------------

- 完全重写本库，包含多项不兼容变更，请参阅文档。

[0.2.4] - 2013-05-10
--------------------

- 新增 `Video\ResizableInterface::getModulus` 方法，改善输出缩放（@retrojunk）。
- 修复音视频编码的超时设置（@xammep-ua）。

[0.2.3] - 2013-04-21
--------------------

- 为 FFMpeg 和 FFProbe 新增超时读取与设置方法。
- 支持通过 `FFMpeg::load` 和 `FFProbe::load` 的第二个参数设置超时。

[0.2.2] - 2013-02-11
--------------------

- 兼容 FFmpeg 1.1。
- 更新已弃用的选项（`-ab`、`-qscale` 和 `-b`）。
- 每次多遍编码使用独立的统计文件，修复 #20。
- 放宽依赖版本范围。

[0.2.1] - 2013-02-04
--------------------

- 使用正确的换行符解析 FFProbe 输出（@ak76）。
- 支持自定义进程超时（@pulse00）。
- 修复 `FFMpeg::extractImage` 的 `accurate` 选项。

[0.2.0] - 2012-12-13
--------------------

- 新增 HelperInterface，并支持实时进度（@pulse00）。
- 为 `FFMpeg::extractImage` 方法新增 `accurate` 选项。

0.1.0 - 2012-10-30
--------------------

- 首个稳定版本。

[Unreleased]: https://github.com/PHP-FFMpeg/PHP-FFMpeg/compare/0.6.1...HEAD
[0.6.1]: https://github.com/PHP-FFMpeg/PHP-FFMpeg/compare/0.6.0...0.6.1
[0.6.0]: https://github.com/PHP-FFMpeg/PHP-FFMpeg/compare/0.5.1...0.6.0
[0.5.1]: https://github.com/PHP-FFMpeg/PHP-FFMpeg/compare/0.5.0...0.5.1
[0.5.0]: https://github.com/PHP-FFMpeg/PHP-FFMpeg/compare/0.4.4...0.5.0
[0.4.4]: https://github.com/PHP-FFMpeg/PHP-FFMpeg/compare/0.4.3...0.4.4
[0.4.3]: https://github.com/PHP-FFMpeg/PHP-FFMpeg/compare/0.4.2...0.4.3
[0.4.2]: https://github.com/PHP-FFMpeg/PHP-FFMpeg/compare/0.4.1...0.4.2
[0.4.1]: https://github.com/PHP-FFMpeg/PHP-FFMpeg/compare/0.4.0...0.4.1
[0.4.0]: https://github.com/PHP-FFMpeg/PHP-FFMpeg/compare/0.3.5...0.4.0
[0.3.5]: https://github.com/PHP-FFMpeg/PHP-FFMpeg/compare/0.3.4...0.3.5
[0.3.4]: https://github.com/PHP-FFMpeg/PHP-FFMpeg/compare/0.3.3...0.3.4
[0.3.3]: https://github.com/PHP-FFMpeg/PHP-FFMpeg/compare/0.3.2...0.3.3
[0.3.2]: https://github.com/PHP-FFMpeg/PHP-FFMpeg/compare/0.3.1...0.3.2
[0.3.1]: https://github.com/PHP-FFMpeg/PHP-FFMpeg/compare/0.3.0...0.3.1
[0.3.0]: https://github.com/PHP-FFMpeg/PHP-FFMpeg/compare/0.2.4...0.3.0
[0.2.4]: https://github.com/PHP-FFMpeg/PHP-FFMpeg/compare/0.2.3...0.2.4
[0.2.3]: https://github.com/PHP-FFMpeg/PHP-FFMpeg/compare/0.2.2...0.2.3
[0.2.2]: https://github.com/PHP-FFMpeg/PHP-FFMpeg/compare/0.2.1...0.2.2
[0.2.1]: https://github.com/PHP-FFMpeg/PHP-FFMpeg/compare/0.2.0...0.2.1
[0.2.0]: https://github.com/PHP-FFMpeg/PHP-FFMpeg/compare/0.1.0...0.2.0
