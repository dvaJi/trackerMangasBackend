<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class PendingReleases extends MY_Model {
	protected $table  = 'pending_releases';

	protected function setRelations() {

    $this->addRelation(array(
			'primary'   => 'id',
			'table'     => 'pending_releases_groups',
			'foreign'   => 'id_pending_releases',
			'variable'  => 'scans'
		));

    $this->addRelation(array(
			'primary'   => 'id_user',
			'table'     => 'users',
			'foreign'   => 'id',
			'variable'  => 'user'
		));
	}

}
