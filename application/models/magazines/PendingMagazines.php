<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class PendingMagazines extends MY_Model {
	protected $table  = 'pending_magazine';

	protected function setRelations() {
    $this->addRelation(array(
			'primary'   => 'id_user',
			'table'     => 'users',
			'foreign'   => 'id',
			'variable'  => 'user'
		));

		$this->addRelation(array(
			'primary'   => 'id_publisher',
			'table'     => 'publisher',
			'foreign'   => 'id',
			'variable'  => 'publisher'
		));
	}

}
