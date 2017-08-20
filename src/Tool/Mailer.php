<?php

namespace CF;


use CF\Runtime\Runtime;

class Mailer {

	private $from;
	private $to;
	private $subject;
	private $nameFrom;
	private $nameTo;
	private $htmlContent;
	private $txtContent;
	private $attachments;

	private $relatedSectionPresent;

	function __construct($from, $to, $subject, $nameFrom = NULL, $nameTo = NULL) {
		$this->from = $from;
		$this->to = $to;
		$this->subject = $subject;
		$this->nameFrom = $nameFrom;
		$this->nameTo = $nameTo;
		$this->headers = array();
		$this->rawContent = NULL;
		$this->attachment = NULL;
		$this->errors = Runtime::gI()->createErrorCollection();
		$this->relatedSectionPresent = false;
	}

	function setHTMLContent($str) {
		$this->htmlContent = $str;
	}

	function setTXTContent($str) {
		$this->txtContent = $str;
	}

	function setRawContent($str) {
		$this->rawContent = $str;
	}

	function setAttachment($filename, $mimeType, $data) {
		$this->attachments = array();
		$this->addAttachment($filename, $mimeType, $data);
	}

	function addAttachment($fileName, $mimeType, $data) {
		if(!isset($this->attachments)) {
			$this->attachments = array();
		}
		$this->attachments[] = array('disposition' => 'attachment',
									 'name' => $fileName,
									 'mimeType' => $mimeType,
									 'data' => $data);
	}

	/**
	 * Voeg een inline afbeelding toe. vervang de src door 'cid:'. $cid (returnwaarde van deze functie)
	 * @param $fileName
	 * @param $mimeType
	 * @param $data
	 * @return string CID
	 */
	function addInlineAttachment($fileName, $mimeType, $data) {
		if(!isset($this->attachments)) {
			$this->attachments = array();
		}

		$contentId = $fileName . '@' . base64_encode(uniqid());
		$this->attachments[] = array('disposition' => 'inline',
									 'contentId' => $contentId,
									 'name' => $fileName,
									 'mimeType' => $mimeType,
									 'data' => $data);

		$this->relatedSectionPresent = true;

		return $contentId;
	}

	function setBulk() {
		$this->headers[] = 'Precedence: bulk';
	}

	function send($generateErrors = false) {
		array_unshift($this->headers, 'MIME-Version: 1.0;');


		if($this->nameFrom !== NULL && $this->nameFrom != $this->from) {

			$this->nameFrom = str_replace(array('@', ',', ';', ':', '<', '>'), '', $this->nameFrom);

			$this->headers[] = 'From: =?UTF-8?B?' . base64_encode($this->nameFrom) . '?= <' . $this->from . '>';
			$this->headers[] = 'Reply-To: =?UTF-8?B?' . base64_encode($this->nameFrom) . '?= <' . $this->from . '>';
			$this->headers[] = 'Return-Path: <' . $this->from . '>';
		} else {
			$this->headers[] = 'From: <' . $this->from . '>';
			$this->headers[] = 'Reply-To: <' . $this->from . '>';
			$this->headers[] = 'Return-Path: <' . $this->from . '>';
		}

		$this->headers[] = 'Date: ' . date('r');
		$this->headers[] = 'X-Mailer: Conscribo Framework Library';

		$generalBoundary = md5(uniqid());

		$alternativeBoundary = "_0_" . $generalBoundary;

		if(is_array($this->attachments) && count($this->attachments) > 0) {
			$mixedBoundary = "_1_" . $generalBoundary;
			$this->headers[] = 'Content-Type: multipart/mixed; boundary=' . $mixedBoundary . "\r\n";

		} else {
			$this->headers[] = 'Content-type: multipart/alternative;boundary=' . $alternativeBoundary . "\r\n";
		}


		$headerStr = implode("\n", $this->headers);

		$body = '';
		if(is_array($this->attachments) && count($this->attachments) > 0) {
			$this->headers = array();
			$this->headers[] = "--" . $mixedBoundary;
			$this->headers[] = 'Content-type: multipart/alternative;boundary=' . $alternativeBoundary . "\r\n\r\n";
			$body = implode("\r\n", $this->headers);
		}


		$body .= "This is a multi-part message in MIME format.\r\n" .
			"\r\n" .
			"--" . $alternativeBoundary . "\r\n" .
			"Content-Type: text/plain; charset=UTF-8\r\n" .
			"\r\n" .
			$this->formatText($this->txtContent) . "\r\n" .
			"\r\n" .
			"--" . $alternativeBoundary . "\r\n";

		if($this->relatedSectionPresent) {
			$relatedBoundary = "_2_" . $generalBoundary;
			$body .= 'Content-Type: multipart/related; boundary=' . $relatedBoundary . "\r\n\r\n";
			$body .= "--" . $relatedBoundary . "\r\n";

		}

		$body .= "Content-Type: text/html; charset=UTF-8\r\n" .
			"\r\n" .
			$this->formatHtml($this->htmlContent) . "\r\n" .
			"\r\n";

		if($this->relatedSectionPresent) {

			foreach($this->attachments as $attachment) {
				if($attachment['disposition'] != 'inline') {
					continue;
				}
				$body .= "--" . $relatedBoundary . "\r\n";
				$body .= "Content-Type: " . $attachment['mimeType'] . ";\r\n" .
					" name=\"" . $attachment['name'] . "\"\r\n";
				$body .= "Content-ID: <" . $attachment['contentId'] . ">\r\n";
				$body .= "Content-Transfer-Encoding: base64\r\n" .
					"Content-Disposition: " . $attachment['disposition'] . ";\r\n" .
					" filename=\"" . $attachment['name'] . "\";\r\n" .
					" size=" . strlen($attachment['data']) . "\r\n".
					"\r\n";

				$body .= chunk_split(base64_encode($attachment['data']));
				$body .= "\r\n";

			}

			$body .= "--" . $relatedBoundary . "--\r\n\r\n";

		}

		$body .= "--" . $alternativeBoundary . "--" . "\r\n";

		if(is_array($this->attachments) && count($this->attachments) > 0) {

			foreach($this->attachments as $attachment) {
				if($attachment['disposition'] != 'attachment') {
					continue;
				}

				$body .= "--" . $mixedBoundary . "\r\n";

				$body .= "Content-Type: " . $attachment['mimeType'] . ";\r\n" .
					" name=\"" . $attachment['name'] . "\"\r\n";

				$body .= "Content-Transfer-Encoding: base64\r\n" .
					"Content-Disposition: " . $attachment['disposition'] . ";\r\n" .
					" filename=\"" . $attachment['name'] . "\";\r\n" .
					" size=" . strlen($attachment['data']) . "\r\n\r\n";

				$body .= chunk_split(base64_encode($attachment['data']));
				$body .= "\r\n";
			}
			$body .= "--" . $mixedBoundary . "--\r\n";
		}

		$body .= "\r\n";

		$body = str_replace("\n.", "\n..", $body);

		if(_DEBUGGING_) {
			$this->to = 'dev-' . str_replace(array('@', ',', ';', ':'), '', $this->to) . '@'. Runtime::gI()->getHostName();

			if(isset($_ENV['NoMail']) && $_ENV['NoMail']) {
				// Dit is een test. We sturen hierbij op dit moment geen mails.
				return true;
			}
		}

		if(!empty($this->nameTo) && $this->nameTo != $this->to) {
			$this->nameTo = str_replace(array('@', ',', ';', ':', '<', '>'), '', $this->nameTo);

			$toString = '=?UTF-8?B?' . base64_encode($this->nameTo) . '?= <' . $this->to . '>';

		} else {
			$toString = $this->to;
		}

		$subjectStr = '=?UTF-8?B?' . base64_encode($this->subject) . '?=';

		if(mail($toString, $subjectStr, $body, $headerStr, '-f ' . $this->from)) {
			return true;
		} else {
			$this->errors->add('Failed to send mail to ' . $this->to);
			return false;
		}
	}

	function formatHtml($str) {
		return $str;
	}

	function formatText($str) {
		return wordwrap($str, 70);
	}

	public function getErrors() {
		return $this->errors;
	}

}

?>
