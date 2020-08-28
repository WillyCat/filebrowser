function
revertSelection(from, to)
{
	for (let i = from ; i < to; i++)
	{
		let id = 'L'+i;

		if ($('#'+id).length == 0) // non-existent
			continue;

		if ($('#'+id).is(':checked') )
			$('#'+id).prop('checked', false);
		else
			$('#'+id).prop('checked', true);
	}
	showHideGroupActions(from, to);
	$('#LREVERT').prop('checked', false);
}

function
showHideGroupActions(from, to)
{
	let checked = 0;
	for (let i = from ; i < to; i++)
	{
		let id = 'L'+i;

		if ($('#'+id).length    // exists
		&&  $('#'+id).is(':checked') // is checked
		) 
			checked++;
	}
	if (checked > 0)
		showGroupActions();
	else
		hideGroupActions();
}

function
showGroupActions()
{
console.log ('showGroupActions');
	$('#multi').show();
}

function
hideGroupActions()
{
console.log ('hideGroupActions');
	$('#multi').hide();
}
