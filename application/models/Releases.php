<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Releases extends MY_Model {
	protected $table  = 'releases';

	protected function setRelations() {
		$this->addRelation(array(
			'primary'   => 'series_id',      
			'table'     => 'series', 
			'foreign'   => 'id',
			'variable'  => 'serie'
		));

        $this->addRelation(array(
			'primary'   => 'id',      
			'table'     => 'release_groups', 
			'foreign'   => 'release_id',
			'variable'  => 'groups'
		));
	}
}