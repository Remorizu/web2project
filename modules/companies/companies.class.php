<?php /* $Id$ $URL$ */

/**
 *	@package web2Project
 *	@subpackage modules
 *	@version $Revision$
 */

/**
 *	Companies Class
 *	@todo Move the 'address' fields to a generic table
 */
class CCompany extends w2p_Core_BaseObject {
	/**
 	@var int Primary Key */
	public $company_id = 0;
	/**
 	@var string */
	public $company_name = null;

	// these next fields should be ported to a generic address book
	public $company_phone1 = null;
	public $company_phone2 = null;
	public $company_fax = null;
	public $company_address1 = null;
	public $company_address2 = null;
	public $company_city = null;
	public $company_state = null;
	public $company_zip = null;
	public $company_country = null;
	public $company_email = null;
	/**
 	@var string */
	public $company_primary_url = null;
	/**
 	@var int */
	public $company_owner = null;
	/**
 	@var string */
	public $company_description = null;
	/**
 	@var int */
	public $company_type = null;
	public $company_custom = null;

	public function __construct() {
	  parent::__construct('companies', 'company_id');
	}

	// overload check
	public function check() {
	  $errorArray = array();
	  $baseErrorMsg = get_class($this) . '::store-check failed - ';

	  if ('' == trim($this->company_name)) {
	    $errorArray['company_name'] = $baseErrorMsg . 'company name is not set';
	  }
	  if ((int) $this->company_owner == 0) {
    	$errorArray['company_owner'] = $baseErrorMsg . 'company owner is not set';
	  }

      $this->_error = $errorArray;
	  return $errorArray;
	}

	// overload canDelete
	public function canDelete($msg = '', $oid = null, $joins = null) {
		$tables[] = array('label' => 'Projects', 'name' => 'projects', 'idfield' => 'project_id', 'joinfield' => 'project_company');
		$tables[] = array('label' => 'Departments', 'name' => 'departments', 'idfield' => 'dept_id', 'joinfield' => 'dept_company');
		$tables[] = array('label' => 'Users', 'name' => 'users', 'idfield' => 'user_id', 'joinfield' => 'user_company');
		// call the parent class method to assign the oid
		return parent::canDelete($msg, $oid, $tables);
	}

    public function delete(w2p_Core_CAppUI $AppUI = null) {
        $perms = $this->_AppUI->acl();

        if ($perms->checkModuleItem($this->_tbl_module, 'delete', $this->{$this->_tbl_key})) {
            if ($msg = parent::delete()) {
                return $msg;
            }
            return true;
        }
        return false;
    }

    public function store(w2p_Core_CAppUI $AppUI = null) {
        $perms = $this->_AppUI->acl();
        $stored = false;

        $this->_error = $this->check();

        if (count($this->_error)) {
            return $this->_error;
        }

        $this->company_id = (int) $this->company_id;
        /*
         * TODO: I don't like the duplication on each of these two branches, but I
         *   don't have a good idea on how to fix it at the moment...
         */
        if ($this->{$this->_tbl_key} && $perms->checkModuleItem($this->_tbl_module, 'edit', $this->{$this->_tbl_key})) {
            if (($msg = parent::store())) {
                $this->_error['store'] = $msg;
            } else {
                $stored = true;
            }
        }
        if (0 == $this->{$this->_tbl_key} && $perms->checkModuleItem($this->_tbl_module, 'add')) {
            if (($msg = parent::store())) {
                $this->_error['store'] = $msg;
            } else {
                $stored = true;
            }
        }

        return $stored;
    }

    protected function hook_postStore() {
        $custom_fields = new w2p_Core_CustomFields('companies', 'addedit', $this->company_id, 'edit');
        //TODO: I still don't like the POST here..
        $custom_fields->bind($_POST);
        $sql = $custom_fields->store($this->company_id); // Store Custom Fields

        parent::hook_postStore();
    }

  public function hook_search()
  {
    $search['table'] = 'companies';
    $search['table_module'] = $search['table'];
    $search['table_key'] = 'company_id';
    $search['table_link'] = 'index.php?m=companies&a=view&company_id=';
    $search['table_title'] = 'Companies';
    $search['table_orderby'] = 'company_name';
    $search['search_fields'] = array('company_name', 'company_address1',
        'company_address2', 'company_city', 'company_state', 'company_zip',
        'company_primary_url', 'company_description', 'company_email');
    $search['display_fields'] = $search['search_fields'];

    return $search;
  }

  public function loadFull(w2p_Core_CAppUI $AppUI = null, $companyId) {
    $q = $this->_getQuery();
    $q->addTable('companies');
    $q->addQuery('companies.*');
    $q->addQuery('con.contact_first_name');
    $q->addQuery('con.contact_last_name');
    $q->addQuery('con.contact_display_name as contact_name');
    $q->leftJoin('users', 'u', 'u.user_id = companies.company_owner');
    $q->leftJoin('contacts', 'con', 'u.user_contact = con.contact_id');
    $q->addWhere('companies.company_id = ' . (int) $companyId);

    $q->loadObject($this, true, false);
  }

  public function getCompanyList($AppUI = null, $companyType = -1, $searchString = '', $ownerId = 0, $orderby = 'company_name', $orderdir = 'ASC') {

    $q = $this->_getQuery();
  	$q->addTable('companies', 'c');
  	$q->addQuery('c.company_id, c.company_name, c.company_type, c.company_description, count(distinct p.project_id) as countp,
        count(distinct p2.project_id) as inactive, con.contact_first_name, con.contact_last_name, con.contact_display_name');
  	$q->addJoin('projects', 'p', 'c.company_id = p.project_company AND p.project_active = 1');
  	$q->addJoin('users', 'u', 'c.company_owner = u.user_id');
  	$q->addJoin('contacts', 'con', 'u.user_contact = con.contact_id');
  	$q->addJoin('projects', 'p2', 'c.company_id = p2.project_company AND p2.project_active = 0');

  	$where = $this->getAllowedSQL($this->_AppUI->user_id, 'c.company_id');
  	$q->addWhere($where);

  	if ($companyType > -1) {
  		$q->addWhere('c.company_type = ' . (int) $companyType);
  	}
  	if ($searchString != '') {
  		$q->addWhere('c.company_name LIKE "%'.$searchString.'%"');
  	}
  	if ($ownerId > 0) {
  		$q->addWhere('c.company_owner = '.$ownerId);
  	}
  	$q->addGroup('c.company_id');
  	$q->addOrder($orderby . ' ' . $orderdir);

  	return $q->loadList();
  }

  public function getCompanies(w2p_Core_CAppUI $AppUI = null) {

    $q = $this->_getQuery();
  	$q->addTable('companies');
  	$q->addQuery('company_id, company_name');

  	$where = $this->getAllowedSQL($this->_AppUI->user_id, 'company_id');
  	$q->addWhere($where);

  	return $q->loadHashList('company_id');
  }

	public static function getProjects(w2p_Core_CAppUI $AppUI, $companyId, $active = 1, $sort = 'project_name') {
		$fields = 'DISTINCT pr.project_id, project_name, project_start_date, ' .
				'project_status, project_target_budget, project_start_date, ' .
				'project_priority, contact_first_name, contact_last_name, ' .
                'contact_display_name as contact_name';

		$q = new w2p_Database_Query();
		$q->addTable('projects', 'pr');
		$q->addQuery($fields);
		$q->leftJoin('users', 'u', 'u.user_id = pr.project_owner');
		$q->leftJoin('contacts', 'con', 'u.user_contact = con.contact_id');
		if ((int) $companyId > 0) {
			$q->addWhere('pr.project_company = ' . (int) $companyId);
		}

		$projObj = new CProject();
//TODO: We need to convert this from static to use ->overrideDatabase() for testing.
		$projObj->setAllowedSQL($AppUI->user_id, $q, null, 'pr');

		$q->addWhere('pr.project_active = '. (int) $active);

		if (strpos($fields, $sort) !== false) {
			$q->addOrder($sort);
		}

		return $q->loadList();
	}

	public static function getContacts(w2p_Core_CAppUI $AppUI, $companyId) {
		$results = array();
		$perms = $AppUI->acl();

		if ($AppUI->isActiveModule('contacts') && canView('contacts') && (int) $companyId > 0) {
			$q = new w2p_Database_Query();
			$q->addQuery('c.*');
            $q->addQuery('c.contact_display_name as contact_name');
			$q->addQuery('dept_name');
			$q->addTable('contacts', 'c');
			$q->leftJoin('companies', 'b', 'c.contact_company = b.company_id');
			$q->leftJoin('departments', '', 'contact_department = dept_id');
			$q->addWhere('contact_company = ' . (int) $companyId);
			$q->addWhere('
				(contact_private=0
					OR (contact_private=1 AND contact_owner=' . $AppUI->user_id . ')
					OR contact_owner IS NULL OR contact_owner = 0
				)');
			$department = new CDepartment;
//TODO: We need to convert this from static to use ->overrideDatabase() for testing.
			$department->setAllowedSQL($AppUI->user_id, $q);

			$q->addOrder('contact_first_name');
			$q->addOrder('contact_last_name');

			$results = $q->loadHashList('contact_id');
		}

		return $results;
	}

	public static function getUsers(w2p_Core_CAppUI $AppUI, $companyId) {

        $q = new w2p_Database_Query();
		$q->addTable('users');
		$q->addQuery('user_id, user_username, contact_first_name, contact_last_name');
        $q->addQuery('contact_display_name as contact_name');
		$q->addJoin('contacts', 'c', 'users.user_contact = contact_id', 'inner');
		$q->addJoin('departments', 'd', 'd.dept_id = contact_department');
		$q->addWhere('contact_company = ' . (int) $companyId);
		$q->addOrder('contact_last_name, contact_first_name');

		$department = new CDepartment;
//TODO: We need to convert this from static to use ->overrideDatabase() for testing.
		$department->setAllowedSQL($AppUI->user_id, $q);

		return $q->loadHashList('user_id');
	}

	public static function getDepartments(w2p_Core_CAppUI $AppUI, $companyId) {
		$perms = $AppUI->acl();

		if ($AppUI->isActiveModule('departments') && canView('departments')) {
			$q = new w2p_Database_Query();
			$q->addTable('departments');
			$q->addQuery('departments.*, COUNT(contact_department) dept_users');
			$q->addJoin('contacts', 'c', 'c.contact_department = dept_id');
			$q->addWhere('dept_company = ' . (int) $companyId);
			$q->addGroup('dept_id');
			$q->addOrder('dept_parent, dept_name');

			$department = new CDepartment;
//TODO: We need to convert this from static to use ->overrideDatabase() for testing.
			$department->setAllowedSQL($AppUI->user_id, $q);

			return $q->loadList();
		}
	}
}