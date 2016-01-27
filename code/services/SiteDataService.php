<?php

/**
 * Capture / cache some commonly used data elements for each page
 * 
 * @author marcus
 */
class SiteDataService {

	protected $items = array();
	protected $mapped = array();

	/**
	 *
	 * @var string
	 */
	public $itemClass = 'DataObjectNode';
	
	/**
	 *
	 * @var string
	 */
	public $baseClass = 'SiteTree';
	
	/**
	 *
	 * @var string
	 */
	public $itemSort = 'ParentID ASC, Sort ASC';
	
	/**
	 * The field to use for parent ordering
	 *
	 * @var string
	 */
	public $parentField = 'ParentID';

	/**
	 * Additional fields to be queried from the SiteTree/Page tables
	 *
	 * @var array
	 */
	public $additionalFields = array(
		'Title', 
		'MenuTitle', 
		'URLSegment', 
		'ParentID', 
		'CanViewType', 
		'Sort', 
		'ShowInMenus',
	);

	/**
	 * Needed to create the menu item objects
	 * 
	 * @var Injector
	 */
	public $injector;

	public function __construct() {
		
	}

	public function getItem($id) {
		$this->getItems();
		return isset($this->items[$id]) ? $this->items[$id] : null;
	}

	public function getItems() {
		if (!$this->items) {
			$this->generateMenuItems();
		}

		return $this->items;
	}

	public function generateMenuItems() {
		$all = array();
		$allids = array(
		);

		if (class_exists('Multisites')) {
			$site = Multisites::inst()->getCurrentSite();

			$all[] = array(
				'ID' => $site->ID,
				'ClassName' => 'Site',
				'Title' => $site->Title,
				'ParentID' => 0,
				'MenuTitle' => $site->Title,
				'URLSegment' => '',
				'CanViewType' => $site->CanViewType,
			);
			$allids[$site->ID] = true;
		}

		$public = $this->getPublicNodes();
		foreach ($public as $row) {
			$allids[$row['ID']] = true;
			$all[] = $row;
		}

		// and private nodes
		$private = $this->getPrivateNodes();
		foreach ($private as $row) {
			$allids[$row['ID']] = true;
			$all[] = $row;
		}

		$others = $this->getAdditionalNodes();
		foreach ($others as $row) {
			$allids[$row['ID']] = true;
			$all[] = $row;
		}
		$deferred = array();
		$final = array();
		$hierarchy = array();

		$counter = 0;

		$this->iterateNodes($final, $all, $allids, 0);

		$ordered = ArrayList::create();
		// start at 0
		if (isset($final[0]['kids'])) {
			foreach ($final[0]['kids'] as $id) {
				$node = $final[$id];
				$this->buildLinks($node, null, $ordered, $final);
			}
		}
	}

	protected function queryFields() {
		$fields = array(
			'"' . $this->baseClass .'"."ID" AS ID',
			'ClassName', 
			'Created',
			'LastEdited',
		);
		
		foreach ($this->additionalFields as $field) {
			$fields[] = $field;
		}
		return $fields;
	}

	protected function getPublicNodes() {
		$fields = $this->queryFields();
		$query = new SQLQuery($fields, '"' . $this->baseClass .'"');
		$query = $query->setOrderBy($this->itemSort);

		// if the user is logged in, we only exclude nodes that have a specific permission set on them
		if (Member::currentUserID()) {
			$query->addWhere('"CanViewType" NOT IN (\'OnlyTheseUsers\')');
		} else {
			$query->addWhere('"CanViewType" NOT IN (\'LoggedInUsers\', \'OnlyTheseUsers\')');
		}

		$this->adjustPublicNodeQuery($query);
		$this->adjustForVersioned($query);
		$results = $query->execute();
		return $results;
	}

	protected function adjustPublicNodeQuery(SQLQuery $query) {
		
	}

	/**
	 * Get private nodes, assuming SilverStripe's default perm structure
	 * @return SS_Query
	 */
	protected function getPrivateNodes() {
		if (!Member::currentUserID()) {
			return array();
		}
		$groups = Member::currentUser()->Groups()->column();

		if (!count($groups)) {
			return $groups;
		}

		$fields = $this->queryFields();
		$query = new SQLQuery($fields, '"'.$this->baseClass.'"');
		$query = $query->setOrderBy($this->itemSort);
		$query->addWhere('"CanViewType" IN (\'OnlyTheseUsers\')');
		if (Permission::check('ADMIN')) {
			// don't need to restrict the canView by anything
		} else {
			$query->addInnerJoin('SiteTree_ViewerGroups', '"SiteTree_ViewerGroups"."SiteTreeID" = "SiteTree"."ID"');
			$query->addWhere('"SiteTree_ViewerGroups"."GroupID" IN (' . implode(',', $groups) . ')');
		}

		$this->adjustPrivateNodeQuery($query);
		$this->adjustForVersioned($query);
		$sql = $query->sql();

		$results = $query->execute();
		return $results;
	}

	protected function adjustForVersioned(SQLQuery $query) {
		$ownerClass = $this->baseClass;
		$stage = Versioned::current_stage();
		if ($stage && ($stage != 'Stage')) {
			foreach ($query->getFrom() as $table => $dummy) {
				// Only rewrite table names that are actually part of the subclass tree
				// This helps prevent rewriting of other tables that get joined in, in
				// particular, many_many tables
				if (class_exists($table) && ($table == $ownerClass || is_subclass_of($table, $ownerClass) || is_subclass_of($ownerClass, $table))) {
					$query->renameTable($table, $table . '_' . $stage);
				}
			}
		}
	}

	protected function adjustPrivateNodeQuery(SQLQuery $query) {
		
	}

	protected function getAdditionalNodes() {
		return array();
	}

	protected function buildLinks($node, $parent, $out, $nodemap) {
		$kids = isset($node['kids']) ? $node['kids'] : array();
		$node = $this->createMenuNode($node);
		$out->push($node);
		$node->Link = ltrim($parent
            ? ($parent->Link == '' ? 'home' : $parent->Link) . '/' . $node->URLSegment
            : $node->URLSegment, '/');
		
		if ($node->Link == 'home') {
			$node->Link = '';
		}

		foreach ($kids as $id) {
			$n = $nodemap[$id];
			$this->buildLinks($n, $node, $out, $nodemap);
		}
	}

	/**
	 * Creates a menu item from an array of data
	 * 
	 * @param array $data
	 * @returns MenuItem
	 */
	public function createMenuNode($data) {
		$cls = $this->itemClass;
		$node = $cls::create($data, $this);
		$this->items[$node->ID] = $node;
		return $node;
	}

	protected function iterateNodes(&$final, $remaining, $ids, $lastcount) {
		$deferred = array();
		foreach ($remaining as $row) {
			// orphan
			if ($row[$this->parentField] && !isset($ids[$row[$this->parentField]])) {
				continue;
			}
			if (!isset($final[$row['ID']])) {
				$final[$row['ID']] = $row;
			}
			if ($row[$this->parentField] && !isset($final[$row[$this->parentField]])) {
				$deferred[$row['ID']] = $row;
			} else {

				// add to the hierarchy of things
				$existing = isset($final[$row[$this->parentField]]['kids']) ? $final[$row[$this->parentField]]['kids'] : array();
				$existing[] = $row['ID'];
				$final[$row[$this->parentField]]['kids'] = $existing;
			}
		}

		if (count($deferred) == $lastcount) {
			return;
		}

		$lastcount = count($deferred);
		if (count($deferred)) {
			$this->iterateNodes($final, $deferred, $ids, $lastcount);
		}
	}

}
