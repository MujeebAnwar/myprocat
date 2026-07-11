<?php
class password_requirements_authenticator
{
	public function __construct()
	{
	}
	public function IsOk($password_to_validate,&$error)
	{
		if(strlen($password_to_validate) < 8)
		{
			$error = "Password too short, minimum length is 8.";
			return false;
		}
		if($password_to_validate === strtoupper($password_to_validate) || $password_to_validate === strtolower($password_to_validate))
		{
			$error = "Password must contain mixed case.";
			return false;
		}
		if(!preg_match('/[^A-Za-z ]/',$password_to_validate))
		{
			$error = "Password must contain a number or special character.";
			return false;
		}
		if(strpos($password_to_validate, '&') !== false || strpos($password_to_validate, ' ') !== false)
		{
			$error = "Password may not contain an ampersand (&) or space";
			return false;
		}
		return true;

	}
	public function javascript_validator()
	{
		return "function password_validator(el,targ)
		{
			let ermsg = '';
			if(el.value.length<8)
			{
				ermsg = 'too short';
			} else if(el.value.toLowerCase() == el.value || el.value.toUpperCase() == el.value)
			{
				ermsg = 'mixed case required';
			} else if(el.value.match(/[^A-Za-z ]/) == null) {
				ermsg = 'number or symbol required';
			} else if(el.value.indexOf('&') != -1 || el.value.indexOf(' ') != -1 ) {
				ermsg = 'no ampersand (&) or space allowed';
			}
			
			targ.innerHTML = ermsg;
			el.setCustomValidity(ermsg);
			if(ermsg)
			{
				el.setAttribute('required','true');
			} else {
				el.removeAttribute('required');
			}
		}";
	}
	public function javascript_compare_validator()
	{
		return "function compare_validator(el,comp,targ)
		{
			targ.innerHTML = '';
			if(el.value != comp.value)
			{
				targ.innerHTML = 'must match';
			}
		}";
	}
}
?>