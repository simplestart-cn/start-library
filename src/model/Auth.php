<?php
declare (strict_types = 1);

namespace start\model;
use start\Model;

/**
 * @mixin start\model
 */
class Auth extends Model
{
	protected $name = 'core_auth';

	public function nodes()
	{
		return $this->hasMany("Node",'auth','name')->field(['auth','node','half']);
	}
}