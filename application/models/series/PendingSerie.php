<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class PendingSerie extends MY_Model {
	protected $table  = 'pending_serie';

	protected function setRelations() {
		$this->addRelation(array(
			'primary'   => 'id',
			'table'     => 'pending_serie_names',
			'foreign'   => 'id_pending_serie',
			'variable'  => 'names'
		));

    $this->addRelation(array(
			'primary'   => 'id',
			'table'     => 'pending_serie_genres',
			'foreign'   => 'id_pending_serie',
			'variable'  => 'genres'
		));

    $this->addRelation(array(
			'primary'   => 'id',
			'table'     => 'pending_serie_staff',
			'foreign'   => 'id_pending_serie',
			'variable'  => 'staff'
		));

    $this->addRelation(array(
			'primary'   => 'id',
			'table'     => 'pending_serie_magazine',
			'foreign'   => 'id_pending_serie',
			'variable'  => 'magazines'
		));

    $this->addRelation(array(
			'primary'   => 'id_user',
			'table'     => 'users',
			'foreign'   => 'id',
			'variable'  => 'user'
		));
	}

}
