<?php

namespace CF\Tool;

// verzorgt kennis van een bankaccount.
use CF\Configuration;

/**
 * Update 2014-02-25, geschikt gemaakt voor extern gebruik.
 */
class BankAccount {

	const TYPE_NATIONAL = 'nat';
	const TYPE_IBAN = 'iban';

	static function getIBANLengthForCountry($country) {
		$country = strtolower($country);
		static $ibanLengths = array('al' => 28, 'ad' => 24, 'at' => 30, 'az' => 28, 'be' => 16, 'bh' => 22, 'ba' => 20, 'br' => 29, 'bg' => 22, 'cr' => 21, 'hr' => 21, 'cy' => 28, 'cz' => 24, 'dk' => 18, 'do' => 28, 'ee' => 20, 'fo' => 18, 'fi' => 18, 'fr' => 27, 'ge' => 22, 'de' => 22, 'gi' => 23, 'gr' => 27, 'gl' => 18, 'gt' => 28, 'hu' => 28, 'is' => 26, 'ie' => 22, 'il' => 23, 'it' => 27, 'kz' => 20, 'kw' => 30, 'lv' => 21, 'lb' => 28, 'li' => 21, 'lt' => 20, 'lu' => 20, 'mk' => 19, 'mt' => 31, 'mr' => 27, 'mu' => 30, 'mc' => 27, 'md' => 24, 'me' => 22, 'nl' => 18, 'no' => 15, 'pk' => 24, 'ps' => 29, 'pl' => 28, 'pt' => 25, 'ro' => 24, 'sm' => 27, 'sa' => 24, 'rs' => 22, 'sk' => 24, 'si' => 19, 'es' => 24, 'se' => 24, 'ch' => 21, 'tn' => 24, 'tr' => 26, 'ae' => 23, 'gb' => 22, 'vg' => 24);
		return (isset($ibanLengths[$country])) ? $ibanLengths[$country] : NULL;
	}

	static function createAccountNr($nr, $name = NULL, $city = NULL, $country = 'nl', $iban = NULL, $bic = NULL, $sanitize = true) {

		$res = array('country' => $country,
					 'nr' => $nr,
					 'name' => $name,
					 'tnv' => $name,
					 'city' => $city,
					 'iban' => $iban,
					 'bic' => $bic,
		);
		if($sanitize) {
			$res = BankAccount::sanitizeAccount($res);
		}
		return $res;
	}

	static function sanitizeAccount($account) {

		if(isset($account['nr'])) {
			$account['nr'] = preg_replace('/[^0-9a-zA-Z]/', '', $account['nr']);
		}

		if(isset($account['name'])) {
			$account['name'] = trim($account['name']);
		} else {
			// legacy compatibility:
			// Vroeger (en in Conscribo) heette dit tnv.
			if(isset($account['tnv'])) {
				$account['name'] = trim($account['tnv']);
				unset($account['tnv']);
			}
		}

		if(isset($account['city'])) {
			$account['city'] = trim($account['city']);
		}
		if(isset($account['country'])) {
			$account['country'] = trim(strtolower($account['country']));
		}
		if(isset($account['iban'])) {
			$account['iban'] = strtoupper(preg_replace('/[^0-9A-Za-z]/', '', $account['iban']));
		}
		if(isset($account['bic'])) {
			$account['bic'] = strtoupper(preg_replace('/[^0-9A-Za-z]/', '', $account['bic']));
		}
		return $account;
	}

	static function formatNumber($val, $type = NULL) {
		if($val === NULL || empty($val['iban'])) {
			return '';
		}
		return BankAccount::formatNr($val['iban'], $type);
	}

	static function formatNr($nr, $type, $country = NULL) {
		switch($type) {
			case BankAccount::TYPE_NATIONAL:
				switch($country) {
					case 'nl':
						if(mb_strlen($nr) < 8) {
							// ing/postbanknr
							return $nr;
						}
						return substr($nr, 0, -7) . '.' . substr($nr, -7, 2) . '.' . substr($nr, -5, 2) . '.' . substr($nr, -3, 3);
						break;
					case 'be':
						if(mb_strlen($nr) != 12) {
							return $nr;
						}
						return (mb_substr($nr, 0, 3) . '-' . mb_substr($nr, 3, 7) . '-' . mb_substr($nr, 10, 2));
						break;
					default:
						return $nr;
				}
				break;
			case BankAccount::TYPE_IBAN:
				return trim(strtoupper(chunk_split($nr, 4, ' ')));
		}
		return $nr;
	}

	static function formatIBAN($value, $displayType = 'txt') {
		if(empty($value['iban'])) {
			return '';
		}
		switch($displayType) {
			case 'txt':
			case 'input': // Dipslay type voor input velden
				return chunk_split($value['iban'], 4, ' ');
		}
	}


	static function enrichNationalFromIBAN($account) {
		if(!static::isValidAccount($account)) {
			return $account;
		}
		if(strtolower(substr($account['iban'], 0, 2)) == 'nl') {
			$newAccount = $account;
			$newAccount['country'] = 'nl';
			$newAccount['nr'] = ltrim(substr($account['iban'], -10), '0');
			if(static::isValidAccount($newAccount)) {
				return $newAccount;
			}
		} else {
			$country = strtolower(substr($account['iban'], 0, 2));
			if(BankAccount::getIBANLengthForCountry($country) !== NULL) {
				$newAccount['country'] = $country;
			}
		}

		return $account;
	}

	/**
	 * Als IBAN NL is, kunnen we hier het BICnummer uit bepalen:
	 * @param type $account
	 */
	static function enrichBICFromIBAN($account) {
		if(!static::isValidAccount($account)) {
			return $account;
		}

		if(strtolower(substr($account['iban'], 0, 2)) == 'nl') {
			$shBic = substr($account['iban'], 4, 4);
			if(isset($_ENV['IBAN_BIC_conversions'][$shBic])) {
				$account['bic'] = $_ENV['IBAN_BIC_conversions'][$shBic];
			}
		}
		$bic = BankAccount::getBicFromAccount($account);
		if($bic !== NULL) {
			$account['bic'] = $bic;
		}
		return $account;
	}

	static function getBicFromAccount($account) {

		if(!isset($_ENV['IBAN_BIC_conversions'])) {
			$ibanBicConversion = NULL;
		include_once Configuration::gI()->getLibraryRoot() . 'resources/iban_bic_conversions.php';
			$_ENV['IBAN_BIC_conversions'] = $ibanBicConversion;
		}

		if(strtolower(substr($account['iban'], 0, 2)) == 'nl') {
			$shBic = substr($account['iban'], 4, 4);
			if(isset($_ENV['IBAN_BIC_conversions'][$shBic])) {
				return $_ENV['IBAN_BIC_conversions'][$shBic];
			}
		}
		return NULL;
	}

	/**
	 *
	 * @param type $account
	 * @param      type Deprecated, automatische detectie
	 * @param type $generateErrors
	 * @return bool isValid
	 */
	static function isValidAccount($account) {

		if($account === NULL) {
			return true;
		}

		if(!array_key_exists('iban', $account) ||
			!array_key_exists('bic', $account) ||
			(!array_key_exists('tnv', $account) && !array_key_exists('name', $account))
		) {
			return false;
		}


		if(!empty($account['nr'])) {

			$nr = strval($account['nr']);
			if(!isset($account['country'])) {
				$account['country'] = 'nl';
			}
			switch($account['country']) {
				case 'nl':
					if(preg_match('/[^0-9]/', $account['nr'])) {
						return false;
					}

					// voorloopnullen verwijderen
					$nr = ltrim($nr, 0);

					if(mb_strlen($nr) < 3) {
						return false;
					}
					if(mb_strlen($nr) < 8) {
						//Postbank
						break;
					}

					if(mb_strlen($nr) < 9 || mb_strlen($nr) > 10) {
						return false;
					}


					//11 proef:
					$par = 0;
					if(strlen($nr) == 9) {
						$nr = '0' . $nr;
					}
					for($i = 0; $i < 10; $i++) {
						$par += ((10 - $i) * intval($nr[$i]));
					}
					if($par % 11 != 0) {
						return false;
					}
					break;
				case 'be':
					//97 rest:
					if(mb_strlen($nr) != 12) {
						return false;
					}
					$check = mb_substr($nr, 0, 10);
					$par = mb_substr($nr, 10, 2);
					if(bcmod($check, 97) != $par) {
						return false;
					}
					break;
				default:
					return false;
			}
		}


		if(!empty($account['iban'])) {
			// validate iban:

			$iban = strtolower($account['iban']);
			$country = substr($iban, 0, 2);
			$len = BankAccount::getIBANLengthForCountry($country);
			// length check
			if($len !== mb_strlen($iban)) {
				return false;
			}

			// eerste 4 chars er achter plaatsen
			$iban = mb_substr($iban, 4) . mb_substr($iban, 0, 4);
			$rangeA = range('a', 'z');
			$rangeB = range('10', '35');
			$iban = str_replace($rangeA, $rangeB, strtolower($iban));
		 	$iban = ltrim($iban, '0');
			if(bankAccount::mod97($iban) !== 1) {
				return false;
			}

			// validate BIC:
			if(!empty($account['bic'])) {
				if(!preg_match('/^[a-z]{6,6}[a-z2-9][a-np-z0-9]([a-z0-9]{3,3}){0,1}$/', strtolower($account['bic']))) {
					return false;
				}
			}
		}
		return true;
	}

	/**
	 * Returns the errormessage why a bankaccount is incorrect or NULL if it is correct or empty.
	 * @param $account
	 * @return null|string
	 */
	static function getErrorMessagesWithInvalidAccount($account) {

		if($account === NULL) {
			return NULL;
		}

		if(!array_key_exists('iban', $account) ||
			!array_key_exists('bic', $account) ||
			(!array_key_exists('tnv', $account) && !array_key_exists('name', $account))
		) {

			return 'Informatie over het rekeningnummer ontbreekt';
		}


		if(!empty($account['nr'])) {

			$nr = strval($account['nr']);
			switch($account['country']) {
				case 'nl':
					if(preg_match('/[^0-9]/', $account['nr'])) {
						return '"' . $account['nr'] . '" mag alleen uit getallen bestaan.';
					}
					// voorloopnullen verwijderen
					$nr = ltrim($nr, 0);

					if(mb_strlen($nr) < 3) {
						return '"' . $account['nr'] . '" is geen valide nederlands bankrekeningnummer.';
					}
					if(mb_strlen($nr) < 8) {
						//Postbank
						break;
					}

					if(mb_strlen($nr) < 9 || mb_strlen($nr) > 10) {

						return 'Een nederlands bankrekeningnummer bestaat uit minimaal 9 en maximaal 10 cijfers';
					}


					//11 proef:
					$par = 0;
					if(strlen($nr) == 9) {
						$nr = '0' . $nr;
					}
					for($i = 0; $i < 10; $i++) {
						$par += ((10 - $i) * intval($nr[$i]));
					}
					if($par % 11 != 0) {

						return '"' . $account['nr'] . '" is geen valide nederlands bankrekeningnummer.';
					}
					break;
				case 'be':
					//97 rest:
					if(mb_strlen($nr) != 12) {
						return '"' . $account['nr'] . '" is geen valide Belgisch bankrekeningnummer.';
					}
					$check = mb_substr($nr, 0, 10);
					$par = mb_substr($nr, 10, 2);
					if(bcmod($check, 97) != $par) {
						return '"' . $account['nr'] . '" is geen valide Belgisch bankrekeningnummer.';
					}
					break;
				default:
					return 'Onbekend Land voor binnenlands nummer';
			}
		}

		if(!empty($account['iban'])) {
			// validate iban:

			$iban = strtolower($account['iban']);
			$country = substr($iban, 0, 2);
			$len = BankAccount::getIBANLengthForCountry($country);
			// length check
			if($len !== mb_strlen($iban)) {
				return '"' . $account['iban'] . '" is geen valide IBAN nummer. (onjuiste lengte, of niet bestaande landcode)';
			}

			// eerste 4 chars er achter plaatsen
			$iban = mb_substr($iban, 4) . mb_substr($iban, 0, 4);
			$rangeA = range('a', 'z');
			$rangeB = range('10', '35');
			$iban = str_replace($rangeA, $rangeB, strtolower($iban));
			$iban = ltrim($iban, '0');
			if(bankAccount::mod97($iban) !== 1) {
				return '"' . $account['iban'] . '" is geen valide IBAN nummer. (onjuist controlegetal)';
			}

			// validate BIC:
			if(!empty($account['bic'])) {
				if(!preg_match('/^[a-z]{6,6}[a-z2-9][a-np-z0-9]([a-z0-9]{3,3}){0,1}$/', strtolower($account['bic']))) {
						$errStr = '"' . $account['bic'] . '" is geen valide BIC nummer.';
						$bic = BankAccount::getBicFromAccount($account);

						if($bic !== NULL) {
							$errStr .= ' Bedoelde u "' . $bic . '" ?';
						}
						return $errStr;
				}
			}
		}
		return NULL;
	}

	static function toDbValue($account) {

		if($account === NULL) {
			return NULL;
		}

		if(!isset($account['iban'])) {
			$account['iban'] = NULL;
			bankAccount::enrichToIBAN($account);
		}
		if(!isset($account['nr'])) {
			$account['nr'] = NULL;
		}

		if(!isset($account['country'])) {
			$account['country'] = '';
		}

		if(!isset($account['city'])) {
			$account['city'] = '';
		}

		return self::fixLength(mb_strtolower($account['country']), 2) .
				self::fixLength($account['nr'],32, true).
				self::fixLength($account['name'],32).
				self::fixLength($account['city'],32).
				self::fixLength($account['iban'], 34).
				self::fixLength($account['bic'], 11);

		//return sprintf('%2.2s%32.32s%-32.32s%-32.32s%-34.34s%-11.11s', mb_strtolower($account['country']), $account['nr'], $account['tnv'], $account['city'], $account['iban'], $account['bic']);


		// 2 nl
		// 32 nr
		// 32 tnv
		// 32 city
		//----
		// 34 IBAN
		// 11 BIC

	}

	static function fixLength($string, $length, $alignRight = false) {
		$l = mb_strlen($string);
		if($l >= $length) {
			return mb_substr($string,0,$length);
		}
		$w = str_repeat(' ', $length-$l);
		if($alignRight) {
			return $w . $string;
		} else {
			return $string . $w;
		}
	}

	static function parseDbValue($str) {
		if($str === NULL) {
			return NULL;
		}
		if(mb_strlen($str) < 34) {
			return NULL;
		}
		return array('nr' => trim(mb_substr($str, 2, 32)),
 					 'country' => mb_substr($str, 0, 2),
					 'name' => trim(mb_substr($str, 34,32)),
					 'city' => trim(mb_substr($str,66,32)),
					 'iban' => trim(mb_substr($str,98,34)),
					 'bic' => trim(mb_substr($str,132,11))
					 );
	}

	static function getDefaultCountry() {
		return 'nl';
	}

	static function getValidCountries() {
		return array('nl', 'be');
	}

	static function getCountryLabel($country) {
		static $countries = array('nl' => 'Nederland',
								  'be' => 'België');
		if(!isset($countries[$country])) {
			raise_error('unknown country: ' . $country, NOTICE);
			return '';
		}
		return $countries[$country];
	}


	/**
	 * enriched een bankAccount naar IBAN
	 * @param BankAccount $account
	 * @param             bool extended: Probeert wat alternatieven die tijd kunnen kosten.
	 * @deprecated
	 */
	static function enrichToIBAN(&$account, $extended = false) {

		return;
		/*
		if(!BankAccount::isValidAccount($account) || BankAccount::isEmptyAccount($account)) {
			return;
		}

		// probeer eerst lokaal, en daarna met een webservice het iban nummer te vinden:
		if($account['country'] == 'nl' || empty($account['country'])) {

			// Lokaal wordt in de algemene library niet ondersteund.
			// .. Code removed from library ..

			if($extended) {
				// probeer op te halen van webservice. bedankt openiban!

				$options = array('http' => array('method' => 'GET', 'max_redirects' => 3, 'timeout' => 1, 'ignore_errors' => '1', 'user_agent' => 'Mozilla/5.0 (Windows NT 6.3; Trident/7.0; rv:11.0) like Gecko'));
				$url = 'http://www.openiban.nl/?rekeningnummer=' . dbInt($account['nr']) . '&output=json';
				$context = stream_context_create($options);
				$result = file_get_contents($url, false, $context);
				if($result = json_decode($result, true, 512, JSON_BIGINT_AS_STRING)) {
					if(isset($result['iban']) && isset($result['bic'])) {
						$account['iban'] = $result['iban'];
						$account['bic'] = $result['bic'];
						if(!BankAccount::isValidAccount($account)) {
							// Output van deze service is niet goed:
							$account['iban'] = NULL;
							$account['bic'] = NULL;
							return;
						}

					}
				}
			}
		}
			*/
		return;
	}

	static function getComparable($bankAccountNr) {
		if($bankAccountNr === NULL) {
			return;
		}
		return $bankAccountNr['iban'];
	}


	/**
	 * Geeft terug of een account leeg is of niet
	 * @param <account> $account
	 * @return boolean
	 */
	static function isEmptyAccount($account) {
		if($account === NULL) {
			return true;
		}
		if(!is_array($account)) {
			return true;
		}

		if(empty($account['nr']) && empty($account['iban'])) {
			return true;
		}
		return false;
	}


	/**
	 * Vergelijkt twee rekeningnummers met elkaar en geeft terug of deze hetzelfde zijn ongeacht of de ene iban is en de andere nat
	 * @param array $a
	 * @param array $b
	 */
	static function compareAccountApproximate($a, $b) {

		if($a === NULL && $b === NULL) {
			return true;
		}
		if($a === NULL || $b === NULL) {
			return false;
		}

		//Beide nummers zijn een struct:

		// is er in beide een IBAN bekend?
		if(!empty($a['iban']) && !empty($b['iban'])) {
			return ($a['iban'] == $b['iban']);
		}

		// converteer iban van wel bekende naar nat
		if(!empty($a['iban']) && empty($a['nr'])) {
			$a = BankAccount::enrichNationalFromIBAN($a);
		}
		if(!empty($b['iban']) && empty($b['nr'])) {
			$b = BankAccount::enrichNationalFromIBAN($b);
		}

		if(!empty($a['nr']) && !empty($b['nr'])) {
			return ($a['nr'] == $b['nr']);
		}

		// allebei leeg?
		if(empty($a['nr']) && empty($b['nr'])) {
			return true;
		} else {
			return false;
		}
	}


	/**
	 * kijkt naar het nummer en genereert hier een accountnr uit als het nummer valide is.
	 * @param (account) || NULL
	 */
	static function parseAccountFromNumber($str) {

		$str = ltrim(strval(trim(strtoupper($str))), '0');

		if(empty($str)) {
			return NULL;
		}

		// Kan geen IBAN zijn:
		if(strlen($str) < 14) {
			$account = BankAccount::createAccountNr($str);
			if(BankAccount::isValidAccount($account)) {
				return $account;
			}
		} else {
			// Kan geen National zijn:
			$account = BankAccount::createAccountNr(NULL, NULL, NULL, substr($str, 0, 2), $str);
			if(BankAccount::isValidAccount($account)) {
				$account = BankAccount::enrichNationalFromIBAN($account);
				$account = BankAccount::enrichBICFromIBAN($account);
			} else {
				$account = NULL;
			}
			return $account;
		}
	}

	static function mod97($number) {
		$mod = 0;
		$number = (String)$number;
		$number = ltrim($number, '0');
		for($i = 0; $i < strlen($number); $i++) {
			$currentDigit = (Int)substr($number, $i, 1);
			$mod = ((10 * $mod) + $currentDigit) % 97;
		}
		return $mod;
	}


	/**
	 * Controleer of twee rekeningnummers identiek zijn (Voor database vergelijkingen bijvoorbeeld)
	 * @param type $a
	 * @param type $b
	 * @return boolean
	 */
	static function equals($a, $b) {
		if(BankAccount::isEmptyAccount($a) && BankAccount::isEmptyAccount($b)) {
			return true;
		}
		if(BankAccount::isEmptyAccount($a) || BankAccount::isEmptyAccount($b)) {
			return false;
		}

		//Beide nummers zijn een struct:
		$equal = true;

		// is er in beide een IBAN bekend?
		if(!empty($a['iban']) && !empty($b['iban'])) {
			$equal = ($a['iban'] == $b['iban']);
		}

		if($equal) {
			if(!empty($a['nr']) && !empty($b['nr'])) {
				$equal = ($a['nr'] == $b['nr']);
			}
		}
		if($equal) {
			if(isset($a['tnv'])) {
				$a['name'] = $a['tnv'];
			}
			if(isset($b['tnv'])) {
				$b['name'] = $b['tnv'];
			}

			if(!empty($a['name']) && !empty($b['name'])) {
				$equal = ($a['name'] == $b['name']);
			}
		}

		return $equal;
	}

	/**
	 * Conscribo used to use 'tnv' instead of name. To make sure this doesn't cause problems we check both on read
	 */
	public static function getNameFromAccount($account) {
		if(isset($account['name'])) {
			return $account['name'];
		}
		if(isset($account['tnv'])) {
			return $account['tnv'];
		}
		return '';
	}

}

?>
