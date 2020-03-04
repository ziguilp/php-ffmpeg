<?php

/*
 * This file is part of PHP-FFmpeg.
 *
 * (c) Alchemy <info@alchemy.fr>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FFMpeg\Format\Audio;

/**
 * The M3u8 audio format
 */
class M3u8 extends DefaultAudio
{
    /**
     * 设置文件保存目录
     */
    private $file_dir = null;

    /**
     * 自动加密
     */
    private $aufo_encrypt = false;

    /** @var boolean */
    private $bframesSupport = true;

    protected $m3u8ExtraParams = [
        // '-c:v' => 'libx264',
        // '-c:a' => 'aac',
        '-hls_time' => 5,
        '-hls_list_size' => 0,
        '-hls_segment_filename' => "%05d.ts",
    ];

    /** @var integer */
    private $passes = 2;

    public function __construct($file_dir, $aufo_encrypt = false)
    {
        if( substr($file_dir, -1) != "/" )
        {
            $file_dir .= "/";
        }

        if(!is_dir($file_dir)) {
            mkdir($file_dir, 511, true);
        }
        
        $this->file_dir = $file_dir;
        $this->setTsName($this->file_dir.'%05d.ts');
        if($aufo_encrypt){
            $this->createKeyFile();
        }
    }

    /**
     * {@inheritDoc}
     */
    public function supportBFrames()
    {
        return $this->bframesSupport;
    }

    /**
     * @param $support
     *
     * @return M3u8
     */
    public function setBFramesSupport($support)
    {
        $this->bframesSupport = $support;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getAvailableAudioCodecs()
    {
        return array('aac');
    }

    /**
     * {@inheritDoc}
     */
    public function getAvailableVideoCodecs()
    {
        return array('libx264');
    }

    /**
     * @param $passes
     *
     * @return M3u8
     */
    public function setPasses($passes)
    {
        $this->passes = $passes;
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getPasses()
    {
        return $this->passes;
    }

    /**
     * @return int
     */
    public function getModulus()
    {
        return 2;
    }

    /**
     * {@inheritDoc}
     */
    public function getExtraParams()
    {
        $res = [];
        foreach ($this->m3u8ExtraParams as $key => $value) {
            $res[] = $key;
            $res[] = $value;
        }
        return $res;
    }

    /**
     * 设置加密文件
     */
    public function setKeyInfoFile($filePath){
        if(is_file($filePath)){
            $this->m3u8ExtraParams["-hls_key_info_file"] = $filePath;
        }
        return $this;
    }

    /**
     * 设置ts时长
     */
    public function setTsTime(int $time = 5){
        $this->m3u8ExtraParams["-hls_time"] = $time;
        return $this;
    }

    /**
     * 设置ts文件命名格式
     */
    public function setTsName($name = "%05d.ts"){
        $this->m3u8ExtraParams["-hls_segment_filename"] = $name;
        return $this;
    }

    /**
     * 生成加密密钥
     */
    public function createKeyFile(){
        $path = $this->file_dir;
        $src_str = "0123456789abcdefghijklmnopqrstuvwxyz";
        $aes_key = substr(str_shuffle($src_str), 0, 16);
        $fp = fopen($path . "key.key", "w");
        fwrite($fp, $aes_key);
        fclose($fp);
        $keyinfo = "key.key\n" . $path . "key.key";
        $fp = fopen($path . "key_info", "w");
        fwrite($fp, $keyinfo);
        fclose($fp);
        return $this->setKeyInfoFile($path . "key_info"); 
    }

    /**
     * @return array
     */
    public function getAdditionalParameters(){

    }
}