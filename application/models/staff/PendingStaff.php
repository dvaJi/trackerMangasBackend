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

}
