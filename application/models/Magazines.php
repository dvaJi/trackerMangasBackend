<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Magazines extends MY_Model {
	protected $table  = 'magazines';

	protected function setRelations() {
		$this->addRelation(array(
			'primary'   => 'publisher_id',      
			'table'     => 'publisher', 
			'foreign'   => 'id',
			'variable'  => 'pub'
		));
	}
}