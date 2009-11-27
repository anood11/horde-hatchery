<?php
/**
 *
 */
class Horde_Image_Exif_Parser_Base
{
    /**
     *
     * @param $type
     * @param $size
     * @return unknown_type
     */
    static protected function _lookupType(&$type,&$size) {
        switch($type) {
            case '0001': $type = 'UBYTE'; $size=1; break;
            case '0002': $type = 'ASCII'; $size=1; break;
            case '0003': $type = 'USHORT'; $size=2; break;
            case '0004': $type = 'ULONG'; $size=4; break;
            case '0005': $type = 'URATIONAL'; $size=8; break;
            case '0006': $type = 'SBYTE'; $size=1; break;
            case '0007': $type = 'UNDEFINED'; $size=1; break;
            case '0008': $type = 'SSHORT'; $size=2; break;
            case '0009': $type = 'SLONG'; $size=4; break;
            case '000a': $type = 'SRATIONAL'; $size=8; break;
            case '000b': $type = 'FLOAT'; $size=4; break;
            case '000c': $type = 'DOUBLE'; $size=8; break;
            default: $type = 'error:'.$type; $size=0; break;
        }

        return $type;
    }

}