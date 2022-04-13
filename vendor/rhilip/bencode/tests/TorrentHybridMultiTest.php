<?php

include_once 'traits/TorrentFileCommonTrait.php';

use Rhilip\Bencode\TorrentFile;
use PHPUnit\Framework\TestCase;

class TorrentHybridMultiTest extends TestCase
{
    use TorrentFileCommonTrait;

    protected $protocol = TorrentFile::PROTOCOL_HYBRID;
    protected $fileMode = TorrentFile::FILEMODE_MULTI;

    protected $infoHashs = [
        TorrentFile::PROTOCOL_V1 => '1e2dbc73590ba3bea3cee6e3053d98da86e6c842',
        TorrentFile::PROTOCOL_V2 => '3f6fb45188917a8aed604ba7f399843f7891f68748bef89b7692465656ca6076'
    ];
}
