<?php
/*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.6                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2014                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
*/

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2014
 * $Id: Selector.php 11510 2007-09-18 09:21:34Z lobo $
 *
 */

/**
 * This class is used to retrieve and display a range of
 * contacts that match the given criteria (specifically for
 * results of advanced search options.
 *
 */
class CRM_Contact_Selector_Custom extends CRM_Contact_Selector {

  /**
   * This defines two actions- View and Edit.
   *
   * @var array
   * @static
   */
  static $_links = NULL;

  /**
   * We use desc to remind us what that column is, name is used in the tpl
   *
   * @var array
   * @static
   */
  static $_columnHeaders;

  /**
   * Properties of contact we're interested in displaying
   * @var array
   * @static
   */
  static $_properties = array('contact_id', 'contact_type', 'display_name');

  /**
   * FormValues is the array returned by exportValues called on
   * the HTML_QuickForm_Controller for that page.
   *
   * @var array
   * @access protected
   */
  public $_formValues;

  /**
   * Params is the array in a value used by the search query creator
   *
   * @var array
   * @access protected
   */
  public $_params;

  /**
   * Represent the type of selector
   *
   * @var int
   * @access protected
   */
  protected $_action;

  protected $_query;

  /**
   * The public visible fields to be shown to the user
   *
   * @var array
   * @access protected
   */
  protected $_fields;

  /**
   * The object that implements the search interface
   */
  protected $_search;

  protected $_customSearchClass;

  /**
   * Class constructor
   *
   * @param $customSearchClass
   * @param array $formValues array of form values imported
   * @param array $params array of parameters for query
   * @param null $returnProperties
   * @param \const|int $action - action of search basic or advanced.
   *
   * @param bool $includeContactIds
   * @param bool $searchChildGroups
   * @param string $searchContext
   * @param null $contextMenu
   *
   * @return \CRM_Contact_Selector_Custom
  @access public
   */
  function __construct(
    $customSearchClass,
    $formValues        = NULL,
    $params            = NULL,
    $returnProperties  = NULL,
    $action            = CRM_Core_Action::NONE,
    $includeContactIds = FALSE,
    $searchChildGroups = TRUE,
    $searchContext     = 'search',
    $contextMenu       = NULL
  ) {
    $this->_customSearchClass = $customSearchClass;
    $this->_formValues = $formValues;
    $this->_includeContactIds = $includeContactIds;

    $ext = CRM_Extension_System::singleton()->getMapper();

    if (!$ext->isExtensionKey($customSearchClass)) {
      if ($ext->isExtensionClass($customSearchClass)) {
        $customSearchFile = $ext->classToPath($customSearchClass);
        require_once ($customSearchFile);
      }
      else {
        require_once (str_replace('_', DIRECTORY_SEPARATOR, $customSearchClass) . '.php');
      }
      $this->_search = new $customSearchClass( $formValues );
    }
    else {
      $fnName = $ext->keyToPath;
      $customSearchFile = $fnName($customSearchClass, 'search');
      $className = $ext->keyToClass($customSearchClass, 'search');
      $this->_search = new $className($formValues);
    }
  }

  /**
   * This method returns the links that are given for each search row.
   * currently the links added for each row are
   *
   * - View
   * - Edit
   *
   * @return array
   * @access public
   *
   */
  static function &links() {
    list($key) = func_get_args();
    $searchContext = "&context=custom";
    $extraParams = ($key) ? "&key={$key}" : NULL;

    if (!(self::$_links)) {
      self::$_links = array(
        CRM_Core_Action::VIEW => array(
          'name' => ts('View'),
          'url' => 'civicrm/contact/view',
          'qs' => "reset=1&cid=%%id%%{$extraParams}{$searchContext}",
          'class' => 'no-popup',
          'title' => ts('View Contact Details'),
        ),
        CRM_Core_Action::UPDATE => array(
          'name' => ts('Edit'),
          'url' => 'civicrm/contact/add',
          'qs' => 'reset=1&action=update&cid=%%id%%',
          'class' => 'no-popup',
          'title' => ts('Edit Contact Details'),
        ),
      );

      $config = CRM_Core_Config::singleton();
      if ($config->mapAPIKey && $config->mapProvider) {
        self::$_links[CRM_Core_Action::MAP] = array(
          'name' => ts('Map'),
          'url' => 'civicrm/contact/map',
          'qs' => 'reset=1&cid=%%id%%&searchType=custom',
          'class' => 'no-popup',
          'title' => ts('Map Contact'),
        );
      }
    }
    return self::$_links;
  }

  /**
   * Getter for array of the parameters required for creating pager.
   *
   * @param $action
   * @param array $params
   *
   * @access public
   */
  function getPagerParams($action, &$params) {
    $params['status']    = ts('Contact %%StatusMessage%%');
    $params['csvString'] = NULL;
    $params['rowCount']  = CRM_Utils_Pager::ROWCOUNT;

    $params['buttonTop'] = 'PagerTopButton';
    $params['buttonBottom'] = 'PagerBottomButton';
  }

  /**
   * Returns the column headers as an array of tuples:
   * (name, sortName (key to the sort array))
   *
   * @param string $action the action being performed
   * @param enum   $output what should the result set include (web/email/csv)
   *
   * @return array the column headers that need to be displayed
   * @access public
   */
  function &getColumnHeaders($action = NULL, $output = NULL) {
    $columns = $this->_search->columns();
    if ($output == CRM_Core_Selector_Controller::EXPORT) {
      return array_keys($columns);
    }
    else {
      $headers = array();
      foreach ($columns as $name => $key) {
        if (!empty($name)) {
          $headers[] = array(
            'name' => $name,
            'sort' => $key,
            'direction' => CRM_Utils_Sort::ASCENDING,
          );
        }
        else {
          $headers[] = array();
        }
      }
      return $headers;
    }
  }

  /**
   * Returns total number of rows for the query.
   *
   * @param
   *
   * @return int Total number of rows
   * @access public
   */
  function getTotalCount($action) {
    return $this->_search->count();
  }

  /**
   * Returns all the rows in the given offset and rowCount
   *
   * @param enum   $action   the action being performed
   * @param int    $offset   the row number to start from
   * @param int    $rowCount the number of rows to return
   * @param string $sort     the sql string that describes the sort order
   * @param enum   $output   what should the result set include (web/email/csv)
   *
   * @return int   the total number of rows for this action
   */
  function &getRows($action, $offset, $rowCount, $sort, $output = NULL) {

    $includeContactIDs = FALSE;
    if (($output == CRM_Core_Selector_Controller::EXPORT ||
        $output == CRM_Core_Selector_Controller::SCREEN
      ) &&
      $this->_formValues['radio_ts'] == 'ts_sel'
    ) {
      $includeContactIDs = TRUE;
    }

    $sql = $this->_search->all($offset, $rowCount, $sort, $includeContactIDs);
    // contact query object used for creating $sql
    $contactQueryObj = NULL;
    if (method_exists($this->_search, 'getQueryObj') &&
      is_a($this->_search->getQueryObj(), 'CRM_Contact_BAO_Query')) {
      $contactQueryObj = $this->_search->getQueryObj();
    }

    $dao = CRM_Core_DAO::executeQuery($sql, CRM_Core_DAO::$_nullArray);

    $columns     = $this->_search->columns();
    $columnNames = array_values($columns);
    $links       = self::links($this->_key);

    $permissions = array(CRM_Core_Permission::getPermission());
    if (CRM_Core_Permission::check('delete contacts')) {
      $permissions[] = CRM_Core_Permission::DELETE;
    }
    $mask = CRM_Core_Action::mask($permissions);

    $alterRow = FALSE;
    if (method_exists($this->_customSearchClass,
        'alterRow'
      )) {
      $alterRow = TRUE;
    }
    $image = FALSE;
    if (is_a($this->_search, 'CRM_Contact_Form_Search_Custom_Basic')) {
      $image = TRUE;
    }
    // process the result of the query
    $rows = array();
    while ($dao->fetch()) {
      $row = array();
      $empty = TRUE;

      // if contact query object present
      // process pseudo constants
      if ($contactQueryObj) {
        $contactQueryObj->convertToPseudoNames($dao);
      }

      // the columns we are interested in
      foreach ($columnNames as $property) {
        $row[$property] = $dao->$property;
        if (!empty($dao->$property)) {
          $empty = FALSE;
        }
      }
      if (!$empty) {
        $contactID = isset($dao->contact_id) ? $dao->contact_id : NULL;

        $row['checkbox'] = CRM_Core_Form::CB_PREFIX . $contactID;
        $row['action'] = CRM_Core_Action::formLink($links,
          $mask,
          array('id' => $contactID),
          ts('more'),
          FALSE,
          'contact.custom.actions',
          'Contact',
          $contactID
        );
        $row['contact_id'] = $contactID;

        if ($alterRow) {
          $this->_search->alterRow($row);
        }

        if ($image) {
          $row['contact_type'] = CRM_Contact_BAO_Contact_Utils::getImage($dao->contact_sub_type ?
            $dao->contact_sub_type : $dao->contact_type, FALSE, $contactID
          );
        }
        $rows[] = $row;
      }
    }

    $this->buildPrevNextCache($sort);

    return $rows;
  }

  /**
   * Given the current formValues, gets the query in local
   * language
   *
   * @param  array(
     reference)   $formValues   submitted formValues
   *
   * @return array              $qill         which contains an array of strings
   * @access public
   */
  public function getQILL() {
    return NULL;
  }

  /**
   * @return mixed
   */
  public function getSummary() {
    return $this->_search->summary();
  }

  /**
   * Name of export file.
   *
   * @param string $output type of output
   *
   * @return string name of the file
   */
  function getExportFileName($output = 'csv') {
    return ts('CiviCRM Custom Search');
  }

  /**
   * @return null
   */
  function alphabetQuery() {
    return NULL;
  }

  /**
   * @param array $params
   * @param $action
   * @param int $sortID
   * @param null $displayRelationshipType
   * @param string $queryOperator
   *
   * @return Object
   */
  function &contactIDQuery($params, $action, $sortID, $displayRelationshipType = NULL, $queryOperator = 'AND') {
    $params = array();
    $sql = $this->_search->contactIDs($params);

    return CRM_Core_DAO::executeQuery($sql, $params);
  }

  /**
   * @param $rows
   */
  function addActions(&$rows) {
    $links = self::links($this->_key);

    $permissions = array(CRM_Core_Permission::getPermission());
    if (CRM_Core_Permission::check('delete contacts')) {
      $permissions[] = CRM_Core_Permission::DELETE;
    }
    $mask = CRM_Core_Action::mask($permissions);

    foreach ($rows as $id => & $row) {
      $row['action'] = CRM_Core_Action::formLink($links,
        $mask,
        array('id' => $row['contact_id']),
        ts('more'),
        FALSE,
        'contact.custom.actions',
        'Contact',
        $row['contact_id']
      );
    }
  }

  /**
   * @param $rows
   */
  function removeActions(&$rows) {
    foreach ($rows as $rid => & $rValue) {
      unset($rValue['action']);
    }
  }
}

