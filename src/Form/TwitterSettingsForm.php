<?php

/**
 * @file
 * Contains \Drupal\system\Form\ImageToolkitForm.
 */

namespace Drupal\twitter_feed\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\ConfigFormBase;

/**
 * Configures image toolkit settings for this site.
 */
class TwitterSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'twitter_feed.settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['twitter_feed.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('twitter_feed.settings');
    $number_array = range(1, 25);

    $form['twitter_api_key'] = array(
      '#type' => 'textfield',
      '#title' => t('Twitter API key'),
      '#default_value' => $config->get('twitter_api_key'),
      '#maxlength' => 512,
      '#description' => t('The Twitter API key for your app.'),
      '#required' => TRUE,
    );

    $form['twitter_secret_key'] = array(
      '#type' => 'textfield',
      '#title' => t('Twitter API secret key'),
      '#default_value' => $config->get('twitter_secret_key'),
      '#maxlength' => 512,
      '#description' => t('The Twitter API secret key for your app.'),
      '#required' => TRUE,
    );

    $form['twitter_access_api_key'] = array(
      '#type' => 'textfield',
      '#title' => t('Twitter API access key'),
      '#default_value' => $config->get('twitter_access_api_key'),
      '#maxlength' => 512,
      '#description' => t('The Twitter API access token for your app.'),
      '#required' => TRUE,
    );

    $form['twitter_access_secret_key'] = array(
      '#type' => 'textfield',
      '#title' => t('Twitter API access secret key'),
      '#default_value' => $config->get('twitter_access_secret_key'),
      '#maxlength' => 512,
      '#description' => t('The Twitter API access secret token for your app.'),
      '#required' => TRUE,
    );

    $form['max_tweets'] = array(
      '#type' => 'select',
      '#title' => t('Maximum number of items to display'),
      '#options' => array_combine($number_array, $number_array),
      '#default_value' => $config->get('max_tweets'),
      '#description' => $this->t('This will set the maximum allowable number of tweets in the Tweets block config form'),
      '#required' => TRUE,
    );

    return parent::buildForm($form, $form_state);
  }


  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('twitter_feed.settings');

    $config->set('twitter_secret_key', $form_state->getValue('twitter_secret_key'))
      ->set('twitter_api_key', $form_state->getValue('twitter_api_key'))
      ->set('twitter_access_api_key', $form_state->getValue('twitter_access_api_key'))
      ->set('twitter_access_secret_key', $form_state->getValue('twitter_access_secret_key'))
      ->set('max_tweets', $form_state->getValue('max_tweets'));
    $config->save();

    parent::submitForm($form, $form_state);
  }

}
