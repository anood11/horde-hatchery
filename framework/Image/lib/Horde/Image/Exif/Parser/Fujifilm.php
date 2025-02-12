<?php
/**
 *
 */

/**
 *   Exifer
 *   Extracts EXIF information from digital photos.
 *
 *   Copyright � 2003 Jake Olefsky
 *   http://www.offsky.com/software/exif/index.php
 *   jake@olefsky.com
 *
 *   Please see exif.php for the complete information about this software.
 *
 *   ------------
 *
 *  This program is free software; you can redistribute it and/or modify it under the terms of
 *    the GNU General Public License as published by the Free Software Foundation; either version 2
 *    of the License, or (at your option) any later version.
 *
 *   This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
 *   without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *   See the GNU General Public License for more details. http://www.gnu.org/copyleft/gpl.html
 */
class Horde_Image_Exif_Parser_Fujifilm extends Horde_Image_Exif_Parser_Base
{
    /**
     * Looks up the name of the tag for the MakerNote (Depends on Manufacturer)
     */
    static protected function _lookupTag($tag) {

        switch($tag) {
            case "0000": $tag = "Version";break;
            case "1000": $tag = "Quality";break;
            case "1001": $tag = "Sharpness";break;
            case "1002": $tag = "WhiteBalance";break;
            case "1003": $tag = "Color";break;
            case "1004": $tag = "Tone";break;
            case "1010": $tag = "FlashMode";break;
            case "1011": $tag = "FlashStrength";break;
            case "1020": $tag = "Macro";break;
            case "1021": $tag = "FocusMode";break;
            case "1030": $tag = "SlowSync";break;
            case "1031": $tag = "PictureMode";break;
            case "1032": $tag = "Unknown";break;
            case "1100": $tag = "ContinuousTakingBracket";break;
            case "1200": $tag = "Unknown";break;
            case "1300": $tag = "BlurWarning";break;
            case "1301": $tag = "FocusWarning";break;
            case "1302": $tag = "AEWarning";break;

            default: $tag = "unknown:".$tag;break;
        }

        return $tag;
    }

    /**
     *
     * @param $type
     * @param $tag
     * @param $intel
     * @param $data
     * @return unknown_type
     */
    static protected function _formatData($type, $tag, $intel, $data)
    {
        if($type=="ASCII") {


        } else if($type=="URATIONAL" || $type=="SRATIONAL") {
            $data = bin2hex($data);
            if($intel==1) $data = Horde_Image_Exif::intel2Moto($data);
            $top = hexdec(substr($data,8,8));
            $bottom = hexdec(substr($data,0,8));
            if($bottom!=0) $data=$top/$bottom;
            else if($top==0) $data = 0;
            else $data=$top."/".$bottom;

            if($tag=="1011") { //FlashStrength
                $data=$data." EV";
            }

        } else if($type=="USHORT" || $type=="SSHORT" || $type=="ULONG" || $type=="SLONG" || $type=="FLOAT" || $type=="DOUBLE") {
            $data = bin2hex($data);
            if($intel==1) $data = Horde_Image_Exif::intel2Moto($data);
            $data=hexdec($data);

            if($tag=="1001") { //Sharpness
                if($data == 1) $data = _("Soft");
                else if($data == 2) $data = _("Soft");
                else if($data == 3) $data = _("Normal");
                else if($data == 4) $data = _("Hard");
                else if($data == 5) $data = _("Hard");
                else $data = _("Unknown").": ".$data;
            }
            if($tag=="1002") { //WhiteBalance
                if($data == 0) $data = _("Auto");
                else if($data == 256) $data = _("Daylight");
                else if($data == 512) $data = _("Cloudy");
                else if($data == 768) $data = _("DaylightColor-fluorescence");
                else if($data == 769) $data = _("DaywhiteColor-fluorescence");
                else if($data == 770) $data = _("White-fluorescence");
                else if($data == 1024) $data = _("Incandescense");
                else if($data == 3840) $data = _("Custom");
                else $data = _("Unknown").": ".$data;
            }
            if($tag=="1003") { //Color
                if($data == 0) $data = _("Chroma Saturation Normal(STD)");
                else if($data == 256) $data = _("Chroma Saturation High");
                else if($data == 512) $data = _("Chroma Saturation Low(ORG)");
                else $data = _("Unknown: ").$data;
            }
            if($tag=="1004") { //Tone
                if($data == 0) $data = _("Contrast Normal(STD)");
                else if($data == 256) $data = _("Contrast High(HARD)");
                else if($data == 512) $data = _("Contrast Low(ORG)");
                else $data = _("Unknown: ").$data;
            }
            if($tag=="1010") { //FlashMode
                if($data == 0) $data = _("Auto");
                else if($data == 1) $data = _("On");
                else if($data == 2) $data = _("Off");
                else if($data == 3) $data = _("Red-Eye Reduction");
                else $data = _("Unknown: ").$data;
            }
            if($tag=="1020") { //Macro
                if($data == 0) $data = _("Off");
                else if($data == 1) $data = _("On");
                else $data = _("Unknown: ").$data;
            }
            if($tag=="1021") { //FocusMode
                if($data == 0) $data = _("Auto");
                else if($data == 1) $data = _("Manual");
                else $data = _("Unknown: ").$data;
            }
            if($tag=="1030") { //SlowSync
                if($data == 0) $data = _("Off");
                else if($data == 1) $data = _("On");
                else $data = _("Unknown: ").$data;
            }
            if($tag=="1031") { //PictureMode
                if($data == 0) $data = _("Auto");
                else if($data == 1) $data = _("Portrait");
                else if($data == 2) $data = _("Landscape");
                else if($data == 4) $data = _("Sports");
                else if($data == 5) $data = _("Night");
                else if($data == 6) $data = _("Program AE");
                else if($data == 256) $data = _("Aperture Prority AE");
                else if($data == 512) $data = _("Shutter Priority");
                else if($data == 768) $data = _("Manual Exposure");
                else $data = _("Unknown: ").$data;
            }
            if($tag=="1100") { //ContinuousTakingBracket
                if($data == 0) $data = _("Off");
                else if($data == 1) $data = _("On");
                else $data = _("Unknown: ").$data;
            }
            if($tag=="1300") { //BlurWarning
                if($data == 0) $data = _("No Warning");
                else if($data == 1) $data = _("Warning");
                else $data = _("Unknown: ").$data;
            }
            if($tag=="1301") { //FocusWarning
                if($data == 0) $data = _("Auto Focus Good");
                else if($data == 1) $data = _("Out of Focus");
                else $data = _("Unknown: ").$data;
            }
            if($tag=="1302") { //AEWarning
                if($data == 0) $data = _("AE Good");
                else if($data == 1) $data = _("Over Exposure");
                else $data = _("Unknown: ").$data;
            }
        } else if($type=="UNDEFINED") {



        } else {
            $data = bin2hex($data);
            if($intel==1) $data = Horde_Image_Exif::intel2Moto($data);
        }

        return $data;
    }

    /**
     *
     * @param $block
     * @param $result
     * @return unknown_type
     */
    static public function parse($block, &$result)
    {
        $intel=1;

        $model = $result['IFD0']['Model'];

        $place=8; //current place
        $offset=8;


        $num = bin2hex(substr($block,$place,4));$place+=4;
        if($intel==1) $num = Horde_Image_Exif::intel2Moto($num);
        $result['SubIFD']['MakerNote']['Offset'] = hexdec($num);

            //Get number of tags (2 bytes)
        $num = bin2hex(substr($block,$place,2));$place+=2;
        if($intel==1) $num = Horde_Image_Exif::intel2Moto($num);
        $result['SubIFD']['MakerNote']['MakerNoteNumTags'] = hexdec($num);

        //loop thru all tags  Each field is 12 bytes
        for($i=0;$i<hexdec($num);$i++) {

                //2 byte tag
            $tag = bin2hex(substr($block,$place,2));$place+=2;
            if($intel==1) $tag = Horde_Image_Exif::intel2Moto($tag);
            $tag_name = self::_lookupTag($tag);

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
                $data = substr($block,hexdec($value)-$offset,$bytesofdata*2);
            }
            $formated_data = self::_formatData($type,$tag,$intel,$data);
            $result['SubIFD']['MakerNote'][$tag_name] = $formated_data;
        }

    }

}