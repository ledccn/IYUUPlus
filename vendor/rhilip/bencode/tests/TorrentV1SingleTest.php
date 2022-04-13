<?php

include_once 'traits/TorrentFileCommonTrait.php';
include_once 'traits/TorrentFileV1Trait.php';

use Rhilip\Bencode\TorrentFile;
use PHPUnit\Framework\TestCase;

class TorrentV1SingleTest extends TestCase
{
    use TorrentFileCommonTrait;
    use TorrentFileV1Trait;

    protected $protocol = TorrentFile::PROTOCOL_V1;
    protected $fileMode = TorrentFile::FILEMODE_SINGLE;

    protected $infoHashs = [
        TorrentFile::PROTOCOL_V1 => 'd0e710431bed8cb4b1860b9a7a40a20df8de8266',
        TorrentFile::PROTOCOL_V2 => null
    ];
}
