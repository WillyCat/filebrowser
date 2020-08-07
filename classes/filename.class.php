<?php

class filename
{
	private $original_pathname;
	private $real_pathname;

	public function
	__construct(string $pathname)
	{
		$pathname = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $pathname);
		$this -> original_pathname = $pathname;
		$this -> real_pathname = $this -> get_absolute_path($pathname);
	}

	// basename() relies on locale
	// this function does not
	public function
	get_basename (): string
	{
		$pathname = $this -> real_pathname;
		$parts = explode (DIRECTORY_SEPARATOR, $pathname);
		return array_pop ($parts);
	}

	// from  https://www.php.net/manual/fr/function.realpath.php
	// similar to realpath()
	// but always returns info, even if file is non-existent
	// realpath with return an empty string if file does not exists
	private function get_absolute_path(string $path) {
		$parts = array_filter(explode(DIRECTORY_SEPARATOR, $path), 'strlen');
		$absolutes = array();
		foreach ($parts as $part) {
		    if ('.' == $part) continue;
		    if ('..' == $part) {
			array_pop($absolutes);
		    } else {
			$absolutes[] = $part;
		    }
		}
		return DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $absolutes);
	    }

	public function
	get_real_pathname (): string
	{
		return $this -> real_pathname;
	}

	public function
	get_dirname (): string
	{
		return dirname ($this -> real_pathname);
	}
}

?>
