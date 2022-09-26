<?php

include_once 'traits/TorrentFileCommonTrait.php';
include_once 'traits/TorrentFileV2Trait.php';

use Rhilip\Bencode\TorrentFile;
use PHPUnit\Framework\TestCase;

class TorrentV2SingleTest extends TestCase
{
    use TorrentFileCommonTrait;
    use TorrentFileV2Trait;

    protected $protocol = TorrentFile::PROTOCOL_V2;
    protected $fileMode = TorrentFile::FILEMODE_SINGLE;

    protected $infoHashs = [
        TorrentFile::PROTOCOL_V1 => null,
        TorrentFile::PROTOCOL_V2 => 'a58e747f0ce2c2073c6fd635d4afdd5c6162574d6c9184318f884f553c3ed65b'
    ];
}
