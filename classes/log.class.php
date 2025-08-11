<?php

class log
{
	private string $fn_pattern;
	private string $pathname;
	private $fp;
	private string $format; // 'text', 'json'
	private string $tz;

	public function
	__construct(string $fn_pattern, string $format = 'json', string $tz = 'Europe/Paris')
	{
		$this -> fn_pattern = $fn_pattern;
		$this -> fp = null;
		$this -> pathname = '';
		$this -> format = $format;
		$this -> tz = $tz;
	}

	private function
	getFilename()
	{
		// do not recompute filename if already done
		// if a bunch of messages has to be written juste during file change,
		// it is easier to read if the whole group is in the same file,
		// i.e. the first one
		if ($this -> pathname == '')
		{
			$timestamp = time();
			$str = $this -> fn_pattern;
			$str = str_replace ('%Y', date('Y',$timestamp), $str);
			$str = str_replace ('%m', date('m',$timestamp), $str);
			$str = str_replace ('%d', date('d',$timestamp), $str);
			$str = str_replace ('%H', date('H',$timestamp), $str);
			$str = str_replace ('%i', date('i',$timestamp), $str);
			$str = str_replace ('%s', date('s',$timestamp), $str);
			$this -> pathname = $str;
		}
	}

	private function
	openFile (): void
	{
		$this -> getFilename();
		$this -> fp = @fopen ($this -> pathname, 'a+');
		if (!$this -> fp)
			throw new Exception ('Cannot open log file '.$this -> pathname);
	}

	private function
	closeFile(): void
	{
		if ($this -> fp != null)
		{
			fclose ($this -> fp);
			$this -> fp = null;
		}
	}

	private function
	buildHeader(): array
	{
		$a = [ ];
		$a[] = date('Y-m-d');
		$a[] = date('H');
		$a[] = date('i');
		$a[] = date('s');
		$a[] = session_id();
		return $a;
	}

	private function
	buildLine (array|string $parms): array
	{
		$a = $this -> buildHeader();
		if (is_array ($parms))
			$a = array_merge ($a, $parms);
		else
			$a[] = $parms;
		return $a;
	}

	private function
	formatLine (array|string $parms): string
	{
		$str = '';
		$a = $this -> buildLine ($parms);
		$str = implode (':', $a);
		return $str;
	}

	public function
	log (array|string $parms)
	{
		switch ($this -> format)
		{
		case 'text' :
			$str = $this -> formatLine ($parms);
			break;
		case 'json' :
			foreach ([ 'REMOTE_ADDR', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CF_CONNECTING_IP' ] as $key)
				if (array_key_exists ($key, $_SERVER))
					$parms['ip'] = $_SERVER[$key];
			$parms['session'] = session_id();
			$parms['date'] = date_format(date_create()->setTimezone(new DateTimeZone($this->tz)), 'c');
			$str = json_encode ($parms, JSON_UNESCAPED_SLASHES);
			break;
		}
		$this -> openFile();
		$l = fwrite ($this -> fp, $str . "\n");
		if ($l === false)
			throw new Exception ('Cannot write to log file');
		$this -> closeFile();
	}
}

?>
