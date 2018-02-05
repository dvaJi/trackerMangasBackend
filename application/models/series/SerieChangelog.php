<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class SerieChangelog extends MY_Model {
	protected $table  = 'serie_changelog';

	protected function setRelations() {
		$this->addRelation(array(
			'primary'   => 'id',
			'table'     => 'serie_changelog_detail',
			'foreign'   => 'id_serie_changelog',
			'variable'  => 'details'
		));

    $this->addRelation(array(
			'primary'   => 'id_serie',
			'table'     => 'series',
			'foreign'   => 'id',
			'variable'  => 'serie'
		));

    $this->addRelation(array(
			'primary'   => 'id_user',
			'table'     => 'users',
			'foreign'   => 'id',
			'variable'  => 'user'
		));

	}

}
