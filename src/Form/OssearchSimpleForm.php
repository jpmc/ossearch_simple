<?php
/**
 * @file
 * Contains \Drupal\ossearch_simple\Form\OssearchForm.
 */

namespace Drupal\ossearch_simple\Form;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * OssearchForm form.
 */
class OssearchSimpleForm extends FormBase {
  /**
   * {@inheritdoc}
   */
  public function getFormId() {

    return 'ossearch_simple_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    //$form = parent::buildForm($form, $form_state);

    $query_term = \Drupal::request()->query->get('query_term');
    if(null == $query_term || strlen(trim($query_term)) == 0) {
      $query_term = NULL;
    }
    else {
      $query_term = trim($query_term);
    }

    $form['query_term'] = array(
      '#type' => 'textfield',
      '#title'=> t('Search:'),
      '#default_value' => NULL == $query_term ? "" : $query_term,
    );
    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Search'),
    );

    $form['#theme'] = 'ossearch_simple_form';

    return $form;
  }


  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $query_term = $form_state->getValue('query_term');
    $rows_per_page = \Drupal::routeMatch()->getParameter('rows');

    $current_uri = Url::fromRoute('<current>',array(),array('absolute'=>'true'))->toString();
    $this_url = Url::fromUri($current_uri,
      ['query' =>
        ['query_term' => $query_term, 'rows' => $rows_per_page]
      ]
    );

    $form_state->setRedirectUrl($this_url);

  }
}
?>