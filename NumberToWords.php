<?php

/**
 * @author Michael Corrigan <mike@corrlabs.com>
 * NumberToWords 
 * This simple class will handle converting numbers to words in English including:
 *  - Currency
 *  - Degrees
 *  - Percents
 *  - Numbers
 *  - Ordinals
 * 
 * original concept: http://www.karlrixon.co.uk/writing/convert-numbers-to-words-with-php/
 */
class NumberToWords {
    
    // base 10 dictionary
    private $dictionary = array(
        0 => 'zero',
        1 => 'one',
        2 => 'two',
        3 => 'three',
        4 => 'four',
        5 => 'five',
        6 => 'six',
        7 => 'seven',
        8 => 'eight',
        9 => 'nine',
        10 => 'ten',
        11 => 'eleven',
        12 => 'twelve',
        13 => 'thirteen',
        14 => 'fourteen',
        15 => 'fifteen',
        16 => 'sixteen',
        17 => 'seventeen',
        18 => 'eighteen',
        19 => 'nineteen',
        20 => 'twenty',
        30 => 'thirty',
        40 => 'fourty',
        50 => 'fifty',
        60 => 'sixty',
        70 => 'seventy',
        80 => 'eighty',
        90 => 'ninety',
        100 => 'hundred',
        1000 => 'thousand',
        1000000 => 'million',
        1000000000 => 'billion',
        1000000000000 => 'trillion',
        1000000000000000 => 'quadrillion',
        1000000000000000000 => 'quintillion',
    );
    
    // irregular ordinals
    private $ordinal_dictionary = array(
        0 => 'zeroth',
        1 => 'first',
        2 => 'second',
        3 => 'third',
        5 => 'fifth',
        8 => 'eighth',
        12 => 'twelfth',
        20 => 'twentieth',
        30 => 'thirtieth',
        40 => 'fourtieth',
        50 => 'fiftieth',
        60 => 'sixtieth',
        70 => 'seventieth',
        80 => 'eightieth',
        90 => 'ninetieth',
        100 => 'hundredth',
        1000 => 'thousandth',
        1000000 => 'millionth',
        1000000000 => 'billionth',
        1000000000000 => 'trillionth',
        1000000000000000 => 'quadrillionth',
        1000000000000000000 => 'quintillionth',
    );
    
    // math dictionary (not currently in use)
    private $math_dictionary = array(
        '+' => 'add',
        '-' => 'subtract',
        '/' => 'divide',
        '*' => 'multiply',
        '%' => 'modulus',
        '>' => 'greater than',
        '<' => 'less than',
        '=' => 'equal',
    );
    
    public $hyphen      = '-';
    public $conjunction = 'and';
    public $separator   = ',';
    public $negative    = 'negative '; // or minus
    public $decimal     = 'point';
    public $percent     = 'percent';
    public $degrees     = 'degree';
    public $currency_type           = 'dollar';
    public $currency_fraction_type  = 'cent';
    public $uppercase_words         = TRUE; // will uc words except 'and'
    public $include_zero_dollars    = TRUE;
    public $include_zero_cents      = TRUE;

    const AUTOMATIC_MODE    = 'automatic_mode';
    const CURRENCY_MODE     = 'currency_mode';

    private $mode; 

    /**
     * Constructor
     */
    public function __construct() {
        $this->mode = self::AUTOMATIC_MODE;
    }

    /**
     * Sets the mode used during conversion
     * @param string $mode
     */
    public function setMode($mode) {
        $this->mode = $mode;
    }

    /**
     * Converts any number to words
     * @param float $number
     * @param string $mode (optional)
     * @return string
     */
    public function convertToWords($number, $mode = null) {
        
        if ($mode != null)
            $this->mode = $mode;
        
        // default vars to null
        $is_negative = FALSE;
        $string = $fraction = null;
        $unit_type = '';
        $support_plural_units = TRUE;
        
        // must be numeric
        if (!is_numeric($number)) {
            // strip chars that make this non-numeric
            
            // auto-detect currency and set mode (if at beginning of string)
            if (strpos($number, '$') == 0) {
                $this->mode = self::CURRENCY_MODE;
            }

            // handle simple percent (if at end of string)
            if (strpos($number, '%') == strlen($number) - 1) {
                $this->mode = self::AUTOMATIC_MODE;
                $unit_type .= $this->percent;
                $support_plural_units = FALSE;
            }

            // handle simple degrees (if at end of string)
            if (strpos($number, '°') == strlen($number) - 1) {
                $this->mode = self::AUTOMATIC_MODE;
                $unit_type .= $this->degrees;
            }

            // clean %, $, ï¿½, comma (,)
            $number = preg_replace('/[,\%\$°]/', '', $number);

            // if it's not clean now, it's not something we can handle
            if (!is_numeric($number)) {
                return false;
            }
        }

        // buffer overflow check
        if (($number >= 0 && (int) $number < 0) || (int) $number < 0 - PHP_INT_MAX) {
            trigger_error('convert only accepts numbers between -' . PHP_INT_MAX . ' and ' . PHP_INT_MAX, E_USER_WARNING);
            return false;
        }
        
        // check for negative number, if so store/remove negative and continue
        if ($number < 0) {
            $is_negative = TRUE;
            $number = abs($number);
        }
        
        if ($this->mode == self::CURRENCY_MODE){
            if ($number == (int)$number){
                $number .= '.00'; // force decimal by string
            }
        }
        
        // check for fraction, store it for later
        if (strpos($number, '.') !== FALSE) {
            // only apply rounding if we have a fraction
            if ($this->mode == self::CURRENCY_MODE && $number != (int) $number) {
                $number = round($number, 2); // we want .109 to round to .11 cents
            }
            list($number, $fraction) = explode('.', $number);
            if ($this->mode == self::CURRENCY_MODE && $fraction != null) {
                if (strlen($fraction) == 1){
                    $fraction *= 10; // we want .1 to become .10 (or 10 cents)
                }
            }
        }
        
        // if dealing in currency, add units (Dollars)
        if ($number != 0 || ($this->include_zero_dollars && $number == 0)){
            $string .= $this->convert($number);
            // add units in currency mode
            if ($this->mode == self::CURRENCY_MODE){
                $string .= ' ' . $this->currency_type.($number != 1 ? 's':'') . '';
            }
        }
        
        // handle fraction 
        if ($fraction !== null && is_numeric($fraction)) {
            if ($this->mode == self::CURRENCY_MODE) {
                // change mode to default so there is no unit type
                if (($fraction == 0 && $this->include_zero_cents) || $fraction != 0){
                    $before_mode = $this->mode;
                    $this->mode = self::AUTOMATIC_MODE;
                
                    // if string is not null, we want to add 'and'
                    if ($string != null)
                        $string .= " ".$this->conjunction." ";
                    
                    $string .= $this->convert($fraction);
                    $this->mode = $before_mode; // restore previous mode
                    $string .= ' ' . $this->currency_fraction_type.($fraction != 1 ? 's' : '');
                }
                
                if ($this->uppercase_words) $string = $this->ucwords($string);
                
                return $string;
            }
            
            if ($this->mode == self::AUTOMATIC_MODE) {
                $string .= " ".$this->decimal." "; // just output 'point'
            }
            
            $words = array();
            foreach (str_split((string) $fraction) as $number) {
                $words[] = $this->dictionary[$number];
            }
            $string .= implode(' ', $words);
        }
        
        // handle units, if required
        if ($unit_type != '' && $support_plural_units && ($number != 1 || $fraction != null))
            $unit_type .= 's';
        
        // apply last minute formatting
        $string = ($is_negative ? $this->negative : '') . $string . " " . $unit_type;
        
        // uppercase words if set
        if ($this->uppercase_words) $string = $this->ucwords($string);
        
        return trim($string);
    }
    
    /**
     * Print out date/time related words
     * @param string $date
     * @param string $pattern
     * @return string
     */
    public function convertDateToWords($date, $pattern = 'l, F jS, Y'){
        // get the time
        $time = strtotime($date);
        // adjust the pattern for testing adding non-interpreted characters
        // $pattern = "l, F \u jS, Y \h";
        $parts = preg_split('//', $pattern);
        $good_parts = array();
        for($i = 0; $i < count($parts); $i++){
            $part = $parts[$i];
            if ($part == "\\"){
                // this is an escaped character, get the character that follows as part
                $part = $parts[++$i]; // get next item as assignment
            }else{
                // track 'S' (st, nd, rd, th suffix), we need the item before (if item exists and is a number)
                // make it read first, second, third, fourth, fifth, sixth, seventh, eighth, nineth, tenth, eleventh, twelveth...
                if ($part == 'S' && $i > 0){
                    $prev_val = date($parts[$i-1], $time);
                    if (is_numeric($prev_val)){
                        // numbers with different rules
                        $part = $this->getNumberOrdinal($prev_val);
                        array_pop($good_parts); // remove last item
                        $good_parts[] = $part; // add this item
                        continue;
                    }
                }
                if (trim($part) != '' && $part != ',' && ($value = date($part, $time)) !== FALSE){
                    if ($value != ''){
                        // we have an equated value
                        if (is_numeric($value)){
                            // this a number, let's convert it to text
                            $good_parts[] = trim($this->convertToWords($value, self::AUTOMATIC_MODE));
                        }else{
                            // this is not a number, we don't alter it
                            $good_parts[] = $value;
                        }
                    }
                    continue;
                }
            }
            $good_parts[] = $part;
        }
        //echo var_export($good_parts, TRUE)."\n";
        return trim(implode('',$good_parts));
    }
    
    /**
     * Converts a number into the word-ordinal form
     * @param int $number
     * @return string
     */
    public function getNumberOrdinal($number){
        $number = (int) abs($number); // cannot have fractions or be negative

        // check if number given is in ordinal dictionary
        if (array_key_exists($number, $this->ordinal_dictionary)){
            // simple lookup in dictionary
            $ord = $this->ordinal_dictionary[$number];
        }else{
            // first remove all but the last two digits
            $n1 = $number % 100; 

            // remove all but last digit unless the number is in the teens, which all should be 'th'
            $n2 = ($n1 < 20 ? $number : $number % 10); 
            
            // check if digit less than 20 is in dictionary
            if (array_key_exists($n2, $this->ordinal_dictionary)){
                $ord = $this->ordinal_dictionary[$n2];
                if ($number > 20){
                    $words = $this->convertToWords($number);
                    // we need to account for a two-part number
                    $word_list = explode('-', $words);
                    $word_list[count($word_list)-1] = $ord;
                    $ord = implode('-', $word_list);
                }
            }else{
                // if not zero, use number straight across
                if ($n2 != 0){
                    $ord = $this->convert($number).'th';
                }
            }
        }
       
        // apply this to finished product, not ord
        if ($this->uppercase_words) $ord = $this->ucwords($ord);
        
        return $ord;
    }
    
    /**
     * Helper method to access converting almost all words to ucfirst
     * @param string $string
     * @return string
     */
    private function ucwords($string){
        return preg_replace_callback("/[a-zA-Z-]+/",array(&$this, 'limitedUcwords'),$string);
    }
    
    /**
     * Called by preg callback
     * @param string $match
     * @return string
     */
    private function limitedUcwords($match)
    {
        $exclude = array('and'); // list of words to not ucfirst
        if ( in_array(strtolower($match[0]),$exclude) ) return $match[0];
        return ucfirst($match[0]);
    }


    /**
     * Converts a number to words regardless of other formatting
     * @param int $number
     * @return boolean
     */
    private function convert($number) {
        $string = '';
        $number = (int)$number; // must be a whole number (no fraction, no strings)
        switch (true) {
            case $number < 21:
                $string = $this->dictionary[$number];
                break;
            case $number < 100:
                $tens = ((int) ($number / 10)) * 10;
                $units = $number % 10;
                $string = $this->dictionary[$tens];
                if ($units) {
                    $string .= $this->hyphen . $this->dictionary[$units];
                }
                break;
            case $number < 1000:
                $hundreds = $number / 100;
                $remainder = $number % 100;
                $string = $this->dictionary[$hundreds] . ' ' . $this->dictionary[100];
                if ($remainder) {
                    $string .= " ".$this->conjunction." ".$this->convert($remainder);
                }
                break;
            default:
                $baseUnit = pow(1000, floor(log($number, 1000)));
                $numBaseUnits = (int) ($number / $baseUnit);
                $remainder = $number % $baseUnit;
                $string = $this->convert($numBaseUnits) . ' ' . $this->dictionary[$baseUnit];
                if ($remainder) {
                    $string .= $remainder < 100 ? " ".$this->conjunction." " : $this->separator." ";
                    $string .= $this->convert($remainder);
                }
                break;
        }
        return $string;
    }

}

// TESTING
/*
$test_count = 0;
$passed_test_count = 0;
$n2w = new NumberToWords(); // not in use

echo '<pre>';

// raw number test
echo "Raw Number Tests\n";
assertEquals(0, "Zero");
assertEquals(-0, "Zero");
assertEquals(1, "One");
assertEquals(-2980.1230, "Negative Two Thousand, Nine Hundred and Eighty Point One Two Three");
assertEquals(120, "One Hundred and Twenty");

// percent test
echo "\nPercent Number Tests\n";
assertEquals('32%', 'Thirty-two Percent');
assertEquals('1%', 'One Percent');
assertEquals('1.2%', 'One Point Two Percent');
assertEquals('-13%', 'Negative Thirteen Percent');

// degree tests
echo "\nDegree Number Tests\n";
assertEquals('32ï¿½',  'Thirty-two Degrees');
assertEquals('1ï¿½',   'One Degree');
assertEquals('32.8ï¿½','Thirty-two Point Eight Degrees');
assertEquals('-32.5ï¿½','Negative Thirty-two Point Five Degrees');

// currency tests (with bills/cents on/off?)
echo "\nCurrency Number Tests\n";
assertEquals('$1','One Dollar and Zero Cents');
assertEquals('$1.506','One Dollar and Fifty-one Cents');
assertEquals('$1235.506','One Thousand, Two Hundred and Thirty-five Dollars and Fifty-one Cents');
assertEquals('$0','Zero Dollars and Zero Cents');
assertEquals('$0.0','Zero Dollars and Zero Cents');
assertEquals('$1.01','One Dollar and One Cent');
assertEquals('$1.1','One Dollar and Ten Cents');
assertEquals('$1.02','One Dollar and Two Cents');
assertEquals('$0.25','Zero Dollars and Twenty-five Cents');
assertEquals('$.25','Zero Dollars and Twenty-five Cents');
assertEquals('$.2','Zero Dollars and Twenty Cents');
assertEquals('$5.26','Five Dollars and Twenty-six Cents');
assertEquals('32,500.00','Thirty-two Thousand, Five Hundred Dollars and Zero Cents');
assertEquals('$325.00','Three Hundred and Twenty-five Dollars and Zero Cents');

// date tests
echo "\nDate Number Tests\n";
assertDateEquals('01/1/2000','Saturday, January First, Two Thousand');
assertDateEquals('01/2/2000','Sunday, January Second, Two Thousand');
assertDateEquals('01/3/2000','Monday, January Third, Two Thousand');
assertDateEquals('01/4/2000','Tuesday, January Fourth, Two Thousand');
assertDateEquals('01/5/2000','Wednesday, January Fifth, Two Thousand');
assertDateEquals('01/6/2000','Thursday, January Sixth, Two Thousand');
assertDateEquals('01/7/2000','Friday, January Seventh, Two Thousand');
assertDateEquals('01/8/2000','Saturday, January Eighth, Two Thousand');
assertDateEquals('01/9/2000','Sunday, January Nineth, Two Thousand');
assertDateEquals('01/10/2000','Monday, January Tenth, Two Thousand');
assertDateEquals('01/11/2000','Tuesday, January Eleventh, Two Thousand');
assertDateEquals('01/12/2000','Wednesday, January Twelfth, Two Thousand');
assertDateEquals('01/13/2000','Thursday, January Thirteenth, Two Thousand');
assertDateEquals('01/14/2000','Friday, January Fourteenth, Two Thousand');
assertDateEquals('01/15/2000','Saturday, January Fifteenth, Two Thousand');
assertDateEquals('01/16/2000','Sunday, January Sixteenth, Two Thousand');
assertDateEquals('01/17/2000','Monday, January Seventeenth, Two Thousand');
assertDateEquals('01/18/2000','Tuesday, January Eighteenth, Two Thousand');
assertDateEquals('01/19/2000','Wednesday, January Nineteenth, Two Thousand');
assertDateEquals('01/20/2000','Thursday, January Twentieth, Two Thousand');
assertDateEquals('01/21/2000','Friday, January Twenty-first, Two Thousand');
assertDateEquals('01/22/2000','Saturday, January Twenty-second, Two Thousand');
assertDateEquals('01/23/2000','Sunday, January Twenty-third, Two Thousand');
assertDateEquals('01/24/2000','Monday, January Twenty-fourth, Two Thousand');
assertDateEquals('01/25/2000','Tuesday, January Twenty-fifth, Two Thousand');
assertDateEquals('01/26/2000','Wednesday, January Twenty-sixth, Two Thousand');
assertDateEquals('01/27/2000','Thursday, January Twenty-seventh, Two Thousand');
assertDateEquals('01/28/2000','Friday, January Twenty-eighth, Two Thousand');
assertDateEquals('01/29/2000','Saturday, January Twenty-nineth, Two Thousand');
assertDateEquals('01/30/2000','Sunday, January Thirtieth, Two Thousand');
assertDateEquals('01/31/2000','Monday, January Thirty-first, Two Thousand');

assertDateEquals('03/03/2012','Saturday, March Third, Two Thousand and Twelve');
assertDateEquals('03/11/2012','Sunday, March Eleventh, Two Thousand and Twelve');
assertDateEquals('03/12/2012','Monday, March Twelfth, Two Thousand and Twelve');
assertDateEquals('03/15/2012','Thursday, March Fifteenth, Two Thousand and Twelve');
assertDateEquals('03/18/2012','Sunday, March Eighteenth, Two Thousand and Twelve');
assertDateEquals('03/20/2012','Tuesday, March Twentieth, Two Thousand and Twelve');
assertDateEquals('03/21/2012','Wednesday, March Twenty-first, Two Thousand and Twelve');
assertDateEquals('03/22/2012','Thursday, March Twenty-second, Two Thousand and Twelve');
assertDateEquals('03/23/2012','Friday, March Twenty-third, Two Thousand and Twelve');
assertDateEquals('03/25/2012','Sunday, March Twenty-fifth, Two Thousand and Twelve');
assertDateEquals('03/30/2012','Friday, March Thirtieth, Two Thousand and Twelve');
assertDateEquals('03/31/2012','Saturday, March Thirty-first, Two Thousand and Twelve');

// ordinal tests
echo "\nOrdinal Number Tests\n"; // should this stem off date method or be own method?
assertOrdinalEquals('1', 'First');
assertOrdinalEquals('2', 'Second');
assertOrdinalEquals('3', 'Third');
assertOrdinalEquals('4', 'Fourth');
assertOrdinalEquals('5', 'Fifth');
assertOrdinalEquals('6', 'Sixth');
assertOrdinalEquals('7', 'Seventh');
assertOrdinalEquals('8', 'Eighth');
assertOrdinalEquals('9', 'Nineth');
assertOrdinalEquals('11', 'Eleventh');
assertOrdinalEquals('12', 'Twelfth');
assertOrdinalEquals('13', 'Thirteenth');
assertOrdinalEquals('14', 'Fourteenth');
assertOrdinalEquals('15', 'Fifteenth');
assertOrdinalEquals('16', 'Sixteenth');
assertOrdinalEquals('17', 'Seventeenth');
assertOrdinalEquals('18', 'Eighteenth');
assertOrdinalEquals('19', 'Nineteenth');
assertOrdinalEquals('20', 'Twentieth');
assertOrdinalEquals('21', 'Twenty-first');
assertOrdinalEquals('22', 'Twenty-second');
assertOrdinalEquals('23', 'Twenty-third');
assertOrdinalEquals('24', 'Twenty-fourth');
assertOrdinalEquals('25', 'Twenty-fifth');
assertOrdinalEquals('26', 'Twenty-sixth');
assertOrdinalEquals('27', 'Twenty-seventh');
assertOrdinalEquals('28', 'Twenty-eighth');
assertOrdinalEquals('29', 'Twenty-nineth');
assertOrdinalEquals('30', 'Thirtieth');
assertOrdinalEquals('35', 'Thirty-fifth');
assertOrdinalEquals('87', 'Eighty-seventh');
assertOrdinalEquals('123', 'One Hundred and Twenty-third');
assertOrdinalEquals('-3', 'Third'); // disregard negatives
assertOrdinalEquals('1233', 'One Thousand, Two Hundred and Thirty-third');
assertOrdinalEquals('452013', 'Four Hundred and Fifty-two Thousand and Thirteenth');


echo "\nTime Number Tests\n"; // should this stem off date method or be own method?
// Goal: Ten o' Clock AM, April Eighth, Two Thousand and Three (in: 10:00 AM 04/08/2003)
// Goal: Ten Thirty-five PM, January First, One Hundred and Four  (in: 10:30 PM 01/01/104)
// identify ':' used with time placeholders (g,G,h,H,i,s)
// identify AM PM following digit
assertDateEquals('01/01/2000','Saturday, January First, Two Thousand', 'h:m:s');
assertDateEquals('01/01/2000','Saturday, January First, Two Thousand', 'h:m:i A');
assertDateEquals('01/01/2000','Saturday, January First, Two Thousand', 'h:m:i');

echo "\n\n<font color='gray'>$test_count total tests run.</font> <b>$passed_test_count</b> <font color='green'>PASSED</font>. <b>".($test_count-$passed_test_count)."</b> <font color='red'>FAILED</font>.";

echo '</pre>';

function assertEquals($val, $expected, $n2w = null){
    global $test_count;
    global $passed_test_count;
    $test_count++;
    if (is_null($n2w))
        $n2w = new NumberToWords();
    $_expected = $n2w->convertToWords($val);
    // handle output
    if ($_expected === $expected){
        echo "<font color='green'>PASS:</font> \t$val \t=\t $expected <br />";
        $passed_test_count++;
    }else
        echo "<font color='red'>FAIL:</font> \tGiven \"$val\" \n\tGot \"$_expected\" \n\tExpected \"$expected\" <br />";
}

function assertDateEquals($val, $expected, $pattern = null){
    global $test_count;
    global $passed_test_count;
    $test_count++;
    $n2w = new NumberToWords();
    if (!is_null($pattern))
        $_expected = $n2w->convertDateToWords($val, $pattern);
    else
        $_expected = $n2w->convertDateToWords($val);
    
    // handle output
    if ($_expected === $expected){
        echo "<font color='green'>PASS:</font> \t<u>$val</u> \t=\t $expected <br />";
        $passed_test_count++;
    }else
        echo "<font color='red'>FAIL:</font> \tGiven \"$val\" \n\tGot \"$_expected\" \n\tExpected \"$expected\" <br />";
}

function assertOrdinalEquals($val, $expected){
    global $test_count;
    global $passed_test_count;
    $test_count++;
    $n2w = new NumberToWords();
    $_expected = $n2w->getNumberOrdinal($val);
    // handle output
    if ($_expected === $expected){
        echo "<font color='green'>PASS:</font> \t$val \t=\t $expected <br />";
        $passed_test_count++;
    }else
        echo "<font color='red'>FAIL:</font> \tGiven \"$val\" \n\tGot \"$_expected\" \n\tExpected \"$expected\" <br />";
}
 */