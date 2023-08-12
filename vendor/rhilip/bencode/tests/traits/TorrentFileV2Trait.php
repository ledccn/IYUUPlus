<?php

use Rhilip\Bencode\ParseException;

trait TorrentFileV2Trait
{
    public function testFileTreeNotExist() {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Checking Dictionary missing key: ');

        $this->torrent->unsetInfoField('file tree');
        $this->torrent->parse();
    }

    public function testFileTreeNotArray() {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Invalid entry type in dictionary, ');

        $this->torrent->setInfoField('file tree', 'someString');
        $this->torrent->parse();
    }

    public function testPieceLayersNotExist() {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Checking Dictionary missing key: ');

        $this->torrent->unsetRootField('piece layers');
        $this->torrent->parse();
    }

    public function testPieceLayersNotArray() {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Invalid entry type in dictionary, ');

        $this->torrent->setRootField('piece layers', 'someString');
        $this->torrent->parse();
    }

    public function testInvalidNodePiecesRootLength() {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Invalid pieces_root length.');

        $fileTree = $this->torrent->getInfoField('file tree');
        $fileTree['file1.dat']['']['pieces root'] .= 'a';

        $this->torrent->setInfoField('file tree', $fileTree);
        $this->torrent->parse();
    }

    public function testInvalidNodeLength() {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Invalid entry type in dictionary,');

        $fileTree = $this->torrent->getInfoField('file tree');
        $fileTree['file1.dat']['']['length'] = '1234566';

        $this->torrent->setInfoField('file tree', $fileTree);
        $this->torrent->parse();
    }

    public function testNodePiecesRootNotExistInPieceLayer() {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Pieces not exist in piece layers');

        $fileTree = $this->torrent->getInfoField('file tree');
        $fileTree['file1.dat']['']['pieces root'] = hash('sha256', 'adfadsfasd',true);

        $this->torrent->setInfoField('file tree', $fileTree);
        $this->torrent->parse();
    }
}
