<?php

use Rhilip\Bencode\ParseException;
use Rhilip\Bencode\TorrentFile;

trait TorrentFileV1Trait
{
    /** @var TorrentFile */
    private $torrent;

    public function testV1WithWrongPieces() {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Invalid pieces length');

        $this->torrent->setInfoField('pieces', $this->torrent->getRootField('pieces') . 'somestring');
        $this->torrent->parse();
    }
}
