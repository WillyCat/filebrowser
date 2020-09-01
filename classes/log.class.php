<?php

class log
{
	private $fn_pattern;
	var $pathname;
	private $fp;

	public function
	__construct(string $fn_pattern)
	{
		$this -> fn_pattern = $fn_pattern;
		$this -> fp = null;
		$this -> pathname = '';
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
			$timestamp = date();
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
		$this -> fp = fopen ($this -> pathname, 'a+');
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
	buildLine ($parms): array
	{
		$a = $this -> buildHeader();
		if (is_array ($parms))
			$a = array_merge ($a, $parms);
		else
			$a[] = $parms;
		return $a;
	}

	private function
	formatLine ($parms): string
	{
		$a = $this -> buildLine ($parms);
		$str = implode (':', $a);
		return $str;
	}

	public function
	log ($parms)
	{
		$str = $this -> formatLine ($parms);
		$this -> openFile();
		$l = fwrite ($this -> fp, $str . "\n");
		if ($l === false)
			throw new Exception ('Cannot write to log file');
//die (realpath('/tmp'));
//$dh = opendir ('/tmp');
//while (($file = readdir ($dh)) !== false)
//echo $file . '<br>';
//closedir ($dh);
//die ('data written to '.$this->pathname.': '.$l.' bytes');
		$this -> closeFile();
	}
}

?>
