<?php

class XLSReader
{
	public const BIFF8 = 0x600;
	public const BIFF7 = 0x500;
	public const WORKBOOKGLOBALS = 0x5;
	public const WORKSHEET = 0x10;

	public const TYPE_EOF = 0x0a;
	public const TYPE_BOUNDSHEET = 0x85;
	public const TYPE_DIMENSION = 0x200;
	public const TYPE_ROW = 0x208;
	public const TYPE_DBCELL = 0xd7;
	public const TYPE_FILEPASS = 0x2f;
	public const TYPE_RK = 0x7e;
	public const TYPE_RK2 = 0x27e;
	public const TYPE_MULRK = 0xbd;
	public const TYPE_MULBLANK = 0xbe;
	public const TYPE_SST = 0xfc;
	public const TYPE_LABEL = 0x204;
	public const TYPE_LABELSST = 0xfd;
	public const TYPE_NUMBER = 0x203;
	public const TYPE_NAME = 0x18;
	public const TYPE_FORMULA = 0x406;
	public const TYPE_FORMULA2 = 0x6;
	public const TYPE_FORMAT = 0x41e;
	public const TYPE_XF = 0xe0;
	public const TYPE_BOOLERR = 0x205;
	public const TYPE_NINETEENFOUR = 0x22;
	public const TYPE_MERGEDCELLS = 0xE5;
	public const TYPE_WINDOW1 = 0x3D;
	public const DEF_NUM_FORMAT = '%s';

	// OLE
	public const NUM_BIG_BLOCK_DEPOT_BLOCKS_POS = 0x2c;
	public const SMALL_BLOCK_DEPOT_BLOCK_POS = 0x3c;
	public const ROOT_START_BLOCK_POS = 0x30;
	public const BIG_BLOCK_SIZE = 0x200;
	public const SMALL_BLOCK_SIZE = 0x40;
	public const EXTENSION_BLOCK_POS = 0x44;
	public const NUM_EXTENSION_BLOCK_POS = 0x48;
	public const PROPERTY_STORAGE_BLOCK_SIZE = 0x80;
	public const BIG_BLOCK_DEPOT_BLOCKS_POS = 0x4c;
	public const SMALL_BLOCK_THRESHOLD = 0x1000;
	// property storage offsets
	public const SIZE_OF_NAME_POS = 0x40;
	public const TYPE_POS = 0x42;
	public const START_BLOCK_POS = 0x74;
	public const SIZE_POS = 0x78;
	public $boundsheets = array();
	public $activeSheet = 0;

	public $formatRecords = array();

	public $sst = array();

	public $sheets = array();
	public $dateFormats = array(
		0xe => 'd/m/Y',
		0xf => 'd-M-Y',
		0x10 => 'd-M',
		0x11 => 'M-Y',
		0x12 => 'h:i a',
		0x13 => 'h:i:s a',
		0x14 => 'H:i',
		0x15 => 'H:i:s',
		0x16 => 'd/m/Y H:i',
		0x2d => 'i:s',
		0x2e => 'H:i:s',
		0x2f => 'i:s.S'
	);
	public $numberFormats = array(
		0x1 => '%1.0f',
		0x2 => '%1.2f',
		0x3 => '%1.0f',
		0x4 => '%1.2f',
		0x5 => '%1.0f',
		0x6 => '$%1.0f',
		0x7 => '$%1.2f',
		0x8 => '$%1.2f',
		0x9 => '%1.0f%%',
		0xa => '%1.2f%%',
		0xb => '%1.2f',
		0x25 => '%1.0f',
		0x26 => '%1.0f',
		0x27 => '%1.2f',
		0x28 => '%1.2f',
		0x29 => '%1.0f',
		0x2a => '$%1.0f',
		0x2b => '%1.2f',
		0x2c => '$%1.2f',
		0x30 => '%1.0f'
	);
	protected $datetimeFormat = 'Y-m-d H:i:s';
	protected $defaultEncoding = 'UTF-8';
	protected $defaultFormat = self::DEF_NUM_FORMAT;
	protected $columnsFormat = array();

	protected $nineteenFour;
	protected $multiplier;
	protected $sn;
	protected $curFormat;

	// OLERead
	protected $data;
	protected $bigBlockChain;
	protected $smallBlockChain;
	protected $rootEntry;
	protected $entry;
	protected $props;

	// sergey.shuchkin@gmail.com
	protected $wrkbook; // false - to use excel format
	protected $error = false;
	protected $debug;

	// {{{ Spreadsheet_Excel_Reader()
	public function __construct(string $filename, bool $isData = false, bool $debug = false)
	{
		$this->debug = $debug;
		$this->_oleread($filename, $isData);
		$this->_parse();
	}
	public static function parseFile($filename, $debug = false)
	{
		return self::parse($filename, false, $debug);
	}

	public static function parseData($data, $debug = false)
	{
		return self::parse($data, true, $debug);
	}
	public static function parse($filename, $isData = false, $debug = false)
	{
		$xls = new self($filename, $isData, $debug);
		if ($xls->success()) {
			return $xls;
		}
		self::parseError($xls->error());

		return false;
	}
	public static function parseError($set = false)
	{
		static $error = false;
		return $set ? $error = $set : $error;
	}
	public function error($set = false)
	{
		if ($set) {
			$this->error = $set;
			if ($this->debug) {
				trigger_error($set);
			}
		}

		return $this->error;
	}
	public function success(): bool
	{
		return !$this->error;
	}
	public function rows($sheetNum = 0, $limit = 0)
	{
		if ($this->sheets[$sheetNum]) {
			$s = $this->sheets[$sheetNum];
			$result = array();
			for ($i = 0; $i < $s['numRows']; $i++) {
				$r = array();
				for ($j = 0; $j < $s['numCols']; $j++) {
					$r[$j] = $s['cells'][$i][$j] ?? '';
				}
				$result[] = $r;
				$limit--;
				if ($limit === 0) {
					break;
				}
			}

			return $result;
		}

		return false;
	}
	public function rowsEx($sheetNum = 0, $limit = 0): array
	{
		if ($this->sheets[$sheetNum]) {
			$s = $this->sheets[$sheetNum];
			$result = array();
			for ($i = 0; $i < $s['numRows']; $i++) {
				$r = array();
				for ($j = 0; $j < $s['numCols']; $j++) {
					$v = $s['cellsInfo'][$i][$j] ?? array();
					//                    if ( $v['type'] === self::TYPE_RK || $v['type'] === self::TYPE_RK2 ||
					$v['value'] = $s['cells'][$i][$j] ?? '';
					$r[$j] = $v;
				}
				$result[] = $r;
				$limit--;
				if ($limit === 0) {
					break;
				}
			}

			return $result;
		}

		return [];
	}
	public function toHTML($worksheetIndex = 0): string
	{
		$s = '<table class=excel>';
		foreach ($this->rows($worksheetIndex) as $r) {
			$s .= '<tr>';
			foreach ($r as $c) {
				$s .= '<td nowrap>' . ($c === '' ? '&nbsp' : htmlspecialchars($c, ENT_QUOTES)) . '</td>';
			}
			$s .= "</tr>\r\n";
		}
		$s .= '</table>';

		return $s;
	}
	public function setDateTimeFormat($value): SimpleXLS
	{
		$this->datetimeFormat = is_string($value) ? $value : false;
		return $this;
	}
	public function sheetNames(): array
	{
		$result = array();
		foreach ($this->boundsheets as $k => $v) {
			$result[$k] = $v['name'];
		}
		return $result;
	}
	public function sheetName($index)
	{
		return isset($this->boundsheets[$index]) ? $this->boundsheets[$index]['name'] : null;
	}

	// }}}

	protected function _oleread($sFileName, $isData = false): bool
	{
		if ($isData) {
			$this->data = $sFileName;
		} else {
			// check if file exist and is readable (Darko Miljanovic)
			if (!is_readable($sFileName)) {
				$this->error('File not is readable ' . $sFileName);
				return false;
			}

			$this->data = file_get_contents($sFileName);
			if (!$this->data) {
				$this->error('File reading error ' . $sFileName);
				return false;
			}
		}
		//echo IDENTIFIER_OLE;
		//echo 'start';
		if (strpos($this->data, pack('CCCCCCCC', 0xd0, 0xcf, 0x11, 0xe0, 0xa1, 0xb1, 0x1a, 0xe1)) !== 0) {
			$this->error('File is not XLS');

			return false;
		}

		$numBigBlockDepotBlocks = $this->_getInt4d(self::NUM_BIG_BLOCK_DEPOT_BLOCKS_POS);
		$sbdStartBlock = $this->_getInt4d(self::SMALL_BLOCK_DEPOT_BLOCK_POS);
		$rootStartBlock = $this->_getInt4d(self::ROOT_START_BLOCK_POS);
		$extensionBlock = $this->_getInt4d(self::EXTENSION_BLOCK_POS);
		$numExtensionBlocks = $this->_getInt4d(self::NUM_EXTENSION_BLOCK_POS);

		$bigBlockDepotBlocks = array();
		$pos = self::BIG_BLOCK_DEPOT_BLOCKS_POS;

		$bbdBlocks = $numBigBlockDepotBlocks;

		if ($numExtensionBlocks !== 0) {
			$bbdBlocks = (self::BIG_BLOCK_SIZE - self::BIG_BLOCK_DEPOT_BLOCKS_POS) / 4;
		}

		for ($i = 0; $i < $bbdBlocks; $i++) {
			$bigBlockDepotBlocks[$i] = $this->_getInt4d($pos);
			$pos += 4;
		}


		for ($j = 0; $j < $numExtensionBlocks; $j++) {
			$pos = ($extensionBlock + 1) * self::BIG_BLOCK_SIZE;
			$blocksToRead = min($numBigBlockDepotBlocks - $bbdBlocks, self::BIG_BLOCK_SIZE / 4 - 1);

			for ($i = $bbdBlocks; $i < $bbdBlocks + $blocksToRead; $i++) {
				$bigBlockDepotBlocks[$i] = $this->_getInt4d($pos);
				$pos += 4;
			}

			$bbdBlocks += $blocksToRead;
			if ($bbdBlocks < $numBigBlockDepotBlocks) {
				$extensionBlock = $this->_getInt4d($pos);
			}
		}

		$index = 0;
		$this->bigBlockChain = array();

		for ($i = 0; $i < $numBigBlockDepotBlocks; $i++) {
			$pos = ($bigBlockDepotBlocks[$i] + 1) * self::BIG_BLOCK_SIZE;

			for ($j = 0; $j < self::BIG_BLOCK_SIZE / 4; $j++) {
				$this->bigBlockChain[$index] = $this->_getInt4d($pos);
				$pos += 4;
				$index++;
			}
		}

		$index = 0;
		$sbdBlock = $sbdStartBlock;
		$this->smallBlockChain = array();

		while ($sbdBlock !== -2) {
			$pos = ($sbdBlock + 1) * self::BIG_BLOCK_SIZE;

			for ($j = 0; $j < self::BIG_BLOCK_SIZE / 4; $j++) {
				$this->smallBlockChain[$index] = $this->_getInt4d($pos);
				$pos += 4;
				$index++;
			}

			$sbdBlock = $this->bigBlockChain[$sbdBlock];
		}


		// readData(rootStartBlock)
		$block = $rootStartBlock;

		$this->entry = $this->_readData($block);

		$this->_readPropertySets();
		$this->data = $this->_readWorkBook();

		return true;
	}

	protected function _getInt2d($pos): int
	{
		return ord($this->data[$pos]) | ord($this->data[$pos + 1]) << 8;
	}
	protected function _getInt4d($pos): int
	{
		$value = ord($this->data[$pos]) | (ord($this->data[$pos + 1]) << 8) | (ord($this->data[$pos + 2]) << 16) | (ord($this->data[$pos + 3]) << 24);
		return ($value > 0x7FFFFFFF) ? $value - 0x100000000 : $value;
	}

	protected function _readData($bl): string
	{
		$block = $bl;

		$data = '';

		while ($block !== -2) {
			$pos = ($block + 1) * self::BIG_BLOCK_SIZE;
			$data .= substr($this->data, $pos, self::BIG_BLOCK_SIZE);
			$block = $this->bigBlockChain[$block];
		}

		return $data;
	}

	protected function _readPropertySets(): void
	{
		$offset = 0;
		while ($offset < strlen($this->entry)) {
			$d = substr($this->entry, $offset, self::PROPERTY_STORAGE_BLOCK_SIZE);

			$nameSize = ord($d[self::SIZE_OF_NAME_POS]) | (ord($d[self::SIZE_OF_NAME_POS + 1]) << 8);

			$type = ord($d[self::TYPE_POS]);

			$startBlock = ord($d[self::START_BLOCK_POS]) | (ord($d[self::START_BLOCK_POS + 1]) << 8) | (ord($d[self::START_BLOCK_POS + 2]) << 16) | (ord($d[self::START_BLOCK_POS + 3]) << 24);
			$size = ord($d[self::SIZE_POS]) | (ord($d[self::SIZE_POS + 1]) << 8) | (ord($d[self::SIZE_POS + 2]) << 16) | (ord($d[self::SIZE_POS + 3]) << 24);

			$name = '';
			for ($i = 0; $i < $nameSize; $i++) {
				$name .= $d[$i];
			}

			$name = str_replace("\x00", '', $name);

			$this->props[] = array(
				'name' => $name,
				'type' => $type,
				'startBlock' => $startBlock,
				'size' => $size
			);

			if (($name === 'Workbook') || ($name === 'Book')) {
				$this->wrkbook = count($this->props) - 1;
			}

			if ($name === 'Root Entry') {
				$this->rootEntry = count($this->props) - 1;
			}
			$offset += self::PROPERTY_STORAGE_BLOCK_SIZE;
		}
	}


	protected function _readWorkBook(): string
	{
		if ($this->props[$this->wrkbook]['size'] < self::SMALL_BLOCK_THRESHOLD) {
			$rootdata = $this->_readData($this->props[$this->rootEntry]['startBlock']);

			$streamData = '';
			$block = (int) $this->props[$this->wrkbook]['startBlock'];
			while ($block !== -2) {
				$pos = $block * self::SMALL_BLOCK_SIZE;
				$streamData .= substr($rootdata, $pos, self::SMALL_BLOCK_SIZE);

				$block = $this->smallBlockChain[$block];
			}

			return $streamData;
		}

		$numBlocks = $this->props[$this->wrkbook]['size'] / self::BIG_BLOCK_SIZE;
		if ($this->props[$this->wrkbook]['size'] % self::BIG_BLOCK_SIZE !== 0) {
			$numBlocks++;
		}

		if ($numBlocks === 0) {
			return '';
		}

		$streamData = '';
		$block = $this->props[$this->wrkbook]['startBlock'];

		//echo "block = $block";
		while ($block !== -2) {
			$pos = ($block + 1) * self::BIG_BLOCK_SIZE;
			$streamData .= substr($this->data, $pos, self::BIG_BLOCK_SIZE);
			$block = $this->bigBlockChain[$block];
		}

		return $streamData;
	}


	// }}}
	protected function parseSubstreamHeader($pos): array
	{
		$length = $this->_getInt2d($pos + 2);

		$version = $this->_getInt2d($pos + 4);
		$substreamType = $this->_getInt2d($pos + 6);
		return array($length, $version, $substreamType);
	}
	protected function _parse()
	{
		$pos = 0;

		[$length, $version, $substreamType] = $this->parseSubstreamHeader($pos);
		if (
			($version !== self::BIFF8) &&
			($version !== self::BIFF7)
		) {
			return false;
		}

		if ($substreamType !== self::WORKBOOKGLOBALS) {
			return false;
		}

		$pos += $length + 4;

		$code = ord($this->data[$pos]) | ord($this->data[$pos + 1]) << 8;
		$length = ord($this->data[$pos + 2]) | ord($this->data[$pos + 3]) << 8;

		while ($code !== self::TYPE_EOF) {
			switch ($code) {
				case self::TYPE_SST:
					$formattingRuns = 0;
					$extendedRunLength = 0;
					$spos = $pos + 4;
					$limitpos = $spos + $length;
					$uniqueStrings = $this->_getInt4d($spos + 4);
					$spos += 8;
					for ($i = 0; $i < $uniqueStrings; $i++) {
						if ($spos === $limitpos) {
							$opcode = ord($this->data[$spos]) | ord($this->data[$spos + 1]) << 8;
							$conlength = ord($this->data[$spos + 2]) | ord($this->data[$spos + 3]) << 8;
							if ($opcode !== 0x3c) {
								return -1;
							}
							$spos += 4;
							$limitpos = $spos + $conlength;
						}
						$numChars = ord($this->data[$spos]) | (ord($this->data[$spos + 1]) << 8);
						$spos += 2;
						$optionFlags = ord($this->data[$spos]);
						$spos++;
						$asciiEncoding = (($optionFlags & 0x01) === 0);
						$extendedString = (($optionFlags & 0x04) !== 0);

						$richString = (($optionFlags & 0x08) !== 0);

						if ($richString) {
							$formattingRuns = $this->_getInt2d($spos);
							$spos += 2;
						}

						if ($extendedString) {
							$extendedRunLength = $this->_getInt4d($spos);
							$spos += 4;
						}

						$len = $asciiEncoding ? $numChars : $numChars * 2;
						if ($spos + $len < $limitpos) {
							$retstr = substr($this->data, $spos, $len);
							$spos += $len;
						} else {
							$retstr = substr($this->data, $spos, $limitpos - $spos);
							$bytesRead = $limitpos - $spos;
							$charsLeft = $numChars - ($asciiEncoding ? $bytesRead : ($bytesRead / 2));
							$spos = $limitpos;

							while ($charsLeft > 0) {
								$opcode = $this->_getInt2d($spos);
								$conlength = $this->_getInt2d($spos + 2);
								if ($opcode !== 0x3c) {
									return -1;
								}
								$spos += 4;
								$limitpos = $spos + $conlength;
								$option = ord($this->data[$spos]);
								$spos++;
								if ($asciiEncoding && ($option === 0)) {
									$len = min($charsLeft, $limitpos - $spos); // min($charsLeft, $conlength);
									$retstr .= substr($this->data, $spos, $len);
									$charsLeft -= $len;
									$asciiEncoding = true;
								} elseif (!$asciiEncoding && ($option !== 0)) {
									$len = min($charsLeft * 2, $limitpos - $spos); // min($charsLeft, $conlength);
									$retstr .= substr($this->data, $spos, $len);
									$charsLeft -= $len / 2;
									$asciiEncoding = false;
								} elseif (!$asciiEncoding && ($option === 0)) {
									$len = min($charsLeft, $limitpos - $spos); // min($charsLeft, $conlength);
									for ($j = 0; $j < $len; $j++) {
										$retstr .= $this->data[$spos + $j] . chr(0);
									}
									$charsLeft -= $len;
									$asciiEncoding = false;
								} else {
									$newstr = '';
									for ($j = 0, $len_retstr = strlen($retstr); $j < $len_retstr; $j++) {
										$newstr = $retstr[$j] . chr(0);
									}
									$retstr = $newstr;
									$len = min($charsLeft * 2, $limitpos - $spos); // min($charsLeft, $conlength);
									$retstr .= substr($this->data, $spos, $len);
									$charsLeft -= $len / 2;
									$asciiEncoding = false;
								}
								$spos += $len;
							}
						}
						$retstr = $asciiEncoding ? $this->_latin1toDef($retstr) : $this->_UTF16toDef($retstr);
						//                                              echo "Str $i = $retstr\n";
						if ($richString) {
							$spos += 4 * $formattingRuns;
						}

						// For extended strings, skip over the extended string data
						if ($extendedString) {
							$spos += $extendedRunLength;
						}
						$this->sst[] = $retstr;
					}
					break;

				case self::TYPE_FILEPASS:
					return false;
				case self::TYPE_NAME:
					break;
				case self::TYPE_FORMAT:
					$indexCode = $this->_getInt2d($pos + 4);

					if ($version === self::BIFF8) {
						$numchars = $this->_getInt2d($pos + 6);
						if (ord($this->data[$pos + 8]) === 0) { // ascii
							$formatString = substr($this->data, $pos + 9, $numchars);
							$formatString = $this->_latin1toDef($formatString);
						} else {
							$formatString = substr($this->data, $pos + 9, $numchars * 2);
							$formatString = $this->_UTF16toDef($formatString);
						}
					} else {
						$numchars = ord($this->data[$pos + 6]);
						$formatString = substr($this->data, $pos + 7, $numchars * 2);
						$formatString = $this->_latin1toDef($formatString);
					}

					$this->formatRecords[$indexCode] = $formatString;
					break;
				case self::TYPE_XF:
					$formatstr = '';
					$indexCode = $this->_getInt2d($pos + 6);
					if (array_key_exists($indexCode, $this->dateFormats)) {
						$this->formatRecords['xfrecords'][] = array(
							'type' => 'date',
							'format' => $this->dateFormats[$indexCode]
						);
					} elseif (array_key_exists($indexCode, $this->numberFormats)) {
						$this->formatRecords['xfrecords'][] = array(
							'type' => 'number',
							'format' => $this->numberFormats[$indexCode]
						);
					} else {
						$isdate = false;
						if ($indexCode > 0) {
							if (isset($this->formatRecords[$indexCode])) {
								$formatstr = $this->formatRecords[$indexCode];
							}
							$fs = str_replace('\\', '', $formatstr);
							if ($fs && preg_match('/^[hmsday\/\-:\., ]+$/i', $fs)) { // found day and time format
								$isdate = true;
								$formatstr = str_replace(array('yyyy', ':mm', 'mm', 'dddd', 'dd', 'h', 'ss'), array('Y', ':i', 'm', 'l', 'd', 'H', 's'), $fs);
							}
						}

						if ($isdate) {
							$this->formatRecords['xfrecords'][] = array(
								'type' => 'date',
								'format' => $formatstr,
								'code' => $indexCode
							);
						} else {
							//                          echo 'fs='.$formatstr.PHP_EOL;
							$this->formatRecords['xfrecords'][] = array(
								'type' => 'other',
								'format' => '',
								'code' => $indexCode
							);
						}
					}
					break;
				case self::TYPE_NINETEENFOUR:
					$this->nineteenFour = (ord($this->data[$pos + 4]) === 1);
					break;
				case self::TYPE_BOUNDSHEET:
					$rec_offset = $this->_getInt4d($pos + 4);
					$rec_length = ord($this->data[$pos + 10]);
					$hidden = false;
					$rec_name = '';
					if ($version === self::BIFF8) {
						//ord($this->data[$pos + 9])
						$hidden = ord($this->data[$pos + 8]) === 1;
						$chartype = ord($this->data[$pos + 11]);
						if ($chartype === 0) {
							$rec_name = substr($this->data, $pos + 12, $rec_length);
							$rec_name = $this->_latin1toDef($rec_name);
						} else {
							$rec_name = substr($this->data, $pos + 12, $rec_length * 2);
							$rec_name = $this->_UTF16toDef($rec_name);
						}
					} elseif ($version === self::BIFF7) {
						$rec_name = substr($this->data, $pos + 11, $rec_length);
					}
					$this->boundsheets[] = array(
						'name' => $rec_name,
						'offset' => $rec_offset,
						'hidden' => $hidden,
						'active' => false
					);

					break;

				case self::TYPE_WINDOW1:
					$this->activeSheet = $this->_getInt2d($pos + 14);
					break;
			}

			$pos += $length + 4;
			$code = $this->_getInt2d($pos);
			$length = $this->_getInt2d($pos + 2);

		}

		foreach ($this->boundsheets as $key => $val) {
			$this->sn = $key;
			$this->_parseSheet($val['offset']);
			if ($key === $this->activeSheet) {
				$this->boundsheets[$key]['active'] = true;
			}
		}

		return true;
	}
	protected function _latin1toDef($string)
	{
		$result = $string;
		if ($this->defaultEncoding) {
			$result = mb_convert_encoding($string, $this->defaultEncoding, 'ISO-8859-1');
		}

		return $result;
	}
	protected function _UTF16toDef($string)
	{
		$result = $string;
		if ($this->defaultEncoding && $this->defaultEncoding !== 'UTF-16LE') {
			$result = mb_convert_encoding($string, $this->defaultEncoding, 'UTF-16LE');
		}

		return $result;
	}

	protected function _parseSheet($spos): bool
	{
		$cont = true;
		[$length, $version, $substreamType] = $this->parseSubstreamHeader($spos);

		if (($version !== self::BIFF8) && ($version !== self::BIFF7)) {
			return false;
		}

		if ($substreamType !== self::WORKSHEET) {
			return false;
		}
		$spos += $length + 4;

		$this->sheets[$this->sn]['maxrow'] = 0;
		$this->sheets[$this->sn]['maxcol'] = 0;
		$this->sheets[$this->sn]['numRows'] = 0;
		$this->sheets[$this->sn]['numCols'] = 0;

		while ($cont) {
			$lowcode = ord($this->data[$spos]);
			if ($lowcode === self::TYPE_EOF) {
				break;
			}
			$t_code = $lowcode | ord($this->data[$spos + 1]) << 8;
			$length = ord($this->data[$spos + 2]) | ord($this->data[$spos + 3]) << 8;
			$spos += 4;

			$this->multiplier = 1; // need for format with %
			switch ($t_code) {
				case self::TYPE_DIMENSION:
					//echo 'Type_DIMENSION ';
					if (!isset($this->numRows)) {
						if (($length === 10) || ($version === self::BIFF7)) {
							$this->sheets[$this->sn]['numRows'] = ord($this->data[$spos + 2]) | ord($this->data[$spos + 3]) << 8;
							$this->sheets[$this->sn]['numCols'] = ord($this->data[$spos + 6]) | ord($this->data[$spos + 7]) << 8;
						} else {
							$this->sheets[$this->sn]['numRows'] = ord($this->data[$spos + 4]) | ord($this->data[$spos + 5]) << 8;
							$this->sheets[$this->sn]['numCols'] = ord($this->data[$spos + 10]) | ord($this->data[$spos + 11]) << 8;
						}
					}
					break;
				case self::TYPE_MERGEDCELLS:
					$cellRanges = ord($this->data[$spos]) | ord($this->data[$spos + 1]) << 8;
					for ($i = 0; $i < $cellRanges; $i++) {
						$fr = ord($this->data[$spos + 8 * $i + 2]) | ord($this->data[$spos + 8 * $i + 3]) << 8;
						$lr = ord($this->data[$spos + 8 * $i + 4]) | ord($this->data[$spos + 8 * $i + 5]) << 8;
						$fc = ord($this->data[$spos + 8 * $i + 6]) | ord($this->data[$spos + 8 * $i + 7]) << 8;
						$lc = ord($this->data[$spos + 8 * $i + 8]) | ord($this->data[$spos + 8 * $i + 9]) << 8;
						if ($lr - $fr > 0) {
							$this->sheets[$this->sn]['cellsInfo'][$fr + 1][$fc + 1]['rowspan'] = $lr - $fr + 1;
						}
						if ($lc - $fc > 0) {
							$this->sheets[$this->sn]['cellsInfo'][$fr + 1][$fc + 1]['colspan'] = $lc - $fc + 1;
						}
					}
					break;
				case self::TYPE_RK:
				case self::TYPE_RK2:
					$row = ord($this->data[$spos]) | ord($this->data[$spos + 1]) << 8;
					$column = ord($this->data[$spos + 2]) | ord($this->data[$spos + 3]) << 8;
					$rknum = $this->_getInt4d($spos + 6);
					$numValue = $this->_getIEEE754($rknum);
					$t_alias = 'n';
					if ($this->isDate($spos)) {
						[$string, $raw] = $this->createDate($numValue);
						$t_alias = 'd';
					} else {
						$raw = $numValue;
						if (isset($this->columnsFormat[$column + 1])) {
							$this->curFormat = $this->columnsFormat[$column + 1];
						}
						$string = sprintf($this->curFormat, $numValue * $this->multiplier);
					}
					$this->addCell($row, $column, $string, $raw, $t_code, $t_alias);
					break;
				case self::TYPE_LABELSST:
					$row = ord($this->data[$spos]) | ord($this->data[$spos + 1]) << 8;
					$column = ord($this->data[$spos + 2]) | ord($this->data[$spos + 3]) << 8;
					$index = $this->_getInt4d($spos + 6);
					$this->addCell($row, $column, $this->sst[$index], $index, $t_code, 's');
					break;
				case self::TYPE_MULRK:
					$row = ord($this->data[$spos]) | ord($this->data[$spos + 1]) << 8;
					$colFirst = ord($this->data[$spos + 2]) | ord($this->data[$spos + 3]) << 8;
					$colLast = ord($this->data[$spos + $length - 2]) | ord($this->data[$spos + $length - 1]) << 8;
					$columns = $colLast - $colFirst + 1;
					$tmppos = $spos + 4;
					$t_alias = 'n';
					for ($i = 0; $i < $columns; $i++) {
						$numValue = $this->_getIEEE754($this->_getInt4d($tmppos + 2));
						if ($this->isDate($tmppos - 4)) {
							[$string, $raw] = $this->createDate($numValue);
							$t_alias = 'd';
						} else {
							$raw = $numValue;
							if (isset($this->columnsFormat[$colFirst + $i + 1])) {
								$this->curFormat = $this->columnsFormat[$colFirst + $i + 1];
							}
							$string = sprintf($this->curFormat, $numValue * $this->multiplier);
						}
						$tmppos += 6;
						$this->addCell($row, $colFirst + $i, $string, $raw, $t_code, $t_alias);
					}
					break;
				case self::TYPE_NUMBER:
					$row = ord($this->data[$spos]) | ord($this->data[$spos + 1]) << 8;
					$column = ord($this->data[$spos + 2]) | ord($this->data[$spos + 3]) << 8;
					$tmp = unpack('ddouble', substr($this->data, $spos + 6, 8)); // It machine machine dependent
					$t_alias = 'n';
					if ($this->isDate($spos)) {
						[$string, $raw] = $this->createDate($tmp['double']);
						$t_alias = 'd';
					} else {
						if (isset($this->columnsFormat[$column + 1])) {
							$this->curFormat = $this->columnsFormat[$column + 1];
						}
						$raw = $this->createNumber($spos);
						$string = sprintf($this->curFormat, $raw * $this->multiplier);
					}
					$this->addCell($row, $column, $string, $raw, $t_code, $t_alias);
					break;
				case self::TYPE_FORMULA:
				case self::TYPE_FORMULA2:
					$row = ord($this->data[$spos]) | ord($this->data[$spos + 1]) << 8;
					$column = ord($this->data[$spos + 2]) | ord($this->data[$spos + 3]) << 8;
					if (!(ord($this->data[$spos + 6]) < 4 && ord($this->data[$spos + 12]) === 255 && ord($this->data[$spos + 13]) === 255)) {
						$tmp = unpack('ddouble', substr($this->data, $spos + 6, 8)); // It machine machine dependent
						if ($this->isDate($spos)) {
							[$string, $raw] = $this->createDate($tmp['double']);
						} else {
							if (isset($this->columnsFormat[$column + 1])) {
								$this->curFormat = $this->columnsFormat[$column + 1];
							}
							$raw = $this->createNumber($spos);
							$string = sprintf($this->curFormat, $raw * $this->multiplier);
						}
						$this->addCell($row, $column, $string, $raw, $t_code, 'f');
					}
					break;
				case self::TYPE_BOOLERR:
					$row = $this->_getInt2d($spos);
					$column = $this->_getInt2d($spos + 2);
					$string = ord($this->data[$spos + 6]);
					$this->addCell($row, $column, $string, $string, $t_code, 'b');
					break;
				case self::TYPE_ROW:
				case self::TYPE_DBCELL:
				case self::TYPE_MULBLANK:
					break;
				case self::TYPE_LABEL:
					$row = $this->_getInt2d($spos);
					$column = $this->_getInt2d($spos);
					$string = substr($this->data, $spos + 8, ord($this->data[$spos + 6]) | ord($this->data[$spos + 7]) << 8);
					$this->addCell($row, $column, $string, '', $t_code, 'inlineStr');
					break;

				case self::TYPE_EOF:
					$cont = false;
					break;
				default:
					break;
			}
			$spos += $length;
		}

		if ($this->sheets[$this->sn]['numRows'] === 0) {
			$this->sheets[$this->sn]['numRows'] = $this->sheets[$this->sn]['maxrow'];
		}
		if ($this->sheets[$this->sn]['numCols'] === 0) {
			$this->sheets[$this->sn]['numCols'] = $this->sheets[$this->sn]['maxcol'];
		}

		return true;
	}

	protected function _getIEEE754($rknum)
	{
		if (($rknum & 0x02) !== 0) {
			$value = $rknum >> 2;
		} else {
			$sign = ($rknum & 0x80000000) >> 31;
			$exp = ($rknum & 0x7ff00000) >> 20;
			$mantissa = (0x100000 | ($rknum & 0x000ffffc));
			$value = $mantissa / (2 ** (20 - ($exp - 1023)));
			if ($sign) {
				$value = -1 * $value;
			}
		}

		if (($rknum & 0x01) !== 0) {
			$value /= 100;
		}

		return $value;
	}

	protected function isDate($spos): bool
	{
		$xfindex = ord($this->data[$spos + 4]) | ord($this->data[$spos + 5]) << 8;



		if ($this->formatRecords['xfrecords'][$xfindex]['type'] === 'date') {
			$this->curFormat = $this->formatRecords['xfrecords'][$xfindex]['format'];

			return true;
		}

		if ($this->formatRecords['xfrecords'][$xfindex]['type'] === 'number') {
			$this->curFormat = $this->formatRecords['xfrecords'][$xfindex]['format'];
			if (strpos($this->curFormat, '%%') !== false) {
				$this->multiplier = 100;
			}
		} else {
			$this->curFormat = $this->defaultFormat;
		}

		return false;
	}
	public function createDate(int $timevalue): array
	{
		if ($timevalue > 1) {
			$timevalue -= ($this->nineteenFour ? 24107 : 25569);
		}
		$ts = round($timevalue * 24 * 3600);
		$string = $this->datetimeFormat ? gmdate($this->datetimeFormat, $ts) : gmdate($this->curFormat, $ts);
		return array($string, $ts);
	}

	protected function addCell($row, $col, $string, $raw = '', $type_code = 0, $type_alias = ''): void
	{
		$this->sheets[$this->sn]['maxrow'] = max($this->sheets[$this->sn]['maxrow'], $row);
		$this->sheets[$this->sn]['maxcol'] = max($this->sheets[$this->sn]['maxcol'], $col);
		$this->sheets[$this->sn]['cells'][$row][$col] = $string;
		if ($raw) {
			$this->sheets[$this->sn]['cellsInfo'][$row][$col]['raw'] = $raw;
		}
		if ($type_code) {
			$this->sheets[$this->sn]['cellsInfo'][$row][$col]['type'] = $type_code;
			$this->sheets[$this->sn]['cellsInfo'][$row][$col]['t'] = $type_alias;
		}
	}

	protected function createNumber($spos)
	{
		$rknumhigh = $this->_getInt4d($spos + 10);
		$rknumlow = $this->_getInt4d($spos + 6);
		$sign = ($rknumhigh & 0x80000000) >> 31;
		$exp = ($rknumhigh & 0x7ff00000) >> 20;
		$mantissa = (0x100000 | ($rknumhigh & 0x000fffff));
		$mantissalow1 = ($rknumlow & 0x80000000) >> 31;
		$mantissalow2 = ($rknumlow & 0x7fffffff);
		$value = $mantissa / (2 ** (20 - ($exp - 1023)));
		if ($mantissalow1 !== 0) {
			$value += 1 / (2 ** (21 - ($exp - 1023)));
		}
		$value += $mantissalow2 / (2 ** (52 - ($exp - 1023)));
		if ($sign) {
			$value = -1 * $value;
		}

		return $value;
	}

	public function setOutputEncoding(string $encoding): SimpleXLS
	{
		$this->defaultEncoding = $encoding;
		return $this;
	}


	public function setDefaultFormat(string $sFormat): SimpleXLS
	{
		$this->defaultFormat = $sFormat;
		return $this;
	}
	public function setColumnFormat(int $column, string $sFormat): SimpleXLS
	{
		$this->columnsFormat[$column] = $sFormat;
		return $this;
	}

}