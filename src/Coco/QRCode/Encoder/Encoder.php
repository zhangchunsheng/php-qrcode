<?php
/*
 * PHP QR Code
 *
 * Based on libqrencode C library distributed under LGPL 2.1
 * Copyright (C) 2006, 2007, 2008, 2009 Kentaro Fukuchi <fukuchi@megaui.net>
 *
 * PHP QR Code is distributed under LGPL 3
 * Copyright (C) 2010 Dominik Dzienia <deltalab at poczta dot fm>
 *
 * Code modifications by Chunsheng Zhang <zhangchunsheng423@gmail.com>
 * Last update - 20.07.2020
 *
 */

namespace Coco\QRCode\Encoder;

// Encoding modes
define('QR_MODE_NUM', 0);
define('QR_MODE_AN', 1);
define('QR_MODE_8', 2);
define('QR_MODE_KANJI', 3);

// Levels of error correction.
define('QR_ECLEVEL_L', 0);
define('QR_ECLEVEL_M', 1);
define('QR_ECLEVEL_Q', 2);
define('QR_ECLEVEL_H', 3);

class Encoder {

    private $dataStr;
    private $dataStrLen;
    private $hint;
    private $pos;
    private $level;
    private $bstream = [];
    private $streams = [];
    private $maxLenlengths = [];
    private $lengthTableBits = [
        [10, 12, 14],
        [ 9, 11, 13],
        [ 8, 16, 16],
        [ 8, 10, 12]
    ];

    private $anTable = [
        -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1,
        -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1,
        36, -1, -1, -1, 37, 38, -1, -1, -1, -1, 39, 40, -1, 41, 42, 43,
        0,  1,  2,  3,  4,  5,  6,  7,  8,  9, 44, -1, -1, -1, -1, -1,
        -1, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24,
        25, 26, 27, 28, 29, 30, 31, 32, 33, 34, 35
    ];

    private $capacity = [
        [0, [0, 0, 0, 0]],
        [26, [7, 10, 13, 17]], // 1
        [44, [10, 16, 22, 28]],
        [70, [15, 26, 36, 44]],
        [100, [20, 36, 52, 64]],
        [134, [26, 48, 72, 88]], // 5
        [172, [36, 64, 96, 112]],
        [196, [40, 72, 108, 130]],
        [242, [48, 88, 132, 156]],
        [292, [60, 110, 160, 192]],
        [346, [72, 130, 192, 224]], //10
        [404, [80, 150, 224, 264]],
        [466, [96, 176, 260, 308]],
        [532, [104, 198, 288, 352]],
        [581, [120, 216, 320, 384]],
        [655, [132, 240, 360, 432]], //15
        [733, [144, 280, 408, 480]],
        [815, [168, 308, 448, 532]],
        [901, [180, 338, 504, 588]],
        [991, [196, 364, 546, 650]],
        [1085, [224, 416, 600, 700]], //20
        [1156, [224, 442, 644, 750]],
        [1258, [252, 476, 690, 816]],
        [1364, [270, 504, 750, 900]],
        [1474, [300, 560, 810, 960]],
        [1588, [312, 588, 870, 1050]], //25
        [1706, [336, 644, 952, 1110]],
        [1828, [360, 700, 1020, 1200]],
        [1921, [390, 728, 1050, 1260]],
        [2051, [420, 784, 1140, 1350]],
        [2185, [450, 812, 1200, 1440]], //30
        [2323, [480, 868, 1290, 1530]],
        [2465, [510, 924, 1350, 1620]],
        [2611, [540, 980, 1440, 1710]],
        [2761, [570, 1036, 1530, 1800]],
        [2876, [570, 1064, 1590, 1890]], //35
        [3034, [600, 1120, 1680, 1980]],
        [3196, [630, 1204, 1770, 2100]],
        [3362, [660, 1260, 1860, 2220]],
        [3532, [720, 1316, 1950, 2310]],
        [3706, [750, 1372, 2040, 2430]] //40
    ];

    function __construct(int $level) {
        $this->level = $level;
    }

    private function lookAnTable($c) {
        return (($c > 90) ? -1 : $this->anTable[$c]);
    }

    private function lengthIndicator($mode, $version) {
        if ($version <= 9) {
            $l = 0;
        } else if ($version <= 26) {
            $l = 1;
        } else {
            $l = 2;
        }

        return $this->lengthTableBits[$mode][$l];
    }

    private function encodeModeNum($size, $data) {
        $this->bstream[] = [4, 1];
        $this->bstream[] = [$this->maxLenlengths[QR_MODE_NUM], $size];

        foreach(array_chunk($data, 3) as $c) {
            $l = count($c);
            $c = array_pad($c, -3, 48);
            $val  = ($c[0] - 48) * 100 + ($c[1] - 48) * 10 + ($c[2] - 48);
            $this->bstream[] = [($l * 3) + 1, $val];
        }
    }

    private function encodeModeAn($size, $data) {
        $this->bstream[] = [4, 2];
        $this->bstream[] = [$this->maxLenlengths[QR_MODE_AN], $size];

        foreach(array_chunk($data, 2) as $c) {
            if (count($c) == 2) {
                $val = ($this->lookAnTable($c[0]) * 45) + $this->lookAnTable($c[1]);
                $this->bstream[] = [11, $val];
            } else {
                $val = $this->lookAnTable($c[0]);
                $this->bstream[] = [6, $val];
            }
        }
    }

    private function encodeMode8($size, $data) {
        $this->bstream[] = [4, 4];
        $this->bstream[] = [$this->maxLenlengths[QR_MODE_8], $size];

        foreach($data as $bit) {
            $this->bstream[] = [8, $bit];
        }
    }

    private function encodeModeKanji($size, $data) {
        if ($size & 1) {
            throw \Coco\QRCode\qrException::EncoderError('Invalid string length for Kanji');
        }

        $this->bstream[] = [4, 8];
        $this->bstream[] = [$this->maxLenlengths[QR_MODE_KANJI], ($size / 2)];

        for($i=0; $i<$size; $i+=2) {
            $val = ($data[$i] << 8) | $data[$i+1];
            if($val <= 40956) {
                $val -= 33088;
            } else {
                $val -= 49472;
            }

            $val = ($val & 255) + (($val >> 8) * 192);

            $this->bstream[] = [13, $val];
        }
    }

    private function estimateVersion($version) {
        $bits = 0;
        foreach($this->streams as $stream) {
            list($mode, $size, ) = $stream;
            switch($mode) {
                case QR_MODE_NUM:
                    $bits += ($size * 3) + 1 + intdiv($size, 3);
                    break;
                case QR_MODE_AN:
                    $bits += (int)($size / 2) * 11;
                    if($size & 1) {
                        $bits += 6;
                    }
                    break;
                case QR_MODE_8:
                    $bits += ($size * 8);
                    break;
                case QR_MODE_KANJI:
                    $bits += (int)($size / 2) * 13;
                    break;
            }

            $l = $this->lengthIndicator($mode, $version);
            $this->maxLenlengths[$mode] = $l;
            $m = 1 << $l;
            $num = (int)(($size + $m - 1) / $m);
            $bits += $num * (4 + $l);
        }

        return $this->getMinimumVersion($bits);
    }

    private function encodeStreams() {
        $version = 1;
        do {
            $prev = $version;
            $package = $this->estimateVersion($version);
            $version = $package[0];
        } while ($version > $prev);

        foreach($this->streams as $stream) {

            list($mode, $size, $data) = $stream;

            switch($mode) {
                case QR_MODE_NUM:
                    $this->encodeModeNum($size, $data);
                    break;
                case QR_MODE_AN:
                    $this->encodeModeAn($size, $data);
                    break;
                case QR_MODE_8:
                    $this->encodeMode8($size, $data);
                    break;
                case QR_MODE_KANJI:
                    $this->encodeModeKanji($size, $data);
                    break;
            }
        }

        $bits = array_pop($package);
        $word_bits = ($package[1] * 8) - $bits - 4;
        $words = $word_bits % 8;
        $padlen = intval($word_bits / 8);

        $this->bstream[] = [$words + 4, 0];

        if($padlen > 0) {
            for($i = 0 ; $i < $padlen ; $i += 2) {
                $this->bstream[] = [8, 236];
                $this->bstream[] = [8, 17];
            }
        }

        $package[] = $this->toByte();

        return $package;
    }

    private function getMinimumVersion($bits) {
        $size = (int)(($bits + 7) / 8);
        for($i=1; $i<= 40; $i++) { # QR_SPEC_VERSION_MAX = 40

            $ecc = $this->capacity[$i][1][$this->level];
            $dataLength = $this->capacity[$i][0] - $ecc;

            if($dataLength >= $size){
                $width = $i * 4 + 17;
                return [$i, $dataLength, $ecc, $width, $this->level, $bits];
            }
        }
    }

    private function toByte() {
        $dataStr = "";
        foreach($this->bstream as $d) {
            $dataStr .= str_pad(decbin($d[1]), $d[0], "0", STR_PAD_LEFT);
        }

        $data = [];
        foreach(str_split($dataStr, 8) as $val){
            $data[] = bindec($val);
        }

        return $data;
    }

    private function is_digit() {
        if ($this->pos >= $this->dataStrLen) {
            return false;
        }
        return ($this->dataStr[$this->pos] >= 48 && $this->dataStr[$this->pos] <= 57);
    }

    private function is_alnum() {
        if ($this->pos >= $this->dataStrLen) {
            return false;
        }
        return ($this->lookAnTable($this->dataStr[$this->pos]) >= 0);
    }

    private function is_kanji() {
        if ($this->pos + 1 < $this->dataStrLen) {
            $word = ($this->dataStr[$this->pos]) << 8 | $this->dataStr[$this->pos+1];
            if(($word >= 33088 && $word <= 40956) || ($word >= 57408 && $word <= 60351)) {
                return true;
            }
        }
        return false;
    }

    private function identifyMode() {
        switch (true) {
            case $this->is_digit():
                $mode = QR_MODE_NUM;
                break;
            case $this->is_alnum():
                $mode = QR_MODE_AN;
                break;
            case ($this->hint == QR_MODE_KANJI):
                # Kanji is not auto detected unless hinted but otherwise it breaks bulgarian chars and possibly others
                $mode = ($this->is_kanji()) ? QR_MODE_KANJI : QR_MODE_8;
                break;
            default:
                $mode = QR_MODE_8;
        }

        return $mode;
    }

    private function eatNum() {
        # the first pos was already identified
        $this->pos++;

        while($this->is_digit()) {
            $this->pos++;
        }
    }

    private function eatAn() {
        $this->pos++;

        while($this->is_alnum()) {
            $this->pos++;
        }
    }

    private function eatKanji() {
        $this->pos += 2;

        while($this->is_kanji()) {
            $this->pos += 2;
        }
    }

    private function eat8() {
        $this->pos++;

        while($this->pos < $this->dataStrLen) {
            switch($this->identifyMode()) {
                case QR_MODE_KANJI:
                    break 2;
                case QR_MODE_NUM:
                    $old_pos = $this->pos;
                    $this->eatNum();
                    if(($this->pos - $old_pos) > 3) {
                        $this->pos = $old_pos;
                        break 2;
                    }
                    break;
                case QR_MODE_AN:
                    $old_pos = $this->pos;
                    $this->eatAn();
                    if(($this->pos - $old_pos) > 5) {
                        $this->pos = $old_pos;
                        break 2;
                    }
                    break;
                default:
                    $this->pos++;
            }
        }
    }

    public function encodeString($text, $hint) {
        $this->dataStr = array_values(unpack('C*', $text));
        $this->dataStrLen = count($this->dataStr);

        if (($hint != QR_MODE_KANJI) && ($hint != -1)) {
            $this->streams[] = [$hint, $this->dataStrLen, $this->dataStr];
        } else {
            $this->hint = $hint;
            $this->pos = 0;

            while ($this->dataStrLen > $this->pos) {
                $prev = $this->pos;
                $mode = $this->identifyMode();

                switch ($mode) {
                    case QR_MODE_NUM:
                        $this->eatNum();
                        break;
                    case QR_MODE_AN:
                        $this->eatAn();
                        break;
                    case QR_MODE_KANJI:
                        $this->eatKanji();
                        break;
                    default:
                        $mode = QR_MODE_8;
                        $this->eat8();
                }

                $size = $this->pos - $prev;
                $this->streams[] = [$mode, $size, array_slice($this->dataStr, $prev, $size)];
            }
        }

        $package = $this->encodeStreams();
        return (new Mask($package))->get();
    }
}
