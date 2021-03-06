<?php

/**
 * @file
 * Contains \Drupal\ethereum\Controller\EthereumController.
 */

namespace Drupal\ethereum\Controller;

use Drupal\Core\Controller\ControllerBase;
use Ethereum\Ethereum;
use Ethereum\EthBlockParam;
use Ethereum\EthB;
use Ethereum\EthS;
use Drupal\Core\Render\Markup;

/**
 * Controller routines for Ethereum routes.
 */
class EthereumController extends ControllerBase {

  private $config;
  public $client;
  private $debug = TRUE;

  public function __construct($host = FALSE) {
    if (!$host) {
      $this->config = \Drupal::config('ethereum.settings');
      $host = $this->config->get($this->config->get('current_server'));
    }
    $this->client = new Ethereum($host);
  }

  /**
   * Outputs function call logging as drupal message.
   *
   * This will output logs of all calls if $this->debug = TRUE.
   * Debug log will be emptied after call.
   *
   * @param bool $clear
   *   If empty is set we will empty the debug log.
   *   You may use to debug a single call.
   */
  public function debug($clear = FALSE) {
    $html = $this->client->debugHtml;
    $this->client->debugHtml = '';
    if (!$clear && $this->debug) {
      // Remove last HR Tag.
      $html = strrev(implode('', explode(strrev('<hr />'), strrev($html), 2)));
      drupal_set_message(Markup::create($html), 'warning');
    }
  }

  /**
   * Displays the ethereum status report page.
   *
   * This page provides a overview about Ethereum functions and usage.
   *
   * @return string
   *   The current status of the ethereum node.
   */
  public function status() {

    $rows[] = [$this->t('<b>JsonRPC standard Methods</b>'), $this->t('Read more about <a href="https://github.com/ethereum/wiki/wiki/JSON-RPC">Ethereum JsonRPC-API</a> implementation.')];
    $rows[] = [$this->t("Client version (web3_clientVersion)"), $this->client->web3_clientVersion()->val()];
    $rows[] = [$this->t("Listening (net_listening)"), $this->client->net_listening()->val() ? '✔' : '✘'];
    $rows[] = [$this->t("Peers (net_peerCount)"), $this->client->net_peerCount()->val()];
    $rows[] = [$this->t("Protocol version (eth_protocolVersion)"), $this->client->eth_protocolVersion()->val()];
    $rows[] = [$this->t("Network version (net_version)"), $this->client->net_version()->val()];
    $rows[] = [$this->t("Syncing (eth_syncing)"), $this->client->eth_syncing()->val() ? '✔' : '✘'];

    // Mining and Hashrate.
    $rows[] = [$this->t("Mining (eth_mining)"), $this->client->eth_mining()->val() ? '✔' : '✘'];

    $hash_rate = $this->client->eth_hashrate();
    $mining = is_a($hash_rate, 'EthQ') ? ((int) ($hash_rate->val() / 1000) . ' KH/s') : '✘';
    $rows[] = [$this->t("Mining hashrate (eth_hashrate)"), $mining];

    // Gas price is returned in WEI. See: http://ether.fund/tool/converter.
    $price = $this->client->eth_gasPrice()->val();
    $price = $price . 'wei ( ≡ ' . number_format(($price / 1000000000000000000), 8, '.', '') . ' Ether)';
    $rows[] = [$this->t("Current price per gas in wei (eth_gasPrice)"), $price];

    // Blocks.
    $rows[] = [$this->t("<b>Block info</b>"), ''];
    $block_latest = $this->client->eth_getBlockByNumber(new EthBlockParam('latest'), new EthB(FALSE));
    $rows[] = [
      $this->t("Latest block age"),
      \Drupal::service('date.formatter')->format($block_latest->getProperty('timestamp'), 'html_datetime'),
    ];

    // Testing_only.

    $block_earliest = $this->client->eth_getBlockByNumber(new EthBlockParam(1), new EthB(FALSE));
    $rows[] = [
      $this->t("Age of block number '1' <br/><small>The 'earliest' block has no timestamp on many networks.</small>"),
      \Drupal::service('date.formatter')->format($block_earliest->getProperty('timestamp'), 'html_datetime'),
    ];
    $rows[] = [
      $this->t("Client first (eth_getBlockByNumber('earliest'))"),
      Markup::create('<div style="max-width: 800px; max-height: 120px; overflow: scroll">' . $this->client->debug('', $block_earliest) . '</div>'),
    ];

    // Second param will return TX hashes instead of full TX.
    $block_latest = $this->client->eth_getBlockByNumber(new EthBlockParam('earliest'), new EthB(FALSE));
    $rows[] = [
      $this->t("Client first (eth_getBlockByNumber('latest'))"),
      Markup::create('<div style="max-width: 800px; max-height: 120px; overflow: scroll">' . $this->client->debug('', $block_latest) . '</div>'),
    ];
    $rows[] = [
      $this->t("Uncles of latest block"),
      Markup::create('<div style="max-width: 800px; max-height: 120px; overflow: scroll">' . $this->client->debug('', $block_latest->getProperty('uncles')) . '</div>'),
    ];

    $high_block = $this->client->eth_getBlockByNumber(new EthBlockParam(999999999), new EthB(FALSE));
    $rows[] = [
      $this->t("Get hash of a high block number<br /><small>Might be empty</small>"),

      // TODO
      // THIS DOSN'T WORK CONSISTENTLY! ANOTHER ARGUMENT FOR A NULL OBJECT!!
      $high_block->getProperty('hash'),
    ];


    // Accounts.
    $rows[] = [$this->t("<b>Accounts info</b>"), ''];
    $coin_base = $this->client->eth_coinbase()->hexVal();
    if ($coin_base === '0x0000000000000000000000000000000000000000') {
      $coin_base = 'No coinbase available at this network node.';
    }
    $rows[] = [$this->t("Coinbase (eth_coinbase)"), $coin_base];
    $address = array();
    foreach ($this->client->eth_accounts() as $addr) {
      $address[] = $addr->hexVal();
    }
    $rows[] = [$this->t("Accounts (eth_accounts)"), implode(', ', $address)];

    // More.
    $rows[] = [
      $this->t("web3_sha3('Hello World')"),
      $this->client->web3_sha3(new EthS('Hello World'))->hexVal(),
    ];

    // NON standard JsonRPC-API Methods below.
    $rows[] = [$this->t('<b>Non standard methods</b>'), $this->t('PHP Ethereum controller API provides additional methods. They are part of the <a href="https://github.com/digitaldonkey/ethereum-php">Ethereum PHP library</a>, but not part of JsonRPC-API standard.')];
    $rows[] = [$this->t("getMethodSignature('validateUserByHash(bytes32)')"), $this->client->getMethodSignature('validateUserByHash(bytes32)')];

    // Debug output for all calls since last call of
    // $this->debug() or $this->debug(TRUE).
    // $this->debug();
    //
    return [
      '#theme' => 'table',
      '#rows' => $rows,
    ];
  }

}
