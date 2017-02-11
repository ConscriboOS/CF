<?php


/**
 * function getRuntime is een shorthand naar \CF\Runtime::gI()
 * Gebruik van deze functie mag buiten en binnen het framework gebruikt worden als shorthand.
 * Alhoewel het hierbij lijkt dat er een "externe" dependency wordt gecreerd, is dit niet het geval omdat de functie
 * in het framework zelf staat, en via het framework de implementatie runtime ophaalt.
 */
function gR() {
	return \CF\Runtime::gI('');
}


function getIntVar($name,$default=0,$rangeStart="no",$rangeStop="no"){
	if (isset($_GET[$name])){
		$value = $_GET[$name];
	} elseif (isset($_POST[$name])){
		$value = $_POST[$name];
	} else {
		return $default;
	}
	if(mb_strlen($value) != 0) {
		$value = intval($value);
	} else {
		return $default;
	}

	if (($rangeStart!="no")&& ($value < $rangeStart)){
		return $rangeStart;
	}

	if (($rangeStop!="no")&& ($value > $rangeStop)){
		return $rangeStop;
	}

	return $value;
}


function getVar_($name, $default = '', $maxLength = NULL) {
	if(isset($_GET[$name])) {
		$value = $_GET[$name];
	} elseif(isset($_POST[$name])) {
		$value = $_POST[$name];
	} else {
		return $default;
	}

	if(is_array($value) || is_object($value) || is_resource($value)) {
		// we verwachten een string. Iemand heeft een php exploit of snapt de urlstructuur bla[dsda]= ... Dit klopt niet:
		return $default;
	}

	if($maxLength !== NULL) {
		if (mb_strlen($value) > $maxLength){
			return(mb_substr($value, 0, $maxLength));
		}
	}
	return $value;
}

function getStrVar($name, $default = NULL, $maxLength = NULL) {
	$ret = getVar_($name, $default, $maxLength);
	if($ret !== $default) {
		if(!is_array($ret)) {
			return strval($ret);
		}
	}
	return $default;
}


function getNumVar($name, $default, $rangeStart = NULL,$rangeStop = NULL) {
	if(isset($_GET[$name])) {
		$value = htmlspecialchars($_GET[$name]);
	} elseif(isset($_POST[$name])) {
		$value = htmlspecialchars($_POST[$name]);
	} else {
		return $default;
	}

	$value = parseNumber($value, $default);
	if($value === $default) {
		return $default;
	}
	if ($rangeStart !== NULL) {
		if($value < $rangeStart) {
			return $default;
		}
	}

	if ($rangeStop !== NULL) {
		if($value > $rangeStop) {
			return $default;
		}
	}
	return $value;
}

function parseNumber($value, $default) {
	$value = trim(str_replace(',','.',$value));
	if (!is_numeric($value)) {
		return $default;
	}
	return $value;
}

function parseAmount($number, $default) {

	$val = parseNumber($number, $default);
	if($val === $default) {
		return $default;
	}

	return round((float)$val * 100);
}

// checks if value is set, is numeric and in range, and returns that value, or false.
function getReqNumVar($name, $errorMessage, $rangeStart = NULL, $rangeStop = NULL){
	$value = getNumVar($name, NULL, $rangeStart, $rangeStop);

	if($value == NULL) {
		gR()->addUserError($errorMessage);
		return false;
	}
	return $value;
}

function getReqIntVar($name, $errorMessage, $rangeStart = NULL, $rangeStop = NULL) {
	$value = getReqNumVar($name, $errorMessage, $rangeStart, $rangeStop);
	if($value != false && (floor($value) != ceil($value))) {
		gR()->addUserError($errorMessage);
		return false;
	}
	return intval($value);
}


function getDateVar($name,$default = false){
	$_date = getVar_($name);
	$date = parseHumanDate($_date);
	if($date === NULL) {
		return $default;
	}
	return $date;
}

function getReqDateVar($name,$errormessage){

	$_date = getReqVar($name,$errormessage);
	if(gR()->getUserErrors()->hasErrors()) {
		return false;
	}

	$date = parseHumanDate($_date);

	if($date === NULL) {
		gR()->addUserError($errormessage);
		return false;
	}
	return $date;
}

function getTimeVar($name, $default = NULL) {
	$str = getStrVar($name, $default);
	if($str == NULL) {
		return $str;
	}
	$str = trim($str);
	$m = NULL;
	if(preg_match('/^([01]?[0-9]|2[0-3])\:([0-5]?[0-9])$/', $str, $m)){
		$hours = $m[1];

		$minutes = $m[2];
		return (intval($hours) * 3600) + ($minutes * 60);
	} elseif (preg_match('/^(([01]?[0-9]|2[0-3]))$/', $str, $m)) {
		$hours = $m[1];
		return (intval($hours) * 3600);
	}
	return $default;
}

function timestampToTime($stamp) {
	$h = date('H', $stamp);
	$m = date('i', $stamp);
	return ($h * 3600) + ($m * 60);
}


function getCheckboxValue($name,$default = false){
	$name = str_replace(" ","_",$name); // strange, but spaces within key names in html get converted to underscores.
	$name = str_replace(".","_",$name); // as well as points

	if(isset($_POST[$name]) && ($_POST[$name] == "on")){
		return true;
	}
	if(isset($_GET[$name]) && ($_GET[$name] == "on")){
		return true;
	}
	return false;
}

function getMultiVar($name, $default = false) {
	if($default === false) {
		$default = array();
	}
	if(!isset($_POST[$name]) && !isset($_GET[$name])) {
		return $default;
	}
	if(isset($_POST[$name])) {
		if(is_array($_POST[$name])) {
			return $_POST[$name];
		}
	}
	if(isset($_GET[$name])) {
		if(is_array($_GET[$name])) {
			return $_GET[$name];
		}
	}
	return $default;
}


function getSesVar($name,$default = ''){


	if (isset($_SESSION['u_'. gR()->getSessionScopeId() .'_'. $name])){
		return $_SESSION['u_'. gR()->getSessionScopeId() .'_'.$name];
	} else {
		return $default;
	}
}

function setSesVar($name,$value){
	$_SESSION['u_'. gR()->getSessionScopeId() .'_'. $name] = $value;
}


function addMetaTag($value) {
	if(!isset($GLOBALS['extraMetaTags'])) {
		$GLOBALS['extraMetaTags'] = array();
	}
	$GLOBALS['extraMetaTags'][] = $value;
}

function checkEmail($email, $extended = true) {
	if(!preg_match('/^.+@[a-zA-Z0-9-.]+\.[A-Za-z]{2,}$/i', $email)) {
		return false;
	}
	/*
	if($extended) {
		list($userName, $domain) = explode('@',$email);
		if(getmxrr($domain, $MXHost) !== false) {
			return true;
		} elseif (@fsockopen($domain, 25, $errno, $errstr, 5)) {
			return false;
		}
	}
	*/
	return true;
}


// Shortens a message larger than $length, and add ...
function shorten($string,$length){
	if (strlen($string) > $length && ($length >3)) {
		return mb_substr($string,0,($length-3)) .'...';
	}
	return $string;
}

/**
 * Split string into array of characters
 */
function mb_str_split($string) {
	return preg_split('/(?<!^)(?!$)/u', $string);
}

// formats a cent value to simple decimal number: 3445 => 34,45 , 4 => 0,04, 423432342 => 4.234.323,42
function formatCents($cents, $decimals = 2){


	$floatval = round($cents * pow(10,$decimals -2)) / pow(10,$decimals );

	return number_format($floatval, $decimals, ',','.');

	// 2 decimals formatting
	$centStr = ''.roundl($cents);
	$sign = '';

	if (mb_substr($centStr,0,1) == '-'){
		$sign = '-';
		$centStr = mb_substr($centStr,1);
	}

	$len = mb_strlen($centStr);
	switch($len){
		case 1:
			$centStr = '0'. $centStr;
		case 2:
			$wholeCur = '0';
			break;
		default:
			$wholeCur = mb_substr($centStr,0,-$decimals);
			$len -= $decimals;
			$count = 1;
			while ($len > ($count *3)){
				$wholeCur = mb_substr($wholeCur,0,0-(($count*3)+($count -1))).'.'.mb_substr($wholeCur,0-(($count*3)+($count -1)));
				$count ++;
			}
	}

	return $sign.$wholeCur.','.mb_substr($centStr,-$decimals);

}

function formatCentsDb($cents) {
	return formatCentsPlain($cents, '.');
}

// formats a cent value to simple decimal number: 3445 -> 34.45 or 34,45 , 4 => 0.04, -423432342 => -4234323.42
function formatCentsPlain($cents ,$decimalSeperator){
	// 2 decimals formatting
	$centStr = ''.intval($cents);
	$sign = '';

	if (mb_substr($centStr,0,1) == '-'){
		$sign = '-';
		$centStr = mb_substr($centStr,1);
	}

	$len = mb_strlen($centStr);
	switch($len){
		case 1:
			$centStr = '0'.$centStr;
		case 2:
			$wholeCur = '0';
			break;
		default:
		$wholeCur = mb_substr($centStr,0,-2);
	}

	return $sign . $wholeCur . $decimalSeperator . mb_substr($centStr, -2);

}

function formatInt($val) {
	return number_format(round($val), 0, ',','.');
}


function formatFloat($val, $decimalSeperator = '.') {
	if($val === NULL) {
		return 'null';
	}
	return str_replace(',', $decimalSeperator, $val);
}

function kbytesToString($size, $precision = 0) {
	return bytesToString($size * 1000, $precision);
}

function bytesToString($size, $precision = 0) {
	$sizes = array('YB', 'ZB', 'EB', 'PB', 'TB', 'GB', 'MB', 'kB', 'bytes');
	$total = count($sizes);
	$divider = 1000;

	while($total-- && $size > $divider){
		$size /= $divider;

	}
	if($precision == 0) {
		if($size >= 0.001 && $size < 10) {
			$precision = 2;
		} elseif($size < 100) {
			$precision = 1;
		}
	}

	return str_replace('.',',', round($size, $precision).' '.$sizes[$total]);
}

function storageSizeToString($size) {
	if($size <= 1 && $size > 0) {
		return bytesToString(1001);
	}

	return bytesToString($size * 1000);
}
/**
 * Returns an array with the array_keys of the srcArray in the order as the keys of the alignmentArray
 * e.g.:
 * $srcArray = array('ds' => '1', 2 => '2', '0' => '3');
 * $alignmentArray = array('0' => '?', 2 => '?', '4' => '?', 'ds' => '?');
 * returns: array('0' => '3', 2 => '2', 'ds' => '1');
 *
 * Complexity = O(count($alignmentArray))
 */

function align($srcArray, $alignmentArray) {

	$retArray = array();

	foreach($alignmentArray as $alignKey => $tmp){
		if (isset($srcArray[$alignKey])){
			$retArray[$alignKey] = $srcArray[$alignKey];
			unset($srcArray[$alignKey]);
		}
	}
	// de rest toevoegen aan het einde:
	foreach ($srcArray as $key => $val){
		$retArray[$key] = $val;
	}

	return $retArray;
}

/**
 * Returns an array with the array_values of the srcArray in the order of the values of the alignmentArray
 * e.g.:
 * $srcArray = array('ds', 2 , '0' );
 * $alignmentArray = array('0' , 2, '4', 'ds');
 * returns: array('0', 2 , 'ds');
 *
 * Complexity = O(5 * $srcArray) + O(align())
 */

function alignValues($srcArray, &$alignmentArray) {
	$alignFlip = array_flip(array_values($alignmentArray));
	$result = (align(array_flip(array_values($srcArray)), $alignFlip));
	return array_combine(array_keys($result), array_keys($result));
}

/**
 * @deprecated
 */
function createJsString($string){
	return jsString($string);
}

function jsString($string) {
	if($string !== NULL) {
		$encoded = '\''. preg_replace(array('/\\\\/', "/\r/", "/\n/", '/\'/'), array('\\\\\\\\','', '\\\n', '\\\''), $string) .'\'';
	} else {
		$encoded = 'null';
	}
	return $encoded;
}

function jsFloat($float) {
	if($float === NULL) {
		return 'null';
	} else {
		// de Locale kan dit vernaggelen. daarom voor de zekerheid een str_replace in floats
		return str_replace(',', '.', $float);
	}
}
function jsInt($int) {
	if($int === NULL) {
		return 'null';
	} else {
		return intval($int);
	}

}

function formatEntities($string) {
	return htmlentities($string, ENT_COMPAT, 'UTF-8');
}

// Datum functies versie: 20091211:

 /**
  * Formateer een datum die in het correcte formaat hoort te staan
  * @param string $rawDate datum yyyy-m[m]-d[d]
  * @return NULL als datum niet parseerbaar is
  * @return String 'yyyy-mm-dd'
  */

function parseDate($rawDate) {
	if(!is_string($rawDate)) {
		return NULL;
	}
	if(strpos($rawDate, '-') === false) {
		return NULL;
	}

	$date = explode('-', $rawDate);

	if (count($date) != 3) {
		return NULL;
	}
	list($year, $month, $day) = $date;

	if ($year < 1901 || $year > 2199 || $month < 1 || $month > 12 || $day < 1 || $day > 31) {
		return NULL;
	}

	return sprintf('%04d-%02d-%02d', $year, $month, $day);
}

/**
 * Parseer een datum zoals een mens deze zou schrijven
 * @param $rawDate string
 * @return NULL als datum niet herkend wordt
 * @return 'yyyy-mm-dd';
 */
function parseHumanDate($rawDate) {
	$rawDate = trim($rawDate);

	if(empty($rawDate)) {
		return NULL;
	}

	if(mb_strpos($rawDate, '-') !== false) {
		$_dateParts = explode('-', $rawDate);
	} elseif (mb_strpos($rawDate, '/') !== false) {
		$_dateParts = explode('/', $rawDate);
	} elseif (mb_strpos($rawDate, '\\') !== false) {
		$_dateParts = explode('\\', $rawDate);	// '
	} elseif (mb_strpos($rawDate, ' ') !== false) {
		$_dateParts = explode(' ', $rawDate);
	} else {
		if(mb_strlen($rawDate) == 8) {
			//aan elkaar geschreven?
			$attempt = mb_substr($rawDate,0,2).'-'. mb_substr($rawDate,2,2) .'-'. mb_substr($rawDate,4,4);
			return parseHumanDate($attempt);
		}
		if(mb_strlen($rawDate) == 6) {
			//aan elkaar geschreven?
			$attempt = mb_substr($rawDate,0,2).'-'.mb_substr($rawDate,2,2) .'-'. mb_substr($rawDate,4,2);
			return parseHumanDate($attempt);
		}
		return NULL;
	}
	$noYear = false;

	if(count($_dateParts) == 3) {
		list($day,$month,$year) = $_dateParts;
	} elseif (count($_dateParts) == 2) {
		list($day, $month) = $_dateParts;
		$year = dateGetYear(dateToday());
		$noYear = true;
	} else {
		return NULL;
	}

	if(is_numeric($month)) {
		$month = intval($month);
		if (!($month > 0 && $month <= 12)){
			return NULL;
		}
	} else {
		$month = str_replace('.', '', $month);
		$month = str_replace(array('januari','jan', 'februari', 'feb', 'mar', 'maart', 'april', 'apr', 'mei', 'juni', 'jun', 'juli', 'jul', 'augustus', 'aug', 'september', 'sept', 'sep','oktober', 'okt', 'november', 'nov', 'december', 'dec'),
							 array(1        ,1    , 2         , 2    , 3    , 3      , 4      , 4    , 5    , 6     , 6    , 7     , 7    , 8         , 8    , 9          , 9     , 9    ,10       , 10   , 11        , 11   , 12        , 12),
							 strtolower($month));
		if(!is_numeric($month)) {
			return NULL;
		}
	}

	if(!is_numeric($year)) {
		return NULL;
	}

	$year = intval($year);
	if ($year < 100) {
		if($year < 50) {
			$year = 2000 + $year;
		} else {
			$year = 1900 + $year;
		}
	} elseif($year < 1900) {
		return NULL;
	}
	$date = parseDate(date('Y-m-d', mktime(0,0,0,intval($month), intval($day), intval($year))));
	if ($date == NULL) {
		return NULL;
	}

	// datums zonder jaartal ingevoerd zijn altijd in de toekomst
//	if ($noYear && dateBefore($date, dateToday())) {
//		$date = dateAdd($date, 0, 0, 1);
//	}

	return $date;
}

/**
 * Formateerd een correcte datum in een leesbaar formaat
 * @param $format <regular/full/date/strftime/timestamp>
*/
function dateFormat($date, $format = 'regular', $pattern = NULL) {
	if($date === NULL || empty($date) || $date == '0000-00-00') {
		return '';
	}
	list($year, $month, $day) = explode('-', $date);
	$timeStamp = mktime(0,0,0,$month, $day, $year);
	switch ($format) {
		case 'full': return strftime('%e %B %Y', $timeStamp);
		case 'date': return date($pattern, $timeStamp);
		case 'strftime': return strftime($pattern, $timeStamp);
		case 'timestamp': return $timeStamp;
		case 'regular':
		default :
			 return date('d-m-Y', $timeStamp);
	}
}

function dateTimeFormat($dateTime) {
	$stamp= DateTime::createFromFormat('Y-m-d H:i:s', $dateTime)->getTimeStamp();
	return strftime('%e %B %Y %R', $stamp);
}

function dateGetMonth($date) {
	list($year, $month, $day) = explode('-', $date);
	return $month;
}

/**
 * @param String $date geeft de datum op de laatste dag van de maand terug
 */
function dateGetEndOfMonth($date) {
	$month = dateGetMonth($date);
	if($month == 2) {
		// te ingewikkeld, dan maar zo: (tel een maand op en haal er vervolgens een dag vanaf)
		return (dateAdd(dateGetYear($date).'-03-01', -1));
	}
	if(in_array($month, array(1,3,5,7,8,10,12))) {
		return substr($date, 0, 8) . '31';
	}
	return substr($date, 0, 8) .'30';
}

/**
 * geeft de datum aan het begin van de maand.
 * @param string $date
 * @return string
 */
function dateGetStartOfMonth($date) {
	return substr($date,0,8) . '01';
}

/**
 * Create an intersection between ranges. a range is defined by an array('start' => <date>, 'end' => <date>)
 * @param $range1
 * @param $range2
 * @return date[] range that instersects, or NULL if no intersection is present
 */
function dateRangeIntersect($range1, $range2) {
	$res = $range1;

	if(dateBefore($res['start'], $range2['start'])) {
		$res['start'] = $range2['start'];
	}

	if(dateAfter($res['end'], $range2['end'])) {
		$res['end'] = $range2['end'];
	}

	if(dateAfter($res['start'], $res['end'])) {
		return NULL;
	}

	return $res;
}

/**
 * Returns the number of days in the month given by the date.
 * @param $date
 * @return int
 */
function dateGetNrDaysInMonth($date) {
	return round(substr(dateGetEndOfMonth($date), 8,2));
}

function dateGetDay($date) {
	list($year, $month, $day) = explode('-', $date);
	return $day;
}

function dateGetYear($date) {
	list($year, $month, $day) = explode('-', $date);
	return $year;
}

function dateToday() {
	return date('Y-m-d');
}

/**
 * 0 is zondag tot 6 is zaterdag
 * @param $date
 */
function dateGetWeekday($date) {
	return dateFormat($date, 'date', 'w');
}



/**
 * Geeft het aantal dagen vanaf $srcDate tot $offsetDate.
 * @return int aantal dagen. Deze is negatief als offsetDate voor $srcDate ligt
 */
function dateDiff($srcDate, $offsetDate) {
	$srcDateTime = new DateTime($srcDate);

	$diff = $srcDateTime->diff(new DateTime($offsetDate));
	return ($diff->invert)? 0-$diff->days:$diff->days;
}

/**
 * Geeft het aantal maanden vanaf $srcDate tot $offsetDate. naar beneden afgerond  dus:
 * vanaf 01-01 tot 01-03 = 2 maanden. van 01-01 tot 28-02 = 1 maand, van 28-02 tot 28-03 = 1 maand, van 31-01 - 28-02 = 0 maanden. van 01-02 - 01-03 = 1 maand
 *
 * functie is niet absolute, maar gedraagt zich met negatieve getallen hetzelfde als positief: 01-04 - 01-03 = -1 maand, 10-02 - 01-02 = 0 maand
  * @return int aantal maanden. Deze is negatief als offsetDate voor $srcDate ligt
 */
function dateDiffMonths($srcDate, $offsetDate) {

	$neg = false;
	if(dateBefore($offsetDate, $srcDate)) {
		$neg = true;
		$b = $srcDate;
		$srcDate = $offsetDate;
		$offsetDate = $b;

	}
	list($aYear, $aMonth, $aDay) = explode('-', $srcDate);
	list($bYear, $bMonth, $bDay) = explode('-', $offsetDate);

	$res = 0;

	$res += round(($bYear - $aYear) * 12);
	$res += round($bMonth - $aMonth);

	if($bDay < $aDay) {
		$res -= 1;
	}
	return ($neg)? 0- $res: $res ;

}

/**
 * Geeft terug of srcDate na compareDate ligt.
 * b.v. srcDate = 2015-01-01, compareDate = 2010-01-01, resultaat: true
 */
function dateAfter($srcDate, $compareDate) {
	if($srcDate == $compareDate) {
		return false;
	}
	return (intval(str_replace('-', '',$srcDate)) > intval(str_replace('-', '', $compareDate)));
}

/**
 * Geeft terug of srcDate voor compareDate ligt.
 * b.v. srcDate = 2015-01-01, compareDate = 2010-01-01, resultaat: false
 */
function dateBefore($srcDate,$compareDate) {
	return (intval(str_replace('-', '', $srcDate)) < intval(str_replace('-', '', $compareDate)));
}

function dateBetween($srcDate,$startDate,$endDate) {
	if($srcDate == $startDate || $srcDate == $endDate) {
		return true;
	}
	if($startDate === NULL && $endDate === NULL) {
		// van het begin tot het einde der tijden
		return true;
	} elseif($startDate === NULL) {
		// vanaf het begin de tijden
		return dateBefore($srcDate, $endDate);
	} elseif($endDate === NULL) {
		return dateAfter($srcDate, $startDate);
	} else {
		return (dateAfter($srcDate, $startDate) && dateBefore($srcDate, $endDate));
	}
}

function dateAdd($srcDate, $days , $months = 0, $years = 0) {

	$srcDateTime = new DateTime($srcDate);
	$intervalPositiveString = '';
	$intervalNegativeString = '';

	if($years > 0) {
		$intervalPositiveString .= intval($years) .'Y';
	} elseif($years < 0) {
		$intervalNegativeString .= (0 - intval($years)) .'Y';
	}

	if($days > 0) {
		$intervalPositiveString .= intval($days) .'D';
	} elseif($days < 0) {
		$intervalNegativeString .= (0 - intval($days)) .'D';
	}

	if (!empty($intervalPositiveString)) {
		date_add($srcDateTime, new DateInterval('P'.$intervalPositiveString));
	}

	if (!empty($intervalNegativeString)) {
		date_sub($srcDateTime, new DateInterval('P'.$intervalNegativeString));
	}

	if($months == 0) {
		return $srcDateTime->format('Y-m-d');
	} else{
		$srcDate = $srcDateTime->format('Y-m-d');
	}

	// maanden doen we handmatig omdat php dit niet goed kan.
	if($months > 0) {
		list($year, $month, $day) = explode('-', $srcDate);
		$month = (intval($month) + $months) - 1;
		$year += floor($month / 12);
		$month = intval(($month % 12) + 1);
	} elseif($months < 0) {
		list($year, $month, $day) = explode('-', $srcDate);
		$month = ((intval($month)-1) + $months);
		if($month < 0) {
			$year -= ceil((0 - $months) / 12);	 // -1 wordt 1 jaar eraf -12 = 1 jaar eraf -13 = 2 jaar eraf
		}
		$month = intval((($month + 120000) % 12) + 1); // ondersteuning voor modulo werkt alleen op positieve getallen.
	}

	if($year < 1900 || $year > 2500) {
		gR()->addNotice('Onbekend jaar ' . $year);
		return $srcDate;
	}

	$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
	if($day > $daysInMonth) {
		$day = $daysInMonth;
	}
	$srcDate = sprintf('%04d-%02d-%02d', $year, $month, $day);
	return $srcDate;
}

// Geeft het aantal zondagen tussen de reeks. Hiermee bereken je een sluitend aantal weken als je aansluitende periodes hebt
function dateGetSundaysBetweenDates($startDate, $endDate) {
	$startDay = dateFormat($startDate,'strftime', '%w');

	$sundays = 0;

	$firstSunday = dateAdd($startDate, 0- $startDay);

	if($firstSunday == $startDate) {
		$sundays ++;
	}

	// bereken het aantal dagen tussen de zondag voor de start een dag na de einddatum (als start en eind gelijk is, kan er ook een zondag tussen zitten,
	$daysBetween = dateDiff($firstSunday, $endDate);
	// aantal weken tussen:
	$sundays += floor($daysBetween / 7 );
	// als de start een zondag is, dan tellen we deze ook als het niet een hele week is, anders tellen we alleen de hele weken.
	return $sundays;
}

/**
 * Geeft volledige weergave van datum en tijd
 * @param $timeStamp
 * @return string
 */
function timeFormat($timeStamp) {
	return strftime('%e %B %Y %R', $timeStamp);
}

/**
 * Geeft tijd terug van een time integer
 * @return string
 */
function formatTime($time) {

	$r = round($time / 60);
	$m = $r % 60;
	$h = round(($r - $m) / 60) ;
	return sprintf('%02d:%02d', $h, $m);

}

function dbStr($str) {
	if(is_string($str)) {
		if(db()->getLink() !== NULL) {
			try {
				return ($str === NULL) ? 'NULL' : '\'' . mysqli_real_escape_string(db()->getLink(), $str) . '\'';
			} catch (Exception $e) {
				return ($str === NULL)? 'NULL': '\''. str_replace(array("'"), array('\\\''), $str) .'\'';
			}
		} else {
			return ($str === NULL)? 'NULL': '\''. str_replace(array("'"), array('\\\''), $str) .'\'';
		}
	} elseif(is_bool($str)) {
		return ($str === NULL)? 'NULL': (($str === true)? 1: 0);
	} elseif($str === NULL) {
		return 'NULL';
	} elseif(is_array($str) || is_object($str)) {
		gR()->addWarning('Trying to use array as string: ' . var_export($str, true));
		return '\'Array|Object\'';
	} else {
		return '\''. mysqli_real_escape_string(db()->getLink(), strval($str)) .'\'';
	}
}

function dbInt($int) {
	if($int === NULL) {
		return 'NULL';
	} else {
		return intval($int);
	}
}

/**
 * Escape een variabele database, table, of columnname.
 * @param String $identifierName
 * @return string
 */
function dbIdentifier($identifierName) {
	if(empty($identifierName)) {
		gR()->addError('Empty identifier');
	}
	if(strpos($identifierName, '`') !== false) {
		gR()->addError('Identifier can not contain `');
	}
	return '`'. $identifierName .'`';
}

function dbFloat($float) {
	if($float === NULL) {
		return 'NULL';
	} else {
		return str_replace(',', '.', floatval($float));
	}
}

function dbAmount($cents) {
	if($cents === NULL) {
		return 'NULL';
	} else {
		return formatCentsPlain($cents, '.');
	}
}

function dbBool($bool) {
	if($bool === NULL) {
		return 'NULL';
	} else {
		return ($bool)? 'TRUE' :'FALSE';
	}
}

/**
 *
 * @param      $array
 * @param null $cast default: NO CAST!!!
 * @return string
 */
function dbArray($array, $cast = NULL) {

	if(!is_array($array)) {
		return '(NULL)';
	}
	if(count($array) == 0) {
		return '(NULL)';
	}

	if($cast !== NULL) {
		$nArray = array();

		foreach($array as $el) {
			switch($cast) {
				case 'string':
					$nArray[] = dbStr($el);
					break;
				case 'int':
					$nArray[] = dbInt($el);
					break;
				case 'amount':
					$nArray[] = dbAmount($el);
					break;
				default:
					gR()->addError('Unkown cast');
			}
		}
		$array = $nArray;
	}
	return '('. implode(',', $array) .')';
}


// returns cents:
function parseDbAmount($amount) {
	if($amount === NULL) {
		return $amount;
	}
	return round($amount * 100);

}

function dbDateTime($timestamp) {
	if($timestamp === NULL) {
		return 'NULL';
	} else {
		$dt = date('Y-m-d H:i:s', $timestamp);
		if(empty($dt)) {
			return 'NULL';
		}
		return dbStr($dt);
	}
}

function parseDbDateTime($dateTime) {
	if($dateTime === NULL) {
		return NULL;
	}
	$m = NULL;
	if(!preg_match('/$([0-9]{4})\-([0-9]{2})\-([0-9]{2}) ([0-9]{2})\:([0-9]{2})\:([0-9]{2})^/', $dateTime, $m)) {
		return NULL;
	}
	return mktime($m[4], $m[5], $m[6], $m[2], $m[3], $m[1]);

}

function dbStruct($struct) {
	return dbStr(json_encode($struct));
}

function parseDbStruct($field) {
	return json_decode($field, true);
}


function is_unserializable($str) {
	if($str === 'b:0;') {
		return true;
	}
	if(is_array($str)) {
		return false;
	}
	gR()->toggleIgnoreNotices(true);

	$test = @unserialize($str);
	gR()->toggleIgnoreNotices(false);

	if($test === false) {
		return false;
	}
	return true;
}


/**
 * Geeft terug of een array aan de definitie zoals opgegeven in de elementDefinition voldoet.
 * @param array $array te testen array
 * @param array $elementDefinition: array met <elementName> => <definition>
 *
 * Definitions:
 *	array ((date|string|int|enum), required, <typespecific>
 *
 * TypeSpecific: bij enum: array met toegestane waarden.
 *
 * @return boolean of de array gevalideert kon worden.
 */
function validateElements(array $array, $elementDefinition) {
		foreach($elementDefinition as $fieldName => $def) {
			if($def[1] == true) {
				// required
				if(!isset($array[$fieldName])) {
					return false;
				}
			} else {
				if(!isset($array[$fieldName])) {
					return true;
				}
			}
			$value = $array[$fieldName];
			switch($def[0]) {
				case 'date':
					if(!empty($value) || $def[1]) {
						if(parseDate($value) === NULL) {
							return false;
						}
					}
				break;
				case 'string':
				case 'text':
					if($def[1]) {
						if(strlen($value) == 0) {
							return false;
						}
					}
				break;
				case 'amount':

				case 'int':
					if($def[1]) {
						if(strlen($value) == 0) {
							return false;
						}
					}
					if($def[0] == 'amount') {
						// in amounts mag een . voorkomen
						$value = str_replace(array('.',','), '', $value);
					}
					if(strlen(ltrim(strval($value),'0')) > 0 && strval(intval($value)) !== ltrim(strval($value),'0')) {
						return false;
					}
				break;
				case 'enum':
					if(!empty($value) || $def[1]) {
						if(!in_array($value, $def[2])) {
							return false;
						}
					}
				break;
			}
		}
		return true;
	}


/**
 * Decodes a json string, if the string cannot be converted it detects encoding and tries again!
 * @param string $str
 * @param bool $assoc
 * @param int $depth
 * @param int $options
 * @return mixed or NULL if undecodable;
 */

function mb_json_decode($str, $assoc = false, $depth = 512, $options = 0) {
	if($str === NULL || mb_strlen($str) === 0) {
		return NULL;
	}

	$res = json_decode($str, $assoc, $depth, $options);
	if($res === NULL) {
		// try iso-8859 charset:
		$list = array('ASCII', 'UTF-8', 'Windows-1252');
		$encoding = mb_detect_encoding($str, $list, true);
		if($encoding === 'UTF-8') {
			return NULL;
		}
		if($encoding !== false) {
			$str = mb_convert_encoding($str, 'UTF-8', $encoding);
		} else {
			$str = mb_convert_encoding($str, 'UTF-8', 'ISO-8859-15');
		}
		return json_decode($str, $assoc, $depth, $options);
	} else {
		return $res;
	}
}

/**
 * @param $className
 * @return string[] $namespace, $class
 */
function splitClassNameIntoNamespaceAndClassName($className) {

	$pos = strrpos($className, '\\');
	if($pos === false) {
		return array('' , $className);
	}
	$res = array(substr($className, 0, $pos + 1), substr($className, $pos +1));
	return $res;
}

?>
