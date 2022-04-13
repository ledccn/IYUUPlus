<?php

include_once 'traits/TorrentFileCommonTrait.php';
include_once 'traits/TorrentFileV2Trait.php';

use Rhilip\Bencode\TorrentFile;
use PHPUnit\Framework\TestCase;

class TorrentV2MultiTest extends TestCase
{
    use TorrentFileCommonTrait;
    use TorrentFileV2Trait;

    protected $protocol = TorrentFile::PROTOCOL_V2;
    protected $fileMode = TorrentFile::FILEMODE_MULTI;

    protected $infoHashs = [
        TorrentFile::PROTOCOL_V1 => null,
        TorrentFile::PROTOCOL_V2 => '832d96b4f8b422aa75f8d40975b1a408154bc1a2bdffccf7b689386cde125a30'
    ];
}
