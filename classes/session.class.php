<?php
class session
{
	public function
	__construct()
	{
		session_start();
	}

	public function
	invalidate(): void
	{
		unset ($_SESSION['filebrowseruser']);
	}

	public function
	is_valid(): bool
	{
		return array_key_exists ('filebrowseruser', $_SESSION)
			&& ($_SESSION['filebrowseruser'] != '');
	}

	public function
	setLogin (string $username): void
	{
		$_SESSION['filebrowseruser'] = $username; // make session valid
	}

	public function
	getLogin(): string
	{
		if (!array_key_exists ('filebrowseruser', $_SESSION))
			return '';

		return $_SESSION['filebrowseruser'];
	}

}
?>
