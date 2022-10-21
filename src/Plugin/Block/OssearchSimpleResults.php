<?php
/**
 * @file
 * Contains \Drupal\ossearch_simple\Plugin\Block\OssearchSimpleBlock.
 */

namespace Drupal\ossearch_simple\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Exception;

//use Drupal\Core\Cache\Cache;

/**
 * OssearchSimpleResults block.
 *
 * @Block(
 *   id = "ossearch_simple_results",
 *   admin_label = @Translation("OSSearch Simple Results"),
 *   category = @Translation("Blocks")
 * )
 */
class OssearchSimpleResults extends BlockBase {

  /**
   * {@inheritdoc}
   *
   * This method defines form elements for custom block configuration. Standard
   * block configuration fields are added by BlockBase::buildConfigurationForm()
   * (block title and title visibility) and BlockFormController::form() (block
   * visibility settings).
   *
   * @see \Drupal\block\BlockBase::buildConfigurationForm()
   * @see \Drupal\block\BlockFormController::form()
   */
  public function blockForm($form, FormStateInterface $form_state)
  {

    $search_settings = $this->configuration;

    $form['slug'] = array(
      '#type' => 'hidden',
      '#value' => $search_settings['slug'],
    );

    $form['title'] = array(
      '#type' => 'textfield',
      '#title' => t('Search name'),
      '#maxlength' => 30,
      '#required' => TRUE,
      '#default_value' => isset($search_settings['title']) ? $search_settings['title'] : '',
    );
    $form['status'] = array(
      '#type' => 'checkbox',
      '#title' => t('Active?'),
      // default to active
      '#default_value' => isset($search_settings['status']) && $search_settings['status'] == FALSE ? FALSE : TRUE,
    );
    $form['server'] = array(
      '#type' => 'textfield',
      '#title' => t('Server name'),
      '#required' => TRUE,
      '#default_value' => isset($search_settings['server']) ? $search_settings['server'] : 'ossearch.si.edu',
    );
    $form['search_collection'] = array(
      '#type' => 'textfield',
      '#title' => t('Search collection name'),
      '#required' => TRUE,
      '#default_value' => isset($search_settings['search_collection']) ? $search_settings['search_collection'] : '',
    );
    $form['requiredfields'] = array(
      '#type' => 'textfield',
      '#title' => t('Requiredfields'),
      '#description' => t('Optionally enter values for the requiredfields parameter such as "dc.format:finding"'),
      '#default_value' => isset($search_settings['subpath']) ? $search_settings['requiredfields'] : '',
    );
    $form['partialfields'] = array(
      '#type' => 'textfield',
      '#title' => t('Partialfields'),
      '#description' => t('Optionally enter values for the partialfields parameter such as "Format:Videos.ContentType:test"'),
      '#default_value' => isset($search_settings['partialfields']) ? $search_settings['partialfields'] : '',
    );
    $form['subpath'] = array(
      '#type' => 'textfield',
      '#title' => t('Sub-path'),
      '#description' => t('Optionally enter one or more comma-separated sub-paths, with no preceding slash; e.g. "media/Videos,media/Media"'),
      '#default_value' => isset($search_settings['subpath']) ? $search_settings['subpath'] : '',
    );
    $form['autosearch'] = array(
      '#type' => 'checkbox',
      '#title' => t('Execute search when block is loaded'),
      '#default_value' => isset($search_settings['autosearch']) && $search_settings['autosearch'] == TRUE ? TRUE : FALSE,
    );
    $form['rows_per_page'] = array(
      '#type' => 'textfield',
      '#title' => t('Pagination- number of results per page'),
      '#default_value' => 10,
      '#default_value' => isset($search_settings['rows_per_page']) ? $search_settings['rows_per_page'] : '',
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * This method processes the blockForm() form fields when the block
   * configuration form is submitted.
   *
   * The blockValidate() method can be used to validate the form submission.
   */
  public function blockSubmit($form, FormStateInterface $form_state)
  {

    $this->configuration['title']
      = $form_state->getValue('title');

    // make a slug from the title
    $this->configuration['slug']
      = $this->make_slug($form_state->getValue('title'));

    $this->configuration['title']
      = $form_state->getValue('title');

    $this->configuration['status']
      = $form_state->getValue('status');

    $this->configuration['server']
      = $form_state->getValue('server');

    $this->configuration['search_collection']
      = $form_state->getValue('search_collection');

    $this->configuration['requiredfields']
      = $form_state->getValue('requiredfields');

    $this->configuration['partialfields']
      = $form_state->getValue('partialfields');

    $this->configuration['subpath']
      = $form_state->getValue('subpath');

    $this->configuration['autosearch']
      = $form_state->getValue('autosearch');

    $this->configuration['rows_per_page']
      = $form_state->getValue('rows_per_page');

  }

  /**
   * {@inheritdoc}
   */
  public function build() {

    $search_settings = $this->configuration;

    // if autosearch is set, also return the results
    if(NULL == $search_settings['server'] || NULL == $search_settings['search_collection']) {
      //@todo drupal_set_message is deprecated
      drupal_set_message("Search is misconfigured.", "error");
      return array();
    }

    $query_term = \Drupal::request()->query->get('query_term');
    if(null == $query_term || strlen(trim($query_term)) == 0) {
      $query_term = NULL;
    }
    else {
      $query_term = trim($query_term);
    }

    $page_number = \Drupal::request()->query->get('page');
    if(NULL == $page_number || empty(trim($page_number)) || !is_numeric($page_number)) {
      $page_number = 0;
    }

    $rows_per_page = \Drupal::request()->query->get('rows');
    if(null == $rows_per_page || strlen(trim($rows_per_page)) == 0 || !is_numeric($rows_per_page)) {
      $rows_per_page = isset($search_settings['rows_per_page']) ? $search_settings['rows_per_page'] : 10;
    }
    if(!isset($rows_per_page) || empty($rows_per_page) ) {
      $rows_per_page = 10;
    }
    $search_settings['rows_per_page'] = $rows_per_page;

    if($search_settings['autosearch'] && NULL == $query_term) {
      $query_term = "*:*";
    }

    $sort_value = NULL;

    $results = array();
    $search_pages = array();

    $start = $this->ossearch_simple_get_current_page_start($page_number, $rows_per_page);

    // Execute the search
    if(NULL !== $query_term) {
      $results = $this->ossearch_execute_search($search_settings['server'], $search_settings['search_collection'],
        $query_term, $search_settings['requiredfields'], $search_settings['partialfields'],
        $search_settings['subpath'], $start, $rows_per_page, $sort_value);
      // returns an array containing: rows, start_row, end_row, num_found, keymatches
      $search_pages = $this->ossearch_simple_get_search_pages($results['num_found'], $page_number,
        $rows_per_page);
    }

    $form = \Drupal::formBuilder()->getForm('Drupal\ossearch_simple\Form\OssearchSimpleForm');

    if(isset($results['num_found'])) {
      $pager = $this->getPager($results['num_found'], $rows_per_page);
    }

    if(isset($results['rows'])) {
      foreach ($results['rows'] as $idx => $result) {
        $themed_result = [
          '#cache'  => ['max-age' => 0],
          '#theme'  => 'ossearch_simple_result',
          '#result' => $result
        ];
        $rendered = \Drupal::service('renderer')->render($themed_result);
        $results['rows'][$idx]['rendered'] = $rendered;
      }
    }

    if(!isset($results['rows'])) {
      $results = [];
      $results['start_row'] = null;
    }

    $themed_result =  [
      '#cache' => array('max-age' => 0),
      '#theme' => 'ossearch_simple_results_control_bar',
      '#results' => $results
    ];
    $rendered = \Drupal::service('renderer')->render($themed_result);
    $results['controlbar'] = $rendered;

    $keymatches = null;
    if(isset($results['keymatches'])) {
//      ksm($results['keymatches']);
      $themed_result =  [
        '#cache' => array('max-age' => 0),
        '#theme' => 'ossearch_simple_keymatch_results',
        '#results' => $results['keymatches']
      ];
      $rendered = \Drupal::service('renderer')->render($themed_result);
      $keymatches = $rendered;
    }

    $return_array = array(
      '#title' => NULL == $search_settings['label'] ? 'OSSearch Simple Results': $search_settings['label'],
      '#cache' => array('max-age' => 0),
      '#theme' => 'ossearch_simple_results',
      '#search_slug' => $search_settings['slug'],
      '#search_form' => $form,
      '#keymatch_results' => $keymatches,
      '#search_results' => $results,
      '#search_query' => $query_term,
      '#search_pages' => $pager,
      '#search_sort' => $sort_value,
      '#ossearch' => $search_settings
    );

    return $return_array;

  }


  public function getPager($total, $resultsPerPage) {
    \Drupal::service('pager.manager')->createPager($total, $resultsPerPage, 0);
    $pager['pager'] = [
      '#type' => 'pager',
    ];

    return \Drupal::service('renderer')->render($pager);
  }

  function make_slug($string) {
    if (is_object($string) || is_array($string)) {
      return $string;
    }
    // Replace with dashes anything that isn't A-Z, numbers, dashes, or underscores.
    return strtolower(preg_replace('/[^a-zA-Z0-9-]+/', '-', $string));
  }

  function ossearch_execute_search($server, $search_collection, $query_term, $requiredfields, $partialfields, $subpath,
                                   $start = 1, $rows_per_page = 10, $sort = NULL) {

    // https://ossearch.si.edu/search?q=*:*&site=folkways&client=folkways
    //&output=xml&getfields=*&requiredfields=Content%20Type:Video

    $query_term = str_replace(" ", "+", $query_term);
    $query_url = "https://" . $server . "/search?q=" . $query_term . "&site="
      . $search_collection . "&client=" . $search_collection
      . "&output=xml&num=" . $rows_per_page;

    if($start > 1) {
      $query_url .= "&start=" . $start;
    }
    if(NULL !== $requiredfields && strlen(trim($requiredfields)) > 0) {
      $requiredfields = str_replace(" ", "+", $requiredfields);
      $query_url .= "&requiredfields=" . $requiredfields;
    }
    if(NULL !== $partialfields && strlen(trim($partialfields)) > 0) {
      $partialfields = str_replace(" ", "+", $partialfields);
      $query_url .= "&partialfields=" . $partialfields;
    }
    if(NULL !== $subpath && strlen(trim($subpath)) > 0) {
      $query_url .= "&dirs=" . $subpath;
    }
    if(NULL !== $sort && strlen(trim($sort)) > 0) {
      $query_url .= "&sort=" . $sort;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $query_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 90);

    $xml_string = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);

    $xml = simplexml_load_string($xml_string);

    $os_search_results = array();
    if ($info['http_code'] !== 200) {
      //@todo error gracefully
      return $os_search_results;
    }

    // this is the only way to get dpm to dump the contents of the xml object
    // dpm(print_r($xml, true));

    if(isset($xml->RES)) {
      $os_search_results['start_row'] = (string)$xml->RES['SN'];
      $os_search_results['end_row'] = (string)$xml->RES['EN'];
      $os_search_results['num_found'] = (string)$xml->RES->M;
      foreach($xml->RES->R as $result)
      {
        $row = array(
          'url' => (string)$result->U,
          'title' => (string)$result->T,
          'context' => (string)$result->S,
        );

        $metatags = array();
        foreach($result->MT as $metatag) {
          $metatags[] = array(
            'name' => (string)$metatag['N'],
            'value' => (string)$metatag['V']
          );
        }
        $row['metatags'] = $metatags;

        $os_search_results['rows'][] = $row;
      }

      if(isset($xml->RES->PARM)) {
        foreach($xml->RES->PARM->PMT as $parm)
        {
          $facet_values = array(
            'facet_name' => (string)$parm['NM'],
            'facet_values' => array()
          );
          foreach($parm->PV as $pv) {
            $facet_values['facet_values'][] = array(
              'value' => (string)$pv['V'],
              'count' => (string)$pv['C']
            );
          }
          $os_search_results['facets'][] = $facet_values;
        }
      }
    }

    if(isset($xml->GM)) {
      foreach($xml->GM as $keymatch)
      {
        $os_search_results['keymatches'][] = array(
          'url' => (string)$keymatch->GL,
          'title' => (string)$keymatch->GD
        );
      }
    }

    return $os_search_results;
  }

  function ossearch_simple_get_search_pages($num_found, $current_page, $rows_per_page) {

    // Return an array of pages- just the "start" value and page number for each page

    $pages = array();
    $page_count = ceil((float)($num_found / $rows_per_page));
    for($i = 0; $i <= $page_count; $i++) {
      $pages[$i] = array(
        'number' => $i,
        'start' => ($i - 1) * $rows_per_page
      );
      if($current_page == $i) {
        $pages[$i]['active'] = true;
      }
    }
    return $pages;
  }

  function ossearch_simple_get_current_page_start(&$current_page, $rows_per_page) {
    try {
      $current_page = (int)$current_page;
    }
    catch(Exception $ex) {
      $current_page = 0;
    }
    $start = 0;
    if($current_page < 0) {
      $current_page = 0;
    }
    elseif($current_page > 0) {
      $start = $current_page * $rows_per_page;
    }
    return $start;
  }

  /**
   * {@inheritdoc}
   *
   */
  public function getCacheMaxAge() {
    return 0;
  }

}


