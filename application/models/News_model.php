<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class News_model extends MY_Model {
	protected $table  = 'news';

	protected function setRelations() {
		$this->addRelation(array(
			'primary'   => 'id_user',
			'table'     => 'users',
			'foreign'   => 'id',
			'variable'  => 'user'
		));
	}

}
