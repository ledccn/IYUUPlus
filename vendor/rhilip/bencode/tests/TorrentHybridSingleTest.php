<?php

include_once 'traits/TorrentFileCommonTrait.php';

use Rhilip\Bencode\TorrentFile;
use PHPUnit\Framework\TestCase;

class TorrentHybridSingleTest extends TestCase
{
    use TorrentFileCommonTrait;

    protected $protocol = TorrentFile::PROTOCOL_HYBRID;
    protected $fileMode = TorrentFile::FILEMODE_SINGLE;

    protected $infoHashs = [
        TorrentFile::PROTOCOL_V1 => 'be2a86eff99608a56c506157dd5c9bc8779aa81d',
        TorrentFile::PROTOCOL_V2 => 'fd0e265c50a080759b61e7a66cf9c9a00af0256815e96a4c3564f733127dda46'
    ];
}
