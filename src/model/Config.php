<?php
declare (strict_types = 1);

namespace start\model;
use start\Model;

/**
 * @mixin think\model
 */
class Config extends Model
{
	protected $name = 'core_config';

	public function setPropsAttr($value)
	{
		return json_encode($value);
	}

	public function getPropsAttr($value)
	{
		return json_decode($value);
	}

	public function setOptionsAttr($value)
	{
		return json_encode($value);
	}

	public function getOptionsAttr($value)
	{
		return json_decode($value);
	}

	public function setValidateAttr($value)
	{
		return json_encode($value);
	}

	public function getValidateAttr($value)
	{
		return json_decode($value);
	}

	public function setIsLockingAttr($value)
	{
		if($value === true || $value === 'true'){
			return 1;
		}else{
			return 0;
		}
	}

	public function getIsLockingAttr($value)
	{
		return boolval($value);
	}


	public function getValueAttr($value, $data)
	{
		if(empty($value)){
			$value = $data['default'];
		}
		if($value === 'true' || $value === 'false'){
			return boolval($value);
		}
		return $value;
	}
}