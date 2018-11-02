<?php

namespace Search;

use SilverStripe\CMS\Model\RedirectorPage;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Convert;
use SilverStripe\ErrorPage\ErrorPage;
use SilverStripe\ORM\Map;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\PaginatedList;
use SilverStripe\Versioned\Versioned;

use Slick\CMS\Control\PageController;
use Slick\CMS\Control\Search;
use Slick\CMS\View\Accordion;
use Slick\CMS\View\Icon;
use Slick\CMS\View\Layout;

class SearchController extends PageController
{
    /**
     * Current page number.
     * @var int Starts at Page 1.
     */
    protected $current_page = -1;
    /**
     * Item index of the first item on the current page.
     * @var int Index starts at 0 on the first page.
     */
    protected $start = -1;
    /**
     * Page length.
     * @var int Default of 20. 0 = no pagination.
     */
    protected $page_length = -1;
    
    /**
     * Query string. Saved on page init.
     * @var string
     */
    protected $query = '';
    
    /**
     * List of all results. Used to populate pagination.
     * @var \SilverStripe\ORM\DataList|SilverStripe\CMS\Model\SiteTree[]
     */
    protected $sub_items;
    
    /**
     * Paginated list of sub items based on the page, page length, and start.
     * @var \SilverStripe\ORM\PaginatedList|SilverStripe\CMS\Model\SiteTree[]
     */
    protected $paginated_items;
    
    /**
     * Collect query variable on page load.
     * 
     * Checks in the following order:
     * - Request variable: 'search'
     * - Request variable: 'query'
     * - Request variable: 's'
     * - Request variable: 'q'
     * Defaults to an empty string which should theoretically find all pages
     * with content. The query will be sql escaped from this point.
     */
    public function init()
    {
        parent::init();
        
        $this->query = Convert::raw2sql(
            $this->request->requestVar('search') ?:
            $this->request->requestVar('query') ?:
            $this->request->requestVar('s') ?:
            $this->request->requestVar('q') ?:
            ''
        );
    }
    
    /**
     * Identifies this page as being a search page.
     * 
     * @return true
     */
    public function IsSearchPage()
    {
        return true;
    }
    
    /**
     * Find, cache and return the current page number.
     * 
     * @return int Page number starting at 1.
     */
    public function CurrentPage()
    {
        if ($this->current_page < 0) {
            // Check page request variable. Default to 1.
            $this->current_page = $this->request->param('page') ?: $this->request->requestVar('page') ?: 1;
            // Override if both Start Index and Page Length are found.
            if ($this->Start() && $this->PageLength()) {
                $this->current_page = ($this->Start() / $this->PageLength()) + 1;
            }
        }
        return $this->current_page;
    }
    
    /**
     * Find, cache and return the starting index of the current page.
     * 
     * @return int Page offset starting at 0 on first page.
     */
    public function Start()
    {
        if ($this->start < 0) {
            // Default to 0 (first page)
            $this->start = $this->request->param('start') ?: $this->request->requestVar('start') ?: 0;
        }
        return $this->start;
    }
    
    /**
     * Find, cache and return the page length.
     * 
     * This differs from the ResultsPerPage property, where any negative value
     * is interpreted as no pagination and empty values fall back to the default.
     * 
     * @return int Default 20. 0 = no pagination.
     */
    public function PageLength()
    {
        if ($this->page_length < 0) {
            $this->page_length = $this->ResultsPerPage < 0 ? 0 : ($this->ResultsPerPage ?: 20);
        }
        return $this->page_length;
    }
    
    /**
     * Collect and cache a paginated list of items.
     * 
     * @uses static::SubItems(false) to collect the full list.
     * 
     * @return \SilverStripe\ORM\PaginatedList
     */
    public function Pagination()
    {
        if (is_null($this->paginated_items)) {
            // All 3 seem to need to be provided in order to ensure pagination works as intended.
            $this->paginated_items = PaginatedList::create($this->SubItems(false)) // At most SubItems should be called twice.
                ->setPageStart($this->Start())
                ->setPageLength($this->PageLength())
                ->setCurrentPage($this->CurrentPage());
        }
        
        return $this->paginated_items;
    }
    
    /**
     * List of search results. Should not contain duplicated items.
     * 
     * @uses 
     * 
     * @param boolean $paginated Whether to return a paginated list or not.
     * @return \SilverStripe\ORM\DataList|\SilverStripe\ORM\PaginatedList List
     * of \SilverStripe\CMS\Model\SiteTree[] objects or any other form of data
     * as required.
     */
    public function SubItems($paginated = true)
    {
        if (is_null($this->sub_items)) {
            $page_ids = [];
            $stage = Versioned::get_stage();
            
            // Page Titles have the highest priority.
            $pages = Versioned::get_by_stage(SiteTree::class, $stage)
                ->where(sprintf(
                    '%s LIKE \'%%%s%%\'',
                    DataObject::getSchema()->sqlColumnForField(SiteTree::class, 'Title'),
                    $this->query
                ));
            if ($pages && $pages->exists()) {
                $pages = $pages->map('ID', 'Title');
                if ($pages instanceof Map) {
                    $pages = $pages->toArray();
                }
                $page_ids += $pages;
            }
            
            // Page Content has the next highest priority.
            $pages = Versioned::get_by_stage(SiteTree::class, $stage)
                ->where(sprintf(
                    '%s LIKE \'%%%s%%\'',
                    DataObject::getSchema()->sqlColumnForField(SiteTree::class, 'Content'),
                    $this->query
                ));
            if ($pages && $pages->exists()) {
                $pages = $pages->map('ID', 'Title');
                if ($pages instanceof Map) {
                    $pages = $pages->toArray();
                }
                $page_ids += $pages;
            }
            
            // Accordions also have high priority.
            $accordion = Versioned::get_by_stage(Accordion::class, $stage)
                ->whereAny(sprintf(
                    '%s LIKE \'%%%s%%\'',
                    DataObject::getSchema()->sqlColumnForField(Accordion::class, 'Title'),
                    $this->query
                ))
                ->whereAny(sprintf(
                    '%s LIKE \'%%%s%%\'',
                    DataObject::getSchema()->sqlColumnForField(Accordion::class, 'Content'),
                    $this->query
                ));
            if ($accordion && $accordion->exists()) {
                $pages = $accordion->map('PageID', 'Title');
                if ($pages instanceof Map) {
                    $pages = $pages->toArray();
                }
                $page_ids += $pages;
            }
            
            // Layouts have almost as much priority - most likely just the homepage at this point.
            $layouts = Versioned::get_by_stage(Layout::class, $stage)
                ->whereAny(sprintf(
                    '%s LIKE \'%%%s%%\'',
                    DataObject::getSchema()->sqlColumnForField(Layout::class, 'CallToActionText'),
                    $this->query
                ))
                ->whereAny(sprintf(
                    '%s LIKE \'%%%s%%\'',
                    DataObject::getSchema()->sqlColumnForField(Layout::class, 'Content'),
                    $this->query
                ));
            if ($layouts && $layouts->exists()) {
                $pages = $layouts->map('PageID', 'CallToActionText');
                if ($pages instanceof Map) {
                    $pages = $pages->toArray();
                }
                $page_ids += $pages;
            }
            
            // Icons have low priority - should include Logos by default.
            $icons = Versioned::get_by_stage(Icon::class, $stage)
                ->whereAny(sprintf(
                    '%s LIKE \'%%%s%%\'',
                    DataObject::getSchema()->sqlColumnForField(Icon::class, 'Title'),
                    $this->query
                ))
                ->whereAny(sprintf(
                    '%s LIKE \'%%%s%%\'',
                    DataObject::getSchema()->sqlColumnForField(Icon::class, 'Description'),
                    $this->query
                ));
            if ($icons && $icons->exists()) {
                foreach ($icons as $icon) {
                    if ($icon->PageID) {
                        $page_ids[$icon->PageID] = $icon->Title;
                    }
                    if ($icon->LayoutID && ($layout = $icon->Layout()) && $layout->exists() && $layout->PageID) {
                        $page_ids[$layout->PageID] = $icon->Title;
                    }
                }
            }
            
            // Get the full list of unique pages in the order they were found where possible.
            $this->sub_items =  SiteTree::get()
                // Exclude specific page types.
                ->where(sprintf(
                    '%s NOT IN (%s)',
                    DataObject::getSchema()->sqlColumnForField(SiteTree::class, 'ClassName'),
                    implode(', ', array_map(
                        [Convert::class, 'raw2sql'],
                        [ErrorPage::class, RedirectorPage::class, Search::class],
                        [true, true, true]
                    ))
                ))
                // Load pages from given IDs.
                ->filter('ID', array_keys($page_ids))
                // Sort by the order of the pages as they appeared in the array.
                ->sort(sprintf(
                    'FIELD(%s, %s)',
                    DataObject::getSchema()->sqlColumnForField(SiteTree::class, 'ID'),
                    implode(', ', array_keys($page_ids))
                ));
        }
        
        // Return a pagination list by default.
        if ($paginated) {
            // Static::Pagination() uses this function by default, but will prevent
            // infinite loops by passing false and returning the full list instead.
            return $this->Pagination();
        }
        // Return all values.
        return $this->sub_items;
    }
}
