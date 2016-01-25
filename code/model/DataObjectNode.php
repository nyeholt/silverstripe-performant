<?php

/**
 * Subclass of ArrayData that has some site specific functionality 
 *
 * @author marcus
 */
class DataObjectNode extends ArrayData {
	/**
	 * @var SiteDataService
	 */
	protected $siteData;
	
	public function __construct($value, SiteDataService $siteData) {
		if (!isset($value['MenuTitle']) || strlen($value['MenuTitle']) == 0 && strlen($value['MenuTitle'])) {
			if (isset($value['Title'])) {
				$value['MenuTitle'] = $value['Title'];
			} 
		}
		parent::__construct($value);
		
		$this->siteData = $siteData;
	}
	
	public function Children() {
		$kids = ArrayList::create();
		
		if (isset($this->array['kids'])) {
			foreach ($this->array['kids'] as $id) {
				$kid = $this->siteData->getItem($id);
				if ($kid && $kid->ShowInMenus) {
					$kids->push($kid);
				}
			}
		}
		$kids = $kids->sort('Sort ASC');
		return $kids;
	}
	
	public function getAncestors() {
		$ancestors = new ArrayList();
		$object    = $this;
		
		while($object = $object->getParent()) {
			$ancestors->push($object);
		}
		
		return $ancestors;
	}
	
	public function getParent() {
		return $this->ParentID ? $this->siteData->getItem($this->ParentID) : null;
	}
	
	/**
	 * Returns true if this is the currently active page being used to handle this request.
	 *
	 * @return bool
	 */
	public function isCurrent() {
		return $this->ID ? $this->ID == Director::get_current_page()->ID : false;
	}
	
	/**
	 * Check if this page is in the currently active section (e.g. it is either current or one of its children is
	 * currently being viewed).
	 *
	 * @return bool
	 */
	public function isSection() {
		if ($this->isCurrent()) {
			return true;
		}
		$ancestors = $this->getAncestors();
		if (Director::get_current_page() instanceof Page) {
			$node = Director::get_current_page()->asMenuItem();
			if ($node) {
				$ancestors = $node->getAncestors();
				return $ancestors && in_array($this->ID, $node->getAncestors()->column());
			}
			
		}
		return false;
	}
	
	/**
	 * Check if the parent of this page has been removed (or made otherwise unavailable), and is still referenced by
	 * this child. Any such orphaned page may still require access via the CMS, but should not be shown as accessible
	 * to external users.
	 * 
	 * @return bool
	 */
	public function isOrphaned() {
		// Always false for root pages
		if(empty($this->ParentID)) return false;
		
		// Parent must exist and not be an orphan itself
		$parent = $this->getParent();
		return !$parent || !$parent->ID || $parent->isOrphaned();
	}
	
	/**
	 * Return "link" or "current" depending on if this is the {@link SiteTree::isCurrent()} current page.
	 *
	 * @return string
	 */
	public function LinkOrCurrent() {
		return $this->isCurrent() ? 'current' : 'link';
	}
	
	/**
	 * Return "link" or "section" depending on if this is the {@link SiteTree::isSeciton()} current section.
	 *
	 * @return string
	 */
	public function LinkOrSection() {
		return $this->isSection() ? 'section' : 'link';
	}
	
	/**
	 * Return "link", "current" or "section" depending on if this page is the current page, or not on the current page
	 * but in the current section.
	 *
	 * @return string
	 */
	public function LinkingMode() {
		if($this->isCurrent()) {
			return 'current';
		} elseif($this->isSection()) {
			return 'section';
		} else {
			return 'link';
		}
	}
	
	/**
	 * Check if this page is in the given current section.
	 *
	 * @param string $sectionName Name of the section to check
	 * @return bool True if we are in the given section
	 */
	public function InSection($sectionName) {
		$page = Director::get_current_page();
		while($page) {
			if($sectionName == $page->URLSegment)
				return true;
			$page = $page->Parent;
		}
		return false;
	}
}