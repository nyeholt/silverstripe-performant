<?php

/**
 * @author marcus
 */
class PerformantPage extends DataExtension {

	/**
	 * Return a breadcrumb trail to this page. Excludes "hidden" pages (with ShowInMenus=0) by default.
	 *
	 * @param int         $maxDepth The maximum depth to traverse.
	 * @param bool        $unlinked Do not make page names links
	 * @param bool|string $stopAtPageType ClassName of a page to stop the upwards traversal.
	 * @param bool        $showHidden Include pages marked with the attribute ShowInMenus = 0
	 * @return HTMLText The breadcrumb trail.
	 */
	public function PerformantBreadcrumbs($maxDepth = 20, $unlinked = false, $stopAtPageType = false, $showHidden = false) {
		$page = $this->asDataNode();
		$pages = array();
		while (
		$page && (!$maxDepth || count($pages) < $maxDepth) && (!$stopAtPageType || $page->ClassName != $stopAtPageType)
		) {
			if ($showHidden || $page->ShowInMenus || ($page->ID == $this->ID)) {
				$pages[] = $page;
			}
			$page = $page->getParent();
		}
		$template = new SSViewer('BreadcrumbsTemplate');
		return $template->process($this->customise(new ArrayData(array(
					'Pages' => new ArrayList(array_reverse($pages))
		))));
	}

	/**
	 * Returns the page in the current page stack of the given level. Level(1) will return the main menu item that
	 * we're currently inside, etc.
	 *
	 * @param int $level
	 * @return SiteTree
	 */
	public function PerformantLevel($level) {
		$menuNode = $this->asDataNode();
		$stack = array();
		if ($menuNode) {
			$parent = $menuNode;
			$stack[] = $parent;
			while ($parent = $parent->getParent()) {
				array_unshift($stack, $parent);
			}
		}
		return isset($stack[$level - 1]) ? $stack[$level - 1] : null;
	}

	public function asDataNode() {
		$item = singleton('SiteDataService')->getItem($this->ID);
		if (!$item) {
			// need to create a new item
			$item = singleton('SiteDataService')->createMenuNode($this->toMap());
		}
		return $item;
	}

}
