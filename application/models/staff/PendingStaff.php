<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class PendingStaff extends MY_Model {
	protected $table  = 'pending_staff';

	protected function setRelations() {
		$this->addRelation(array(
			'primary'   => 'id',
			'table'     => 'pending_staff_names',
			'foreign'   => 'id_pending_staff',
			'variable'  => 'names'
		));

    $this->addRelation(array(
			'primary'   => 'id_user',
			'table'     => 'users',
			'foreign'   => 'id',
			'variable'  => 'user'
		));
	}

	/*
	 * Obtiene los nombres alternativos del staff.
	 *
	 * @autor dvaJi
	*/
	public function getDefaultName($names) {
		foreach ($names as $key => $value) {
			if ($value->def == 1) {
				return $value->name;
			}
		}

		return null;
	}

}
