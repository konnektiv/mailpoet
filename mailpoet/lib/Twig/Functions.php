<?php

namespace MailPoet\Twig;

use MailPoet\Referrals\UrlDecorator;
use MailPoet\Settings\SettingsController;
use MailPoet\Util\FreeDomains;
use MailPoet\WooCommerce\Helper as WooCommerceHelper;
use MailPoet\WP\Functions as WPFunctions;
use MailPoetVendor\Carbon\Carbon;
use MailPoetVendor\Twig\Extension\AbstractExtension;
use MailPoetVendor\Twig\TwigFunction;

class Functions extends AbstractExtension {

  /** @var SettingsController */
  private $settings;

  /** @var WooCommerceHelper */
  private $woocommerceHelper;

  /** @var WPFunctions */
  private $wp;

  /** @var UrlDecorator */
  private $referralUrlDecorator;

  public function __construct() {
    $this->settings = SettingsController::getInstance();
    $this->woocommerceHelper = new WooCommerceHelper();
    $this->wp = WPFunctions::get();
    $this->referralUrlDecorator = new UrlDecorator($this->wp, $this->settings);
  }

  public function getFunctions() {
    return [
      new TwigFunction(
        'json_encode',
        'json_encode',
        ['is_safe' => ['all']]
      ),
      new TwigFunction(
        'json_decode',
        'json_decode',
        ['is_safe' => ['all']]
      ),
      new TwigFunction(
        'wp_nonce_field',
        'wp_nonce_field',
        ['is_safe' => ['all']]
      ),
      new TwigFunction(
        'params',
        [$this, 'params'],
        ['is_safe' => ['all']]
      ),
      new TwigFunction(
        'admin_url',
        'admin_url',
        ['is_safe' => ['all']]
      ),
      new TwigFunction(
        'get_option',
        'get_option',
        ['is_safe' => ['all']]
      ),
      new TwigFunction(
        'sending_frequency',
        [$this, 'getSendingFrequency'],
        ['is_safe' => ['all']]
      ),
      new TwigFunction(
        'wp_date_format',
        [$this, 'getWPDateFormat'],
        ['is_safe' => ['all']]
      ),
      new TwigFunction(
        'mailpoet_version',
        [$this, 'getMailPoetVersion'],
        ['is_safe' => ['all']]
      ),
      new TwigFunction(
        'mailpoet_premium_version',
        [$this, 'getMailPoetPremiumVersion'],
        ['is_safe' => ['all']]
      ),
      new TwigFunction(
        'wp_date_format',
        [$this, 'getWPDateFormat'],
        ['is_safe' => ['all']]
      ),
      new TwigFunction(
        'wp_time_format',
        [$this, 'getWPTimeFormat'],
        ['is_safe' => ['all']]
      ),
      new TwigFunction(
        'wp_datetime_format',
        [$this, 'getWPDateTimeFormat'],
        ['is_safe' => ['all']]
      ),
      new TwigFunction(
        'do_action',
        'do_action',
        ['is_safe' => ['all']]
      ),
      new TwigFunction(
        'is_rtl',
        [$this, 'isRtl'],
        ['is_safe' => ['all']]
      ),
      new TwigFunction(
        'number_format_i18n',
        'number_format_i18n',
        ['is_safe' => ['all']]
      ),
      new TwigFunction(
        'mailpoet_locale',
        [$this, 'getTwoLettersLocale'],
        ['is_safe' => ['all']]
      ),
      new TwigFunction(
        'mailpoet_free_domains',
        [$this, 'getFreeDomains'],
        ['is_safe' => ['all']]
      ),
      new TwigFunction(
        'is_woocommerce_active',
        [$this, 'isWoocommerceActive'],
        ['is_safe' => ['all']]
      ),
      new TwigFunction(
        'wp_start_of_week',
        [$this, 'getWPStartOfWeek'],
        ['is_safe' => ['all']]
      ),
      new TwigFunction(
        'stats_color',
        [$this, 'statsColor'],
        ['is_safe' => ['all']]
      ),
      new TwigFunction(
        'clicked_stats_text',
        [$this, 'clickedStatsText'],
        ['is_safe' => ['all']]
      ),
      new TwigFunction(
        'stats_number_format_i18n',
        [$this, 'statsNumberFormatI18n'],
        ['is_safe' => ['all']]
      ),
      new TwigFunction(
        'add_referral_id',
        [$this, 'addReferralId'],
        ['is_safe' => ['all']]
      ),
      new TwigFunction(
        'is_loading_3rd_party_enabled',
        [$this, 'libs3rdPartyEnabled'],
        ['is_safe' => ['all']]
      ),
    ];
  }

  public function getSendingFrequency() {
    $args = func_get_args();
    $value = (int)array_shift($args);

    $label = null;
    $labels = [
      'minute' => $this->wp->__('every minute', 'mailpoet'),
      'minutes' => $this->wp->__('every %1$d minutes', 'mailpoet'),
      'hour' => $this->wp->__('every hour', 'mailpoet'),
      'hours' => $this->wp->__('every %1$d hours', 'mailpoet'),
    ];

    if ($value >= 60) {
      // we're dealing with hours
      if ($value === 60) {
        $label = $labels['hour'];
      } else {
        $label = $labels['hours'];
      }
      $value /= 60;
    } else {
      // we're dealing with minutes
      if ($value === 1) {
        $label = $labels['minute'];
      } else {
        $label = $labels['minutes'];
      }
    }

    if ($label !== null) {
      return sprintf($label, $value);
    } else {
      return $value;
    }
  }

  public function getWPDateFormat() {
    return $this->wp->getOption('date_format') ?: 'F j, Y';
  }

  public function getWPStartOfWeek() {
    return $this->wp->getOption('start_of_week') ?: 0;
  }

  public function getMailPoetVersion() {
    return MAILPOET_VERSION;
  }

  public function getMailPoetPremiumVersion() {
    return (defined('MAILPOET_PREMIUM_VERSION')) ? MAILPOET_PREMIUM_VERSION : false;
  }

  public function getWPTimeFormat() {
    return $this->wp->getOption('time_format') ?: 'g:i a';
  }

  public function getWPDateTimeFormat() {
    return sprintf('%s %s', $this->getWPDateFormat(), $this->getWPTimeFormat());
  }

  public function params($key = null) {
    $args = $this->wp->stripslashesDeep($_GET);
    if (array_key_exists($key, $args)) {
      return $args[$key];
    }
    return null;
  }

  public function installedInLastTwoWeeks() {
    $maxNumberOfWeeks = 2;
    $installedAt = Carbon::createFromFormat('Y-m-d H:i:s', $this->settings->get('installed_at'));
    if ($installedAt === false) {
      return false;
    }
    return $installedAt->diffInWeeks(Carbon::now()) < $maxNumberOfWeeks;
  }

  public function isRtl() {
    return $this->wp->isRtl();
  }

  public function getTwoLettersLocale() {
    return explode('_', $this->wp->getLocale())[0];
  }

  public function getFreeDomains() {
    return FreeDomains::FREE_DOMAINS;
  }

  public function isWoocommerceActive() {
    return $this->woocommerceHelper->isWooCommerceActive();
  }

  public function statsColor($percentage) {
    if ($percentage > 3) {
      return '#7ed321';
    } elseif ($percentage > 1) {
      return '#ff9f00';
    } else {
      return '#f559c3';
    }
  }

  public function clickedStatsText($clicked) {
    if ($clicked > 3) {
      return __('Excellent', 'mailpoet');
    } elseif ($clicked > 1) {
      return __('Good', 'mailpoet');
    } else {
      return __('Average', 'mailpoet');
    }
  }

  /**
   * Wrapper around number_format_i18n() to return two decimals digits if the number
   * is smaller than 0.1 and one decimal digit if the number is equal or greater
   * than 0.1.
   *
   * @param int|float $number
   *
   * @return string
   */
  public function statsNumberFormatI18n($number) {
    if ($number < 0.1) {
      $decimals = 2;
    } else {
      $decimals = 1;
    }

    return number_format_i18n($number, $decimals);
  }

  public function addReferralId($url) {
    return $this->referralUrlDecorator->decorate($url);
  }

  public function libs3rdPartyEnabled(): bool {
    return $this->settings->get('3rd_party_libs.enabled') === '1';
  }
}
