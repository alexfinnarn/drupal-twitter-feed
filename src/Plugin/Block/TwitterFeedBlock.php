<?php

/**
 * @file
 * Contains \Drupal\system\Plugin\Block\SystemMenuBlock.
 */

namespace Drupal\twitter_feed\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a generic Menu block.
 *
 * @Block(
 *   id = "twitter_feed_block",
 *   admin_label = @Translation("Twitter Feed"),
 *   category = @Translation("Media")
 * )
 */
class TwitterFeedBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Stores the configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Creates a TwitterFeedBlock instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $config = \Drupal::config('twitter_feed.settings');
    return array(
      'username' => '',
      'max_tweets' => $config->get('max_tweets'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $config = $this->configuration;

    $defaults = $this->defaultConfiguration();
    $options = range(0, $defaults['max_tweets']);

    $form['username'] = array(
      '#type' => 'textfield',
      '#title' => t('Twitter username'),
      '#default_value' => $config['username'],
      '#maxlength' => 512,
      '#description' => t('The Twitter username whose tweets will be displayed.'),
      '#required' => TRUE,
    );

    $form['num_tweets'] = array(
      '#type' => 'select',
      '#title' => $this->t('Number of tweets to display'),
      '#default_value' => $config['num_tweets'],
      '#options' => $options,
      '#description' => $this->t('This will be the number of tweets displayed in the block.'),
      '#required' => TRUE,
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['username'] = $form_state->getValue('username');
    $this->configuration['num_tweets'] = $form_state->getValue('num_tweets');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {

    // Get tweets
    $tweets = $this->getBearerToken()->performRequest();

    // Grab text and created at
    $tweets_text = array();
    foreach($tweets as $key => $tweet) {
      $tweets_text[$key]['text'] = $this->prepare_tweet($tweet['text']);
      $tweets_text[$key]['created_at'] = $tweet['created_at'];
    }

    // Make username a URL to Twitter account.
    $username = '<a href="http://www.twitter.com/' . $this->configuration['username'] . '">@' . $this->configuration['username'] . '</a>';

    return array(
      '#theme' => 'twitter_feed_block',
      '#username' => $username,
      '#tweets' => $tweets_text,
      '#attached' => array(
        'library' =>  array(
          'twitter_feed/base'
        ),
      ),
    );
  }

  private function getBearerToken() {
    $config = \Drupal::config('twitter_feed.settings');

    //TODO - store bearer token and check here if it exists. If it does return.
    $encoded_key = base64_encode($config->get('twitter_api_key') . ':' . $config->get('twitter_secret_key'));

    $data = 'grant_type=client_credentials';

    $header = array(
      'Authorization: Basic ' . $encoded_key,
      'Content-Length: ' . strlen($data),
    );

    $options = array(
      CURLOPT_HTTPHEADER => $header,
      CURLOPT_POST => true,
      CURLOPT_ENCODING => 'gzip',
      CURLOPT_HEADER => false,
      CURLOPT_POSTFIELDS => $data,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT => 10,
      CURLOPT_AUTOREFERER => true,
      CURLOPT_USERAGENT => 'My Twitter App v1.0.23',
      CURLOPT_FOLLOWLOCATION => true,
    );

    $feed = curl_init('https://api.twitter.com/oauth2/token');
    curl_setopt_array($feed, $options);
    $json = curl_exec($feed);
    curl_close($feed);

    $parsed_response = json_decode($json, true);

    $this->access_token = $parsed_response['access_token'];
    return $this;
  }

  private function performRequest() {

    $header = array(
      'Authorization: Bearer ' . $this->access_token,
    );

    $options = array(
      CURLOPT_HTTPHEADER => $header,
      CURLOPT_ENCODING => 'gzip',
      CURLOPT_HEADER => false,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT => 10,
      CURLOPT_AUTOREFERER => true,
      CURLOPT_USERAGENT => 'Finn\'s Twitter App v1.0.0',
      CURLOPT_FOLLOWLOCATION => true,
    );

    $feed = curl_init('https://api.twitter.com/1.1/statuses/user_timeline.json?screen_name=' . $this->configuration['username'] . '&count=' . $this->configuration['num_tweets']);
    curl_setopt_array($feed, $options);
    $json = curl_exec($feed);
    curl_close($feed);

    $parsed_response = json_decode($json, true);
    return $parsed_response;
  }

  /**
   * Adds #hashtags, @mentions, and links to a tweet body
   * @param array $tweet
   * @return array
   */
  private function prepare_tweet($tweet = array()) {

    $tweet = $this->format_links($tweet);
    $tweet = $this->format_hashtags($tweet);
    $tweet = $this->format_mentions($tweet);

    return $tweet;
  }

  private function format_hashtags($tweet = array()) {

    if(strpos($tweet,'#') !== false) {
      $tweet = preg_replace('/(^|\s)#(\w*[a-zA-Z_]+\w*)/', ' <a href="https://twitter.com/hashtag/$2" target="_blank">#$2</a>', $tweet);
    }
    return $tweet;
  }

  private function format_links($tweet = array()) {

    // TODO - Make better check for URLs
    if((strpos($tweet,'http') !== false)) {
      $pattern = "/(?i)\b((?:https?:\/\/|www\d{0,3}[.]|[a-z0-9.\-]+[.][a-z]{2,4}\/)(?:[^\s()<>]+|\(([^\s()<>]+|(\([^\s()<>]+\)))*\))+(?:\(([^\s()<>]+|(\([^\s()<>]+\)))*\)|[^\s`!()\[\]{};:'\".,<>?«»“”‘’]))/";
      $tweet = preg_replace($pattern, '<a href="$1" target="_blank">$1</a>', $tweet);
    }
    return $tweet;
  }

  private function format_mentions($tweet = array()) {

    if(strpos($tweet,'@') !== false) {
      $tweet = preg_replace('/(^|\s)@(\w*[a-zA-Z_]+\w*)/', ' <a href="https://twitter.com/$2" target="_blank">@$2</a>', $tweet);
    }
    return $tweet;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    // I don't know what this means yet.
    $cache_tags = parent::getCacheTags();
    $cache_tags[] = 'config:twitter_feed.block';
    return $cache_tags;
  }

  /**
   * {@inheritdoc}
   */
  protected function getRequiredCacheContexts() {
    // TODO - understand caching better
    return array('cache_context.user.roles', 'cache_context.language');
  }
}