<?php
class message
{
	private $msg;
	private $level; // primary (bleu), secondary (gris clair), success (vert), danger (rouge), warning (jaune), info (gris-bleu), light (blanc), dark (gris fonce)
	private $feather;
	private $buttons;
	private $parmskeys;

	function __construct ()
	{
		// fill missing entries
		$this -> parmskeys = [ 'msg', 'level', 'feather', 'buttons' ];
		foreach ($this -> parmskeys as $parmkey)
			$this -> $parmkey = '';

		// default values
		$this -> level = 'info';
	}

	// set a bunch of properties
	function set (array $parms): void
	{
		foreach ($this -> parmskeys as $parmkey)
			if (array_key_exists ($parmkey, $parms))
				$this -> $parmkey = $parms[$parmkey];
	}

	// build HTML for message display
	function html(): string
	{
		$str = '';
		if ($this -> msg != '')
		{
			$str .= '<div class="alert alert-' . $this -> level . '" role="alert">';
			if ($this -> feather != '')
				$str .= '<span class="feather-32" data-feather="'.$this -> feather.'"></span>';

			  $str .= '<div class="align-middle message">' . htmlentities($this->msg) . '</div>';

			if ($this -> buttons != '')
			{
				$str .= '&nbsp;';
				$str .= '&nbsp;';
				$str .= '&nbsp;';
				foreach ($this -> buttons as $button)
					$str .= $button . '&nbsp;';
			}
			$str .= '</div>';
		}
		return $str;
	}

	// send HTML to stdout
	function display(): void
	{
		echo $this -> html();
	}

	// return raw message
	function getMsg(): string
	{
		return $this -> msg;
	}
}
?>
