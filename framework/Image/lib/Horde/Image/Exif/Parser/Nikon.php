<?php
/**
 *
 */

/*
    Exifer
    Extracts EXIF information from digital photos.

    Copyright � 2003 Jake Olefsky
    http://www.offsky.com/software/exif/index.php
    jake@olefsky.com

    Please see exif.php for the complete information about this software.

    ------------

    This program is free software; you can redistribute it and/or modify it under the terms of
    the GNU General Public License as published by the Free Software Foundation; either version 2
    of the License, or (at your option) any later version.

    This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
    without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
    See the GNU General Public License for more details. http://www.gnu.org/copyleft/gpl.html
*/
class Horde_Image_Exif_Parser_Nikon extends Horde_Image_Exif_Parser_Base
{
    /**
     *
     * @param $tag
     * @param $model
     * @return unknown_type
     */
    static protected function _lookupTag($tag,$model)
    {
        if($model==0) {
            switch($tag) {
                case "0003": $tag = "Quality";break;
                case "0004": $tag = "ColorMode";break;
                case "0005": $tag = "ImageAdjustment";break;
                case "0006": $tag = "CCDSensitivity";break;
                case "0007": $tag = "WhiteBalance";break;
                case "0008": $tag = "Focus";break;
                case "0009": $tag = "Unknown2";break;
                case "000a": $tag = "DigitalZoom";break;
                case "000b": $tag = "Converter";break;

                default: $tag = "unknown:".$tag;break;
            }
        } else if($model==1) {
            switch($tag) {
                case "0002": $tag = "ISOSetting";break;
                case "0003": $tag = "ColorMode";break;
                case "0004": $tag = "Quality";break;
                case "0005": $tag = "Whitebalance";break;
                case "0006": $tag = "ImageSharpening";break;
                case "0007": $tag = "FocusMode";break;
                case "0008": $tag = "FlashSetting";break;
                case "0009": $tag = "FlashMode";break;
                case "000b": $tag = "WhiteBalanceFine";break;
                case "000f": $tag = "ISOSelection";break;
                case "0013": $tag = "ISOSelection2";break;
                case "0080": $tag = "ImageAdjustment";break;
                case "0081": $tag = "ToneCompensation";break;
                case "0082": $tag = "Adapter";break;
                case "0083": $tag = "LensType";break;
                case "0084": $tag = "LensInfo";break;
                case "0085": $tag = "ManualFocusDistance";break;
                case "0086": $tag = "DigitalZoom";break;
                case "0087": $tag = "FlashUsed";break;
                case "0088": $tag = "AFFocusPosition";break;
                case "008d": $tag = "ColorMode";break;
                case "0090": $tag = "LightType";break;
                case "0094": $tag = "Saturation";break;
                case "0095": $tag = "NoiseReduction";break;
                case "0010": $tag = "DataDump";break;

                default: $tag = "unknown:".$tag;break;
            }
        }

        return $tag;
    }

    /**
     *
     * @param $type
     * @param $tag
     * @param $intel
     * @param $model
     * @param $data
     * @return unknown_type
     */
    static protected function _formatData($type,$tag,$intel,$model,$data)
    {
        if ($type != "ASCII" && ($type=="URATIONAL" || $type=="SRATIONAL")) {
            $data = bin2hex($data);
            if($intel==1) $data = Horde_Image_Exif::intel2Moto($data);
            $top = hexdec(substr($data,8,8));
            $bottom = hexdec(substr($data,0,8));
            if($bottom!=0) $data=$top/$bottom;
            else if($top==0) $data = 0;
            else $data=$top."/".$bottom;

                     if($tag=="0085" && $model==1) { //ManualFocusDistance
                $data=$data." m";
            }
            if($tag=="0086" && $model==1) { //DigitalZoom
                $data=$data."x";
            }
            if($tag=="000a" && $model==0) { //DigitalZoom
                $data=$data."x";
            }
        } else if($type=="USHORT" || $type=="SSHORT" || $type=="ULONG" || $type=="SLONG" || $type=="FLOAT" || $type=="DOUBLE") {
            $data = bin2hex($data);
            if($intel==1) $data = Horde_Image_Exif::intel2Moto($data);
            $data=hexdec($data);

            if($tag=="0003" && $model==0) { //Quality
                if($data == 1) $data = _("VGA Basic");
                else if($data == 2) $data = _("VGA Normal");
                else if($data == 3) $data = _("VGA Fine");
                else if($data == 4) $data = _("SXGA Basic");
                else if($data == 5) $data = _("SXGA Normal");
                else if($data == 6) $data = _("SXGA Fine");
                else $data = _("Unknown").": ".$data;
            }
            if($tag=="0004" && $model==0) { //Color
                if($data == 1) $data = _("Color");
                else if($data == 2) $data = _("Monochrome");
                else $data = _("Unknown").": ".$data;
            }
            if($tag=="0005" && $model==0) { //Image Adjustment
                if($data == 0) $data = _("Normal");
                else if($data == 1) $data = _("Bright+");
                else if($data == 2) $data = _("Bright-");
                else if($data == 3) $data = _("Contrast+");
                else if($data == 4) $data = _("Contrast-");
                else $data = _("Unknown").": ".$data;
            }
            if($tag=="0006" && $model==0) { //CCD Sensitivity
                if($data == 0) $data = "ISO-80";
                else if($data == 2) $data = "ISO-160";
                else if($data == 4) $data = "ISO-320";
                else if($data == 5) $data = "ISO-100";
                else $data = _("Unknown").": ".$data;
            }
            if($tag=="0007" && $model==0) { //White Balance
                if($data == 0) $data = _("Auto");
                else if($data == 1) $data = _("Preset");
                else if($data == 2) $data = _("Daylight");
                else if($data == 3) $data = _("Incandescense");
                else if($data == 4) $data = _("Flourescence");
                else if($data == 5) $data = _("Cloudy");
                else if($data == 6) $data = _("SpeedLight");
                else $data = _("Unknown").": ".$data;
            }
            if($tag=="000b" && $model==0) { //Converter
                if($data == 0) $data = _("None");
                else if($data == 1) $data = _("Fisheye");
                else $data = _("Unknown").": ".$data;
            }
        } else if($type=="UNDEFINED") {

            if($tag=="0001" && $model==1) { //Unknown (Version?)
                $data=$data/100;
            }
            if($tag=="0088" && $model==1) { //AF Focus Position
                $temp = _("Center");
                $data = bin2hex($data);
                $data = str_replace("01","Top",$data);
                $data = str_replace("02","Bottom",$data);
                $data = str_replace("03","Left",$data);
                $data = str_replace("04","Right",$data);
                $data = str_replace("00","",$data);
                if(strlen($data)==0) $data = $temp;
            }

        } else {
            $data = bin2hex($data);
            if($intel==1) $data = Horde_Image_Exif::intel2Moto($data);

            if($tag=="0083" && $model==1) { //Lens Type
                    $data = hexdec(substr($data,0,2));
                if($data == 0) $data = _("AF non D");
                else if($data == 1) $data = _("Manual");
                else if($data == 2) $data = "AF-D or AF-S";
                else if($data == 6) $data = "AF-D G";
                else if($data == 10) $data = "AF-D VR";
                else $data = _("Unknown").": ".$data;
            }
            if($tag=="0087" && $model==1) { //Flash type
                    $data = hexdec(substr($data,0,2));
                if($data == 0) $data = _("Did Not Fire");
                else if($data == 4) $data = _("Unknown");
                else if($data == 7) $data = _("External");
                else if($data == 9) $data = _("On Camera");
                else $data = _("Unknown").": ".$data;
            }
        }

        return $data;
    }

    /**
     *
     * @param $block
     * @param $result
     * @return unknown_type
     */
    static public function parse($block,&$result)
    {
        if($result['Endien']=="Intel") $intel=1;
        else $intel=0;

        $model = $result['IFD0']['Model'];

        //these 6 models start with "Nikon".  Other models dont.
        if($model=="E700\0" || $model=="E800\0" || $model=="E900\0" || $model=="E900S\0" || $model=="E910\0" || $model=="E950\0") {
            $place=8; //current place
            $model = 0;

            //Get number of tags (2 bytes)
            $num = bin2hex(substr($block,$place,2));$place+=2;
            if($intel==1) $num = Horde_Image_Exif::intel2Moto($num);
            $result['SubIFD']['MakerNote']['MakerNoteNumTags'] = hexdec($num);

            //loop thru all tags  Each field is 12 bytes
            for($i=0;$i<hexdec($num);$i++) {
                //2 byte tag
                $tag = bin2hex(substr($block,$place,2));$place+=2;
                if($intel==1) $tag = Horde_Image_Exif::intel2Moto($tag);
                $tag_name = self::_lookupTag($tag, $model);

                //2 byte type
                $type = bin2hex(substr($block,$place,2));$place+=2;
                if($intel==1) $type = Horde_Image_Exif::intel2Moto($type);
                self::_lookupType($type,$size);

                //4 byte count of number of data units
                $count = bin2hex(substr($block,$place,4));$place+=4;
                if($intel==1) $count = Horde_Image_Exif::intel2Moto($count);
                $bytesofdata = $size*hexdec($count);

                //4 byte value of data or pointer to data
                $value = substr($block,$place,4);$place+=4;

                //if tag is 0002 then its the ASCII value which we know is at 140 so calc offset
                //THIS HACK ONLY WORKS WITH EARLY NIKON MODELS
                if($tag=="0002") $offset = hexdec($value)-140;
                if($bytesofdata<=4) {
                    $data = $value;
                } else {
                    $value = bin2hex($value);
                    if($intel==1) $value = Horde_Image_Exif::intel2Moto($value);
                    $data = substr($block,hexdec($value)-$offset,$bytesofdata*2);
                }
                $formated_data = self::_formatData($type,$tag,$intel,$model,$data);
                $result['SubIFD']['MakerNote'][$tag_name] = $formated_data;
            }

        } else {
            $place=0;//current place
            $model = 1;

            $nikon = substr($block,$place,8);$place+=8;
            $endien = substr($block,$place,4);$place+=4;

            //2 bytes of 0x002a
            $tag = bin2hex(substr($block,$place,2));$place+=2;

            //Then 4 bytes of offset to IFD0 (usually 8 which includes all 8 bytes of TIFF header)
            $offset = bin2hex(substr($block,$place,4));$place+=4;
            if($intel==1) $offset = Horde_Image_Exif::intel2Moto($offset);
            if(hexdec($offset)>8) $place+=$offset-8;

            //Get number of tags (2 bytes)
            $num = bin2hex(substr($block,$place,2));$place+=2;
            if($intel==1) $num = Horde_Image_Exif::intel2Moto($num);

            //loop thru all tags  Each field is 12 bytes
            for($i=0;$i<hexdec($num);$i++) {
                //2 byte tag
                $tag = bin2hex(substr($block,$place,2));$place+=2;
                if($intel==1) $tag = Horde_Image_Exif::intel2Moto($tag);
                $tag_name = self::_lookupTag($tag, $model);

                //2 byte type
                $type = bin2hex(substr($block,$place,2));$place+=2;
                if($intel==1) $type = Horde_Image_Exif::intel2Moto($type);
                self::_lookupType($type,$size);

                //4 byte count of number of data units
                $count = bin2hex(substr($block,$place,4));$place+=4;
                if($intel==1) $count = Horde_Image_Exif::intel2Moto($count);
                $bytesofdata = $size*hexdec($count);

                //4 byte value of data or pointer to data
                $value = substr($block,$place,4);$place+=4;

                if($bytesofdata<=4) {
                    $data = $value;
                } else {
                    $value = bin2hex($value);
                    if($intel==1) $value = Horde_Image_Exif::intel2Moto($value);
                    $data = substr($block,hexdec($value)+hexdec($offset)+2,$bytesofdata);
                }
                $formated_data = self::_formatData($type,$tag,$intel,$model,$data);
                $result['SubIFD']['MakerNote'][$tag_name] = $formated_data;
            }
        }

    }

}