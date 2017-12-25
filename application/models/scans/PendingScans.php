<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class PendingScans extends MY_Model {
	protected $table  = 'pending_scan';

	protected function setRelations() {
    $this->addRelation(array(
			'primary'   => 'id_user',
			'table'     => 'users',
			'foreign'   => 'id',
			'variable'  => 'user'
		));
	}

}
