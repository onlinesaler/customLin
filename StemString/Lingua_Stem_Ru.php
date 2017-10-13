<?php

class Lingua_Stem_Ru
{
    private $VERSION = "0.02";
    private $unset_predlog = true; // удал¤ть пердлоги?
    private $Stem_Caching = 0;
    private $Stem_Cache = array();
    private $VOWEL = '/аеиоуыэю¤/u';
    private $PERFECTIVEGROUND = '/((ив|ивши|ившись|ыв|ывши|ывшись)|((?<=[а¤])(в|вши|вшись)))$/u';
    private $REFLEXIVE = '/(с[¤ь])$/u';
    private $ADJECTIVE = '/(ее|ие|ые|ое|ими|ыми|ей|ий|ый|ой|ем|им|ым|ом|его|ого|еых|ую|юю|а¤|¤¤|ою|ею)$/u';
    private $PARTICIPLE = '/((ивш|ывш|ующ)|((?<=[а¤])(ем|нн|вш|ющ|щ)))$/u';
    private $VERB = '/((ила|ыла|ена|ейте|уйте|ите|или|ыли|ей|уй|ил|ыл|им|ым|ены|ить|ыть|ишь|ую|ю)|((?<=[а¤])(ла|на|ете|йте|ли|й|л|ем|н|ло|но|ет|ют|ны|ть|ешь|нно)))$/u';
    private $NOUN = '/(а|ев|ов|ие|ье|е|и¤ми|¤ми|ами|еи|ии|и|ией|ей|ой|ий|й|и|ы|ь|ию|ью|ю|и¤|ь¤|¤)$/u';
    private $RVRE = '/^(.*?[аеиоуыэю¤])(.*)$/u';
    private $DERIVATIONAL = '/[^аеиоуыэю¤][аеиоуыэю¤]+[^аеиоуыэю¤]+[аеиоуыэю¤].*(?<=о)сть?$/u';
    private $PREDLOG = '/(^|\s)(и|дл¤|в|на|под|из|с|по)(\s|$)/u';

    private function s(&$s, $re, $to) {
        $orig = $s;
        $s = preg_replace($re, $to, $s);
        return $orig !== $s;
    }

    private function m($s, $re) {
        return preg_match($re, $s);
    }
    public function stem_string($words) {

        $word=explode(' ',$words);

        for ($i=0;$i<count($word);$i++) {
            if ($this->unset_predlog === TRUE) $word[$i] = preg_replace($this->PREDLOG, '', $word[$i]);

            $word[$i]=$this->stem_word($word[$i]);

            if(empty($word[$i])) unset($word[$i]);
        }
        return implode(' ',$word); //if you need return array change on -> return $word;
    }

    private function stem_word($word) {

        //mb_regex_encoding( 'UTF-8' );
        //mb_internal_encoding( 'UTF-8' );

        $word = strtolower($word);
        $word= str_ireplace('Є', 'е', $word);

        # Check against cache of stemmed words
        if ($this->Stem_Caching && isset($this->Stem_Cache[$word])) {
            return $this->Stem_Cache[$word];
        }

        $stem = $word;
        do {
            if (!preg_match($this->RVRE, $word, $p)) break;
            $start = $p[1];
            $RV = $p[2];
            if (!$RV) break;

            # Step 1
            if (!$this->s($RV, $this->PERFECTIVEGROUND, '')) {
                $this->s($RV, $this->REFLEXIVE, '');

                if ($this->s($RV, $this->ADJECTIVE, '')) {
                    $this->s($RV, $this->PARTICIPLE, '');
                } else {
                    if (!$this->s($RV, $this->VERB, '')) $this->s($RV, $this->NOUN, '');
                }
            }

            # Step 2
            $this->s($RV, '/и$/', '');

            # Step 3
            if ($this->m($RV, $this->DERIVATIONAL)) $this->s($RV, '/ость?$/', '');

            # Step 4
            if (!$this->s($RV, '/ь$/', '')) {
                $this->s($RV, '/ейше?/', '');
                $this->s($RV, '/нн$/', 'н');
            }

            $stem = $start.$RV;
        } while(false);
        if ($this->Stem_Caching) $this->Stem_Cache[$word] = $stem;
        return $stem;
    }

    private function stem_caching($parm_ref) {
        $caching_level = @$parm_ref['-level'];
        if ($caching_level) {
            if (!$this->m($caching_level, '/^[012]$/')) {
                die(__CLASS__ . "::stem_caching() - Legal values are '0','1' or '2'. '$caching_level' is not a legal value");
            }
            $this->Stem_Caching = $caching_level;
        }
        return $this->Stem_Caching;
    }

    public function clear_stem_cache() {
        $this->Stem_Cache = array();
    }
}