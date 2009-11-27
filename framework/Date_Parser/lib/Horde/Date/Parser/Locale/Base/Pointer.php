<?php
class Horde_Date_Parser_Locale_Base_Pointer
{
    public $scanner = array(
        '/\bpast\b/' => 'past',
        '/\bfuture\b/' => 'future',
        '/\bin\b/' => 'future',
    );

    public function scan($tokens)
    {
        foreach ($tokens as &$token) {
            if ($t = $this->scanForAll($token)) {
                $token->tag('pointer', $t);
            }
        }
        return $tokens;
    }

    public function scanForAll($token)
    {
        foreach ($this->scanner as $scannerItem => $scannerTag) {
            if (preg_match($scannerItem, $token->word)) {
                return $scannerTag;
            }
        }
    }

}
