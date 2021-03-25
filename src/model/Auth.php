<?php
declare (strict_types = 1);

namespace start\model;
use start\Model;
/**
 * @mixin think\model
 */
class Auth extends Model
{
	protected $name = 'core_auth';

	public function nodes()
	{
		return $this->hasMany("Node",'auth','id')->field(['auth','node','half']);
	}
}